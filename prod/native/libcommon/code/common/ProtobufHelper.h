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

#include <google/protobuf/repeated_ptr_field.h>
#include <string>

namespace opentelemetry::php::common {

template <typename KeyValue, typename ValueType>
void addKeyValue(google::protobuf::RepeatedPtrField<KeyValue> *map, std::string key, ValueType const &value) {
    auto kv = map->Add();
    kv->set_key(std::move(key));
    auto val = kv->mutable_value();
    if constexpr (std::is_same_v<decltype(value), bool>) {
        val->set_bool_value(value);
    } else if constexpr (std::is_floating_point_v<std::remove_reference_t<decltype(value)>>) {
        val->set_double_value(value);
    } else if constexpr (!std::is_null_pointer_v<std::remove_reference_t<decltype(value)>> && std::is_convertible_v<decltype(value), std::string_view>) {
        val->set_string_value(value);
    } else {
        val->set_int_value(value);
    }
}

} // namespace opentelemetry::php::common