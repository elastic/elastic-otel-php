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

#define ELASTIC_OTEL_CFG_OPT_NAME_BOOTSTRAP_PHP_PART_FILE bootstrap_php_part_file
#define ELASTIC_OTEL_CFG_OPT_NAME_ENABLED enabled

#define ELASTIC_OTEL_CFG_OPT_NAME_LOG_FILE log_file
#define ELASTIC_OTEL_CFG_OPT_NAME_LOG_LEVEL log_level
#define ELASTIC_OTEL_CFG_OPT_NAME_LOG_LEVEL_FILE log_level_file
#define ELASTIC_OTEL_CFG_OPT_NAME_LOG_LEVEL_STDERR log_level_stderr
#define ELASTIC_OTEL_CFG_OPT_NAME_LOG_LEVEL_SYSLOG log_level_syslog
#define ELASTIC_OTEL_CFG_OPT_NAME_LOG_FEATURES log_features
#define ELASTIC_OTEL_CFG_OPT_NAME_VERIFY_SERVER_CERT verify_server_cert
#define ELASTIC_OTEL_CFG_OPT_NAME_DEBUG_DIAGNOSTICS_FILE debug_diagnostic_file
#define ELASTIC_OTEL_CFG_OPT_NAME_MAX_SEND_QUEUE_SIZE max_send_queue_size
#define ELASTIC_OTEL_CFG_OPT_NAME_ASYNC_TRANSPORT async_transport
#define ELASTIC_OTEL_CFG_OPT_NAME_ASYNC_TRANSPORT_SHUTDOWN_TIMEOUT async_transport_shutdown_timeout

#define ELASTIC_OTEL_CFG_OPT_NAME_DEBUG_INSTRUMENT_ALL debug_instrument_all

namespace elasticapm::php {

using namespace std::string_literals;

struct ConfigurationSnapshot {
    std::string ELASTIC_OTEL_CFG_OPT_NAME_BOOTSTRAP_PHP_PART_FILE;
    bool ELASTIC_OTEL_CFG_OPT_NAME_ENABLED = true;
    std::string ELASTIC_OTEL_CFG_OPT_NAME_LOG_FILE;
    LogLevel ELASTIC_OTEL_CFG_OPT_NAME_LOG_LEVEL = LogLevel::logLevel_off;
    LogLevel ELASTIC_OTEL_CFG_OPT_NAME_LOG_LEVEL_FILE = LogLevel::logLevel_off;
    LogLevel ELASTIC_OTEL_CFG_OPT_NAME_LOG_LEVEL_STDERR = LogLevel::logLevel_off;
    LogLevel ELASTIC_OTEL_CFG_OPT_NAME_LOG_LEVEL_SYSLOG = LogLevel::logLevel_off;
    std::string ELASTIC_OTEL_CFG_OPT_NAME_LOG_FEATURES;
    std::string ELASTIC_OTEL_CFG_OPT_NAME_DEBUG_DIAGNOSTICS_FILE;
    bool ELASTIC_OTEL_CFG_OPT_NAME_VERIFY_SERVER_CERT = true;
    std::size_t ELASTIC_OTEL_CFG_OPT_NAME_MAX_SEND_QUEUE_SIZE = 2 * 1024 * 1204;
    bool ELASTIC_OTEL_CFG_OPT_NAME_ASYNC_TRANSPORT = true;
    std::chrono::milliseconds ELASTIC_OTEL_CFG_OPT_NAME_ASYNC_TRANSPORT_SHUTDOWN_TIMEOUT = std::chrono::seconds(30);
    bool ELASTIC_OTEL_CFG_OPT_NAME_DEBUG_INSTRUMENT_ALL = false;

    uint64_t revision = 0;
};

} // namespace elasticapm::php
