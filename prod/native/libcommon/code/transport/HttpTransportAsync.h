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

#include "ForkableInterface.h"
#include "ConfigurationStorage.h"
#include "CurlSender.h"
#include "HttpEndpoints.h"
#include "SpinLock.h"
#include "CommonUtils.h"

#include <algorithm>
#include <chrono>
#include <condition_variable>
#include <memory>
#include <queue>
#include <span>
#include <string>
#include <string_view>
#include <thread>
#include <vector>
#include <boost/container_hash/hash.hpp>
#include <curl/curl.h>

using namespace std::literals;

namespace elasticapm::php::transport {

template <typename CurlSender = CurlSender, typename Endpoints = HttpEndpoints>
class HttpTransportAsync : public ForkableInterface, public boost::noncopyable {
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

    void initializeConnection(std::string endpointUrl, size_t endpointHash, std::string contentType, HttpEndpoint::enpointHeaders_t const &endpointHeaders, std::chrono::milliseconds timeout, std::size_t maxRetries, std::chrono::milliseconds retryDelay) {
        ELOG_DEBUG(log_, "HttpTransportAsync::initializeConnection endpointUrl '%s' enpointHash: %X timeout: %zums retries: %zu retry delay: %zums", endpointUrl.c_str(), endpointHash, timeout.count(), maxRetries, retryDelay.count());

        try {
            endpoints_.add(std::move(endpointUrl), endpointHash, config_->get().verify_server_cert, std::move(contentType), endpointHeaders, timeout, maxRetries, retryDelay);
            startThread();
        } catch (std::exception const &error) {
            ELOG_ERROR(log_, "HttpTransportAsync::initializeConnection exception '%s'", error.what());
        }
    }

    void enqueue(size_t endpointHash, std::span<std::byte> payload) {
        {
            std::lock_guard<std::mutex> lock(mutex_);
            ELOG_TRACE(log_, "HttpTransportAsync::enqueue enpointHash: %X payload size: %zu, current queue size %zu usage %zu bytes", endpointHash, payload.size(), payloadsToSend_.size(), payloadsByteUsage_);

            if (payloadsByteUsage_ + payload.size() > config_->get().max_send_queue_size) {
                ELOG_DEBUG(log_, "HttpTransportAsync::enqueue payloadsByteUsageLimit %zu reached. Payload will be dropped. enpointHash: %X payload size: %zu, current queue size %zu usage %zu bytes", config_->get().max_send_queue_size, endpointHash, payload.size(), payloadsToSend_.size(), payloadsByteUsage_);
                return;
            }

            payloadsToSend_.emplace(endpointHash, std::vector<std::byte>(payload.begin(), payload.end()));
            payloadsByteUsage_ += payload.size();
        }
        pauseCondition_.notify_all();
    }

    void prefork() final {
        shutdownThread();

        ELOG_DEBUG(log_, "HttpTransportAsync::prefork payloads queue size %zu", payloadsToSend_.size());
        CurlCleanup();
    }

    void postfork([[maybe_unused]] bool child) final {
        CurlInit();

        if (child && !payloadsToSend_.empty()) {
            ELOG_DEBUG(log_, "HttpTransportAsync::postfork child emptying payloads queue. %zu will be sent from parent", payloadsToSend_.size());
            decltype(payloadsToSend_) q;
            payloadsToSend_.swap(q);
        }
        working_ = true;
        startThread();
        pauseCondition_.notify_all();
    }

protected:
    void startThread() {
        std::lock_guard<std::mutex> lock(mutex_);
        if (!thread_) {
            ELOG_DEBUG(log_, "HttpTransportAsync startThread");
            thread_ = std::make_unique<std::thread>([this]() { asyncSender(); });
        }
    }

    void shutdownThread() {
        {
            std::lock_guard<std::mutex> lock(mutex_);
            if (thread_) {
                ELOG_DEBUG(log_, "HttpTransportAsync shutdownThread");
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
        ELOG_DEBUG(log_, "HttpTransportAsync::asyncSender blocking signals and starting work");

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
            auto [endpointHash, payload] = std::move(payloadsToSend_.front());
            payloadsToSend_.pop();
            payloadsByteUsage_ -= payload.size();

            ELOG_TRACE(log_, "HttpTransportAsync::send enpointHash: %X payload size: %zu", endpointHash, payload.size());

            lockedPayloadsMutex.unlock();

            try {
                auto [endpointUrl, headers, connId, conn, maxRetries, retryDelay] = endpoints_.getConnection(endpointHash);
                try {
                    std::size_t retry = 0;

                    while (retry < maxRetries) {
                        auto responseCode = conn.sendPayload(endpointUrl, headers, payload);

                        ELOG_TRACE(log_, "HttpTransportAsync::send enpointHash: %X connectionId: %X payload size: %zu responseCode %d", endpointHash, connId, payload.size(), static_cast<int>(responseCode));

                        if (responseCode >= 200 && responseCode < 300) {
                            break;
                        }

                        if (responseCode >= 400 && responseCode < 500 && responseCode != 408 && responseCode != 429) {
                            std::string msg = "server returned with code "s;
                            msg.append(std::to_string(responseCode));
                            throw std::runtime_error(msg);
                        }

                        retry++;
                        ELOG_DEBUG(log_, "HttpTransportAsync::send enpointHash: %X connectionId: %X payload size: %zu retry %zu/%zu delay: %zu responseCode %d ", endpointHash, connId, payload.size(), retry, maxRetries, retryDelay.count(), static_cast<int>(responseCode));
                        std::this_thread::sleep_for(retryDelay);
                    }
                    ELOG_DEBUG(log_, "HttpTransportAsync::send enpointHash: %X connectionId: %X payload size: %zu", endpointHash, connId, payload.size());
                } catch (std::runtime_error const &e) {
                    ELOG_WARNING(log_, "HttpTransportAsync::send exception '%s'. enpointHash: %X connectionId: %X payload size: %zu", e.what(), endpointHash, connId, payload.size());
                }
            } catch (std::runtime_error const &error) {
                ELOG_WARNING(log_, "HttpTransportAsync::send %s", error.what());
            }

            lockedPayloadsMutex.lock();

            // it will break sending and emit log if class destructor was triggered, payloads queue is not empty and timeout was set and reached
            if (forceFlushOnDestruction_ && !payloadsToSend_.empty() && config_->get().async_transport_shutdown_timeout.count() > 0 && ((std::chrono::steady_clock::now() - shutdownStart_) >= config_->get().async_transport_shutdown_timeout)) {
                ELOG_WARNING(log_, "Dropping %zu payloads because ELASTIC_OTEL_ASYNC_TRANSPORT_SHUTDOWN_TIMEOUT (%zums) was reached", payloadsToSend_.size(), config_->get().async_transport_shutdown_timeout.count());
                break;
            }
        }
    }

    void CurlInit() {
        auto curlInitResult = curl_global_init(CURL_GLOBAL_ALL);
        if (curlInitResult != CURLE_OK) {
            ELOG_ERROR(log_, "HttpTransportAsync curl_global_init failed: %s (%d)", curl_easy_strerror(curlInitResult), (int)curlInitResult);
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
    std::queue<std::pair<endpointUrlHash_t, std::vector<std::byte>>> payloadsToSend_;
    std::size_t payloadsByteUsage_ = 0;

    std::unique_ptr<std::thread> thread_;
    std::condition_variable pauseCondition_;
    bool working_ = true;
    std::atomic_bool forceFlushOnDestruction_ = false;
    std::chrono::time_point<std::chrono::steady_clock> shutdownStart_;
};

} // namespace elasticapm::php::transport
