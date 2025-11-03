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
#include <functional>

using namespace std::literals;

namespace elasticapm::php::transport {

using headersCallback_t = std::function<void(std::string_view)>;

static int CurlDebugFunc(CURL *handle, curl_infotype type, char *data, size_t size, void *logger) {
    auto &log = *static_cast<std::shared_ptr<LoggerInterface> *>(logger);
    if (logger && log->doesFeatureMeetsLevelCondition(LogLevel::logLevel_trace, elasticapm::php::LogFeature::TRANSPORT) && type < 3) {
        char prefix = type == CURLINFO_TEXT ? '*' : (type == CURLINFO_HEADER_IN ? '<' : '>');
        log->printf(LogLevel::logLevel_trace, "CurlSender %c %.*s", prefix, size - 1, data);
    }
    return 0;
}

static size_t CurlWriteFunc(char *data, size_t size, size_t nmemb, void *clientp) {
    if (clientp != nullptr) {
        std::string *buffer = static_cast<std::string *>(clientp);
        buffer->append(data, size * nmemb);
    }
    return size * nmemb;
}

static size_t CurlHeaderFunc(char *data, size_t size, size_t nItems, void *headersCallback) {
    if (headersCallback == nullptr) {
        return size * nItems;
    }

    std::string_view header(data, size * nItems - 2); // remove \r\n
    (*static_cast<headersCallback_t *>(headersCallback))(header);

    return size * nItems;
}

CurlSender::CurlSender(std::shared_ptr<LoggerInterface> logger, std::chrono::milliseconds timeout, HttpEndpointSSLOptions const &sslOptions) : log_(std::move(logger)) {

    handle_ = curl_easy_init();
    if (!handle_) {
        throw std::runtime_error("curl_easy_init() failed");
    }

    if (sslOptions.insecureSkipVerify) {
        curl_easy_setopt(handle_, CURLOPT_SSL_VERIFYHOST, 0L);
        curl_easy_setopt(handle_, CURLOPT_SSL_VERIFYPEER, 0L);
    } else {
        curl_easy_setopt(handle_, CURLOPT_SSL_VERIFYHOST, 2L);
        curl_easy_setopt(handle_, CURLOPT_SSL_VERIFYPEER, 1L);
    }

    if (!sslOptions.caInfo.empty()) {
        curl_easy_setopt(handle_, CURLOPT_CAINFO, sslOptions.caInfo.c_str());
    }

    if (!sslOptions.cert.empty()) {
        curl_easy_setopt(handle_, CURLOPT_SSLCERT, sslOptions.cert.c_str());
    }

    if (!sslOptions.certKey.empty()) {
        curl_easy_setopt(handle_, CURLOPT_SSLKEY, sslOptions.certKey.c_str());
    }
    if (!sslOptions.certKeyPassword.empty()) {
        curl_easy_setopt(handle_, CURLOPT_KEYPASSWD, sslOptions.certKeyPassword.c_str());
    }

    curl_easy_setopt(handle_, CURLOPT_TIMEOUT_MS, static_cast<long>(timeout.count()));
    curl_easy_setopt(handle_, CURLOPT_CONNECTTIMEOUT_MS, static_cast<long>(timeout.count()));

    curl_easy_setopt(handle_, CURLOPT_FOLLOWLOCATION, 1L);
    curl_easy_setopt(handle_, CURLOPT_FORBID_REUSE, 0L);
    curl_easy_setopt(handle_, CURLOPT_WRITEFUNCTION, CurlWriteFunc);
    curl_easy_setopt(handle_, CURLOPT_WRITEDATA, nullptr);
    curl_easy_setopt(handle_, CURLOPT_HEADERFUNCTION, CurlHeaderFunc);
    curl_easy_setopt(handle_, CURLOPT_HEADERDATA, nullptr);

    if (log_ && log_->doesMeetsLevelCondition(LogLevel::logLevel_trace)) {
        curl_easy_setopt(handle_, CURLOPT_DEBUGFUNCTION, CurlDebugFunc);
        curl_easy_setopt(handle_, CURLOPT_DEBUGDATA, &log_);
        curl_easy_setopt(handle_, CURLOPT_VERBOSE, 1L);
    }
}

int16_t CurlSender::sendPayload(std::string const &endpointUrl, struct curl_slist *headers, std::vector<std::byte> const &payload, std::function<void(std::string_view)> headerCallback, std::string *responseBuffer) const {
    curl_easy_setopt(handle_, CURLOPT_URL, endpointUrl.c_str());
    curl_easy_setopt(handle_, CURLOPT_HTTPHEADER, headers);

    curl_easy_setopt(handle_, CURLOPT_POSTFIELDS, payload.data());
    curl_easy_setopt(handle_, CURLOPT_POSTFIELDSIZE, payload.size());
    curl_easy_setopt(handle_, CURLOPT_POST, 1L);

    if (responseBuffer) {
        curl_easy_setopt(handle_, CURLOPT_WRITEDATA, responseBuffer);
    } else {
        curl_easy_setopt(handle_, CURLOPT_WRITEDATA, nullptr);
    }

    if (!headerCallback) {
        curl_easy_setopt(handle_, CURLOPT_HEADERDATA, nullptr);
    } else {
        curl_easy_setopt(handle_, CURLOPT_HEADERDATA, &headerCallback);
    }

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