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

#include <string_view>

/**
 * The order is important because lower numeric values are considered contained in higher ones
 * for example logLevel_error means that both logLevel_error and logLevel_critical is enabled.
 */


// namespace elasticapm::php {


enum LogLevel {
    /**
     * logLevel_off should not be used by logging statements - it is used only in configuration.
     */
    logLevel_off = 0,
    logLevel_critical,
    logLevel_error,
    logLevel_warning,
    logLevel_info,
    logLevel_debug,
    logLevel_trace,

    first = logLevel_off,
    last = logLevel_trace
};

// }

std::string_view getLogLevelName(LogLevel level);