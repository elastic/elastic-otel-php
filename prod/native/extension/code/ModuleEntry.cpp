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

#ifdef HAVE_CONFIG_H
# include "config.h"
#endif
#include "elastic_otel_version.h"

#include "ModuleGlobals.h"
// external libraries
#include <main/php.h>
#include <Zend/zend_types.h>

#include "ModuleInit.h"

#include "AutoZval.h"
#include "os/OsUtils.h"
#include "CallOnScopeExit.h"
#include "ConfigurationManager.h"
#include "InstrumentedFunctionHooksStorage.h"
#include "InternalFunctionInstrumentation.h"
#include "Logger.h"
#include "ModuleInfo.h"
#include "ModuleFunctions.h"
#include "PeriodicTaskExecutor.h"
#include "PhpBridge.h"
#include "PhpBridgeInterface.h"
#include "RequestScope.h"
#include "SharedMemoryState.h"



ZEND_DECLARE_MODULE_GLOBALS( elastic_otel )

elasticapm::php::ConfigurationManager configManager([](std::string_view iniName) -> std::optional<std::string> {
    zend_bool exists = false;
    auto value = zend_ini_string_ex(iniName.data(), iniName.length(), 0, &exists);
    if (!value) {
        return std::nullopt;
    }
    return std::string(value);
});

#ifndef ZEND_PARSE_PARAMETERS_NONE
#   define ZEND_PARSE_PARAMETERS_NONE() \
        ZEND_PARSE_PARAMETERS_START(0, 0) \
        ZEND_PARSE_PARAMETERS_END()
#endif

PHP_RINIT_FUNCTION(elastic_otel) {
    ELASTICAPM_G(globals)->requestScope_->onRequestInit();
    return SUCCESS;
}

PHP_RSHUTDOWN_FUNCTION(elastic_otel) {
    ELASTICAPM_G(globals)->requestScope_->onRequestShutdown();
    return SUCCESS;
}

ZEND_RESULT_CODE  elasticApmRequestPostDeactivate(void) {
    ELASTICAPM_G(globals)->requestScope_->onRequestPostDeactivate();
    return ZEND_RESULT_CODE::SUCCESS;
}

PHP_MINFO_FUNCTION(elastic_otel) {
    printPhpInfo(zend_module);
}


static PHP_GINIT_FUNCTION(elastic_otel) {
    //TODO for ZTS logger must be initialized in MINIT! (share fd between threads) - different lifecycle

    //TODO store in globals and allow watch for config change (change of level)
    auto logSinkStdErr = std::make_shared<elasticapm::php::LoggerSinkStdErr>();
    auto logSinkSysLog = std::make_shared<elasticapm::php::LoggerSinkSysLog>();
    auto logSinkFile = std::make_shared<elasticapm::php::LoggerSinkFile>();

    auto logger = std::make_shared<elasticapm::php::Logger>(std::vector<std::shared_ptr<elasticapm::php::LoggerSinkInterface>>{logSinkStdErr, logSinkSysLog, logSinkFile});

    configManager.attachLogger(logger);

    ELOG_DEBUG(logger, "%s: GINIT called; parent PID: %d", __FUNCTION__, static_cast<int>(elasticapm::osutils::getParentProcessId()));
    elastic_otel_globals->globals = nullptr;

    auto phpBridge = std::make_shared<elasticapm::php::PhpBridge>(logger);

    auto hooksStorage = std::make_shared<elasticapm::php::InstrumentedFunctionHooksStorage_t>();

    try {
        elastic_otel_globals->globals = new elasticapm::php::AgentGlobals(logger, std::move(logSinkStdErr), std::move(logSinkSysLog), std::move(logSinkFile), std::move(phpBridge), std::move(hooksStorage), [](elasticapm::php::ConfigurationSnapshot &cfg) { return configManager.updateIfChanged(cfg); });
    } catch (std::exception const &e) {
        ELOG_CRITICAL(logger, "Unable to allocate AgentGlobals. '%s'", e.what());
    }

    // ZVAL_UNDEF(&elastic_otel_globals->lastException);
    // new (&elastic_otel_globals->lastErrorData) std::unique_ptr<elasticapm::php::PhpErrorData>;
    elastic_otel_globals->captureErrors = false;
}

