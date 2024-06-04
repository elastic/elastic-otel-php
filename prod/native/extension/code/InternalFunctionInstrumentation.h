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

#include "AutoZval.h"
#include "PhpBridgeInterface.h"
#include "LoggerInterface.h"
#include "InternalFunctionInstrumentationStorage.h"
#include "InstrumentedFunctionHooksStorage.h"
#include <string_view>
#include <Zend/zend_observer.h>

namespace elasticapm::php {

using InstrumentedFunctionHooksStorage_t = InstrumentedFunctionHooksStorage<zend_ulong, AutoZval>;

bool instrumentFunction(LoggerInterface *log, std::string_view className, std::string_view functionName, zval *callableOnEntry, zval *callableOnExit);
zend_observer_fcall_handlers elasticRegisterObserver(zend_execute_data *execute_data);


}
