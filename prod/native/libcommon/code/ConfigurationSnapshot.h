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

#include "LogLevel.h"
#include <string>
#include <chrono>




/**
 * Internal configuration option (not included in public documentation)
 */
#   ifdef PHP_WIN32
#define ELASTIC_APM_CFG_OPT_NAME_ALLOW_ABORT_DIALOG allow_abort_dialog
#   endif

#define ELASTIC_APM_CFG_OPT_NAME_API_KEY api_key

/**
 * Internal configuration option (not included in public documentation)
 */
#define ELASTIC_APM_CFG_OPT_NAME_BOOTSTRAP_PHP_PART_FILE bootstrap_php_part_file
#define ELASTIC_APM_CFG_OPT_NAME_BREAKDOWN_METRICS breakdown_metrics

/**
 * Internal configuration option (not included in public documentation)
 */
#define ELASTIC_APM_CFG_OPT_NAME_CAPTURE_ERRORS capture_errors

/**
 * Internal configuration option (not included in public documentation)
 */
#define ELASTIC_APM_CFG_OPT_NAME_DEV_INTERNAL dev_internal

#define ELASTIC_APM_CFG_OPT_NAME_DISABLE_INSTRUMENTATIONS disable_instrumentations
#define ELASTIC_APM_CFG_OPT_NAME_DISABLE_SEND disable_send
#define ELASTIC_APM_CFG_OPT_NAME_ENABLED enabled
#define ELASTIC_APM_CFG_OPT_NAME_ENVIRONMENT environment
#define ELASTIC_APM_CFG_OPT_NAME_GLOBAL_LABELS global_labels
#define ELASTIC_APM_CFG_OPT_NAME_HOSTNAME hostname


#define ELASTIC_APM_CFG_OPT_NAME_LOG_FILE log_file
#define ELASTIC_APM_CFG_OPT_NAME_LOG_LEVEL log_level

/**
 * Internal configuration option (not included in public documentation)
 */
#define ELASTIC_APM_CFG_OPT_NAME_LOG_LEVEL_FILE log_level_file

#define ELASTIC_APM_CFG_OPT_NAME_LOG_LEVEL_STDERR log_level_stderr
#   ifndef PHP_WIN32
#define ELASTIC_APM_CFG_OPT_NAME_LOG_LEVEL_SYSLOG log_level_syslog
#   endif

/**
 * Internal configuration option (not included in public documentation)
 */
#define ELASTIC_APM_CFG_OPT_NAME_LOG_LEVEL_WIN_SYS_DEBUG log_level_win_sys_debug

/**
 * Internal configuration option (not included in public documentation)
 */
#define ELASTIC_APM_CFG_OPT_NAME_NON_KEYWORD_STRING_MAX_LENGTH non_keyword_string_max_length

#define ELASTIC_APM_CFG_OPT_NAME_SANITIZE_FIELD_NAMES sanitize_field_names
#define ELASTIC_APM_CFG_OPT_NAME_SECRET_TOKEN secret_token
#define ELASTIC_APM_CFG_OPT_NAME_SERVER_TIMEOUT server_timeout
#define ELASTIC_APM_CFG_OPT_NAME_SERVER_URL server_url
#define ELASTIC_APM_CFG_OPT_NAME_SERVICE_NAME service_name
#define ELASTIC_APM_CFG_OPT_NAME_SERVICE_NODE_NAME service_node_name
#define ELASTIC_APM_CFG_OPT_NAME_SERVICE_VERSION service_version
#define ELASTIC_APM_CFG_OPT_NAME_SPAN_COMPRESSION_ENABLED span_compression_enabled
#define ELASTIC_APM_CFG_OPT_NAME_SPAN_COMPRESSION_EXACT_MATCH_MAX_DURATION span_compression_exact_match_max_duration
#define ELASTIC_APM_CFG_OPT_NAME_SPAN_COMPRESSION_SAME_KIND_MAX_DURATION span_compression_same_kind_max_duration
#define ELASTIC_APM_CFG_OPT_NAME_SPAN_STACK_TRACE_MIN_DURATION span_stack_trace_min_duration
#define ELASTIC_APM_CFG_OPT_NAME_STACK_TRACE_LIMIT stack_trace_limit
#define ELASTIC_APM_CFG_OPT_NAME_TRANSACTION_IGNORE_URLS transaction_ignore_urls
#define ELASTIC_APM_CFG_OPT_NAME_TRANSACTION_MAX_SPANS transaction_max_spans
#define ELASTIC_APM_CFG_OPT_NAME_TRANSACTION_SAMPLE_RATE transaction_sample_rate
#define ELASTIC_APM_CFG_OPT_NAME_URL_GROUPS url_groups
#define ELASTIC_APM_CFG_OPT_NAME_VERIFY_SERVER_CERT verify_server_cert

