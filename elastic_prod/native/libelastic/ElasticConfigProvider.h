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

#include "config/OptionValueProviderInterface.h"

namespace elastic::otel {

/**
 * Elastic-specific OptionValueProvider that maps legacy ELASTIC_OTEL_*
 * environment variables and elastic_otel.* INI entries to the upstream
 * OTEL_PHP_* / opentelemetry_distro.* names.
 *
 * The upstream ConfigurationManager calls
 * getIniOptionValue("opentelemetry_distro.log_level") and
 * getEnvironmentOptionValue("OTEL_PHP_LOG_LEVEL"). This provider translates
 * those to elastic_otel.log_level and ELASTIC_OTEL_LOG_LEVEL respectively,
 * providing backward compatibility for existing Elastic deployments.
 */
class ElasticConfigProvider : public opentelemetry::php::config::OptionValueProviderInterface {
public:
    std::optional<std::string> getEnvironmentOptionValue(std::string_view name) override;
    std::optional<std::string> getIniOptionValue(std::string_view name) override;
    std::optional<std::string> getDynamicOptionValue(std::string_view name) override;
    void update(configFiles_t const &configFiles) override;

   private:
    configFiles_t configFiles_;
};

} // namespace elastic::otel
