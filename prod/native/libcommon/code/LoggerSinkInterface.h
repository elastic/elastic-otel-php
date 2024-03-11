#pragma once

#include "LogLevel.h"
#include <string>
#include <string_view>

namespace elasticapm::php {

class LoggerSinkInterface {
public:
    virtual ~LoggerSinkInterface() {
    }

    virtual LogLevel getLevel() const = 0;
    virtual void setLevel(LogLevel) = 0;

    virtual void writeLog(std::string const &formattedOutput, std::string_view message, std::string_view time, std::string_view level, std::string_view process) const = 0;
};

}
