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

#include "CommonUtils.h"

#include <map>
#include <string>
#include <string_view>
#include <cstring>

using namespace std::literals;

namespace opentelemetry::php {

class ResourceDetector {
public:
    static constexpr const char *OTEL_RESOURCE_ATTRIBUTES = "OTEL_RESOURCE_ATTRIBUTES";
    static constexpr const char *OTEL_SERVICE_NAME = "OTEL_SERVICE_NAME";

    ResourceDetector() {
        getFromEnvironment();
    }

    std::string get(std::string const &key) {
        if (auto search = resourceAttributes_.find(key); search != resourceAttributes_.end()) {
            return search->second;
        }
        return {};
    }

protected:
    void getFromEnvironment();

private:
    std::map<std::string, std::string> resourceAttributes_;
};
} // namespace opentelemetry::php
