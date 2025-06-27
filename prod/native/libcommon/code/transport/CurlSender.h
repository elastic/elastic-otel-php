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

#include <chrono>
#include <functional>
#include <memory>
#include <string>
#include <string_view>
#include <vector>
#include <curl/curl.h>

#include <boost/noncopyable.hpp>

namespace elasticapm::php::transport {

class CurlSender {
public:
    CurlSender(std::shared_ptr<LoggerInterface> logger, std::chrono::milliseconds timeout, bool verifyCert);

    CurlSender(CurlSender &&) = delete;
    CurlSender &operator=(CurlSender &&) = delete;
    CurlSender(CurlSender const &) = delete;
    CurlSender &operator=(CurlSender const &) = delete;

    ~CurlSender() {
        if (handle_) {
            curl_easy_cleanup(handle_);
        }
    }

    int16_t sendPayload(std::string const &endpointUrl, struct curl_slist *headers, std::vector<std::byte> const &payload, std::function<void(std::string_view)> headerCallback, std::string *responseBuffer = nullptr) const;

private:
    CURL *handle_ = nullptr;
    std::shared_ptr<LoggerInterface> log_;
};

} // namespace elasticapm::php::transport