#define ELASTIC_APM_CFG_OPT_NAME_DEBUG_DIAGNOSTICS_FILE debug_diagnostic_file




namespace elasticapm::php {

using namespace std::string_literals;

struct ConfigurationSnapshot {
    std::string ELASTIC_APM_CFG_OPT_NAME_API_KEY;
    std::string ELASTIC_APM_CFG_OPT_NAME_BOOTSTRAP_PHP_PART_FILE;
    bool ELASTIC_APM_CFG_OPT_NAME_BREAKDOWN_METRICS = true;
    bool ELASTIC_APM_CFG_OPT_NAME_CAPTURE_ERRORS = true;
    std::string ELASTIC_APM_CFG_OPT_NAME_DEV_INTERNAL;
    std::string ELASTIC_APM_CFG_OPT_NAME_DISABLE_INSTRUMENTATIONS;
    bool ELASTIC_APM_CFG_OPT_NAME_DISABLE_SEND = false;
    bool ELASTIC_APM_CFG_OPT_NAME_ENABLED = true;
    std::string ELASTIC_APM_CFG_OPT_NAME_ENVIRONMENT;
    std::string ELASTIC_APM_CFG_OPT_NAME_GLOBAL_LABELS;
    std::string ELASTIC_APM_CFG_OPT_NAME_HOSTNAME;
    std::string ELASTIC_APM_CFG_OPT_NAME_LOG_FILE;
    LogLevel ELASTIC_APM_CFG_OPT_NAME_LOG_LEVEL = LogLevel::logLevel_off;
    LogLevel ELASTIC_APM_CFG_OPT_NAME_LOG_LEVEL_FILE = LogLevel::logLevel_off;
    LogLevel ELASTIC_APM_CFG_OPT_NAME_LOG_LEVEL_STDERR = LogLevel::logLevel_off;
    LogLevel ELASTIC_APM_CFG_OPT_NAME_LOG_LEVEL_SYSLOG = LogLevel::logLevel_off;
    LogLevel ELASTIC_APM_CFG_OPT_NAME_LOG_LEVEL_WIN_SYS_DEBUG = LogLevel::logLevel_off;
    std::string ELASTIC_APM_CFG_OPT_NAME_NON_KEYWORD_STRING_MAX_LENGTH;
    std::string ELASTIC_APM_CFG_OPT_NAME_SANITIZE_FIELD_NAMES;
    std::string ELASTIC_APM_CFG_OPT_NAME_SECRET_TOKEN;
    std::chrono::milliseconds ELASTIC_APM_CFG_OPT_NAME_SERVER_TIMEOUT = std::chrono::seconds(30);
    std::string ELASTIC_APM_CFG_OPT_NAME_SERVER_URL = "http://localhost:8200"s;
    std::string ELASTIC_APM_CFG_OPT_NAME_SERVICE_NAME;
    std::string ELASTIC_APM_CFG_OPT_NAME_SERVICE_NODE_NAME;
    std::string ELASTIC_APM_CFG_OPT_NAME_SERVICE_VERSION;
    bool ELASTIC_APM_CFG_OPT_NAME_SPAN_COMPRESSION_ENABLED;
    std::string ELASTIC_APM_CFG_OPT_NAME_SPAN_COMPRESSION_EXACT_MATCH_MAX_DURATION;
    std::string ELASTIC_APM_CFG_OPT_NAME_SPAN_COMPRESSION_SAME_KIND_MAX_DURATION;
    std::string ELASTIC_APM_CFG_OPT_NAME_SPAN_STACK_TRACE_MIN_DURATION;
    std::string ELASTIC_APM_CFG_OPT_NAME_STACK_TRACE_LIMIT;
    std::string ELASTIC_APM_CFG_OPT_NAME_TRANSACTION_IGNORE_URLS;
    std::string ELASTIC_APM_CFG_OPT_NAME_TRANSACTION_MAX_SPANS;
    std::string ELASTIC_APM_CFG_OPT_NAME_TRANSACTION_SAMPLE_RATE;
    std::string ELASTIC_APM_CFG_OPT_NAME_URL_GROUPS;
    bool ELASTIC_APM_CFG_OPT_NAME_VERIFY_SERVER_CERT = true;
    std::string ELASTIC_APM_CFG_OPT_NAME_DEBUG_DIAGNOSTICS_FILE;

    uint64_t revision = 0;
};

} // namespace elasticapm::php