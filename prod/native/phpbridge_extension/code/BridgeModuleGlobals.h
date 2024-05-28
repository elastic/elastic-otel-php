/*
 * Licensed to Elasticsearch B.V. under one or more contributor
 * license agreements. See the NOTICE file distributed with
 * this work for additional information regarding copyright
 * ownership. Elasticsearch B.V. licenses this file to you under
 * the Apache License, Version 2.0 (the "License"); you may
 * not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied.  See the License for the
 * specific language governing permissions and limitations
 * under the License.
 */

#pragma once

#include <main/php.h>
#include <Zend/zend.h>
#include <Zend/zend_API.h>
#include <Zend/zend_modules.h>

#include "Logger.h"
#include "PhpBridge.h"

#include <memory>

#if defined(ZTS) && defined(COMPILE_DL_ELASTIC_APM)
ZEND_TSRMLS_CACHE_EXTERN()
#endif

struct BridgeGlobals {
    BridgeGlobals() {
        auto sink = std::make_shared<elasticapm::php::LoggerSinkStdErr>();
        sink->setLevel(LogLevel::logLevel_trace);
        logger = std::make_shared<elasticapm::php::Logger>(std::vector<std::shared_ptr<elasticapm::php::LoggerSinkInterface>>{std::move(sink)});
    }

    std::shared_ptr<elasticapm::php::Logger> logger;
    elasticapm::php::PhpBridge bridge{logger};
};

ZEND_BEGIN_MODULE_GLOBALS(phpbridge)
    BridgeGlobals *globals;
ZEND_END_MODULE_GLOBALS(phpbridge)

ZEND_EXTERN_MODULE_GLOBALS(phpbridge)

#ifdef ZTS
#define BRIDGE_G(member) ZEND_MODULE_GLOBALS_ACCESSOR(phpbridge, member)
#else
#define BRIDGE_G(member) (phpbridge_globals.member)
#endif
