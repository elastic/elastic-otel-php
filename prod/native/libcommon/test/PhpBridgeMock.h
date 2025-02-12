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

#include "PhpBridgeInterface.h"
#include <gmock/gmock.h>

namespace elasticapm::php::test {

class PhpBridgeMock : public PhpBridgeInterface {
public:
    MOCK_METHOD(bool, callInferredSpans, (std::chrono::milliseconds duration), (const, override));
    MOCK_METHOD(bool, callPHPSideEntryPoint, (LogLevel logLevel, std::chrono::time_point<std::chrono::system_clock> requestInitStart), (const, override));
    MOCK_METHOD(bool, callPHPSideExitPoint, (), (const, override));
    MOCK_METHOD(bool, callPHPSideErrorHandler, (int type, std::string_view errorFilename, uint32_t errorLineno, std::string_view message), (const, override));

    MOCK_METHOD(std::vector<phpExtensionInfo_t>, getExtensionList, (), (const, override));
    MOCK_METHOD(std::string, getPhpInfo, (), (const, override));

    MOCK_METHOD(std::string_view, getPhpSapiName, (), (const, override));

    MOCK_METHOD(std::optional<std::string_view>, getCurrentExceptionMessage, (), (const, override));

    MOCK_METHOD(void, compileAndExecuteFile, (std::string_view fileName), (const, override));

    MOCK_METHOD(void, enableAccessToServerGlobal, (), (const, override));

    MOCK_METHOD(bool, detectOpcachePreload, (), (const, override));
    MOCK_METHOD(bool, isScriptRestricedByOpcacheAPI, (), (const, override));
    MOCK_METHOD(bool, detectOpcacheRestartPending, (), (const, override));
    MOCK_METHOD(bool, isOpcacheEnabled, (), (const, override));

    MOCK_METHOD(void, getCompiledFiles, (std::function<void(std::string_view)> recordFile), (const, override));
    MOCK_METHOD((std::pair<std::size_t, std::size_t>), getNewlyCompiledFiles, (std::function<void(std::string_view)> recordFile, std::size_t lastClassIndex, std::size_t lastFunctionIndex), (const, override));

    MOCK_METHOD((std::pair<int, int>), getPhpVersionMajorMinor, (), (const, override));
};

} // namespace elasticapm::php::test
