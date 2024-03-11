

#include <array>
#include "LogLevel.h"
#include <string_view>

using namespace std::string_view_literals;


std::string_view getLogLevelName(LogLevel level) {
    constexpr std::array<std::string_view, LogLevel::last - LogLevel::first + 1> logLevelNames = {
        "OFF     "sv,
        "CRITICAL"sv,
        "ERROR   "sv,
        "WARNING "sv,
        "INFO    "sv,
        "DEBUG   "sv,
        "TRACE   "sv
    };

    if (level < LogLevel::first || level > LogLevel::last) {
        return "UNKNOWN"sv;
    }
    return logLevelNames[level];
}