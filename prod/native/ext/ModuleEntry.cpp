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

#ifdef HAVE_CONFIG_H
# include "config.h"
#endif
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



ZEND_DECLARE_MODULE_GLOBALS( elastic_apm )

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

PHP_RINIT_FUNCTION(elastic_apm) {
    ELASTICAPM_G(globals)->requestScope_->onRequestInit();
    return SUCCESS;
}

PHP_RSHUTDOWN_FUNCTION(elastic_apm) {
    ELASTICAPM_G(globals)->requestScope_->onRequestShutdown();
    return SUCCESS;
}

ZEND_RESULT_CODE  elasticApmRequestPostDeactivate(void) {
    ELASTICAPM_G(globals)->requestScope_->onRequestPostDeactivate();
    return ZEND_RESULT_CODE::SUCCESS;
}

PHP_MINFO_FUNCTION(elastic_apm) {
    printPhpInfo(zend_module);
}


static PHP_GINIT_FUNCTION(elastic_apm) {
    //TODO for ZTS logger must be initialized in MINIT! (share fd between threads) - different lifecycle

    //TODO store in globals and allow watch for config change (change of level)
    auto logSinkStdErr = std::make_shared<elasticapm::php::LoggerSinkStdErr>();
    auto logSinkSysLog = std::make_shared<elasticapm::php::LoggerSinkSysLog>();

    auto logger = std::make_shared<elasticapm::php::Logger>(std::vector<std::shared_ptr<elasticapm::php::LoggerSinkInterface>>{logSinkStdErr, logSinkSysLog});

    ELOG_DEBUG(logger, "%s: GINIT called; parent PID: %d", __FUNCTION__, static_cast<int>(elasticapm::osutils::getParentProcessId()));
    elastic_apm_globals->globals = nullptr;

    auto phpBridge = std::make_shared<elasticapm::php::PhpBridge>(logger);

    auto hooksStorage = std::make_shared<elasticapm::php::InstrumentedFunctionHooksStorage_t>();

    try {
        elastic_apm_globals->globals = new elasticapm::php::AgentGlobals(logger, std::move(logSinkStdErr), std::move(logSinkSysLog), std::move(phpBridge), std::move(hooksStorage), [](elasticapm::php::ConfigurationSnapshot &cfg) { return configManager.updateIfChanged(cfg); });
    } catch (std::exception const &e) {
        ELOG_CRITICAL(logger, "Unable to allocate AgentGlobals. '%s'", e.what());
    }

    ZVAL_UNDEF(&elastic_apm_globals->lastException);
    new (&elastic_apm_globals->lastErrorData) std::unique_ptr<elasticapm::php::PhpErrorData>;
    elastic_apm_globals->captureErrors = false;
}

PHP_GSHUTDOWN_FUNCTION(elastic_apm) {
    if (elastic_apm_globals->globals) {
        ELOG_DEBUG(elastic_apm_globals->globals->logger_, "%s: GSHUTDOWN called; parent PID: %d", __FUNCTION__, static_cast<int>(elasticapm::osutils::getParentProcessId()) );
        delete elastic_apm_globals->globals;
    }

    if (elastic_apm_globals->lastErrorData) {
        // ELASTIC_APM_LOG_DIRECT_WARNING( "%s: still holding error", __FUNCTION__);
        // we need to relese any dangling php error data beacause it is already freed (it was allocated in request pool)
        elastic_apm_globals->lastErrorData.release();
    }
}

PHP_MINIT_FUNCTION(elastic_apm) {
    REGISTER_LONG_CONSTANT("ELASTIC_APM_LOG_LEVEL_OFF", logLevel_off, CONST_CS | CONST_PERSISTENT);
    REGISTER_LONG_CONSTANT("ELASTIC_APM_LOG_LEVEL_CRITICAL", logLevel_critical, CONST_CS | CONST_PERSISTENT);
    REGISTER_LONG_CONSTANT("ELASTIC_APM_LOG_LEVEL_ERROR", logLevel_error, CONST_CS | CONST_PERSISTENT);
    REGISTER_LONG_CONSTANT("ELASTIC_APM_LOG_LEVEL_WARNING", logLevel_warning, CONST_CS | CONST_PERSISTENT);
    REGISTER_LONG_CONSTANT("ELASTIC_APM_LOG_LEVEL_INFO", logLevel_info, CONST_CS | CONST_PERSISTENT);
    REGISTER_LONG_CONSTANT("ELASTIC_APM_LOG_LEVEL_DEBUG", logLevel_debug, CONST_CS | CONST_PERSISTENT);
    REGISTER_LONG_CONSTANT("ELASTIC_APM_LOG_LEVEL_TRACE", logLevel_trace, CONST_CS | CONST_PERSISTENT);

    elasticApmModuleInit(type, module_number);
    return SUCCESS;
}

PHP_MSHUTDOWN_FUNCTION(elastic_apm) {
    elasticApmModuleShutdown(type, module_number);
    return SUCCESS;
}

zend_module_entry elastic_apm_module_entry = {
	STANDARD_MODULE_HEADER,
	"elastic_apm",					 /* Extension name */
	elastic_apm_functions,			 /* zend_function_entry */
	PHP_MINIT(elastic_apm),		     /* PHP_MINIT - Module initialization */
	PHP_MSHUTDOWN(elastic_apm),		 /* PHP_MSHUTDOWN - Module shutdown */
	PHP_RINIT(elastic_apm),			 /* PHP_RINIT - Request initialization */
	PHP_RSHUTDOWN(elastic_apm),		 /* PHP_RSHUTDOWN - Request shutdown */
	PHP_MINFO(elastic_apm),			 /* PHP_MINFO - Module info */
	PHP_ELASTIC_APM_VERSION,		 /* Version */
	PHP_MODULE_GLOBALS(elastic_apm), /* PHP_MODULE_GLOBALS */
    PHP_GINIT(elastic_apm), 	     /* PHP_GINIT */
    PHP_GSHUTDOWN(elastic_apm),		 /* PHP_GSHUTDOWN */
	elasticApmRequestPostDeactivate, /* post deactivate */
	STANDARD_MODULE_PROPERTIES_EX
};

#   ifdef ZTS
ZEND_TSRMLS_CACHE_DEFINE()
#   endif
extern "C" ZEND_GET_MODULE(elastic_apm)
