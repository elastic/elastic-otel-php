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

namespace elastic::otel {

// Mapping: upstream short option name → legacy ELASTIC_OTEL_* env var name.
// The upstream ConfigurationManager resolves option names using their short forms
// (e.g. "log_level", "enabled"), which are the keys here.
const std::unordered_map<std::string_view, std::string> ElasticConfigProvider::envAliasMap_ = {
    {"bootstrap_php_part_file",            "ELASTIC_OTEL_BOOTSTRAP_PHP_PART_FILE"},
    {"enabled",                            "ELASTIC_OTEL_ENABLED"},
    {"log_file",                           "ELASTIC_OTEL_LOG_FILE"},
    {"log_level",                          "ELASTIC_OTEL_LOG_LEVEL"},
    {"log_level_file",                     "ELASTIC_OTEL_LOG_LEVEL_FILE"},
    {"log_level_stderr",                   "ELASTIC_OTEL_LOG_LEVEL_STDERR"},
    {"log_level_syslog",                   "ELASTIC_OTEL_LOG_LEVEL_SYSLOG"},
    {"log_features",                       "ELASTIC_OTEL_LOG_FEATURES"},
    {"debug_diagnostic_file",              "ELASTIC_OTEL_DEBUG_DIAGNOSTICS_FILE"},
    {"max_send_queue_size",                "ELASTIC_OTEL_MAX_SEND_QUEUE_SIZE"},
    {"async_transport",                    "ELASTIC_OTEL_ASYNC_TRANSPORT"},
    {"async_transport_shutdown_timeout",   "ELASTIC_OTEL_ASYNC_TRANSPORT_SHUTDOWN_TIMEOUT"},
    {"debug_instrument_all",               "ELASTIC_OTEL_DEBUG_INSTRUMENT_ALL"},
    {"debug_php_hooks_enabled",            "ELASTIC_OTEL_DEBUG_PHP_HOOKS_ENABLED"},
    {"inferred_spans_enabled",             "ELASTIC_OTEL_INFERRED_SPANS_ENABLED"},
    {"inferred_spans_reduction_enabled",   "ELASTIC_OTEL_INFERRED_SPANS_REDUCTION_ENABLED"},
    {"inferred_spans_stacktrace_enabled",  "ELASTIC_OTEL_INFERRED_SPANS_STACKTRACE_ENABLED"},
    {"inferred_spans_sampling_interval",   "ELASTIC_OTEL_INFERRED_SPANS_SAMPLING_INTERVAL"},
    {"inferred_spans_min_duration",        "ELASTIC_OTEL_INFERRED_SPANS_MIN_DURATION"},
    {"dependency_autoloader_guard_enabled","ELASTIC_OTEL_DEPENDENCY_AUTOLOADER_GUARD_ENABLED"},
    {"native_otlp_serializer_enabled",     "ELASTIC_OTEL_NATIVE_OTLP_SERIALIZER_ENABLED"},
    {"opamp_headers",                      "ELASTIC_OTEL_OPAMP_HEADERS"},
    {"opamp_endpoint",                     "ELASTIC_OTEL_OPAMP_ENDPOINT"},
    {"opamp_heartbeat_interval",           "ELASTIC_OTEL_OPAMP_HEARTBEAT_INTERVAL"},
    {"opamp_send_timeout",                 "ELASTIC_OTEL_OPAMP_SEND_TIMEOUT"},
    {"opamp_send_max_retries",             "ELASTIC_OTEL_OPAMP_SEND_MAX_RETRIES"},
    {"opamp_send_retry_delay",             "ELASTIC_OTEL_OPAMP_SEND_RETRY_DELAY"},
    {"opamp_insecure",                     "ELASTIC_OTEL_OPAMP_INSECURE"},
    {"opamp_certificate",                  "ELASTIC_OTEL_OPAMP_CERTIFICATE"},
    {"opamp_client_certificate",           "ELASTIC_OTEL_OPAMP_CLIENT_CERTIFICATE"},
    {"opamp_client_key",                   "ELASTIC_OTEL_OPAMP_CLIENT_KEY"},
    {"opamp_client_keypass",               "ELASTIC_OTEL_OPAMP_CLIENT_KEYPASS"},
};

// Mapping: upstream short option name → legacy elastic_otel.* INI entry name.
const std::unordered_map<std::string_view, std::string> ElasticConfigProvider::iniAliasMap_ = {
    {"bootstrap_php_part_file",            "elastic_otel.bootstrap_php_part_file"},
    {"enabled",                            "elastic_otel.enabled"},
    {"log_file",                           "elastic_otel.log_file"},
    {"log_level",                          "elastic_otel.log_level"},
    {"log_level_file",                     "elastic_otel.log_level_file"},
    {"log_level_stderr",                   "elastic_otel.log_level_stderr"},
    {"log_level_syslog",                   "elastic_otel.log_level_syslog"},
    {"log_features",                       "elastic_otel.log_features"},
    {"debug_diagnostic_file",              "elastic_otel.debug_diagnostic_file"},
    {"max_send_queue_size",                "elastic_otel.max_send_queue_size"},
    {"async_transport",                    "elastic_otel.async_transport"},
    {"async_transport_shutdown_timeout",   "elastic_otel.async_transport_shutdown_timeout"},
    {"debug_instrument_all",               "elastic_otel.debug_instrument_all"},
    {"debug_php_hooks_enabled",            "elastic_otel.debug_php_hooks_enabled"},
    {"inferred_spans_enabled",             "elastic_otel.inferred_spans_enabled"},
    {"inferred_spans_reduction_enabled",   "elastic_otel.inferred_spans_reduction_enabled"},
    {"inferred_spans_stacktrace_enabled",  "elastic_otel.inferred_spans_stacktrace_enabled"},
    {"inferred_spans_sampling_interval",   "elastic_otel.inferred_spans_sampling_interval"},
    {"inferred_spans_min_duration",        "elastic_otel.inferred_spans_min_duration"},
    {"dependency_autoloader_guard_enabled","elastic_otel.dependency_autoloader_guard_enabled"},
    {"native_otlp_serializer_enabled",     "elastic_otel.native_otlp_serializer_enabled"},
    {"opamp_headers",                      "elastic_otel.opamp_headers"},
    {"opamp_endpoint",                     "elastic_otel.opamp_endpoint"},
    {"opamp_heartbeat_interval",           "elastic_otel.opamp_heartbeat_interval"},
    {"opamp_send_timeout",                 "elastic_otel.opamp_send_timeout"},
    {"opamp_send_max_retries",             "elastic_otel.opamp_send_max_retries"},
    {"opamp_send_retry_delay",             "elastic_otel.opamp_send_retry_delay"},
    {"opamp_insecure",                     "elastic_otel.opamp_insecure"},
    {"opamp_certificate",                  "elastic_otel.opamp_certificate"},
    {"opamp_client_certificate",           "elastic_otel.opamp_client_certificate"},
    {"opamp_client_key",                   "elastic_otel.opamp_client_key"},
    {"opamp_client_keypass",               "elastic_otel.opamp_client_keypass"},
};

std::optional<std::string> ElasticConfigProvider::getEnvironmentOptionValue(std::string_view name) {
    auto it = envAliasMap_.find(name);
    if (it == envAliasMap_.end()) {
        return std::nullopt;
    }

    const char *value = std::getenv(it->second.c_str());
    if (value == nullptr) {
        return std::nullopt;
    }
    return std::string(value);
}

std::optional<std::string> ElasticConfigProvider::getIniOptionValue(std::string_view name) {
    // INI values for legacy elastic_otel.* entries are read via the same mechanism
    // as the default provider — through the PHP INI system. The upstream extension
    // registers INI entries with otel_php.* prefix. For backward compat, users may
    // still have elastic_otel.* in their php.ini.
    //
    // This is a placeholder — actual INI reading for legacy entries will require
    // integration with the PHP INI API (zend_ini_string) which is done at the
    // extension level. For now, return nullopt to let the default provider handle it.
    return std::nullopt;
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
