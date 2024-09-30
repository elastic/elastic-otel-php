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
#include <unordered_map>
#include <vector>
#include <boost/container_hash/hash.hpp>
#include <curl/curl.h>

using namespace std::literals;

namespace elasticapm::php::transport {

class HttpEndpoint : public boost::noncopyable {
public:
    using enpointHeaders_t = std::vector<std::pair<std::string_view, std::string_view>>;
    using connectionId_t = std::size_t;

    HttpEndpoint(HttpEndpoint &&src) {
        endpoint_ = std::move(src.endpoint_);
        connectionId_ = std::move(src.connectionId_);
        curlHeaders_ = src.curlHeaders_;
        src.curlHeaders_ = nullptr;
        maxRetries_ = src.maxRetries_;
        retryDelay_ = src.retryDelay_;
    }

    HttpEndpoint(std::string endpoint, std::string_view contentType, enpointHeaders_t const &headers, std::size_t maxRetries, std::chrono::milliseconds retryDelay) : endpoint_(std::move(endpoint)), maxRetries_(maxRetries), retryDelay_(retryDelay) {
        auto connectionDetails = utils::getConnectionDetailsFromURL(endpoint_);
        if (!connectionDetails) {
            std::string msg = "Unable to parse connection details from endpoint: "s;
            msg.append(endpoint_);
            throw std::runtime_error(msg);
        }
        connectionId_ = std::hash<std::string>{}(connectionDetails.value());

        fillCurlHeaders(contentType, headers);
    }

    ~HttpEndpoint() {
        if (curlHeaders_) {
            curl_slist_free_all(curlHeaders_);
            curlHeaders_ = nullptr;
        }
    }

    std::string const &getEndpoint() const {
        return endpoint_;
    }

    struct curl_slist *getHeaders() {
        return curlHeaders_;
    }

    connectionId_t getConnectionId() const {
        return connectionId_;
    }

    std::size_t getMaxRetries() const {
        return maxRetries_;
    }

    std::chrono::milliseconds getRetryDelay() const {
        return retryDelay_;
    }

private:
    void fillCurlHeaders(std::string_view contentType, enpointHeaders_t const &headers) {
        if (!contentType.empty()) {
            std::string cType = "Content-Type: "s;
            cType.append(contentType);
            curlHeaders_ = curl_slist_append(curlHeaders_, cType.c_str());
        }

        for (auto const &hdr : headers) {
            std::string header;
            header.append(hdr.first);
            header.append(": "sv);
            header.append(hdr.second);
            curlHeaders_ = curl_slist_append(curlHeaders_, header.c_str());
        }
    }

    std::string endpoint_;
    std::size_t maxRetries_ = 1;
    std::chrono::milliseconds retryDelay_ = 0ms;
    connectionId_t connectionId_;
    struct curl_slist *curlHeaders_ = nullptr;
};

template <typename CurlSender = CurlSender>
class HttpTransportAsync : public ForkableInterface, public boost::noncopyable {

    using endpointUrlHash_t = std::size_t;

public:
    HttpTransportAsync(std::shared_ptr<LoggerInterface> log, std::shared_ptr<ConfigurationStorage> config) : log_(std::move(log)), config_(std::move(config)) {
        CurlInit();
    }

    ~HttpTransportAsync() {
        forceFlush_ = true;
        shutdownThread();
        CurlCleanup();
    }

    void initializeConnection(std::string endpointUrl, size_t endpointHash, std::string contentType, HttpEndpoint::enpointHeaders_t const &endpointHeaders, std::chrono::milliseconds timeout, std::size_t maxRetries, std::chrono::milliseconds retryDelay) {
        ELOG_TRACE(log_, "HttpTransportAsync::initializeConnection endpointUrl '%s' enpointHash: %X timeout: %zums retries: %zu retry delay: %zums", endpointUrl.c_str(), endpointHash, timeout.count(), maxRetries, retryDelay.count());

        try {
            std::lock_guard<std::mutex> lock(mutex_);

            HttpEndpoint endpoint(std::move(endpointUrl), std::move(contentType), endpointHeaders, maxRetries, retryDelay);

            if (connections_.try_emplace(endpoint.getConnectionId(), log_, timeout, config_->get().verify_server_cert).second) { // CurlSender
                ELOG_TRACE(log_, "HttpTransportAsync::initializeConnection endpointUrl '%s' enpointHash: %X initialize new connectionId: %X", endpoint.getEndpoint().c_str(), endpointHash, endpoint.getConnectionId());
            }
            endpoints_.emplace(std::make_pair(endpointHash, std::move(endpoint)));

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
                ELOG_TRACE(log_, "HttpTransportAsync::enqueue payloadsByteUsageLimit %zu reached. Payload will be dropped. enpointHash: %X payload size: %zu, current queue size %zu usage %zu bytes", config_->get().max_send_queue_size, endpointHash, payload.size(), payloadsToSend_.size(), payloadsByteUsage_);
                return;
            }

            payloadsToSend_.emplace(endpointHash, std::vector<std::byte>(payload.begin(), payload.end()));
            payloadsByteUsage_ += payload.size();
        }
        pauseCondition_.notify_all();
    }

