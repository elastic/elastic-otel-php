
#include "CommonUtils.h"
#include "CiCharTraits.h"
#include "LogLevel.h"

#include <algorithm>
#include <array>
#include <charconv>
#include <chrono>
#include <cstdarg>
#include <string_view>
#include <signal.h>
#include <stddef.h>
#include <sys/types.h>
#include <unistd.h>


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


bool parseBoolean(std::string_view val) { // throws std::invalid_argument and others
    constexpr std::array<istring_view, 3> trueValues = {"true"_cisv, "yes"_cisv, "on"_cisv}; // same in zend_ini_parse_bool

    auto value = traits_cast<CiCharTraits>(utils::trim(val));
    if (!value.length()) {
        throw std::invalid_argument("Unable to parse boolean from empty string");
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
    auto name = "elastic_apm."s;
    return name.append(optionName);
}

std::string getEnvName(std::string_view optionName) {
    std::string envName = "ELASTIC_APM_"s;
    std::transform(optionName.begin(), optionName.end(), std::back_inserter(envName), ::toupper);
    return envName;
}

std::string sanitizeKeyValueString(std::string const &tokenName, std::string const &text) {
    std::regex regex(tokenName + R"(=("[^"]*"|[^,\s]*))"s, std::regex::icase);
    return std::regex_replace(text, regex, tokenName + "=***"s);
}


}