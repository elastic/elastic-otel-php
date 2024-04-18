#include "AgentGlobals.h"

#include "PhpBridgeInterface.h"
#include "SharedMemoryState.h"
#include "PeriodicTaskExecutor.h"
#include "RequestScope.h"
#include "LoggerInterface.h"
#include "LoggerSinkInterface.h"
#include "ConfigurationStorage.h"
#include "InstrumentedFunctionHooksStorage.h"
#include "CommonUtils.h"

namespace elasticapm::php {

AgentGlobals::AgentGlobals(std::shared_ptr<LoggerInterface> logger,
        std::shared_ptr<LoggerSinkInterface> logSinkStdErr,
        std::shared_ptr<LoggerSinkInterface> logSinkSysLog,
        std::shared_ptr<LoggerSinkFile> logSinkFile,
        std::shared_ptr<PhpBridgeInterface> bridge,
        std::shared_ptr<InstrumentedFunctionHooksStorageInterface> hooksStorage,
        ConfigurationStorage::configUpdate_t updateConfigurationSnapshot) :
    config_(std::make_shared<elasticapm::php::ConfigurationStorage>(std::move(updateConfigurationSnapshot))),
    logger_(std::move(logger)),
    bridge_(std::move(bridge)),
    hooksStorage_(std::move(hooksStorage)),
    sapi_(std::make_shared<elasticapm::php::PhpSapi>(bridge_->getPhpSapiName())),
    periodicTaskExecutor_(),
    sharedMemory_(std::make_shared<elasticapm::php::SharedMemoryState>()),
    requestScope_(std::make_shared<elasticapm::php::RequestScope>(logger_, bridge_, sapi_, sharedMemory_, config_, [hs = hooksStorage_]() { hs->clear(); })),
    logSinkStdErr_(std::move(logSinkStdErr)),
    logSinkSysLog_(std::move(logSinkSysLog)),
    logSinkFile_(std::move(logSinkFile))
    {
        config_->addConfigUpdateWatcher([stderrsink = logSinkStdErr_, syslogsink = logSinkSysLog_, filesink = logSinkFile_](ConfigurationSnapshot const &cfg) {
            stderrsink->setLevel(cfg.log_level_stderr);
            syslogsink->setLevel(cfg.log_level_syslog);
            if (filesink) {
                if (cfg.log_file.empty()) {
                    filesink->setLevel(LogLevel::logLevel_off);
                    return;
                }

                filesink->setLevel(cfg.log_level_file);
                filesink->reopen(utils::getParameterizedString(cfg.log_file));
            }
        });
    }


AgentGlobals::~AgentGlobals() {
    config_->removeAllConfigUpdateWatchers();
}


}

