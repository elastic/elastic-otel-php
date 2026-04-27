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

#include "ElasticConfigProvider.h"

#include <cstdlib>

#include "AutoZval.h"

extern "C" {
#include <main/php.h>
}

namespace elastic::otel {

static constexpr std::string_view UPSTREAM_INI_PREFIX = "opentelemetry_distro.";
static constexpr std::string_view UPSTREAM_ENV_PREFIX = "OTEL_PHP_";
static constexpr std::string_view ELASTIC_INI_PREFIX = "elastic_otel.";
static constexpr std::string_view ELASTIC_ENV_PREFIX = "ELASTIC_OTEL_";

std::optional<std::string> ElasticConfigProvider::getEnvironmentOptionValue(std::string_view name) {
  if (name.substr(0, UPSTREAM_ENV_PREFIX.size()) != UPSTREAM_ENV_PREFIX) {
    return std::nullopt;
  }

  auto shortName = name.substr(UPSTREAM_ENV_PREFIX.size());
  auto elasticEnvName =
      std::string(ELASTIC_ENV_PREFIX) + std::string(shortName);

  const char* value = std::getenv(elasticEnvName.c_str());
  if (value == nullptr) {
    return std::nullopt;
  }
  return std::string(value);
}

std::optional<std::string> ElasticConfigProvider::getIniOptionValue(
    std::string_view name) {
  if (name.substr(0, UPSTREAM_INI_PREFIX.size()) != UPSTREAM_INI_PREFIX) {
    return std::nullopt;
  }

  auto shortName = name.substr(UPSTREAM_INI_PREFIX.size());
  auto elasticIniName =
      std::string(ELASTIC_INI_PREFIX) + std::string(shortName);

  auto val = cfg_get_entry(elasticIniName.data(), elasticIniName.length());
  opentelemetry::php::AutoZval autoZval(val);
  auto optStringView = autoZval.getOptStringView();
  if (!optStringView.has_value()) {
    return std::nullopt;
  }
  return std::string(*optStringView);
}

std::optional<std::string> ElasticConfigProvider::getDynamicOptionValue(std::string_view name) {
    // Dynamic options from remote config (OpAmp) are handled by the coordinator.
    // No Elastic-specific dynamic overrides needed at this time.
    return std::nullopt;
}

void ElasticConfigProvider::update(configFiles_t const &configFiles) {
    configFiles_ = configFiles;
}

} // namespace elastic::otel
