#pragma once

#include "PhpSapi.h"
#include <functional>
#include <memory>

namespace elasticapm::php {

class LoggerInterface;
class PhpBridgeInterface;
class PeriodicTaskExecutor;
class SharedMemoryState;
class RequestScope;
class ConfigurationStorage;
class ConfigurationSnapshot;
class LoggerSinkInterface;
class InstrumentedFunctionHooksStorageInterface;

class AgentGlobals {
public:
    AgentGlobals(std::shared_ptr<LoggerInterface> logger,
        std::shared_ptr<LoggerSinkInterface> logSinkStdErr,
        std::shared_ptr<LoggerSinkInterface> logSinkSysLog,
        std::shared_ptr<PhpBridgeInterface> bridge,
        std::shared_ptr<InstrumentedFunctionHooksStorageInterface> hooksStorage,
        std::function<bool(ConfigurationSnapshot &)> updateConfigurationSnapshot);

    ~AgentGlobals();

    std::shared_ptr<ConfigurationStorage> config_;
    std::shared_ptr<LoggerInterface> logger_;
    std::shared_ptr<PhpBridgeInterface> bridge_;
    std::shared_ptr<InstrumentedFunctionHooksStorageInterface> hooksStorage_;
    std::shared_ptr<PhpSapi> sapi_;
    std::unique_ptr<PeriodicTaskExecutor> periodicTaskExecutor_;
    std::shared_ptr<SharedMemoryState> sharedMemory_;
    std::shared_ptr<RequestScope> requestScope_;

    std::shared_ptr<LoggerSinkInterface> logSinkStdErr_;
    std::shared_ptr<LoggerSinkInterface> logSinkSysLog_;
    // std::shared_ptr<elasticapm::php::LoggerSinkInterface> logSinkFile_;
};

} // namespace elasticapm::php
