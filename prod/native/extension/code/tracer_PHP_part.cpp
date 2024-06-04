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

#include "tracer_PHP_part.h"
#include "util_for_PHP.h"
#include "basic_macros.h"
#include "ModuleGlobals.h"
#include "LoggerInterface.h"

#define ELASTIC_APM_PHP_PART_FUNC_PREFIX "\\Elastic\\Apm\\Impl\\AutoInstrument\\PhpPartFacade::"
#define ELASTIC_APM_PHP_PART_BOOTSTRAP_FUNC ELASTIC_APM_PHP_PART_FUNC_PREFIX "bootstrap"
#define ELASTIC_APM_PHP_PART_SHUTDOWN_FUNC ELASTIC_APM_PHP_PART_FUNC_PREFIX "shutdown"
#define ELASTIC_APM_PHP_PART_INTERNAL_FUNC_CALL_PRE_HOOK_FUNC ELASTIC_APM_PHP_PART_FUNC_PREFIX "internalFuncCallPreHook"
#define ELASTIC_APM_PHP_PART_INTERNAL_FUNC_CALL_POST_HOOK_FUNC ELASTIC_APM_PHP_PART_FUNC_PREFIX "internalFuncCallPostHook"
#define ELASTIC_APM_PHP_PART_EMPTY_METHOD_FUNC ELASTIC_APM_PHP_PART_FUNC_PREFIX "emptyMethod"

static uint32_t g_maxInterceptedCallArgsCount = 100;

bool tracerPhpPartInternalFuncCallPreHook(uint32_t interceptRegistrationId, zend_execute_data *execute_data) {
    // ELOG_TRACE_FUNCTION_ENTRY_MSG( "interceptRegistrationId: %u", interceptRegistrationId );

    zval preHookRetVal;
    ZVAL_UNDEF(&preHookRetVal);
    bool shouldCallPostHook = false;
    zval interceptRegistrationIdAsZval;
    ZVAL_UNDEF(&interceptRegistrationIdAsZval);
    zval phpPartArgs[g_maxInterceptedCallArgsCount + 2];


    // The first argument to PHP part's interceptedCallPreHook() is $interceptRegistrationId
    ZVAL_LONG(&interceptRegistrationIdAsZval, interceptRegistrationId);
    phpPartArgs[0] = interceptRegistrationIdAsZval;

    // The second argument to PHP part's interceptedCallPreHook() is $thisObj
    if (Z_TYPE(execute_data->This) == IS_UNDEF) {
        ZVAL_NULL(&phpPartArgs[1]);
    } else {
        phpPartArgs[1] = execute_data->This;
    }

    uint32_t interceptedCallArgsCount;
    getArgsFromZendExecuteData(execute_data, g_maxInterceptedCallArgsCount, &(phpPartArgs[2]), &interceptedCallArgsCount);
    if (!callPhpFunctionRetZval((ELASTIC_APM_PHP_PART_INTERNAL_FUNC_CALL_PRE_HOOK_FUNC), interceptedCallArgsCount + 2, phpPartArgs, /* out */ &preHookRetVal)) {
        zval_dtor(&interceptRegistrationIdAsZval);
        return false;
    }
    ELOG_TRACE(EAPM_GL(logger_), "Successfully finished call to PHP part. Return value type: %u", Z_TYPE_P(&preHookRetVal));

    if (Z_TYPE(preHookRetVal) != IS_FALSE && Z_TYPE(preHookRetVal) != IS_TRUE) {
        ELOG_ERROR(EAPM_GL(logger_), "Call to PHP part returned value that is not bool. Return value type: %u", Z_TYPE_P(&preHookRetVal));
        zval_dtor(&interceptRegistrationIdAsZval);
        return false;
    }
    shouldCallPostHook = (Z_TYPE(preHookRetVal) == IS_TRUE);

    zval_dtor(&interceptRegistrationIdAsZval);

    return shouldCallPostHook;
}

void tracerPhpPartInternalFuncCallPostHook(uint32_t dbgInterceptRegistrationId, zval *interceptedCallRetValOrThrown) {
    // ELOG_TRACE_FUNCTION_ENTRY_MSG( "dbgInterceptRegistrationId: %u; interceptedCallRetValOrThrown type: %u"
    //   , dbgInterceptRegistrationId, Z_TYPE_P( interceptedCallRetValOrThrown ) );

    zval phpPartArgs[2];


    // The first argument to PHP part's interceptedCallPostHook() is $hasExitedByException (bool)
    ZVAL_FALSE(&(phpPartArgs[0]));

    // The second argument to PHP part's interceptedCallPreHook() is $returnValueOrThrown (mixed|Throwable)
    phpPartArgs[1] = *interceptedCallRetValOrThrown;

    if (callPhpFunctionRetVoid((ELASTIC_APM_PHP_PART_INTERNAL_FUNC_CALL_POST_HOOK_FUNC), ELASTIC_APM_STATIC_ARRAY_SIZE(phpPartArgs), phpPartArgs)) {
        ELOG_TRACE(EAPM_GL(logger_), "Successfully finished call to PHP part");
    }

    // ELOG_TRACE_RESULT_CODE_FUNCTION_EXIT_MSG( "dbgInterceptRegistrationId: %u; interceptedCallRetValOrThrown type: %u."
    //  , dbgInterceptRegistrationId, Z_TYPE_P( interceptedCallRetValOrThrown ) );
}

void tracerPhpPartInterceptedCallEmptyMethod() {
    zval phpPartDummyArgs[1];
    ZVAL_UNDEF(&(phpPartDummyArgs[0]));



    if (callPhpFunctionRetVoid((ELASTIC_APM_PHP_PART_EMPTY_METHOD_FUNC), 0 /* <- argsCount */
                               ,
                               phpPartDummyArgs)) {
        ELOG_TRACE(EAPM_GL(logger_), "Successfully finished call to PHP part");
    }
}

void tracerPhpPartForwardCall(std::string_view phpFuncName, zend_execute_data *execute_data, /* out */ zval *retVal, const char *dbgCalledFrom) {
    ZVAL_NULL(retVal);
    uint32_t callArgsCount;
    zval callArgs[g_maxInterceptedCallArgsCount];


    getArgsFromZendExecuteData(execute_data, g_maxInterceptedCallArgsCount, &(callArgs[0]), &callArgsCount);
    // tracerPhpPartLogArguments( logLevel_trace, callArgsCount, callArgs );

    if (!callPhpFunctionRetZval(phpFuncName, callArgsCount, callArgs, /* out */ retVal)) {
        ZVAL_NULL(retVal);
    }
}