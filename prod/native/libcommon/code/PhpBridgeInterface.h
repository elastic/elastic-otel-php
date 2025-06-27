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

#include "LogLevel.h"
#include <chrono>
#include <functional>
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

    virtual void getCompiledFiles(std::function<void(std::string_view)> recordFile) const = 0;
    virtual std::pair<std::size_t, std::size_t> getNewlyCompiledFiles(std::function<void(std::string_view)> recordFile, std::size_t lastClassIndex, std::size_t lastFunctionIndex) const = 0;

    virtual std::pair<int, int> getPhpVersionMajorMinor() const = 0;

    virtual std::string phpUname(char mode) const = 0;
};

}
