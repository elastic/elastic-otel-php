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

#include "elastic_otel_version.h"

#include "AgentGlobals.h"
#include "PhpErrorData.h"

#include <main/php.h>
#include <Zend/zend.h>
#include <Zend/zend_API.h>
#include <Zend/zend_modules.h>

#include <memory>

ZEND_BEGIN_MODULE_GLOBALS(elastic_otel)
    elasticapm::php::AgentGlobals *globals;
    // zval lastException;
    // std::unique_ptr<elasticapm::php::PhpErrorData> lastErrorData;
    bool captureErrors;
ZEND_END_MODULE_GLOBALS(elastic_otel)

ZEND_EXTERN_MODULE_GLOBALS(elastic_otel)

#ifdef ZTS
#define ELASTICAPM_G(member) ZEND_MODULE_GLOBALS_ACCESSOR(elastic_otel, member)
#define EAPM_GL(member) ZEND_MODULE_GLOBALS_ACCESSOR(elastic_otel, globals)->member
#else
#define ELASTICAPM_G(member) (elastic_otel_globals.member)
#define EAPM_GL(member) (elastic_otel_globals.globals)->member
#endif
#define EAPM_CFG(option) (*EAPM_GL(config_))->option