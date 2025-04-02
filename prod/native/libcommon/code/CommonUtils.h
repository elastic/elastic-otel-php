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
#include "LogFeature.h"
#include "LogLevel.h"

#include <chrono>
#include <memory>
#include <optional>
#include <regex>
#include <string>
#include <string_view>
#include <unordered_map>

namespace elasticapm::utils {

[[maybe_unused]] bool blockSignal(int signo);
void blockApacheAndPHPSignals();

std::chrono::milliseconds convertDurationWithUnit(std::string timeWithUnit); // default unit - ms, handles ms, s, m, throws std::invalid_argument if unit is unknown
std::size_t parseByteUnits(std::string bytesWithUnit);                       // default unit - b, handles b, kb, mb, gb , throws std::invalid_argument if unit is unknown

bool parseBoolean(std::string_view val); // throws  std::invalid_argument
LogLevel parseLogLevel(std::string_view val); // throws  std::invalid_argument

std::string getParameterizedString(std::string_view format);

std::string stringPrintf(const char *format, ...);
std::string stringVPrintf(const char *format, va_list args);

template<typename StringType>
StringType trim(StringType value) {
    using namespace std::string_view_literals;
    auto constexpr space = " \f\n\r\t\v"sv;
    auto lpos = value.find_first_not_of(space);
    auto rpos = value.find_last_not_of(space);

    std::size_t resultLen = value.length() - (lpos == StringType::npos ? 0 : lpos) - (value.length() - (rpos == StringType::npos ? 0 : rpos + 1));
    if (resultLen == value.length()) {
        return value;
    } else if (resultLen == 0) {
        return {};
    }
    return value.substr(lpos, resultLen);
}

std::string getIniName(std::string_view optionName);
std::string getEnvName(std::string_view optionName);

std::string sanitizeKeyValueString(std::string const &tokenName, std::string const &text);

struct ParsedURL {
    std::string protocol;
    std::string host;
    std::optional<std::string> port;
    std::optional<std::string> query;
};

std::optional<ParsedURL> parseUrl(std::string const &url);

std::optional<std::string> getConnectionDetailsFromURL(std::string const &url);

std::unordered_map<elasticapm::php::LogFeature, LogLevel> parseLogFeatures(std::shared_ptr<elasticapm::php::LoggerInterface> logger, std::string_view logFeatures);

bool isUtf8(std::string_view input);
}