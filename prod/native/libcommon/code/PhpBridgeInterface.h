#pragma once

#include "LogLevel.h"
#include <chrono>
#include <optional>
#include <string>
#include <string_view>
#include <vector>

namespace elasticapm::php {


class PhpBridgeInterface {
public:

    struct phpExtensionInfo_t {
        std::string name;
        std::string version;
    };

    virtual ~PhpBridgeInterface() = default;

    virtual bool callInferredSpans(std::chrono::milliseconds duration) const = 0;
    virtual bool callPHPSideEntryPoint(LogLevel logLevel, std::chrono::time_point<std::chrono::system_clock> requestInitStart) const = 0;
    virtual bool callPHPSideExitPoint() const = 0;
    virtual bool callPHPSideErrorHandler(int type, std::string_view errorFilename, uint32_t errorLineno, std::string_view message) const = 0;

    virtual std::vector<phpExtensionInfo_t> getExtensionList() const = 0;
    virtual std::string getPhpInfo() const = 0;

    virtual std::string_view getPhpSapiName() const = 0;

    virtual std::optional<std::string_view> getCurrentExceptionMessage() const = 0;

    virtual void compileAndExecuteFile(std::string_view fileName) const = 0;

    virtual void enableAccessToServerGlobal() const = 0;

    virtual bool detectOpcachePreload() const = 0;
    virtual bool isScriptRestricedByOpcacheAPI() const = 0;
    virtual bool detectOpcacheRestartPending() const = 0;
    virtual bool isOpcacheEnabled() const = 0;
};

}
