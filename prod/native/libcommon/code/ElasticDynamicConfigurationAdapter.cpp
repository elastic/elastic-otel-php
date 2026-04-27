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

#include "ElasticDynamicConfigurationAdapter.h"
#include "basic_macros.h"
#include "ConfigurationSnapshot.h"
#include <nlohmann/json.hpp>

namespace opentelemetry::php::config {

std::unordered_map<std::string, std::string> ElasticDynamicConfigurationAdapter::parseJsonConfigFile(const std::string &jsonStr) const {
    std::unordered_map<std::string, std::string> result;

    nlohmann::json doc;
    try {
        doc = nlohmann::json::parse(jsonStr);
    } catch (const nlohmann::json::parse_error &e) {
        throw std::runtime_error("Error parsing json config: " + std::string(e.what()));
    }

    if (!doc.is_object()) {
        throw std::runtime_error("Expected top-level JSON object");
    }

    for (auto &[key, value] : doc.items()) {
        if (value.is_string()) {
            result[key] = value.get<std::string>();
        } else if (value.is_number_integer()) {
            result[key] = std::to_string(value.get<int>());
        } else if (value.is_number_float()) {
            result[key] = std::to_string(value.get<float>());
        } else if (value.is_boolean()) {
            result[key] = value.get<bool>() ? "true" : "false";
        }
    }

    return result;
}

void ElasticDynamicConfigurationAdapter::update(configFiles_t const &files) {
    auto elasticConfig = files.find("elastic"s);
    if (elasticConfig == std::end(files) || elasticConfig->second.empty()) {
        options_.clear();
        return;
    }

    try {
        options_ = remapOptions(parseJsonConfigFile(elasticConfig->second));
    } catch (std::exception const &e) {
        ELOG_WARNING(logger_, CONFIG, "Failed to parse elastic dynamic configuration file: {}", e.what());
    }
}

ElasticDynamicConfigurationAdapter::optionsMap_t ElasticDynamicConfigurationAdapter::remapOptions(optionsMap_t remoteOptions) const {
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
                loglevel = opt.second; // log level parser will emit warning
            }

            result[EL_STRINGIFY(ELASTIC_OTEL_LOG_LEVEL)] = loglevel;
            ELOG_DEBUG(logger_, CONFIG, "ElasticDynamicConfigurationAdapter remapOptions Mapped remote 'logging_level->{}' to '{}->{}'.", opt.second, EL_STRINGIFY(ELASTIC_OTEL_LOG_LEVEL), loglevel);
        }

        if (opt.first == "infer_spans") {
            if (opt.second == "true"s) {
                result[EL_STRINGIFY(ELASTIC_OTEL_INFERRED_SPANS_ENABLED)] = "true"s;
            } else if (opt.second == "false"s) {
                result[EL_STRINGIFY(ELASTIC_OTEL_INFERRED_SPANS_ENABLED)] = "false"s;
            }
            ELOG_DEBUG(logger_, CONFIG, "ElasticDynamicConfigurationAdapter remapOptions Mapped remote 'infer_spans->{}' to '{}->{}'.", opt.second, EL_STRINGIFY(ELASTIC_OTEL_INFERRED_SPANS_ENABLED), result[EL_STRINGIFY(ELASTIC_OTEL_INFERRED_SPANS_ENABLED)]);
        }
    }

    return result;
}

} // namespace opentelemetry::php::config
