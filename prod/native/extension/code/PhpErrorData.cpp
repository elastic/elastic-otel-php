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


#include "PhpErrorData.h"

#include <main/php.h>
#include <Zend/zend_alloc.h>
#include <Zend/zend_builtin_functions.h>
#include <Zend/zend_types.h>
#include <Zend/zend_variables.h>

#include <string>
#include <string_view>

namespace elasticapm::php {
PhpErrorData::PhpErrorData(int type, std::string_view fileName, uint32_t lineNumber, std::string_view message) : type_(type), fileName_(fileName), lineNumber_(lineNumber), message_(message) {
    ZVAL_UNDEF(&stackTrace_);
    zend_fetch_debug_backtrace(&stackTrace_, /* skip_last */ 0, /* options */ 0, /* limit */ 0);
}

PhpErrorData::~PhpErrorData() {
    zval_ptr_dtor(&stackTrace_);
}

int PhpErrorData::getType() const {
    return type_;
}

std::string_view PhpErrorData::getFileName() const {
    return fileName_;
}

int PhpErrorData::getLineNumber() const {
    return lineNumber_;
}

std::string_view PhpErrorData::getMessage() const {
    return message_;
}

zval *PhpErrorData::getStackTrace()  {
    return &stackTrace_;
}

}