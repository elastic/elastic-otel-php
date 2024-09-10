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

#include <stdarg.h>

namespace elasticapm::php {

class LoggerInterface {
public:
    virtual ~LoggerInterface() {
    }

    virtual void printf(LogLevel level, const char *format, ...) const = 0;
    virtual bool doesMeetsLevelCondition(LogLevel level) const = 0;
};


#define PRsv "%.*s"
#define PRsvArg(strv) static_cast<int>(strv.length()), strv.data()
#define PRcsvArg(str, len) len, str
#define PRzsArg(strv) ZSTR_LEN(strv), ZSTR_VAL(strv)

#define ELOG_CRITICAL(logger, format, ...) do { if (!logger || !logger->doesMeetsLevelCondition(LogLevel::logLevel_critical)) break; logger->printf(LogLevel::logLevel_critical, format, ##__VA_ARGS__); } while(false);
#define ELOG_ERROR(logger, format, ...) do { if (!logger || !logger->doesMeetsLevelCondition(LogLevel::logLevel_error)) break; logger->printf(LogLevel::logLevel_error, format, ##__VA_ARGS__); } while(false);
#define ELOG_WARNING(logger, format, ...) do { if (!logger || !logger->doesMeetsLevelCondition(LogLevel::logLevel_warning)) break; logger->printf(LogLevel::logLevel_warning, format, ##__VA_ARGS__); } while(false);
#define ELOG_INFO(logger, format, ...) do { if (!logger || !logger->doesMeetsLevelCondition(LogLevel::logLevel_info)) break; logger->printf(LogLevel::logLevel_info, format, ##__VA_ARGS__); } while(false);
#define ELOG_DEBUG(logger, format, ...) do { if (!logger || !logger->doesMeetsLevelCondition(LogLevel::logLevel_debug)) break; logger->printf(LogLevel::logLevel_debug, format, ##__VA_ARGS__); } while(false);
#define ELOG_TRACE(logger, format, ...) do { if (!logger || !logger->doesMeetsLevelCondition(LogLevel::logLevel_trace)) break; logger->printf(LogLevel::logLevel_trace, format, ##__VA_ARGS__); } while(false);
#define ELOG(logger, level, format, ...) do { if (!logger || !logger->doesMeetsLevelCondition(level)) break; logger->printf(level, format, ##__VA_ARGS__); } while(false);

}
