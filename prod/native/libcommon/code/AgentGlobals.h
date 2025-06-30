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

#include "PhpSapi.h"
#include "Logger.h"
#include <functional>
#include <memory>

namespace opentelemetry {

namespace php {
class ResourceDetector;
namespace config {
class ElasticDynamicConfigurationAdapter;
}
namespace transport {
class OpAmp;
}
} // namespace php
} // namespace opentelemetry

namespace elasticapm::php {

class LoggerInterface;
class PhpBridgeInterface;
class InferredSpans;
class PeriodicTaskExecutor;
class SharedMemoryState;
class RequestScope;
class ConfigurationStorage;
class ConfigurationSnapshot;
class LoggerSinkInterface;
class LogSinkFile;
class InstrumentedFunctionHooksStorageInterface;
class DependencyAutoLoaderGuard;
namespace transport {
class CurlSender;
class HttpEndpoints;
template <typename Sender, typename Endpoints>
class HttpTransportAsync;
} // namespace transport

// clang-format off

class AgentGlobals {
public:
    AgentGlobals(std::shared_ptr<LoggerInterface> logger,
        std::shared_ptr<LoggerSinkInterface> logSinkStdErr,
        std::shared_ptr<LoggerSinkInterface> logSinkSysLog,
        std::shared_ptr<LoggerSinkFile> logSinkFile,
        std::shared_ptr<PhpBridgeInterface> bridge,
        std::shared_ptr<InstrumentedFunctionHooksStorageInterface> hooksStorage,
        std::shared_ptr<InferredSpans> inferredSpans,
        std::function<bool(ConfigurationSnapshot &)> updateConfigurationSnapshot);

    ~AgentGlobals();

    std::shared_ptr<PeriodicTaskExecutor> getPeriodicTaskExecutor();

    std::shared_ptr<ConfigurationStorage> config_;
    std::shared_ptr<LoggerInterface> logger_;
    std::shared_ptr<LoggerSinkInterface> logSinkStdErr_;
    std::shared_ptr<LoggerSinkInterface> logSinkSysLog_;
    std::shared_ptr<LoggerSinkFile> logSinkFile_;
    std::shared_ptr<PhpBridgeInterface> bridge_;
    std::shared_ptr<DependencyAutoLoaderGuard> dependencyAutoLoaderGuard_;
    std::shared_ptr<InstrumentedFunctionHooksStorageInterface> hooksStorage_;
    std::shared_ptr<PhpSapi> sapi_;
    std::shared_ptr<InferredSpans> inferredSpans_;
    std::shared_ptr<PeriodicTaskExecutor> periodicTaskExecutor_;
    std::shared_ptr<transport::HttpTransportAsync<transport::CurlSender, transport::HttpEndpoints> > httpTransportAsync_;
    std::shared_ptr<opentelemetry::php::ResourceDetector> resourceDetector_;
    std::shared_ptr<opentelemetry::php::config::ElasticDynamicConfigurationAdapter> elasticDynamicConfig_;
    std::shared_ptr<opentelemetry::php::transport::OpAmp> opAmp_;
    std::shared_ptr<SharedMemoryState> sharedMemory_;
    std::shared_ptr<RequestScope> requestScope_;

};

} // namespace elasticapm::php
