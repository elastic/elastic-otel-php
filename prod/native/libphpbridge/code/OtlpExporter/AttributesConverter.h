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

#include "AutoZval.h"
#include "CommonUtils.h"

#include <opentelemetry/proto/common/v1/common.pb.h>
#include <string_view>

namespace elasticapm::php {

class AttributesConverter {
public:
    static opentelemetry::proto::common::v1::AnyValue convertAnyValue(AutoZval const &val) {
        using opentelemetry::proto::common::v1::AnyValue;
        using opentelemetry::proto::common::v1::ArrayValue;
        using opentelemetry::proto::common::v1::KeyValue;
        using opentelemetry::proto::common::v1::KeyValueList;

        AnyValue result;

        if (val.isArray()) {
            if (isSimpleArray(val)) {
                ArrayValue *arr = new ArrayValue();
                for (auto const &item : val) {
                    *arr->add_values() = convertAnyValue(item);
                }
                result.set_allocated_array_value(arr);
            } else {
                KeyValueList *kvlist = new KeyValueList();
                for (auto it = val.kvbegin(); it != val.kvend(); ++it) {
                    auto [key, v] = *it;
                    if (!std::holds_alternative<std::string_view>(key)) {
                        continue;
                    }
                    KeyValue *kv = kvlist->add_values();
                    kv->set_key(std::get<std::string_view>(key));
                    *kv->mutable_value() = convertAnyValue(v);
                }
                result.set_allocated_kvlist_value(kvlist);
            }
            return result;
        }

        switch (val.getType()) {
            case IS_LONG:
                result.set_int_value(val.getLong());
                break;
            case IS_DOUBLE:
                result.set_double_value(val.getDouble());
                break;
            case IS_TRUE:
            case IS_FALSE:
                result.set_bool_value(val.getBoolean());
                break;
            case IS_STRING:
                if (val.isStringValidUtf8() || elasticapm::utils::isUtf8(val.getStringView())) {
                    result.set_string_value(val.getStringView());
                } else {
                    result.set_bytes_value(val.getStringView());
                }
                break;
            default:
                break;
        }

        return result;
    }

    static void convertAttributes(AutoZval const &attributes, google::protobuf::RepeatedPtrField<opentelemetry::proto::common::v1::KeyValue> *out) {
        using namespace std::string_view_literals;
        auto attributesArray = attributes.callMethod("toArray"sv);
        for (auto it = attributesArray.kvbegin(); it != attributesArray.kvend(); ++it) {
            auto [key, val] = *it;
            if (!std::holds_alternative<std::string_view>(key)) {
                continue;
            }

            auto *kv = out->Add();
            kv->set_key(std::get<std::string_view>(key));
            *kv->mutable_value() = AttributesConverter::convertAnyValue(val);
        }
    }

private:
    static bool isSimpleArray(AutoZval const &arr) {
        if (!arr.isArray()) {
            return false;
        }

        HashTable const *ht = Z_ARRVAL_P(arr.get());
        return ht->nNumOfElements == ht->nNextFreeElement;
    }
};

} // namespace elasticapm::php