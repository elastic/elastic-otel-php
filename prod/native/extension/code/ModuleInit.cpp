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

#if defined(PHP_WIN32) && ! defined(CURL_STATICLIB)
#   define CURL_STATICLIB
#endif

#include "elastic_otel_version.h"
#include "CommonUtils.h"
#include "ConfigurationManager.h"
#include "ConfigurationSnapshot.h"
#include "ForkHandler.h"
#include "Hooking.h"
#include "InternalFunctionInstrumentation.h"
#include "ModuleIniEntries.h"
#include "ModuleGlobals.h"
#include "PeriodicTaskExecutor.h"
#include "RequestScope.h"
#include "SigSegvHandler.h"
#include "os/OsUtils.h"

#include <curl/curl.h>
#include <inttypes.h> // PRIu64
#include <stdbool.h>
#include <php.h>
#include <zend_compile.h>
#include <zend_exceptions.h>
#include <zend_builtin_functions.h>
#include <Zend/zend_observer.h>
#include "php_error.h"
#include "util_for_PHP.h"


extern elasticapm::php::ConfigurationManager configManager;


void logStartupPreamble(elasticapm::php::LoggerInterface *logger) {
    constexpr LogLevel level = LogLevel::logLevel_debug;
    constexpr int colWidth = 40;

    using namespace std::literals;
    ELOG(logger, level, "Elastic Distribution for OpenTelemetry PHP");
    ELOG(logger, level, "%*s%s", -colWidth, "Native part version:", ELASTIC_OTEL_VERSION);
    ELOG(logger, level, "%*s%s", -colWidth, "Process command line:", elasticapm::utils::sanitizeKeyValueString(elasticapm::utils::getEnvName(EL_STRINGIFY(ELASTIC_OTEL_CFG_OPT_NAME_API_KEY)), elasticapm::osutils::getCommandLine()).c_str());
    ELOG(logger, level, "%*s%s", -colWidth, "Process environment:", elasticapm::utils::sanitizeKeyValueString(elasticapm::utils::getEnvName(EL_STRINGIFY(ELASTIC_OTEL_CFG_OPT_NAME_API_KEY)), elasticapm::osutils::getProcessEnvironment()).c_str());
}

void elasticApmModuleInit(int moduleType, int moduleNumber) {
    auto const &sapi = *ELASTICAPM_G(globals)->sapi_;
    auto globals = ELASTICAPM_G(globals);

    elasticapm::php::registerElasticApmIniEntries(EAPM_GL(logger_).get(), moduleNumber);
    configManager.update();
    globals->config_->update();

    ELOGF_DEBUG(globals->logger_, MODULE, "%s entered: moduleType: %d, moduleNumber: %d, parent PID: %d, SAPI: %s (%d) is %s", __FUNCTION__, moduleType, moduleNumber, static_cast<int>(elasticapm::osutils::getParentProcessId()), sapi.getName().data(), static_cast<uint8_t>(sapi.getType()), sapi.isSupported() ? "supported" : "unsupported");
    if (!sapi.isSupported()) {
        return;
    }

    registerSigSegvHandler(globals->logger_.get());

    elasticapm::php::Hooking::getInstance().fetchOriginalHooks();

    // CURLcode curlCode;

    logStartupPreamble(globals->logger_.get());

    if (!EAPM_CFG(enabled)) {
        ELOGF_INFO(globals->logger_, MODULE, "Extension is disabled");
        return;
    }

    ELOGF_DEBUG(globals->logger_, MODULE, "MINIT Replacing hooks");
    elasticapm::php::Hooking::getInstance().replaceHooks();

    zend_observer_activate();
    zend_observer_fcall_register(elasticapm::php::elasticRegisterObserver);


    if (php_check_open_basedir_ex(EAPM_GL(config_)->get(&elasticapm::php::ConfigurationSnapshot::bootstrap_php_part_file).c_str(), false) != 0) {
        ELOGF_WARNING(globals->logger_, MODULE, "Elastic Agent bootstrap file (%s) is located outside of paths allowed by open_basedir ini setting. Read more details here https://www.elastic.co/guide/en/apm/agent/php/current/setup.html#limitations", EAPM_GL(config_)->get(&elasticapm::php::ConfigurationSnapshot::bootstrap_php_part_file).c_str());
    }
}

void elasticApmModuleShutdown( int moduleType, int moduleNumber ) {
    if (!ELASTICAPM_G(globals)->sapi_->isSupported()) {
        return;
    }

    if (!EAPM_CFG(enabled)) {
        return;
    }

    elasticapm::php::Hooking::getInstance().restoreOriginalHooks();

    // curl_global_cleanup();

    zend_unregister_ini_entries(moduleNumber);

    unregisterSigSegvHandler();
}

// void elasticApmGetLastPhpError(zval* return_value) {
//     if (!ELASTICAPM_G(lastErrorData)) {
//         RETURN_NULL();
//     }

//     array_init( return_value );
//     ELASTIC_OTEL_ZEND_ADD_ASSOC(return_value, "type", long, static_cast<zend_long>(ELASTICAPM_G(lastErrorData)->getType()));
//     ELASTIC_OTEL_ZEND_ADD_ASSOC_NULLABLE_STRING( return_value, "fileName", ELASTICAPM_G(lastErrorData)->getFileName().data() );
//     ELASTIC_OTEL_ZEND_ADD_ASSOC(return_value, "lineNumber", long, static_cast<zend_long>(ELASTICAPM_G(lastErrorData)->getLineNumber()));
//     ELASTIC_OTEL_ZEND_ADD_ASSOC_NULLABLE_STRING( return_value, "message", ELASTICAPM_G(lastErrorData)->getMessage().data());
//     Z_TRY_ADDREF_P((ELASTICAPM_G(lastErrorData)->getStackTrace()));
//     ELASTIC_OTEL_ZEND_ADD_ASSOC(return_value, "stackTrace", zval, (ELASTICAPM_G(lastErrorData)->getStackTrace()));
// }
//
// void elasticApmGetLastThrown(zval *return_value) {
//     if (Z_TYPE(ELASTICAPM_G(lastException)) == IS_UNDEF) {
//         RETURN_NULL();
//     }

//     RETURN_ZVAL(&ELASTICAPM_G(lastException), /* copy */ true, /* dtor */ false );
// }
