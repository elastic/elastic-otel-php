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


#include <string_view>
#include <stdexcept>
#include <Zend/zend_string.h>


namespace elasticapm::php {

class AutoZendString {
public:
    AutoZendString(const AutoZendString&) = delete;
    AutoZendString& operator=(const AutoZendString&) = delete;
    AutoZendString() = delete;

    template<typename T, std::enable_if_t< std::is_convertible<T, std::string_view>::value, bool> = true >
    AutoZendString(T value) {
        std::string_view data(value);
        value_ = zend_string_init(data.data(), data.length(), 0);
    }

    // WARNING: doesn't add reference
    AutoZendString(zend_string *value) {
        value_ = value;
    }

    ~AutoZendString() {
        if (!value_) {
            return;
        }
        zend_string_release(value_);
    }

    zend_string *get() {
        return value_;
    }

private:
    zend_string *value_ = nullptr;
};


}