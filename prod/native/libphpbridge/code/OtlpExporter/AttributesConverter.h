#pragma once

#include "AutoZval.h"
#include "CommonUtils.h"

#include <opentelemetry/proto/common/v1/common.pb.h>
#include <string_view>
#include <regex>
#include <vector>

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