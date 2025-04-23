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


#include "CommonUtils.h"
#include "CiCharTraits.h"
#include "LogLevel.h"
#include "LogFeature.h"
#include "LoggerInterface.h"

#include <algorithm>
#include <array>
#include <charconv>
#include <chrono>
#include <cstdarg>
#include <memory>
#include <optional>
#include <ranges>
#include <string_view>
#include <signal.h>
#include <stddef.h>
#include <sys/types.h>
#include <unistd.h>
#include <boost/regex.hpp>

namespace elasticapm::utils {

using namespace std::literals;


[[maybe_unused]] bool blockSignal(int signo) {
    sigset_t currentSigset;

    if (pthread_sigmask(SIG_BLOCK, NULL, &currentSigset) != 0) {
    	sigemptyset(&currentSigset);
    }

    if (sigismember(&currentSigset, signo) == 1) {
        return true;
    }

    sigaddset(&currentSigset, signo);
    return pthread_sigmask(SIG_BLOCK, &currentSigset, NULL) == 0;
}

void blockApacheAndPHPSignals() {
    // block signals for this thread to be handled by main Apache/PHP thread
    // list of signals from Apaches mpm handlers
    elasticapm::utils::blockSignal(SIGTERM);
    elasticapm::utils::blockSignal(SIGHUP);
    elasticapm::utils::blockSignal(SIGINT);
    elasticapm::utils::blockSignal(SIGWINCH);
    elasticapm::utils::blockSignal(SIGUSR1);
    elasticapm::utils::blockSignal(SIGPROF); // php timeout signal
}

std::size_t parseByteUnits(std::string bytesWithUnit) {
    auto endWithoutSpaces = std::remove_if(bytesWithUnit.begin(), bytesWithUnit.end(), [](unsigned char c) { return std::isspace(c); });
    bytesWithUnit.erase(endWithoutSpaces, bytesWithUnit.end());

    std::size_t value = std::stoul(bytesWithUnit.data());
    auto unitPos = bytesWithUnit.find_first_not_of("0123456789"sv);

    if (unitPos == std::string_view::npos) {
        return value;
    }

    auto unitBuf = bytesWithUnit.substr(unitPos);
    istring_view unit{unitBuf.data(), unitBuf.length()};

    if (unit == "b"_cisv) {
        return value;
    } else if (unit == "kb"_cisv) {
        return value * 1024;
    } else if (unit == "mb"_cisv) {
        return value * 1024 * 1024;
    } else if (unit == "gb"_cisv) {
        return value * 1024 * 1024 * 1024;
    }

    throw std::invalid_argument("Invalid byte unit.");
}

//TODO handle other string types
std::chrono::milliseconds convertDurationWithUnit(std::string timeWithUnit) {
    auto endWithoutSpaces = std::remove_if(timeWithUnit.begin(), timeWithUnit.end(), [](unsigned char c) { return std::isspace(c); });
    timeWithUnit.erase(endWithoutSpaces, timeWithUnit.end());

    double timeValue = std::stod(timeWithUnit.data());
    auto unitPos = timeWithUnit.find_first_not_of("0123456789."sv);

    if (unitPos == std::string_view::npos) {
        return std::chrono::duration_cast<std::chrono::milliseconds>(std::chrono::duration<double, std::milli>(timeValue)) ;
    }

    std::string unit{timeWithUnit.substr(unitPos)};

    if (unit == "ms") {
        return std::chrono::duration_cast<std::chrono::milliseconds>(std::chrono::duration<double, std::milli>(timeValue)) ;
    } else if (unit == "s") {
        return std::chrono::duration_cast<std::chrono::milliseconds>(std::chrono::duration<double>(timeValue)) ;
    } else if (unit == "m") {
       return std::chrono::duration_cast<std::chrono::milliseconds>(std::chrono::duration<double, std::ratio<60>>(timeValue)) ;
    }

    throw std::invalid_argument("Invalid time unit.");
}


bool parseBoolean(std::string_view val) {
    constexpr std::array<istring_view, 3> trueValues = {"true"_cisv, "yes"_cisv, "on"_cisv}; // same in zend_ini_parse_bool

    auto value = traits_cast<CiCharTraits>(utils::trim(val));
    if (!value.length()) {
        return false;
    }

    auto res = std::find(std::begin(trueValues), std::end(trueValues), value);
    if (res != std::end(trueValues)) {
        return true;
    } else {
        int iVal{};
        if (std::from_chars(value.data(), value.data() + value.length(), iVal).ec == std::errc{}) {
            return iVal;
        }
    }
    return false;
}

LogLevel parseLogLevel(std::string_view val) { // throws  std::invalid_argument
    constexpr std::array<istring_view, LogLevel::last - LogLevel::first + 1> levels = {"OFF"_cisv, "CRITICAL"_cisv, "ERROR"_cisv, "WARNING"_cisv, "INFO"_cisv, "DEBUG"_cisv, "TRACE"_cisv};

    auto value = traits_cast<CiCharTraits>(utils::trim(val));
    auto found = std::find(std::begin(levels), std::end(levels), value);

    if (found != std::end(levels)) {
        auto index = std::distance(levels.begin(), found);
        return static_cast<LogLevel>(index);
    }
    throw std::invalid_argument("Unknown log level: "s + std::string(val));
}


std::string getParameterizedString(std::string_view format) {

    std::string out;

    for (auto c = format.begin(); c < format.end(); ++c) {
        if (*c == '%') {
            c++;
            if (c == format.end()) {
                out.append(1, '%');
                break;
            }

            switch (*c) {
                case 'p':
                    out.append(std::to_string(getpid()));
                    break;
                case 't':
                    out.append(std::to_string(std::chrono::milliseconds(std::time(NULL)).count()));
                    break;
                default:
                    out.append(1, '%');
                    out.append(1, *c);
                }
        } else {
            out.append({*c});
        }
    }

    return out;
}

std::string stringPrintf(const char *format, ...) {
    va_list args;
    va_start(args, format);
    auto result = stringVPrintf(format, args);
    va_end(args);
    return result;
}

std::string stringVPrintf(const char *format, va_list args) {
    std::va_list argsCopy;
    va_copy(argsCopy, args);
    auto reqSpace = std::vsnprintf(nullptr, 0, format, argsCopy);
    va_end(argsCopy);
    if (reqSpace < 0) {
        return {};
    }

    std::string buffer;
    buffer.reserve(reqSpace + 1);
    buffer.resize(reqSpace);

    auto allocated = std::vsnprintf(buffer.data(), buffer.capacity(), format, args);
    buffer.resize(allocated);
    return buffer;
}

std::string getIniName(std::string_view optionName) {
    auto name = "elastic_otel."s;
    return name.append(optionName);
}

std::string getEnvName(std::string_view optionName) {
    std::string envName = "ELASTIC_OTEL_"s;
    std::transform(optionName.begin(), optionName.end(), std::back_inserter(envName), ::toupper);
    return envName;
}

std::string sanitizeKeyValueString(std::string const &tokenName, std::string const &text) {
    std::regex regex(tokenName + R"(=("[^"]*"|[^,\s]*))"s, std::regex::icase);
    return std::regex_replace(text, regex, tokenName + "=***"s);
}

std::optional<ParsedURL> parseUrl(std::string const &url) {
    std::regex url_regex(R"((http|https)://([\w.-]+)(?::(\d+))?(?:/(.*))?)");
    std::smatch match;

    if (std::regex_match(url, match, url_regex)) {
        ParsedURL parsed_url;
        parsed_url.protocol = match[1];
        parsed_url.host = match[2];

        if (match[3].matched) {
            parsed_url.port = match[3];
        }

        if (match[4].matched) {
            parsed_url.query = match[4];
        }

        return parsed_url;
    }

    return std::nullopt;
}

std::optional<std::string> getConnectionDetailsFromURL(std::string const &url) {
    boost::regex urlPattern(R"(^((https?):\/\/([^\/:]+))(?::(\d+))?)");
    boost::smatch match;

    if (boost::regex_search(url, match, urlPattern)) {
        return match[4].matched ? match[1].str() + ":" + match[4].str() : match[1].str();
    }

    return std::nullopt;
}

// FEATURENAME=LEVEL,ANOTHERFEATURE=LOGLEVEL
std::unordered_map<elasticapm::php::LogFeature, LogLevel> parseLogFeatures(std::shared_ptr<elasticapm::php::LoggerInterface> logger, std::string_view logFeatures) {
    std::unordered_map<elasticapm::php::LogFeature, LogLevel> features;

    if (!logFeatures.empty()) {
        using namespace std::string_view_literals;
        for (const auto split : std::views::split(logFeatures, ","sv)) {
            std::string_view featureAndValue(split);
            auto pos = featureAndValue.find('=');
            if (pos != std::string_view::npos) {
                std::string_view option = featureAndValue.substr(0, pos);
                std::string_view value = featureAndValue.substr(pos + 1);

                try {
                    auto level = parseLogLevel(value);
                    auto feature = elasticapm::php::parseLogFeature(option);
                    features.emplace(feature, level);
                } catch (std::invalid_argument const &e) {
                    ELOGF_NF_WARNING(logger, "Error while parsing LogFeature " PRsv ", exception: %s ", PRsvArg(featureAndValue), e.what());
                }
            } else {
                ELOGF_NF_WARNING(logger, "Error while parsing LogFeature " PRsv, PRsvArg(featureAndValue));
            }
        }
    }
    return features;
}

bool isUtf8(std::string_view input) {
    const uint8_t *p = reinterpret_cast<const uint8_t *>(input.data());
    size_t length = input.size();

    static constexpr uint8_t utf8_table[] = {1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 3, 3, 3, 3, 3, 3, 3, 3};

    while (length > 0) {
        uint32_t d;
        uint8_t c = *p++;
        length--;

        if (c < 0x80)
            continue;

        if (c < 0xC0 || c >= 0xF5)
            return false;

        uint8_t ab = utf8_table[c & 0x3F];
        if (length < ab)
            return false;
        length -= ab;

        if (((d = *p++) & 0xC0) != 0x80)
            return false;

        switch (ab) {
            case 1:
                if ((c & 0x3E) == 0)
                    return false;
                break;

            case 2:
                if ((*p++ & 0xC0) != 0x80 || (c == 0xE0 && (d & 0x20) == 0) || (c == 0xED && d >= 0xA0))
                    return false;
                break;

            case 3:
                if ((*p++ & 0xC0) != 0x80 || (*p++ & 0xC0) != 0x80 || (c == 0xF0 && (d & 0x30) == 0) || (c > 0xF4 || (c == 0xF4 && d > 0x8F)))
                    return false;
                break;
        }
    }

    return true;
}
}