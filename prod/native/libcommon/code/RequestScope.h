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
        ELOGF_DEBUG(log_, REQUEST, __FUNCTION__);

        resetRequest();

        if (!sapi_->isSupported()) {
            ELOGF_DEBUG(log_, REQUEST, "SAPI '%s' not supported", sapi_->getName().data());
            return;
        }

        config_->update();

        if (!(*config_)->enabled) {
            ELOGF_DEBUG(log_, REQUEST, "Global instrumentation not enabled");
            return;
        }

        requestCounter_++;

        auto requestStartTime = std::chrono::system_clock::now();

        bridge_->enableAccessToServerGlobal();

        preloadDetected_ = requestCounter_ == 1 ? bridge_->detectOpcachePreload() : false;

        if (requestCounter_ == 1 && preloadDetected_) {
            ELOGF_DEBUG(log_, REQUEST, "opcache.preload request detected on init");
            return;
        } else if (!preloadDetected_ && requestCounter_ <= 2) {
            auto const &diagnosticFile = (*config_)->debug_diagnostic_file;
            if (!diagnosticFile.empty()) {
                if (sharedMemory_->shouldExecuteOneTimeTaskAmongWorkers()) {
                    try {
                        // TODO log supportability info
                        elasticapm::utils::storeDiagnosticInformation(elasticapm::utils::getParameterizedString(diagnosticFile), *(bridge_));
                    } catch (std::exception const &e) {
                        ELOGF_WARNING(log_, REQUEST, "Unable to write agent diagnostics: %s", e.what());
                    }
                }
            }
        }

        if (!bridge_->isScriptRestricedByOpcacheAPI() && bridge_->detectOpcacheRestartPending()) {
            ELOGF_WARNING(log_, REQUEST, "Detected that opcache reset is in a pending state. Instrumentation has been disabled for this request. There may be warnings or errors logged for this request.");
            return;
        }

        bootstrapSuccessfull_ = bootstrapPHPSideInstrumentation(requestStartTime);

    }

    void onRequestShutdown() {
        ELOGF_DEBUG(log_, REQUEST, __FUNCTION__);

        if (preloadDetected_) {
            ELOGF_DEBUG(log_, REQUEST, "opcache.preload request detected on shutdown");
            return;
        }

        if (!bootstrapSuccessfull_) {
            ELOGF_DEBUG(log_, REQUEST, "onRequestShutdown bootstrap not successfull");
            return;
        }

        if (!bridge_->callPHPSideExitPoint()) {
            ELOGF_ERROR(log_, REQUEST, "callPHPSideExitPoint failed");
        }
    }

    void onRequestPostDeactivate() {
        ELOGF_DEBUG(log_, REQUEST, __FUNCTION__);

        resetRequest();

        if (!bootstrapSuccessfull_) {
            return;
        }
    }

    bool handleError(int type, std::string_view errorFilename, uint32_t errorLineno, std::string_view message) {
        ELOGF_DEBUG(log_, REQUEST, "RequestScope::handleError type: %d fn: %s:%d msg: %s", type, errorFilename.data(), errorLineno, message.data());

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
            bridge_->callPHPSideEntryPoint(log_->getMaxLogLevel(), requestStartTime);
        } catch (std::exception const &e) {
            ELOGF_CRITICAL(log_, REQUEST, "Unable to bootstrap PHP-side instrumentation '%s'", e.what());
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