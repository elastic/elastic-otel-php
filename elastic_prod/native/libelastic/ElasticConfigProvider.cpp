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
#include <nlohmann/json.hpp>

#include "AutoZval.h"
#include "ConfigurationSnapshot.h"

extern "C" {
#include <main/php.h>
}

namespace elastic::otel {

using namespace std::string_literals;
using namespace std::string_view_literals;

static constexpr std::string_view UPSTREAM_INI_PREFIX = "opentelemetry_distro.";
static constexpr std::string_view UPSTREAM_ENV_PREFIX = "OTEL_PHP_";
static constexpr std::string_view ELASTIC_INI_PREFIX = "elastic_otel.";
static constexpr std::string_view ELASTIC_ENV_PREFIX = "ELASTIC_OTEL_";

std::optional<std::string> ElasticConfigProvider::getEnvironmentOptionValue(std::string_view name) {
    if (name.substr(0, UPSTREAM_ENV_PREFIX.size()) != UPSTREAM_ENV_PREFIX) {
        return std::nullopt;
    }

    auto shortName = name.substr(UPSTREAM_ENV_PREFIX.size());
    auto elasticEnvName = std::string(ELASTIC_ENV_PREFIX) + std::string(shortName);

    const char *value = std::getenv(elasticEnvName.c_str());
    if (value == nullptr) {
        return std::nullopt;
    }
    return std::string(value);
}

std::optional<std::string> ElasticConfigProvider::getIniOptionValue(std::string_view name) {
    if (name.substr(0, UPSTREAM_INI_PREFIX.size()) != UPSTREAM_INI_PREFIX) {
        return std::nullopt;
    }

    auto shortName = name.substr(UPSTREAM_INI_PREFIX.size());
    auto elasticIniName = std::string(ELASTIC_INI_PREFIX) + std::string(shortName);

    auto val = cfg_get_entry(elasticIniName.data(), elasticIniName.length());
    opentelemetry::php::AutoZval autoZval(val);
    auto optStringView = autoZval.getOptStringView();
    if (!optStringView.has_value()) {
        return std::nullopt;
    }
    return std::string(*optStringView);
}

std::optional<std::string> ElasticConfigProvider::getDynamicOptionValue(std::string_view name) {
    auto found = dynamicOptions_.find(std::string(name));
    if (found != dynamicOptions_.end()) {
        return found->second;
    }
    return std::nullopt;
}

void ElasticConfigProvider::update(configFiles_t const &configFiles) {
    configFiles_ = configFiles;

    auto elasticConfig = configFiles_.find("elastic"s);
    if (elasticConfig == configFiles_.end() || elasticConfig->second.empty()) {
        dynamicOptions_.clear();
        return;
    }

    try {
        dynamicOptions_ = remapOptions(parseElasticJsonConfig(elasticConfig->second));
    } catch (std::exception const &e) {
        ELOG_WARNING(logger_, CONFIG, "Failed to parse elastic remote config JSON: {}", e.what());
    }
}

ElasticConfigProvider::optionsMap_t ElasticConfigProvider::parseElasticJsonConfig(std::string const &jsonStr) {
    optionsMap_t result;

    auto doc = nlohmann::json::parse(jsonStr);
    if (!doc.is_object()) {
        return result;
    }

    for (auto &[key, value] : doc.items()) {
        if (value.is_string()) {
            result[key] = value.get<std::string>();
        } else if (value.is_number_integer()) {
            result[key] = std::to_string(value.get<int64_t>());
        } else if (value.is_number_float()) {
            result[key] = std::to_string(value.get<double>());
        } else if (value.is_boolean()) {
            result[key] = value.get<bool>() ? "true" : "false";
        }
    }

    return result;
}

ElasticConfigProvider::optionsMap_t ElasticConfigProvider::remapOptions(optionsMap_t const &remoteOptions) const {
    optionsMap_t result;
    for (auto const &opt : remoteOptions) {
        if (opt.first == "logging_level"sv) {
            std::string loglevel;
            if (opt.second == "trace"s) {
                loglevel = opt.second;
            } else if (opt.second == "debug"s) {
                loglevel = opt.second;
            } else if (opt.second == "info"s) {
                loglevel = opt.second;
            } else if (opt.second == "error"s) {
                loglevel = opt.second;
            } else if (opt.second == "off"s) {
                loglevel = opt.second;
            } else if (opt.second == "warn"s) {
                loglevel = "warning"s;
            } else if (opt.second == "fatal"s) {
                loglevel = "critical"s;
            } else {
                loglevel = opt.second; // log level parser with emit warning
            }

            result[STRINGIFY_HELPER(OTEL_PHP_LOG_LEVEL)] = loglevel;
            result[STRINGIFY_HELPER(OTEL_PHP_LOG_LEVEL_STDERR)] = loglevel;
            result[STRINGIFY_HELPER(OTEL_PHP_LOG_LEVEL_SYSLOG)] = loglevel;
            result[STRINGIFY_HELPER(OTEL_PHP_LOG_LEVEL_FILE)] = loglevel;
            ELOG_DEBUG(logger_, CONFIG, "ElasticConfigProvider remapOptions Mapped remote 'logging_level->{}' to '{}->{}'.", opt.second, STRINGIFY_HELPER(OTEL_PHP_LOG_LEVEL), loglevel);
        }

        if (opt.first == "infer_spans"sv) {
            if (opt.second == "true"s) {
                result[STRINGIFY_HELPER(OTEL_PHP_INFERRED_SPANS_ENABLED)] = "true"s;
            } else if (opt.second == "false"s) {
                result[STRINGIFY_HELPER(OTEL_PHP_INFERRED_SPANS_ENABLED)] = "false"s;
            }
            ELOG_DEBUG(logger_, CONFIG, "ElasticConfigProvider remapOptions Mapped remote 'infer_spans->{}' to '{}->{}'.", opt.second, STRINGIFY_HELPER(OTEL_PHP_INFERRED_SPANS_ENABLED), result[STRINGIFY_HELPER(OTEL_PHP_INFERRED_SPANS_ENABLED)]);
        }

        if (opt.first == "opamp_polling_interval"sv) {
            // Duration values: if plain number, treat as seconds and append "s"
            // so that convertDurationWithUnit() parses it correctly.
            // If already has a unit suffix (ms, s, m), pass as-is.
            // TODO check in kibana that the value is a valid duration
            bool hasUnit = false;
            if (!opt.second.empty()) {
                char last = opt.second.back();
                hasUnit = (last == 's' || last == 'm');
            }

            result[STRINGIFY_HELPER(OTEL_PHP_OPAMP_POLLING_INTERVAL)] = hasUnit ? opt.second : opt.second + "s";
            ELOG_DEBUG(logger_, CONFIG, "ElasticConfigProvider remapOptions Mapped remote 'opamp_polling_interval->{}' to '{}->{}'.", opt.second, STRINGIFY_HELPER(OTEL_PHP_OPAMP_POLLING_INTERVAL), result[STRINGIFY_HELPER(OTEL_PHP_OPAMP_POLLING_INTERVAL)]);
        }
    }

    return result;
}

} // namespace elastic::otel
