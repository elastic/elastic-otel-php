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


#include "AutoZval.h"
#include "PhpBridge.h"
#include "LoggerInterface.h"

#include "main/SAPI.h"
#include "Zend/zend_ini.h"
#include "Zend/zend_globals_macros.h"

#include "ext/opcache/ZendAccelerator.h"


namespace elasticapm::php {

bool PhpBridge::isOpcacheEnabled() const {
    using namespace std::string_view_literals;
    return getPhpSapiName() == "cli"sv ? INI_BOOL("opcache.enable_cli") : INI_BOOL("opcache.enable");
}


bool PhpBridge::detectOpcachePreload() const {
    if (!isOpcacheEnabled()) {
        return false;
    }

    const char *preloadValue = INI_STR("opcache.preload");
    if (!preloadValue || strlen(preloadValue) == 0) {
        return false;
    }

    return sapi_module.activate == nullptr && sapi_module.deactivate == nullptr && sapi_module.register_server_variables == nullptr && sapi_module.getenv == nullptr; // EG(error_reporting) == 0 is null only in request init scope
}


bool PhpBridge::isScriptRestricedByOpcacheAPI() const {
    if (!isOpcacheEnabled()) {
        return false;
    }

    char *restrict_api = INI_STR("opcache.restrict_api");
    if (!restrict_api || strlen(restrict_api) == 0) {
        return false;
    }

    size_t len = strlen(restrict_api);
    if (!SG(request_info).path_translated ||
        strlen(SG(request_info).path_translated) < len ||
        memcmp(SG(request_info).path_translated, restrict_api, len) != 0) {
        ELOG_WARNING(log_, "Script '%s' is restricted by \"opcache.restrict_api\" configuration directive. Can't perform any opcache API calls.", SG(request_info).path_translated);
        return true;
    }
    return false;
}

bool PhpBridge::detectOpcacheRestartPending() const {
    if (!isOpcacheEnabled()) {
        return false;
    }
    if (EG(function_table) && !zend_hash_str_find_ptr(EG(function_table), ZEND_STRL("opcache_get_status"))) {
        return false;
    }

    AutoZval rv;
    rv.setNull();

    AutoZval parameters{false};

	int originalErrorReportingState = EG(error_reporting); // suppress error/warning reporing
	EG(error_reporting) = 0;
    bool result = callMethod(NULL, "opcache_get_status", parameters.get(), 1, rv.get());
	EG(error_reporting) = originalErrorReportingState;

    if (!result) {
        ELOG_ERROR(log_, "opcache_get_status failure");
        return false;
    }

    if (Z_TYPE(*rv) != IS_ARRAY) {
        ELOG_DEBUG(log_, "opcache_get_status failed, rvtype: %d", Z_TYPE(*rv));
        return false;
    }

	zval *restartPending = zend_hash_str_find(Z_ARRVAL(*rv), ZEND_STRL("restart_pending"));
    if (restartPending && Z_TYPE_P(restartPending) == IS_TRUE) {
        return true;
    } else if (!restartPending || Z_TYPE_P(restartPending) != IS_FALSE) {
        ELOG_DEBUG(log_, "opcache_get_status returned unexpected data ptr: %p t:%d", restartPending, restartPending ? Z_TYPE_P(restartPending) : -1);
    }
    return false;
}


}