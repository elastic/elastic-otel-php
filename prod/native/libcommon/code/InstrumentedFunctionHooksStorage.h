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

#include <exception>
#include <list>
#include <unordered_map>

namespace elasticapm::php {

class InstrumentedFunctionHooksStorageInterface {
public:
    virtual ~InstrumentedFunctionHooksStorageInterface() = default;
    virtual void clear() = 0;
};


template<typename key_t, typename callback_t>
class InstrumentedFunctionHooksStorage : public InstrumentedFunctionHooksStorageInterface {
public:
    using callbacks_t = std::pair<callback_t, callback_t>;

    void store(key_t functionKey, callback_t callableOnEntry, callback_t callableOnExit) {
        callbacks_[functionKey].emplace_back(callbacks_t(std::move(callableOnEntry), std::move(callableOnExit)));
    }

    std::list<callbacks_t> *storeFront(key_t functionKey, callback_t callableOnEntry, callback_t callableOnExit) {
        callbacks_[functionKey].emplace_front(callbacks_t(std::move(callableOnEntry), std::move(callableOnExit)));
        return &callbacks_[functionKey];
    }

    std::list<callbacks_t> *find(key_t functionKey) {
        auto found = callbacks_.find(functionKey);
        if (found == std::end(callbacks_)) {
            return nullptr;
        }
        return &found->second;
    }

    void clear() final {
        callbacks_.clear();
    }

private:
    std::unordered_map<key_t, std::list<callbacks_t>> callbacks_;
};


}