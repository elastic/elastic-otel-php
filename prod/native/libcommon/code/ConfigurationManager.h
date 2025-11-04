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

#include "ConfigurationSnapshot.h"
#include "LoggerInterface.h"
#include "basic_macros.h"

#include <atomic>
#include <chrono>
#include <functional>
#include <map>
#include <memory>
#include <optional>
#include <string>
#include <variant>



namespace elasticapm::php {

using namespace std::string_literals;

//TODO default unit?
//TODO sign

class ConfigurationManager {
public:
    using optionValueProvider_t = std::function<std::optional<std::string>(std::string_view)>;
    struct OptionMetadata  {
        enum type { boolean, string, duration, loglevel, bytes } type;
        size_t offset;
        bool secret = false;
        bool otelNativeOption = false;
    };

    using optionValue_t = std::variant<std::chrono::milliseconds, LogLevel, bool, std::string, std::size_t, std::nullopt_t>;

    ConfigurationManager(optionValueProvider_t readIniValue) : readIniValue_(readIniValue) {
        current_.revision = getNextRevision();
    }

    //TODO class might be used in different threads, right now it is pretty safe as log is attached on globals init (for zts it should be in minit)
    void attachLogger(std::shared_ptr<LoggerInterface> logger) {
        logger_ = std::move(logger);
    }

//TODO lock
    void update();

//TODO lock
    bool updateIfChanged(ConfigurationSnapshot &snapshot) {
        if (snapshot.revision != current_.revision) {
            snapshot = current_;
            return true;
        }
        return false;
    }

    std::map<std::string, OptionMetadata> const &getOptionMetadata() {
        return options_;
    }

    optionValue_t getOptionValue(std::string_view optionName, ConfigurationSnapshot const &snapshot) const;

    void setReadDynamicOptionValue(optionValueProvider_t readDynamicOptionValue) {
        readDynamicOptionValue_ = readDynamicOptionValue;
    }

//TODO test
    static std::string accessOptionStringValueByMetadata(OptionMetadata const &metadata, ConfigurationSnapshot const &snapshot);

private:
    std::optional<std::string> fetchStringValue(std::string_view name, bool isOtelNativeOption);
    uint64_t getNextRevision();


private:
    optionValueProvider_t readIniValue_;
    optionValueProvider_t readDynamicOptionValue_;
    std::atomic_uint64_t upcomingConfigRevision_ = 0;
    ConfigurationSnapshot current_;
    std::shared_ptr<LoggerInterface> logger_;

#define BUILD_ELASTIC_OPTION_METADATA(optname, type, secret)              \
    {                                                                     \
        EL_STRINGIFY(optname), {                                          \
            type, offsetof(ConfigurationSnapshot, optname), secret, false \
        }                                                                 \
    }
#define BUILD_OPTION_METADATA(optname, type, secret)                     \
    {                                                                    \
        EL_STRINGIFY(optname), {                                         \
            type, offsetof(ConfigurationSnapshot, optname), secret, true \
        }                                                                \
    }

