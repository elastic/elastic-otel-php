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
#include "LogFeature.h"
#include "basic_macros.h"

#include <format>
#include <unordered_map>
#include <stdarg.h>

namespace elasticapm::php {

class LoggerInterface {
public:
    virtual ~LoggerInterface() {
    }

    virtual void log(LogLevel level, const std::string &message) const = 0;
    virtual void printf(LogLevel level, const char *format, ...) const = 0;
    virtual bool doesMeetsLevelCondition(LogLevel level) const = 0;
    virtual bool doesFeatureMeetsLevelCondition(LogLevel level, LogFeature feature) const = 0;
    virtual LogLevel getMaxLogLevel() const = 0;
    virtual void setLogFeatures(std::unordered_map<elasticapm::php::LogFeature, LogLevel> features) = 0;
};

#define PRsv "%.*s"
#define PRsvArg(strv) static_cast<int>(strv.size()), strv.data()
#define PRcsvArg(str, len) len, str
#define PRzsArg(strv) ZSTR_LEN(strv), ZSTR_VAL(strv)

// clang-format off

// ELOG*_NF_* - means no feature log
// ELOGF_* - printf style
// ELOG_* - std::format style

#define ELOG(logger, level, feature, formatStr, ...) \
    do { \
        if (!logger || !logger->doesFeatureMeetsLevelCondition(level, elasticapm::php::LogFeature::feature)) break; \
        logger->log(level, std::format("[{}] {}", #feature, std::format(formatStr, ##__VA_ARGS__))); \
    } while(false)

#define ELOG_NF(logger, level, formatStr, ...) ELOG(logger, level, ALL, formatStr, ##__VA_ARGS__)

#define ELOG_CRITICAL(logger, feature, formatStr, ...) ELOG(logger, LogLevel::logLevel_critical, feature, formatStr, ##__VA_ARGS__)
#define ELOG_ERROR(logger, feature, formatStr, ...)    ELOG(logger, LogLevel::logLevel_error, feature, formatStr, ##__VA_ARGS__)
#define ELOG_WARNING(logger, feature, formatStr, ...)  ELOG(logger, LogLevel::logLevel_warning, feature, formatStr, ##__VA_ARGS__)
#define ELOG_INFO(logger, feature, formatStr, ...)     ELOG(logger, LogLevel::logLevel_info, feature, formatStr, ##__VA_ARGS__)
#define ELOG_DEBUG(logger, feature, formatStr, ...)    ELOG(logger, LogLevel::logLevel_debug, feature, formatStr, ##__VA_ARGS__)
#define ELOG_TRACE(logger, feature, formatStr, ...)    ELOG(logger, LogLevel::logLevel_trace, feature, formatStr, ##__VA_ARGS__)

#define ELOG_NF_CRITICA(logger, formatStr, ...) ELOG_NF(logger, LogLevel::logLevel_critical, formatStr, ##__VA_ARGS__)
#define ELOG_NF_ERROR(logger, formatStr, ...)    ELOG_NF(logger, LogLevel::logLevel_error, formatStr, ##__VA_ARGS__)
#define ELOG_NF_WARNING(logger, formatStr, ...)  ELOG_NF(logger, LogLevel::logLevel_warning, formatStr, ##__VA_ARGS__)
#define ELOG_NF_INFO(logger, formatStr, ...)     ELOG_NF(logger, LogLevel::logLevel_info, formatStr, ##__VA_ARGS__)
#define ELOG_NF_DEBUG(logger, formatStr, ...)    ELOG_NF(logger, LogLevel::logLevel_debug, formatStr, ##__VA_ARGS__)
#define ELOG_NF_TRACE(logger, formatStr, ...)    ELOG_NF(logger, LogLevel::logLevel_trace, formatStr, ##__VA_ARGS__)

// printf style

#define ELOGF(logger, level, feature, format, ...) do { if (!logger || !logger->doesFeatureMeetsLevelCondition(level, elasticapm::php::LogFeature::feature)) break; logger->printf(level, "[" EL_STRINGIFY(feature) "] " format, ##__VA_ARGS__); } while(false);
#define ELOGF_NF(logger, level,  format, ...) ELOGF(logger, level, ALL, format, ##__VA_ARGS__)

#define ELOGF_CRITICAL(logger, feature, format, ...) ELOGF(logger, LogLevel::logLevel_critical, feature, format, ##__VA_ARGS__)
#define ELOGF_ERROR(logger, feature, format, ...)    ELOGF(logger, LogLevel::logLevel_error, feature, format, ##__VA_ARGS__)
#define ELOGF_WARNING(logger, feature, format, ...)  ELOGF(logger, LogLevel::logLevel_warning, feature, format, ##__VA_ARGS__)
#define ELOGF_INFO(logger, feature, format, ...)     ELOGF(logger, LogLevel::logLevel_info, feature, format, ##__VA_ARGS__)
#define ELOGF_DEBUG(logger, feature, format, ...)    ELOGF(logger, LogLevel::logLevel_debug, feature, format, ##__VA_ARGS__)
#define ELOGF_TRACE(logger, feature, format, ...)    ELOGF(logger, LogLevel::logLevel_trace, feature, format, ##__VA_ARGS__)

#define ELOGF_NF_CRITICAL(logger, format, ...) ELOGF_NF(logger, LogLevel::logLevel_critical, format, ##__VA_ARGS__)
#define ELOGF_NF_ERROR(logger, format, ...)    ELOGF_NF(logger, LogLevel::logLevel_error, format, ##__VA_ARGS__)
#define ELOGF_NF_WARNING(logger, format, ...)  ELOGF_NF(logger, LogLevel::logLevel_warning, format, ##__VA_ARGS__)
#define ELOGF_NF_INFO(logger, format, ...)     ELOGF_NF(logger, LogLevel::logLevel_info, format, ##__VA_ARGS__)
#define ELOGF_NF_DEBUG(logger, format, ...)    ELOGF_NF(logger, LogLevel::logLevel_debug, format, ##__VA_ARGS__)
#define ELOGF_NF_TRACE(logger, format, ...)    ELOGF_NF(logger, LogLevel::logLevel_trace, format, ##__VA_ARGS__)

// clang-format on
}
