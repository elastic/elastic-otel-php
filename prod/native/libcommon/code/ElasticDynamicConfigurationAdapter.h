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

#include "LoggerInterface.h"
#include <memory>
#include <optional>
#include <string>
#include <string_view>
#include <unordered_map>

#include <iostream>

namespace opentelemetry::php::config {

using namespace std::literals;

std::unordered_map<std::string, std::string> parseJsonConfigFile(const std::string &jsonStr); // throws

class ElasticDynamicConfigurationAdapter {
public:
    using configFiles_t = std::unordered_map<std::string, std::string>; // filename->content
    using optionsMap_t = std::unordered_map<std::string, std::string>;  // optname->value

    ElasticDynamicConfigurationAdapter(std::shared_ptr<elasticapm::php::LoggerInterface> logger) : logger_(std::move(logger)) {
    }

    void update(configFiles_t const &files);

    std::optional<std::string> getOption(std::string const &optionName) const {
        auto found = options_.find(optionName);
        if (found != options_.end()) {
            return found->second;
        }
        return std::nullopt;
    }

protected:
    optionsMap_t remapOptions(optionsMap_t remoteOptions) const;
    std::unordered_map<std::string, std::string> parseJsonConfigFile(const std::string &jsonStr) const;

private:
    optionsMap_t options_;
    std::shared_ptr<elasticapm::php::LoggerInterface> logger_;
};

} // namespace opentelemetry::php::config