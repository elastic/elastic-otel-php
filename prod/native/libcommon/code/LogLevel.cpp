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



#include <array>
#include "LogLevel.h"
#include <string_view>

using namespace std::string_view_literals;


std::string_view getLogLevelName(LogLevel level) {
    constexpr std::array<std::string_view, LogLevel::last - LogLevel::first + 1> logLevelNames = {
        "OFF     "sv,
        "CRITICAL"sv,
        "ERROR   "sv,
        "WARNING "sv,
        "INFO    "sv,
        "DEBUG   "sv,
        "TRACE   "sv
    };

    if (level < LogLevel::first || level > LogLevel::last) {
        return "UNKNOWN"sv;
    }
    return logLevelNames[level];
}