    // clang-format off
    std::map<std::string, OptionMetadata> options_ = {
        BUILD_ELASTIC_OPTION_METADATA(ELASTIC_OTEL_BOOTSTRAP_PHP_PART_FILE, OptionMetadata::type::string, false),
        BUILD_ELASTIC_OPTION_METADATA(ELASTIC_OTEL_ENABLED, OptionMetadata::type::boolean, false),
        BUILD_ELASTIC_OPTION_METADATA(ELASTIC_OTEL_LOG_FILE, OptionMetadata::type::string, false),
        BUILD_ELASTIC_OPTION_METADATA(ELASTIC_OTEL_LOG_LEVEL, OptionMetadata::type::loglevel, false),
        BUILD_ELASTIC_OPTION_METADATA(ELASTIC_OTEL_LOG_LEVEL_FILE, OptionMetadata::type::loglevel, false),
        BUILD_ELASTIC_OPTION_METADATA(ELASTIC_OTEL_LOG_LEVEL_STDERR, OptionMetadata::type::loglevel, false),
        BUILD_ELASTIC_OPTION_METADATA(ELASTIC_OTEL_LOG_LEVEL_SYSLOG, OptionMetadata::type::loglevel, false),
        BUILD_ELASTIC_OPTION_METADATA(ELASTIC_OTEL_LOG_FEATURES, OptionMetadata::type::string, false),
        BUILD_ELASTIC_OPTION_METADATA(ELASTIC_OTEL_DEBUG_DIAGNOSTICS_FILE, OptionMetadata::type::string, false),
        BUILD_ELASTIC_OPTION_METADATA(ELASTIC_OTEL_MAX_SEND_QUEUE_SIZE, OptionMetadata::type::bytes, false),
        BUILD_ELASTIC_OPTION_METADATA(ELASTIC_OTEL_ASYNC_TRANSPORT, OptionMetadata::type::boolean, false),
        BUILD_ELASTIC_OPTION_METADATA(ELASTIC_OTEL_ASYNC_TRANSPORT_SHUTDOWN_TIMEOUT, OptionMetadata::type::duration, false),
        BUILD_ELASTIC_OPTION_METADATA(ELASTIC_OTEL_DEBUG_INSTRUMENT_ALL, OptionMetadata::type::boolean, false),
        BUILD_ELASTIC_OPTION_METADATA(ELASTIC_OTEL_DEBUG_PHP_HOOKS_ENABLED, OptionMetadata::type::boolean, false),
        BUILD_ELASTIC_OPTION_METADATA(ELASTIC_OTEL_INFERRED_SPANS_ENABLED, OptionMetadata::type::boolean, false),
        BUILD_ELASTIC_OPTION_METADATA(ELASTIC_OTEL_INFERRED_SPANS_REDUCTION_ENABLED, OptionMetadata::type::boolean, false),
        BUILD_ELASTIC_OPTION_METADATA(ELASTIC_OTEL_INFERRED_SPANS_STACKTRACE_ENABLED, OptionMetadata::type::boolean, false),
        BUILD_ELASTIC_OPTION_METADATA(ELASTIC_OTEL_INFERRED_SPANS_SAMPLING_INTERVAL, OptionMetadata::type::duration, false),
        BUILD_ELASTIC_OPTION_METADATA(ELASTIC_OTEL_INFERRED_SPANS_MIN_DURATION, OptionMetadata::type::duration, false),
        BUILD_ELASTIC_OPTION_METADATA(ELASTIC_OTEL_DEPENDENCY_AUTOLOADER_GUARD_ENABLED, OptionMetadata::type::boolean, false),
        BUILD_ELASTIC_OPTION_METADATA(ELASTIC_OTEL_NATIVE_OTLP_SERIALIZER_ENABLED, OptionMetadata::type::boolean, false),
        BUILD_ELASTIC_OPTION_METADATA(ELASTIC_OTEL_OPAMP_HEADERS, OptionMetadata::type::string, false),
        BUILD_ELASTIC_OPTION_METADATA(ELASTIC_OTEL_OPAMP_ENDPOINT, OptionMetadata::type::string, false),
        BUILD_ELASTIC_OPTION_METADATA(ELASTIC_OTEL_OPAMP_HEARTBEAT_INTERVAL, OptionMetadata::type::duration, false),
        BUILD_ELASTIC_OPTION_METADATA(ELASTIC_OTEL_OPAMP_SEND_TIMEOUT, OptionMetadata::type::duration, false),
        BUILD_ELASTIC_OPTION_METADATA(ELASTIC_OTEL_OPAMP_SEND_MAX_RETRIES, OptionMetadata::type::bytes, false),
        BUILD_ELASTIC_OPTION_METADATA(ELASTIC_OTEL_OPAMP_SEND_RETRY_DELAY, OptionMetadata::type::duration, false),

        BUILD_ELASTIC_OPTION_METADATA(ELASTIC_OTEL_OPAMP_INSECURE, OptionMetadata::type::boolean, false),
        BUILD_ELASTIC_OPTION_METADATA(ELASTIC_OTEL_OPAMP_CERTIFICATE, OptionMetadata::type::string, false),
        BUILD_ELASTIC_OPTION_METADATA(ELASTIC_OTEL_OPAMP_CLIENT_CERTIFICATE, OptionMetadata::type::string, false),
        BUILD_ELASTIC_OPTION_METADATA(ELASTIC_OTEL_OPAMP_CLIENT_KEY, OptionMetadata::type::string, false),
        BUILD_ELASTIC_OPTION_METADATA(ELASTIC_OTEL_OPAMP_CLIENT_KEYPASS, OptionMetadata::type::string, true),

        // otel native options
        BUILD_OPTION_METADATA(OTEL_EXPORTER_OTLP_INSECURE, OptionMetadata::type::boolean, false),
        BUILD_OPTION_METADATA(OTEL_EXPORTER_OTLP_CERTIFICATE, OptionMetadata::type::string, false),
        BUILD_OPTION_METADATA(OTEL_EXPORTER_OTLP_CLIENT_CERTIFICATE, OptionMetadata::type::string, false),
        BUILD_OPTION_METADATA(OTEL_EXPORTER_OTLP_CLIENT_KEY, OptionMetadata::type::string, false),
        BUILD_OPTION_METADATA(OTEL_EXPORTER_OTLP_CLIENT_KEYPASS, OptionMetadata::type::string, true),

        BUILD_OPTION_METADATA(OTEL_EXPORTER_OTLP_TRACES_INSECURE, OptionMetadata::type::boolean, false),
        BUILD_OPTION_METADATA(OTEL_EXPORTER_OTLP_TRACES_CERTIFICATE, OptionMetadata::type::string, false),
        BUILD_OPTION_METADATA(OTEL_EXPORTER_OTLP_TRACES_CLIENT_CERTIFICATE, OptionMetadata::type::string, false),
        BUILD_OPTION_METADATA(OTEL_EXPORTER_OTLP_TRACES_CLIENT_KEY, OptionMetadata::type::string, false),
        BUILD_OPTION_METADATA(OTEL_EXPORTER_OTLP_TRACES_CLIENT_KEYPASS, OptionMetadata::type::string, true),

        BUILD_OPTION_METADATA(OTEL_EXPORTER_OTLP_METRICS_INSECURE, OptionMetadata::type::boolean, false),
        BUILD_OPTION_METADATA(OTEL_EXPORTER_OTLP_METRICS_CERTIFICATE, OptionMetadata::type::string, false),
        BUILD_OPTION_METADATA(OTEL_EXPORTER_OTLP_METRICS_CLIENT_CERTIFICATE, OptionMetadata::type::string, false),
        BUILD_OPTION_METADATA(OTEL_EXPORTER_OTLP_METRICS_CLIENT_KEY, OptionMetadata::type::string, false),
        BUILD_OPTION_METADATA(OTEL_EXPORTER_OTLP_METRICS_CLIENT_KEYPASS, OptionMetadata::type::string, true),

        BUILD_OPTION_METADATA(OTEL_EXPORTER_OTLP_LOGS_INSECURE, OptionMetadata::type::boolean, false),
        BUILD_OPTION_METADATA(OTEL_EXPORTER_OTLP_LOGS_CERTIFICATE, OptionMetadata::type::string, false),
        BUILD_OPTION_METADATA(OTEL_EXPORTER_OTLP_LOGS_CLIENT_CERTIFICATE, OptionMetadata::type::string, false),
        BUILD_OPTION_METADATA(OTEL_EXPORTER_OTLP_LOGS_CLIENT_KEY, OptionMetadata::type::string, false),
        BUILD_OPTION_METADATA(OTEL_EXPORTER_OTLP_LOGS_CLIENT_KEYPASS, OptionMetadata::type::string, true),

        BUILD_OPTION_METADATA(OTEL_EXPORTER_OTLP_ENDPOINT, OptionMetadata::type::string, false),
        BUILD_OPTION_METADATA(OTEL_EXPORTER_OTLP_TRACES_ENDPOINT, OptionMetadata::type::string, false),
        BUILD_OPTION_METADATA(OTEL_EXPORTER_OTLP_METRICS_ENDPOINT, OptionMetadata::type::string, false),
        BUILD_OPTION_METADATA(OTEL_EXPORTER_OTLP_LOGS_ENDPOINT, OptionMetadata::type::string, false)

        };

    // clang-format on
};

} // namespace elasticapm::php
