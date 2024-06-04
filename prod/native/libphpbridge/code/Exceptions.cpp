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

#include "PhpBridge.h"
#include "Helpers.h"

#include <Zend/zend_exceptions.h>
#include <Zend/zend_globals.h>
#include <Zend/zend_types.h>
#include <optional>

namespace elasticapm::php {

using namespace std::literals;

SavedException saveExceptionState() {
    SavedException savedException;
    savedException.exception = EG(exception);
    savedException.prev_exception = EG(prev_exception);
    savedException.opline_before_exception = EG(opline_before_exception);

    EG(exception) = nullptr;
    EG(prev_exception) = nullptr;
    EG(opline_before_exception) = nullptr;

    if (EG(current_execute_data)) {
        savedException.opline = EG(current_execute_data)->opline;
    }
    return savedException;
}

void restoreExceptionState(SavedException savedException) {
    EG(exception) = savedException.exception;
    EG(prev_exception) = savedException.prev_exception;
    EG(opline_before_exception) = savedException.opline_before_exception;

    if (EG(current_execute_data) && savedException.opline.has_value()) {
        EG(current_execute_data)->opline = savedException.opline.value();
    }
}


std::optional<std::string_view> getExceptionMessage(zend_object *exception) {
    return zvalToOptionalStringView(getClassPropertyValue(exception->ce, exception, "message"sv));
}

std::optional<std::string_view> getExceptionFileName(zend_object *exception) {
    return zvalToOptionalStringView(getClassPropertyValue(exception->ce, exception, "file"sv));
}

std::optional<long> getExceptionLine(zend_object *exception) {
    auto value = getClassPropertyValue(exception->ce, exception, "line"sv);
    if (Z_TYPE_P(value) == IS_LONG) {
        return Z_LVAL_P(value);
    }
    return -1;
}

std::optional<long> getExceptionCode(zend_object *exception) {
    auto value = getClassPropertyValue(exception->ce, exception, "code"sv);
    if (Z_TYPE_P(value) == IS_LONG) {
        return Z_LVAL_P(value);
    }
    return std::nullopt;
}

std::optional<std::string_view> getExceptionClass(zend_object *exception) {
    return zvalToOptionalStringView(getClassPropertyValue(exception->ce, exception, "class"sv));
}

std::optional<std::string_view> getExceptionFunction(zend_object *exception) {
    return zvalToOptionalStringView(getClassPropertyValue(exception->ce, exception, "function"sv));
}

std::optional<std::string_view> getExceptionStringStackTrace(zend_object *exception) {
    return zvalToOptionalStringView(getClassPropertyValue(exception->ce, exception, "string"sv));
}

std::string_view getExceptionName(zend_object *exception) {
    zend_string *str = exception->handlers->get_class_name(exception);
    if (!str) {
        return {};
    }
    return {ZSTR_VAL(str), ZSTR_LEN(str)};
}

std::string exceptionToString(zend_object *exception) {
    if (!exception || !instanceof_function(exception->ce, zend_ce_throwable)) {
        return {};
    }

    std::stringstream msg;
    auto exceptionClass = getExceptionClass(exception);
    auto exceptionName = getExceptionName(exception);

    msg << exceptionClass.value_or(exceptionName);
    msg << " thrown"sv;

    auto message = getExceptionMessage(exception);
    if (message.has_value()) {
        msg << " with message '"sv << *message << "'";
    }

    auto code = getExceptionCode(exception);
    if (code.has_value() && *code != 0) {
        msg << " with code "sv << *code;
    }

    auto fileName = getExceptionFileName(exception);
    if (fileName.has_value()) {
        msg << " in "sv << *fileName;
        auto line = getExceptionLine(exception);
        if (line.has_value()) {
            msg << ":"sv << *line;
        }
    }

    auto stack = getExceptionStringStackTrace(exception);
    if (stack.has_value() && !stack.value().empty()) {
        msg << " stacktrace: '"sv << *stack << "'";
    }
    return msg.str();
}
}
