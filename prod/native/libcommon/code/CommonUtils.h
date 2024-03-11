
#pragma once

#include "LogLevel.h"
#include <chrono>
#include <regex>
#include <string>
#include <string_view>

namespace elasticapm::utils {

[[maybe_unused]] bool blockSignal(int signo);

std::chrono::milliseconds convertDurationWithUnit(std::string timeWithUnit); // default unit - ms, handles ms, s, m, throws std::invalid_argument if unit is unknown
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


}