PHP_GSHUTDOWN_FUNCTION(elastic_otel) {
    if (elastic_otel_globals->globals) {
        ELOG_DEBUG(elastic_otel_globals->globals->logger_, "%s: GSHUTDOWN called; parent PID: %d", __FUNCTION__, static_cast<int>(elasticapm::osutils::getParentProcessId()) );
        delete elastic_otel_globals->globals;
    }

    // if (elastic_otel_globals->lastErrorData) {
    //     // ELASTIC_OTEL_LOG_DIRECT_WARNING( "%s: still holding error", __FUNCTION__);
    //     // we need to relese any dangling php error data beacause it is already freed (it was allocated in request pool)
    //     elastic_otel_globals->lastErrorData.release();
    // }
}

zend_module_entry elastic_otel_fake = {STANDARD_MODULE_HEADER,
                                       "opentelemetry", /* Extension name */
                                       nullptr,         /* zend_function_entry */
                                       nullptr,         /* PHP_MINIT - Module initialization */
                                       nullptr,         /* PHP_MSHUTDOWN - Module shutdown */
                                       nullptr,         /* PHP_RINIT - Request initialization */
                                       nullptr,         /* PHP_RSHUTDOWN - Request shutdown */
                                       nullptr,         /* PHP_MINFO - Module info */
                                       "2.0",           /* Version */
                                       0,               /* globals size */
                                       nullptr,         /* PHP_MODULE_GLOBALS */
                                       nullptr,         /* PHP_GINIT */
                                       nullptr,         /* PHP_GSHUTDOWN */
                                       nullptr,         /* post deactivate */
                                       STANDARD_MODULE_PROPERTIES_EX};

PHP_MINIT_FUNCTION(elastic_otel) {
    REGISTER_LONG_CONSTANT("ELASTIC_OTEL_LOG_LEVEL_OFF", logLevel_off, CONST_CS | CONST_PERSISTENT);
    REGISTER_LONG_CONSTANT("ELASTIC_OTEL_LOG_LEVEL_CRITICAL", logLevel_critical, CONST_CS | CONST_PERSISTENT);
    REGISTER_LONG_CONSTANT("ELASTIC_OTEL_LOG_LEVEL_ERROR", logLevel_error, CONST_CS | CONST_PERSISTENT);
    REGISTER_LONG_CONSTANT("ELASTIC_OTEL_LOG_LEVEL_WARNING", logLevel_warning, CONST_CS | CONST_PERSISTENT);
    REGISTER_LONG_CONSTANT("ELASTIC_OTEL_LOG_LEVEL_INFO", logLevel_info, CONST_CS | CONST_PERSISTENT);
    REGISTER_LONG_CONSTANT("ELASTIC_OTEL_LOG_LEVEL_DEBUG", logLevel_debug, CONST_CS | CONST_PERSISTENT);
    REGISTER_LONG_CONSTANT("ELASTIC_OTEL_LOG_LEVEL_TRACE", logLevel_trace, CONST_CS | CONST_PERSISTENT);

    elasticApmModuleInit(type, module_number);

    if (!zend_register_internal_module(&elastic_otel_fake)) {
        ELOG_WARNING(ELASTICAPM_G(globals)->logger_, "Unable to create artificial opentelemetry extension. There might be stability issues.");
    }

    return SUCCESS;
}

PHP_MSHUTDOWN_FUNCTION(elastic_otel) {
    elasticApmModuleShutdown(type, module_number);
    return SUCCESS;
}

zend_module_entry elastic_otel_module_entry = {
	STANDARD_MODULE_HEADER,
	"elastic_otel",					 /* Extension name */
	elastic_otel_functions,			 /* zend_function_entry */
	PHP_MINIT(elastic_otel),		     /* PHP_MINIT - Module initialization */
	PHP_MSHUTDOWN(elastic_otel),		 /* PHP_MSHUTDOWN - Module shutdown */
	PHP_RINIT(elastic_otel),			 /* PHP_RINIT - Request initialization */
	PHP_RSHUTDOWN(elastic_otel),		 /* PHP_RSHUTDOWN - Request shutdown */
	PHP_MINFO(elastic_otel),			 /* PHP_MINFO - Module info */
	ELASTIC_OTEL_VERSION,		     /* Version */
	PHP_MODULE_GLOBALS(elastic_otel), /* PHP_MODULE_GLOBALS */
    PHP_GINIT(elastic_otel), 	     /* PHP_GINIT */
    PHP_GSHUTDOWN(elastic_otel),		 /* PHP_GSHUTDOWN */
	elasticApmRequestPostDeactivate, /* post deactivate */
	STANDARD_MODULE_PROPERTIES_EX
};

#   ifdef ZTS
ZEND_TSRMLS_CACHE_DEFINE()
#   endif
extern "C" ZEND_GET_MODULE(elastic_otel)