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
#include "Helpers.h"
#include "Exceptions.h"

#include <main/php_version.h>
#include <Zend/zend_types.h>
#include "LoggerInterface.h"

#include <memory>

namespace elasticapm::php {

class PhpBridge : public PhpBridgeInterface {
public:
    PhpBridge(std::shared_ptr<elasticapm::php::LoggerInterface> log) : log_(std::move(log)) {
    }

    bool callInferredSpans(std::chrono::milliseconds duration) const final;
    bool callPHPSideEntryPoint(LogLevel logLevel, std::chrono::time_point<std::chrono::system_clock> requestInitStart) const final;
    bool callPHPSideExitPoint() const final;
    bool callPHPSideErrorHandler(int type, std::string_view errorFilename, uint32_t errorLineno, std::string_view message) const final;

    std::vector<phpExtensionInfo_t> getExtensionList() const final;
    std::string getPhpInfo() const final;

    std::string_view getPhpSapiName() const final;

    std::optional<std::string_view> getCurrentExceptionMessage() const final;

    void compileAndExecuteFile(std::string_view fileName) const final;

    void enableAccessToServerGlobal() const final;

    bool detectOpcachePreload() const final;

    bool isScriptRestricedByOpcacheAPI() const final;
    bool detectOpcacheRestartPending() const final;
    bool isOpcacheEnabled() const final;

    void getCompiledFiles(std::function<void(std::string_view)> recordFile) const final;
    std::pair<std::size_t, std::size_t> getNewlyCompiledFiles(std::function<void(std::string_view)> recordFile, std::size_t lastClassIndex, std::size_t lastFunctionIndex) const final;

    std::pair<int, int> getPhpVersionMajorMinor() const final;

    std::string phpUname(char mode) const final;

private:
    std::shared_ptr<elasticapm::php::LoggerInterface> log_;
};


zend_class_entry *findClassEntry(std::string_view className);
zval *getClassStaticPropertyValue(zend_class_entry *ce, std::string_view propertyName);
zval *getClassPropertyValue(zend_class_entry *ce, zval *object, std::string_view propertyName);
zval *getClassPropertyValue(zend_class_entry *ce, zend_object *object, std::string_view propertyName);
bool callMethod(zval *object, std::string_view methodName, zval arguments[], int32_t argCount, zval *returnValue);

std::string_view getExceptionName(zend_object *exception);
bool isObjectOfClass(zval *object, std::string_view className);

void getCallArguments(zval *zv, zend_execute_data *ex);
void getScopeNameOrThis(zval *zv, zend_execute_data *execute_data);
void getFunctionName(zval *zv, zend_execute_data *ex);
void getFunctionDeclaringScope(zval *zv, zend_execute_data *ex);
void getFunctionDeclarationFileName(zval *zv, zend_execute_data *ex);
void getFunctionDeclarationLineNo(zval *zv, zend_execute_data *ex);
void getFunctionReturnValue(zval *zv, zval *retval);
void getCurrentException(zval *zv, zend_object *exception);
bool forceSetObjectPropertyValue(zend_object *object, zend_string *propertyName, zval *value);

} // namespace elasticapm::php