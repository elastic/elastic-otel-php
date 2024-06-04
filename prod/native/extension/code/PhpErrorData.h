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

#include <Zend/zend_types.h>
#include <string>
#include <string_view>

namespace elasticapm::php {

class PhpErrorData {
public:
    PhpErrorData(int type, std::string_view fileName, uint32_t lineNumber, std::string_view message);
    ~PhpErrorData();

    int getType() const;
    std::string_view getFileName() const;
    int getLineNumber() const;
    std::string_view getMessage() const;
    zval *getStackTrace();

private:
    int type_ = -1;
    std::string fileName_;
    uint32_t lineNumber_ = 0;
    std::string message_;
    zval stackTrace_;
};
    
}