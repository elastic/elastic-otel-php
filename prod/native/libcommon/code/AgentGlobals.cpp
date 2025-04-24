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

#include "AgentGlobals.h"

#include "PhpBridgeInterface.h"
#include "SharedMemoryState.h"
#include "InferredSpans.h"
#include "PeriodicTaskExecutor.h"
#include "PeriodicTaskExecutor.h"
#include "RequestScope.h"
#include "LoggerInterface.h"
#include "LoggerSinkInterface.h"
#include "ConfigurationStorage.h"
#include "InstrumentedFunctionHooksStorage.h"
#include "CommonUtils.h"
#include "transport/HttpTransportAsync.h"
#include "transport/OpAmp.h"
#include "DependencyAutoLoaderGuard.h"
#include "LogFeature.h"
#include <signal.h>

namespace elasticapm::php {
// clang-format off

AgentGlobals::AgentGlobals(std::shared_ptr<LoggerInterface> logger,
        std::shared_ptr<LoggerSinkInterface> logSinkStdErr,
        std::shared_ptr<LoggerSinkInterface> logSinkSysLog,
        std::shared_ptr<LoggerSinkFile> logSinkFile,
        std::shared_ptr<PhpBridgeInterface> bridge,
        std::shared_ptr<InstrumentedFunctionHooksStorageInterface> hooksStorage,
        std::shared_ptr<InferredSpans> inferredSpans,
        ConfigurationStorage::configUpdate_t updateConfigurationSnapshot) :
    config_(std::make_shared<elasticapm::php::ConfigurationStorage>(std::move(updateConfigurationSnapshot))),
    logger_(std::move(logger)),
    bridge_(std::move(bridge)),
    dependencyAutoLoaderGuard_(std::make_shared<DependencyAutoLoaderGuard>(bridge_, logger_)),
    hooksStorage_(std::move(hooksStorage)),
    sapi_(std::make_shared<elasticapm::php::PhpSapi>(bridge_->getPhpSapiName())),
    inferredSpans_(std::move(inferredSpans)),
    periodicTaskExecutor_(),
    httpTransportAsync_(std::make_unique<elasticapm::php::transport::HttpTransportAsync<>>(logger_, config_)),
    opAmp_(std::make_shared<opentelemetry::php::transport::OpAmp>(logger_, config_)),
    sharedMemory_(std::make_shared<elasticapm::php::SharedMemoryState>()),
    requestScope_(std::make_shared<elasticapm::php::RequestScope>(logger_, bridge_, sapi_, sharedMemory_, dependencyAutoLoaderGuard_, inferredSpans_, config_, [hs = hooksStorage_]() { hs->clear(); }, [this]() { return getPeriodicTaskExecutor();})),
    logSinkStdErr_(std::move(logSinkStdErr)),
    logSinkSysLog_(std::move(logSinkSysLog)),
    logSinkFile_(std::move(logSinkFile))
    {
        config_->addConfigUpdateWatcher([logger = logger_, stderrsink = logSinkStdErr_, syslogsink = logSinkSysLog_, filesink = logSinkFile_](ConfigurationSnapshot const &cfg) {
            stderrsink->setLevel(cfg.log_level_stderr);
            syslogsink->setLevel(cfg.log_level_syslog);
            if (filesink) {
                if (cfg.log_file.empty()) {
                    filesink->setLevel(LogLevel::logLevel_off);
                } else {
                    filesink->setLevel(cfg.log_level_file);
                    filesink->reopen(utils::getParameterizedString(cfg.log_file));
                }
            }

            logger->setLogFeatures(utils::parseLogFeatures(logger, cfg.log_features));
        });
    }


AgentGlobals::~AgentGlobals() {
    config_->removeAllConfigUpdateWatchers();
}

std::shared_ptr<PeriodicTaskExecutor> AgentGlobals::getPeriodicTaskExecutor() {
    if (periodicTaskExecutor_) {
        return periodicTaskExecutor_;
    }

    periodicTaskExecutor_ = std::make_shared<elasticapm::php::PeriodicTaskExecutor>(
            std::vector<elasticapm::php::PeriodicTaskExecutor::task_t>{
            [inferredSpans = inferredSpans_](elasticapm::php::PeriodicTaskExecutor::time_point_t now) { inferredSpans->tryRequestInterrupt(now); }
            },
            []() {
                // block signals for this thread to be handled by main Apache/PHP thread
                // list of signals from Apaches mpm handlers
                elasticapm::utils::blockSignal(SIGTERM);
                elasticapm::utils::blockSignal(SIGHUP);
                elasticapm::utils::blockSignal(SIGINT);
                elasticapm::utils::blockSignal(SIGWINCH);
                elasticapm::utils::blockSignal(SIGUSR1);
                elasticapm::utils::blockSignal(SIGPROF); // php timeout signal
            }
        );
        return periodicTaskExecutor_;
    }


}

