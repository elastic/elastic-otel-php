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

#include "config/OptionValueProviderInterface.h"

#include <optional>
#include <string>
#include <string_view>
#include <unordered_map>

namespace elastic::otel {

/**
 * Elastic-specific OptionValueProvider that maps legacy ELASTIC_OTEL_* environment
 * variables and INI entries to the upstream OTEL_PHP_* names.
 *
 * When the upstream ConfigurationManager asks for e.g. "log_level", this provider
 * checks the ELASTIC_OTEL_LOG_LEVEL environment variable (and elastic_otel.log_level INI).
 * If found, it returns the value — providing backward compatibility for existing
 * Elastic deployments.
 */
class ElasticConfigProvider : public opentelemetry::php::config::OptionValueProviderInterface {
public:
    std::optional<std::string> getEnvironmentOptionValue(std::string_view name) override;
    std::optional<std::string> getIniOptionValue(std::string_view name) override;
    std::optional<std::string> getDynamicOptionValue(std::string_view name) override;
    void update(configFiles_t const &configFiles) override;

private:
    /**
     * Maps upstream option short names (e.g. "log_level") to the legacy
     * ELASTIC_OTEL_* environment variable name (e.g. "ELASTIC_OTEL_LOG_LEVEL").
     */
    static const std::unordered_map<std::string_view, std::string> envAliasMap_;

    /**
     * Maps upstream option short names to legacy elastic_otel.* INI entry names.
     */
    static const std::unordered_map<std::string_view, std::string> iniAliasMap_;

    configFiles_t configFiles_;
};

} // namespace elastic::otel