    void prefork() final {
        shutdownThread();

        ELOG_TRACE(log_, "HttpTransportAsync::prefork payloads queue size %zu", payloadsToSend_.size());
        CurlCleanup();
    }

    void postfork([[maybe_unused]] bool child) final {
        CurlInit();

        if (child && !payloadsToSend_.empty()) {
            ELOG_TRACE(log_, "HttpTransportAsync::postfork child emptying payloads queue. %zu will be sent from parent", payloadsToSend_.size());
            decltype(payloadsToSend_) q;
            payloadsToSend_.swap(q);
        }
        working_ = true;
        startThread();
        pauseCondition_.notify_all();
    }

protected:
    void startThread() {
        if (!thread_) {
            ELOG_TRACE(log_, "HttpTransportAsync startThread");
            thread_ = std::make_unique<std::thread>([this]() { asyncSender(); });
        }
    }

    void shutdownThread() {
        if (thread_) {
            ELOG_TRACE(log_, "HttpTransportAsync shutdownThread");
        }

        {
            std::lock_guard<std::mutex> lock(mutex_);
            working_ = false;
        }
        pauseCondition_.notify_all();

        if (thread_ && thread_->joinable()) {
            thread_->join();
        }
        thread_.reset();
    }

    void asyncSender() {
        ELOG_TRACE(log_, "HttpTransportAsync::asyncSender blocking signals and starting work");

        elasticapm::utils::blockApacheAndPHPSignals();

        std::unique_lock<std::mutex> lock(mutex_);
        while (working_) {
            pauseCondition_.wait(lock, [this]() -> bool { return !payloadsToSend_.empty() || !working_; });

            if (!working_ && !forceFlush_) {
                break;
            }

            send(lock);
        }
    }

    void send(std::unique_lock<std::mutex> &locked) {
        while (!payloadsToSend_.empty()) {
            auto [endpointHash, payload] = std::move(payloadsToSend_.front());
            payloadsToSend_.pop();
            payloadsByteUsage_ -= payload.size();

            ELOG_TRACE(log_, "HttpTransportAsync::send enpointHash: %X payload size: %zu", endpointHash, payload.size());

            auto const &endpoint = endpoints_.find(endpointHash);
            if (endpoint == std::end(endpoints_)) {
                ELOG_WARNING(log_, "HttpTransportAsync::send enpointHash: %X not found", endpointHash);
                continue;
            }

            auto const &connection = connections_.find(endpoint->second.getConnectionId());
            if (connection == std::end(connections_)) {
                ELOG_WARNING(log_, "HttpTransportAsync::send enpointHash: %X  connectionId: %X not found", endpointHash, endpoint->second.getConnectionId());
                continue;
            }

            ELOG_TRACE(log_, "HttpTransportAsync::send enpointHash: %X connectionId: %X payload size: %zu", endpointHash, endpoint->second.getConnectionId(), payload.size());

            auto maxRetries = std::max(static_cast<std::size_t>(1), static_cast<std::size_t>(endpoint->second.getMaxRetries()));
            auto retryDelay = endpoint->second.getRetryDelay();

            locked.unlock();

            try {
                std::size_t retry = 0;

                while (retry < maxRetries) {
                    auto responseCode = connection->second.sendPayload(endpoint->second.getEndpoint(), endpoint->second.getHeaders(), payload);

                    ELOG_DEBUG(log_, "HttpTransportAsync::send enpointHash: %X connectionId: %X payload size: %zu responseCode %d", endpointHash, endpoint->second.getConnectionId(), payload.size(), static_cast<int>(responseCode));

                    if (responseCode >= 200 && responseCode < 300) {
                        break;
                    }

                    if (responseCode >= 400 && responseCode < 500 && responseCode != 408 && responseCode != 429) {
                        std::string msg = "server returned with code "s;
                        msg.append(std::to_string(responseCode));
                        throw std::runtime_error(msg);
                    }

                    retry++;
                    ELOG_DEBUG(log_, "HttpTransportAsync::send enpointHash: %X connectionId: %X payload size: %zu retry %zu/%zu delay: %zu responseCode %d ", endpointHash, endpoint->second.getConnectionId(), payload.size(), retry, maxRetries, retryDelay.count(), static_cast<int>(responseCode));
                    std::this_thread::sleep_for(retryDelay);
                }

            } catch (std::runtime_error const &e) {
                ELOG_WARNING(log_, "HttpTransportAsync::send exception '%s'. enpointHash: %X connectionId: %X payload size: %zu", e.what(), endpointHash, endpoint->second.getConnectionId(), payload.size());
            }

            locked.lock();
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
    std::unordered_map<endpointUrlHash_t, HttpEndpoint> endpoints_;
    std::unordered_map<HttpEndpoint::connectionId_t, CurlSender> connections_;

    std::queue<std::pair<endpointUrlHash_t, std::vector<std::byte>>> payloadsToSend_;
    std::size_t payloadsByteUsage_ = 0;

    std::mutex mutex_;
    std::unique_ptr<std::thread> thread_;
    std::condition_variable pauseCondition_;
    bool working_ = true;
    bool resumed_ = false;
    std::atomic_bool forceFlush_ = false;
};

} // namespace elasticapm::php::transport
