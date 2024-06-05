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

#include "Helpers.h"
#include <optional>
#include <tuple>

namespace elasticapm::php {

std::optional<std::string_view> zvalToOptionalStringView(zval *zv) {
    if (!zv || Z_TYPE_P(zv) != IS_STRING) {
        return std::nullopt;
    }
    return std::string_view{Z_STRVAL_P(zv), Z_STRLEN_P(zv)};
}


std::string_view zvalToStringView(zval *zv) {
    if (!zv || Z_TYPE_P(zv) != IS_STRING) {
        return {};
    }
    return {Z_STRVAL_P(zv), Z_STRLEN_P(zv)};
}

zend_ulong getClassAndFunctionHashFromExecuteData(zend_execute_data *execute_data) {
    if (!execute_data || !execute_data->func || !execute_data->func->common.function_name) {
        return 0;
    }

    zend_ulong classHash = 0;
    if (execute_data->func->common.scope && execute_data->func->common.scope->name) {
        classHash = ZSTR_HASH(execute_data->func->common.scope->name);
    }


    zend_ulong funcHash = ZSTR_HASH(execute_data->func->common.function_name);
    zend_ulong hash = classHash ^ (funcHash << 1);
    return hash;
}

std::tuple<std::string_view, std::string_view> getClassAndFunctionName(zend_execute_data *execute_data) {
    std::string_view cls;
    if (execute_data->func->common.scope && execute_data->func->common.scope->name) {
        cls = {ZSTR_VAL(execute_data->func->common.scope->name), ZSTR_LEN(execute_data->func->common.scope->name)};
    }
    std::string_view func;
    if (execute_data->func->common.function_name) {
        func = {ZSTR_VAL(execute_data->func->common.function_name), ZSTR_LEN(execute_data->func->common.function_name)};
    }
    return std::make_tuple(cls, func);
}



}
