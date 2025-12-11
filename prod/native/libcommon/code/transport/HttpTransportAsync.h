/*
 * Copyright Elasticsearch B.V. and/or licensed to Elasticsearch B.V. under one
 * or more contributor license agreements. See the NOTICE file distributed with
 * this work for additional information regarding copyright
 * ownership. Elasticsearch B.V. licenses this file to you under
 * the Apache License, Version 2.0 (the "License"); you may
 * not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *  http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied.  See the License for the
 * specific language governing permissions and limitations
 * under the License.
 */

#pragma once

#include "HttpTransportAsyncInterface.h"
#include "CiCharTraits.h"
#include "ForkableInterface.h"
#include "ConfigurationStorage.h"
#include "CurlSender.h"
#include "HttpEndpoints.h"
#include "SpinLock.h"
#include "CommonUtils.h"

#include <algorithm>
#include <chrono>
#include <condition_variable>
#include <functional>
#include <memory>
#include <queue>
#include <span>
#include <string>
#include <string_view>
#include <thread>
#include <vector>
#include <boost/container_hash/hash.hpp>
#include <curl/curl.h>
#include <iostream>

using namespace std::literals;
using namespace std::string_view_literals;

namespace elasticapm::php::transport {

template <typename CurlSender = CurlSender, typename Endpoints = HttpEndpoints>
class HttpTransportAsync : public HttpTransportAsyncInterface, public ForkableInterface, public boost::noncopyable {
    using endpointUrlHash_t = Endpoints::endpointUrlHash_t;

public:
    HttpTransportAsync(std::shared_ptr<LoggerInterface> log, std::shared_ptr<ConfigurationStorage> config) : log_(std::move(log)), config_(std::move(config)), endpoints_(log_) {
        CurlInit();
    }

    ~HttpTransportAsync() {
        shutdownStart_ = std::chrono::steady_clock::now();
        forceFlushOnDestruction_ = true;
        shutdownThread();
        CurlCleanup();
    }

    void initializeConnection(std::string endpointUrl, endpointUrlHash_t endpointHash, std::string contentType, HttpEndpoint::enpointHeaders_t const &endpointHeaders, std::chrono::milliseconds timeout, std::size_t maxRetries, std::chrono::milliseconds retryDelay, HttpEndpointSSLOptions sslOptions) override {
        ELOG_DEBUG(log_, TRANSPORT, "HttpTransportAsync::initializeConnection endpointUrl '{}' enpointHash: {:X} timeout: {}ms retries: {} retry delay: {}ms", endpointUrl, endpointHash, timeout.count(), maxRetries, retryDelay.count());
        ELOG_TRACE(log_, TRANSPORT, "HttpTransportAsync::initializeConnection enpointHash '{:X}', SSL options: insecureSkipVerify: {}, caInfo: '{}', cert: '{}', certKey: '{}', certKeyPassword: '{}'", endpointHash, sslOptions.insecureSkipVerify, sslOptions.caInfo, sslOptions.cert, sslOptions.certKey, !sslOptions.certKeyPassword.empty() ? "<redacted>"sv : "");

        try {
            endpoints_.add(std::move(endpointUrl), endpointHash, std::move(contentType), endpointHeaders, timeout, maxRetries, retryDelay, std::move(sslOptions));
            startThread();
        } catch (std::exception const &error) {
            ELOG_ERROR(log_, TRANSPORT, "HttpTransportAsync::initializeConnection exception '{}'", error.what());
        }
    }

    void enqueue(endpointUrlHash_t endpointHash, std::span<std::byte> payload, responseCallback_t callback = {}) override {
        {
            std::lock_guard<std::mutex> lock(mutex_);
            ELOG_TRACE(log_, TRANSPORT, "HttpTransportAsync::enqueue enpointHash: {:X} payload size: {}, current queue size {} usage {} bytes", endpointHash, payload.size(), payloadsToSend_.size(), payloadsByteUsage_);

            if (payloadsByteUsage_ + payload.size() > config_->get().max_send_queue_size) {
                ELOG_DEBUG(log_, TRANSPORT, "HttpTransportAsync::enqueue payloadsByteUsageLimit {} reached. Payload will be dropped. enpointHash: {:X} payload size: {}, current queue size {} usage {} bytes", config_->get().max_send_queue_size, endpointHash, payload.size(), payloadsToSend_.size(), payloadsByteUsage_);
                return;
            }

            payloadsToSend_.emplace(endpointHash, std::vector<std::byte>(payload.begin(), payload.end()), callback);
            payloadsByteUsage_ += payload.size();
        }
        pauseCondition_.notify_all();
    }

