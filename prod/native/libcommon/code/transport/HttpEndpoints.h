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

#include "CommonUtils.h"
#include "CurlSender.h"
#include "HttpEndpoint.h"
#include "LoggerInterface.h"

#include <chrono>
#include <memory>
#include <string>
#include <string_view>
#include <unordered_map>

namespace elasticapm::php::transport {

using namespace std::literals;

class HttpEndpoints {
public:
    using endpointUrlHash_t = std::size_t;

    HttpEndpoints(std::shared_ptr<LoggerInterface> log) : log_(log) {
    }

    bool add(std::string endpointUrl, size_t endpointHash, bool verifyServerCertificate, std::string contentType, HttpEndpoint::enpointHeaders_t const &endpointHeaders, std::chrono::milliseconds timeout, std::size_t maxRetries, std::chrono::milliseconds retryDelay) {
        std::lock_guard<std::mutex> lock(mutex_);
        auto result = endpoints_.try_emplace(endpointHash, std::move(endpointUrl), std::move(contentType), endpointHeaders, maxRetries, retryDelay);
        if (connections_.try_emplace(result.first->second.getConnectionId(), log_, timeout, verifyServerCertificate).second) { // CurlSender
            ELOGF_DEBUG(log_, TRANSPORT, "HttpEndpoints::add endpointUrl '%s' enpointHash: %X initialize new connectionId: %X", result.first->second.getEndpoint().c_str(), endpointHash, result.first->second.getConnectionId());
            return true;
        }
        return false;
    }

    std::tuple<std::string, curl_slist *, HttpEndpoint::connectionId_t, CurlSender &, std::size_t, std::chrono::milliseconds> getConnection(size_t endpointHash) {
        std::lock_guard<std::mutex> lock(mutex_);
        auto const &endpoint = endpoints_.find(endpointHash);
        if (endpoint == std::end(endpoints_)) {
            std::stringstream stream;
            stream << "HttpEnpoints missing enpointHash:" << std::hex << endpointHash;
            throw std::runtime_error(stream.str());
        }

        auto const &connection = connections_.find(endpoint->second.getConnectionId());
        if (connection == std::end(connections_)) {
            std::stringstream stream;
            stream << "HttpEndpoints enpointHash:" << std::hex << endpointHash << " missing connectionId " << std::hex << endpoint->second.getConnectionId();
            throw std::runtime_error(stream.str());
        }

        auto &conn = connection->second;
        auto maxRetries = std::max(static_cast<std::size_t>(1), static_cast<std::size_t>(endpoint->second.getMaxRetries()));
        auto retryDelay = endpoint->second.getRetryDelay();

        return {endpoint->second.getEndpoint(), endpoint->second.getHeaders(), endpoint->second.getConnectionId(), conn, maxRetries, retryDelay};
    }

    void updateRetryDelay(size_t endpointHash, std::chrono::milliseconds retryDelay) {
        std::lock_guard<std::mutex> lock(mutex_);
        auto const &endpoint = endpoints_.find(endpointHash);
        if (endpoint == std::end(endpoints_)) {
            std::stringstream stream;
            stream << "HttpEnpoints can't update retryDelay for missing enpointHash:" << std::hex << endpointHash;
            throw std::runtime_error(stream.str());
        }
        endpoint->second.setRetryDelay(retryDelay);
    }

protected:
    std::shared_ptr<LoggerInterface> log_;
    std::mutex mutex_;
    std::unordered_map<endpointUrlHash_t, HttpEndpoint> endpoints_;
    std::unordered_map<HttpEndpoint::connectionId_t, CurlSender> connections_;
};

} // namespace elasticapm::php::transport