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

#include "HttpEndpointSSLOptions.h"

#include <chrono>
#include <cstddef>
#include <functional>
#include <span>
#include <string_view>
#include <vector>

using namespace std::literals;

namespace elasticapm::php::transport {

class HttpTransportAsyncInterface {
public:
    using responseCallback_t = std::function<void(int16_t responseCode, std::span<std::byte> data)>;
    using enpointHeaders_t = std::vector<std::pair<std::string_view, std::string_view>>;

    virtual ~HttpTransportAsyncInterface() = default;

    virtual void initializeConnection(std::string endpointUrl, std::size_t endpointHash, std::string contentType, enpointHeaders_t const &endpointHeaders, std::chrono::milliseconds timeout, std::size_t maxRetries, std::chrono::milliseconds retryDelay, HttpEndpointSSLOptions sslOptions) = 0;
    virtual void enqueue(std::size_t endpointHash, std::span<std::byte> payload, responseCallback_t callback = {}) = 0;
    virtual void updateRetryDelay(size_t endpointHash, std::chrono::milliseconds retryDelay) = 0;
};

} // namespace elasticapm::php::transport