    void prefork() final {
        shutdownThread();

        ELOG_DEBUG(log_, TRANSPORT, "HttpTransportAsync::prefork payloads queue size {}", payloadsToSend_.size());
        CurlCleanup();
    }

    void postfork([[maybe_unused]] bool child) final {
        CurlInit();

        if (child && !payloadsToSend_.empty()) {
            ELOG_DEBUG(log_, TRANSPORT, "HttpTransportAsync::postfork child emptying payloads queue. {} will be sent from parent", payloadsToSend_.size());
            decltype(payloadsToSend_) q;
            payloadsToSend_.swap(q);
        }
        working_ = true;
        startThread();
        pauseCondition_.notify_all();
    }

    void updateRetryDelay(endpointUrlHash_t endpointHash, std::chrono::milliseconds retryDelay) {
        try {
            endpoints_.updateRetryDelay(endpointHash, retryDelay);
        } catch (std::runtime_error const &error) {
            ELOG_WARNING(log_, TRANSPORT, "HttpTransportAsync::updateRetryDelay unable to update retry delay: '{}'", error.what());
        }
    }

protected:
    void startThread() {
        std::lock_guard<std::mutex> lock(mutex_);
        if (!thread_) {
            ELOG_DEBUG(log_, TRANSPORT, "HttpTransportAsync startThread");
            thread_ = std::make_unique<std::thread>([this]() { asyncSender(); });
        }
    }

    void shutdownThread() {
        {
            std::lock_guard<std::mutex> lock(mutex_);
            if (thread_) {
                ELOG_DEBUG(log_, TRANSPORT, "HttpTransportAsync shutdownThread");
            }

            working_ = false;
        }
        pauseCondition_.notify_all();

        if (thread_ && thread_->joinable()) {
            thread_->join();
        }
        thread_.reset();
    }

    void asyncSender() {
        ELOG_DEBUG(log_, TRANSPORT, "HttpTransportAsync::asyncSender blocking signals and starting work");

        elasticapm::utils::blockApacheAndPHPSignals();

        std::unique_lock<std::mutex> lock(mutex_);
        while (working_) {
            pauseCondition_.wait(lock, [this]() -> bool { return !payloadsToSend_.empty() || !working_; });

            if (!working_ && !forceFlushOnDestruction_) {
                break;
            }

            send(lock);
        }
    }

