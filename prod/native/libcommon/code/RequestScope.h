#pragma once

#include "CommonUtils.h"
#include "Diagnostics.h"
#include "LoggerInterface.h"
#include "PhpBridgeInterface.h"
#include "PhpSapi.h"
#include "SharedMemoryState.h"
#include "ConfigurationStorage.h"

#include <memory>
#include <string_view>

namespace elasticapm::php {

class RequestScope {
public:
    using clearHooks_t = std::function<void()>;

    RequestScope(std::shared_ptr<LoggerInterface> log, std::shared_ptr<PhpBridgeInterface> bridge, std::shared_ptr<PhpSapi> sapi, std::shared_ptr<SharedMemoryState> sharedMemory, std::shared_ptr<ConfigurationStorage> config, clearHooks_t clearHooks) : log_(log), bridge_(std::move(bridge)), sapi_(std::move(sapi)), sharedMemory_(sharedMemory), config_(config), clearHooks_(std::move(clearHooks)) {
    }

    void onRequestInit() {
        ELOG_DEBUG(log_, __FUNCTION__);

        if (!sapi_->isSupported()) {
            ELOG_DEBUG(log_, "SAPI '%s' not supported", sapi_->getName().data());
            return;
        }

        config_->update();

        if (!(*config_)->enabled) {
            ELOG_DEBUG(log_, "Global instrumentation not enabled");
            return;
        }

        resetRequest();
        requestCounter_++;

        auto requestStartTime = std::chrono::system_clock::now();

        bridge_->enableAccessToServerGlobal();

        preloadDetected_ = requestCounter_ == 1 ? bridge_->detectOpcachePreload() : false;

        if (requestCounter_ == 1 && preloadDetected_) {
            ELOG_DEBUG(log_, "opcache.preload request detected on init");
            return;
        } else if (!preloadDetected_ && requestCounter_ <= 2) {
            auto const &diagnosticFile = (*config_)->debug_diagnostic_file;
            if (!diagnosticFile.empty()) {
                if (sharedMemory_->shouldExecuteOneTimeTaskAmongWorkers()) {
                    try {
                        // TODO log supportability info
                        elasticapm::utils::storeDiagnosticInformation(elasticapm::utils::getParameterizedString(diagnosticFile), *(bridge_));
                    } catch (std::exception const &e) {
                        ELOG_WARNING(log_, "Unable to write agent diagnostics: %s", e.what());
                    }
                }
            }
        }

        if (!bridge_->isScriptRestricedByOpcacheAPI() && bridge_->detectOpcacheRestartPending()) {
            ELOG_WARNING(log_, "Detected that opcache reset is in a pending state. Instrumentation has been disabled for this request. There may be warnings or errors logged for this request.");
            return;
        }

        bootstrapSuccessfull_ = bootstrapPHPSideInstrumentation(requestStartTime);

    }

    void onRequestShutdown() {
        ELOG_DEBUG(log_, __FUNCTION__);

        if (!bootstrapSuccessfull_) {
            return;
        }

        if (preloadDetected_) {
            ELOG_DEBUG(log_, "opcache.preload request detected on shutdown");
            return;
        }

        if (!bridge_->callPHPSideExitPoint()) {
            ELOG_ERROR(log_, "callPHPSideExitPoint failed");
        }
    }

    void onRequestPostDeactivate() {
        ELOG_DEBUG(log_, __FUNCTION__);

        if (!bootstrapSuccessfull_) {
            return;
        }
    }

    bool handleError(int type, std::string_view errorFilename, uint32_t errorLineno, std::string_view message) {
        ELOG_INFO(log_, "handleError type: %d fn: %s:%d msg: %s\n", type, errorFilename.data(), errorLineno, message.data());

        bridge_->callPHPSideErrorHandler(type, errorFilename, errorLineno, message);

        return false;
    }

    bool isFunctional() {
        return bootstrapSuccessfull_;
    }

protected:
    bool bootstrapPHPSideInstrumentation(std::chrono::system_clock::time_point requestStartTime) {
        using namespace std::string_view_literals;
        try {
            bridge_->compileAndExecuteFile((*config_)->bootstrap_php_part_file);
            bridge_->callPHPSideEntryPoint(LogLevel::logLevel_trace, requestStartTime);
        } catch (std::exception const &e) {
            ELOG_CRITICAL(log_, "Unable to bootstrap PHP-side instrumentation '%s'", e.what());
            return false;
        }

        return true;
    }

    void resetRequest() {
        bootstrapSuccessfull_ = false;
        clearHooks_();
    }

private:
    std::shared_ptr<LoggerInterface> log_;
    std::shared_ptr<PhpBridgeInterface> bridge_;
    std::shared_ptr<PhpSapi> sapi_;
    std::shared_ptr<SharedMemoryState> sharedMemory_;
    std::shared_ptr<ConfigurationStorage> config_;
    clearHooks_t clearHooks_;
    size_t requestCounter_ = 0;
    bool bootstrapSuccessfull_ = false;
    bool preloadDetected_ = false;
};

} // namespace elasticapm::php