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

#include <optional>
#include <string>
#include <string_view>
#include <unordered_map>

namespace elasticapm::php {


//TODO sync for ZTS, need to be pure global for ZTS, doesnt need to be global for NTS
template <typename key_t, typename handler_t>
class InternalFunctionInstrumentationStorage {
public:

    static auto &getInstance() {
        static InternalFunctionInstrumentationStorage instance_;
        return instance_;
    }

    handler_t get(size_t functionKey) {
        auto data = storage_.find(functionKey);
        if (data != storage_.end()) {
            return data->second;
        }
        return nullptr;
    }

    void store(key_t functionKey, handler_t originalHandler) {
        auto instrumentation = storage_.find(functionKey);
        if (instrumentation == std::end(storage_)) {
            storage_.emplace(functionKey, originalHandler);
        }
    }

    void remove(key_t functionKey) {
        storage_.erase(functionKey);
    }

private:
    std::unordered_map<key_t, handler_t> storage_;
};

}