    void send(std::unique_lock<std::mutex> &lockedPayloadsMutex) {
        while (!payloadsToSend_.empty()) {
            auto [endpointHash, payload, callback] = std::move(payloadsToSend_.front());
            payloadsToSend_.pop();
            payloadsByteUsage_ -= payload.size();

            ELOG_TRACE(log_, TRANSPORT, "HttpTransportAsync::send enpointHash: {:X} payload size: {}", endpointHash, payload.size());

            lockedPayloadsMutex.unlock();

            std::function<void(std::string_view)> headerCallback = [endpointHash, this](std::string_view header) {
                auto hdr = elasticapm::utils::traits_cast<elasticapm::utils::CiCharTraits>(header);
                if (hdr.starts_with(elasticapm::utils::traits_cast<elasticapm::utils::CiCharTraits>("Retry-After: "sv))) {
                    std::string_view value = header.substr("Retry-After: "sv.length());

                    auto retryValue = elasticapm::utils::parseRetryAfter(value);
                    if (retryValue.has_value() && retryValue.value().count() > 0) {
                        ELOG_TRACE(log_, TRANSPORT, "HttpTransportAsync::send updating endpoint {:X} retry delay to {}ms", endpointHash, retryValue.value().count());
                        endpoints_.updateRetryDelay(endpointHash, retryValue.value());
                    }
                }
            };

            try {
                auto [endpointUrl, headers, connId, conn, maxRetries, retryDelay] = endpoints_.getConnection(endpointHash);
                try {
                    std::size_t retry = 0;

                    while (retry < maxRetries) {
                        std::string responseBuffer;

                        auto responseCode = conn.sendPayload(endpointUrl, headers, payload, callback ? headerCallback : std::function<void(std::string_view)>{}, callback ? &responseBuffer : nullptr);

                        ELOG_TRACE(log_, TRANSPORT, "HttpTransportAsync::send enpointHash: {:X} connectionId: {:X} payload size: {} responseCode {}", endpointHash, connId, payload.size(), static_cast<int>(responseCode));

                        if (responseCode >= 200 && responseCode < 300) {
                            if (callback) {
                                callback(responseCode, {reinterpret_cast<std::byte *>(responseBuffer.data()), responseBuffer.size()});
                            }
                            break;
                        }

                        if (responseCode >= 400 && responseCode < 500 && responseCode != 408 && responseCode != 429) {
                            if (callback) {
                                callback(responseCode, {reinterpret_cast<std::byte *>(responseBuffer.data()), responseBuffer.size()});
                            }
                            std::string msg = "server returned with code "s;
                            msg.append(std::to_string(responseCode));
                            throw std::runtime_error(msg);
                        }

                        retry++;
                        ELOG_DEBUG(log_, TRANSPORT, "HttpTransportAsync::send enpointHash: {:X} connectionId: {:X} payload size: {} retry {}/{} delay: {} responseCode {}", endpointHash, connId, payload.size(), retry, maxRetries, retryDelay.count(), static_cast<int>(responseCode));
                        std::this_thread::sleep_for(retryDelay);
                    }
                    ELOG_DEBUG(log_, TRANSPORT, "HttpTransportAsync::send enpointHash: {:X} connectionId: {:X} payload size: {}", endpointHash, connId, payload.size());
                } catch (std::runtime_error const &e) {
                    ELOG_WARNING(log_, TRANSPORT, "HttpTransportAsync::send exception '{}'. enpointHash: {:X} connectionId: {:X} payload size: {}", e.what(), endpointHash, connId, payload.size());
                }
            } catch (std::runtime_error const &error) {
                ELOG_WARNING(log_, TRANSPORT, "HttpTransportAsync::send {}", error.what());
            }

            lockedPayloadsMutex.lock();

            // it will break sending and emit log if class destructor was triggered, payloads queue is not empty and timeout was set and reached
            if (forceFlushOnDestruction_ && !payloadsToSend_.empty() && config_->get().async_transport_shutdown_timeout.count() > 0 && ((std::chrono::steady_clock::now() - shutdownStart_) >= config_->get().async_transport_shutdown_timeout)) {
                ELOG_WARNING(log_, TRANSPORT, "Dropping {} payloads because ELASTIC_OTEL_ASYNC_TRANSPORT_SHUTDOWN_TIMEOUT ({}ms) was reached", payloadsToSend_.size(), config_->get().async_transport_shutdown_timeout.count());
                break;
            }
        }
    }

    void CurlInit() {
        auto curlInitResult = curl_global_init(CURL_GLOBAL_ALL);
        if (curlInitResult != CURLE_OK) {
            ELOG_ERROR(log_, TRANSPORT, "HttpTransportAsync curl_global_init failed: {} ({})", curl_easy_strerror(curlInitResult), (int)curlInitResult);
        }
    }
    void CurlCleanup() {
        curl_global_cleanup();
    }

protected:
    std::shared_ptr<LoggerInterface> log_;
    std::shared_ptr<ConfigurationStorage> config_;
    Endpoints endpoints_;
    std::mutex mutex_;
    std::queue<std::tuple<endpointUrlHash_t, std::vector<std::byte>, responseCallback_t>> payloadsToSend_;
    std::size_t payloadsByteUsage_ = 0;

    std::unique_ptr<std::thread> thread_;
    std::condition_variable pauseCondition_;
    bool working_ = true;
    std::atomic_bool forceFlushOnDestruction_ = false;
    std::chrono::time_point<std::chrono::steady_clock> shutdownStart_;
};

} // namespace elasticapm::php::transport
