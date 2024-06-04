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
#include "BridgeModuleGlobals.h"
#include "BridgeModuleFunctions.h"

#include "elastic_apm_version.h"


#include <main/php.h>
#include <Zend/zend_types.h>

ZEND_DECLARE_MODULE_GLOBALS(phpbridge);

#ifndef ZEND_PARSE_PARAMETERS_NONE
#   define ZEND_PARSE_PARAMETERS_NONE() \
        ZEND_PARSE_PARAMETERS_START(0, 0) \
        ZEND_PARSE_PARAMETERS_END()
#endif

PHP_RINIT_FUNCTION(phpbridge) {
    return SUCCESS;
}

PHP_RSHUTDOWN_FUNCTION(phpbridge) {
    return SUCCESS;
}

ZEND_RESULT_CODE  PhpBridgePostDeactivate(void) {
    return ZEND_RESULT_CODE::SUCCESS;
}

PHP_MINFO_FUNCTION(phpbridge) {
}


PHP_GINIT_FUNCTION(phpbridge) {
    phpbridge_globals->globals = new BridgeGlobals();
}

PHP_GSHUTDOWN_FUNCTION(phpbridge) {
    delete phpbridge_globals->globals;
    phpbridge_globals->globals = nullptr;
}

PHP_MINIT_FUNCTION(phpbridge) {
    return SUCCESS;
}

PHP_MSHUTDOWN_FUNCTION(phpbridge) {
    return SUCCESS;
}

zend_module_entry phpbridge_module_entry = {
    STANDARD_MODULE_HEADER,
    "elastic_phpbridge",                /* Extension name */
    phpbridge_functions,                /* zend_function_entry */
    PHP_MINIT(phpbridge),               /* PHP_MINIT - Module initialization */
    PHP_MSHUTDOWN(phpbridge),           /* PHP_MSHUTDOWN - Module shutdown */
    PHP_RINIT(phpbridge),               /* PHP_RINIT - Request initialization */
    PHP_RSHUTDOWN(phpbridge),           /* PHP_RSHUTDOWN - Request shutdown */
    PHP_MINFO(phpbridge),               /* PHP_MINFO - Module info */
    PHP_ELASTIC_APM_VERSION,            /* Version */
    PHP_MODULE_GLOBALS(phpbridge),      /* PHP_MODULE_GLOBALS */
    PHP_GINIT(phpbridge),               /* PHP_GINIT */
    PHP_GSHUTDOWN(phpbridge),           /* PHP_GSHUTDOWN */
    PhpBridgePostDeactivate,            /* post deactivate */
    STANDARD_MODULE_PROPERTIES_EX
};

#   ifdef ZTS
ZEND_TSRMLS_CACHE_DEFINE()
#   endif
extern "C" ZEND_GET_MODULE(phpbridge)