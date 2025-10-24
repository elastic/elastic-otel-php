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

#include "LoggerInterface.h"
#include "transport/HttpTransportAsyncInterface.h"

#include <functional>
#include <memory>
#include <string>

namespace elasticapm::php::coordinator {

class CoordinatorTelemetrySignalsSender : public transport::HttpTransportAsyncInterface {
public:
    using sendPayload_t = std::function<bool(std::string const &payload)>;

    CoordinatorTelemetrySignalsSender(std::shared_ptr<LoggerInterface> logger, sendPayload_t sendPayload)
        : logger_(std::move(logger)), sendPayload_(std::move(sendPayload)) {
    }

    ~CoordinatorTelemetrySignalsSender() = default;

    void initializeConnection(std::string endpointUrl, std::size_t endpointHash, std::string contentType, enpointHeaders_t const &endpointHeaders, std::chrono::milliseconds timeout, std::size_t maxRetries, std::chrono::milliseconds retryDelay);
    void enqueue(std::size_t endpointHash, std::span<std::byte> payload, responseCallback_t callback = {});
    void updateRetryDelay(size_t endpointHash, std::chrono::milliseconds retryDelay) {
    }

private:
    std::shared_ptr<LoggerInterface> logger_;
    sendPayload_t sendPayload_;
};
}
