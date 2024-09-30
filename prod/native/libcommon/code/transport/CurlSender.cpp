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

#include "CurlSender.h"

using namespace std::literals;

namespace elasticapm::php::transport {

static int CurlDebugFunc(CURL *handle, curl_infotype type, char *data, size_t size, void *logger) {
    auto &log = *static_cast<std::shared_ptr<LoggerInterface> *>(logger);
    if (log->doesMeetsLevelCondition(LogLevel::logLevel_info) && type < 3) {
        char prefix = type == CURLINFO_TEXT ? '*' : (type == CURLINFO_HEADER_IN ? '<' : '>');
        log->printf(LogLevel::logLevel_trace, "CurlSender %c %.*s", prefix, size - 1, data);
    }
    return 0;
}

CurlSender::CurlSender(std::shared_ptr<LoggerInterface> logger, std::chrono::milliseconds timeout, bool verifyCert) : log_(std::move(logger)) {

    handle_ = curl_easy_init();
    if (!handle_) {
        throw std::runtime_error("curl_easy_init() failed");
    }

    if (!verifyCert) {
        curl_easy_setopt(handle_, CURLOPT_SSL_VERIFYHOST, 0L);
        curl_easy_setopt(handle_, CURLOPT_SSL_VERIFYPEER, 0L);
    }

    curl_easy_setopt(handle_, CURLOPT_TIMEOUT_MS, static_cast<long>(timeout.count()));
    curl_easy_setopt(handle_, CURLOPT_CONNECTTIMEOUT_MS, static_cast<long>(timeout.count()));

    curl_easy_setopt(handle_, CURLOPT_FOLLOWLOCATION, 1L);
    curl_easy_setopt(handle_, CURLOPT_FORBID_REUSE, 0L);

    curl_easy_setopt(handle_, CURLOPT_DEBUGFUNCTION, CurlDebugFunc);
    curl_easy_setopt(handle_, CURLOPT_DEBUGDATA, &log_);

    curl_easy_setopt(handle_, CURLOPT_VERBOSE, 1L);
}

int16_t CurlSender::sendPayload(std::string const &endpointUrl, struct curl_slist *headers, std::vector<std::byte> const &payload) const {
    curl_easy_setopt(handle_, CURLOPT_URL, endpointUrl.c_str());
    curl_easy_setopt(handle_, CURLOPT_HTTPHEADER, headers);

    curl_easy_setopt(handle_, CURLOPT_POST, 1L);
    curl_easy_setopt(handle_, CURLOPT_POSTFIELDS, payload.data());
    curl_easy_setopt(handle_, CURLOPT_POSTFIELDSIZE, payload.size());

    CURLcode res = curl_easy_perform(handle_);
    if (res != CURLE_OK) {
        std::string msg = "sendPayload failed: "s;
        msg.append(curl_easy_strerror(res));
        throw std::runtime_error(msg);
    }

    long responseCode = 0;
    curl_easy_getinfo(handle_, CURLINFO_RESPONSE_CODE, &responseCode);

    return responseCode;
}

} // namespace elasticapm::php::transport