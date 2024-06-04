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

#include "ModuleFunctionsImpl.h"
#include "ConfigurationManager.h"
#include "ConfigurationStorage.h"

#include <php.h>
#include "ModuleGlobals.h"

extern elasticapm::php::ConfigurationManager configManager;

void elasticApmGetConfigOption(std::string_view optionName, zval *return_value) {
    auto value = configManager.getOptionValue(optionName, EAPM_GL(config_)->get());

    std::visit([return_value](auto &&arg) {
        using T = std::decay_t<decltype(arg)>;
        if constexpr (std::is_same_v<T, std::chrono::milliseconds>) {
            ZVAL_DOUBLE(return_value, arg.count());
            return;
        } else if constexpr (std::is_same_v<T, LogLevel>) {
            ZVAL_LONG(return_value, arg);
            return;
        } else if constexpr (std::is_same_v<T, bool>) {
            if (arg) {
                ZVAL_TRUE(return_value);
            } else {
                ZVAL_FALSE(return_value);
            }
            return;
        } else if constexpr (std::is_same_v<T, std::string>) {
            ZVAL_STRINGL(return_value, arg.c_str(), arg.length());
            return;
        } else {
            ZVAL_NULL(return_value);
        }
    }, value);
}