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

#include "util_for_PHP.h"
#include <stdio.h>
#include <php_main.h>
#include <zend_hash.h>
#include <zend_compile.h>

void getArgsFromZendExecuteData( zend_execute_data* execute_data, size_t dstArraySize, zval dstArray[], uint32_t* argsCount )
{
    *argsCount = ZEND_CALL_NUM_ARGS( execute_data );
    ZEND_PARSE_PARAMETERS_START( /* min_num_args: */ 0, /* max_num_args: */ ( (int) dstArraySize ) )
    Z_PARAM_OPTIONAL
    ELASTIC_APM_FOR_EACH_INDEX( i, *argsCount )
    {
        zval* pArgAsZval;
        Z_PARAM_ZVAL( pArgAsZval )
        dstArray[ i ] = *pArgAsZval;
    }
    ZEND_PARSE_PARAMETERS_END();
}

typedef void (* ConsumeZvalFunc)( void* ctx, const zval* pZval );

bool callPhpFunction( std::string_view phpFunctionName, uint32_t argsCount, zval args[], ConsumeZvalFunc consumeRetVal, void* consumeRetValCtx ) {
    // ELASTIC_APM_LOG_DEBUG_FUNCTION_ENTRY_MSG( "phpFunctionName: `%.*s', argsCount: %u"
    //                                           , (int) phpFunctionName.length(), phpFunctionName.data(), argsCount );

    zval phpFunctionNameAsZval;
    ZVAL_UNDEF( &phpFunctionNameAsZval );
    zval phpFunctionRetVal;
    ZVAL_UNDEF( &phpFunctionRetVal );

    ZVAL_STRINGL( &phpFunctionNameAsZval, phpFunctionName.data(), phpFunctionName.length() );
    // call_user_function(function_table, object, function_name, retval_ptr, param_count, params)
    int callUserFunctionRetVal = call_user_function(
            EG( function_table )
            , /* object: */ NULL
            , /* function_name: */ &phpFunctionNameAsZval
            , /* retval_ptr: */ &phpFunctionRetVal
            , argsCount
            , args );
    if ( callUserFunctionRetVal != SUCCESS )
    {
        // ELASTIC_APM_LOG_ERROR( "call_user_function failed. Return value: %d. PHP function name: `%.*s'. argsCount: %u."
        //         , callUserFunctionRetVal, (int) phpFunctionName.length(), phpFunctionName.data(), argsCount );
    zval_dtor( &phpFunctionNameAsZval );
    zval_dtor( &phpFunctionRetVal );

        return false;
    }

    if ( consumeRetVal != NULL ) consumeRetVal( consumeRetValCtx, &phpFunctionRetVal );


    zval_dtor( &phpFunctionNameAsZval );
    zval_dtor( &phpFunctionRetVal );
    return true;
}

static
void consumeBoolRetVal( void* ctx, const zval* pZval )
{

    if ( Z_TYPE_P( pZval ) == IS_TRUE )
    {
        *((bool*)ctx) = true;
    }
    else
    {
        // ELASTIC_APM_ASSERT( Z_TYPE_P( pZval ) == IS_FALSE, "Z_TYPE_P( pZval ) as int: %d", (int) ( Z_TYPE_P( pZval ) ) );
        *((bool*)ctx) = false;
    }
}

bool callPhpFunctionRetBool( std::string_view phpFunctionName, uint32_t argsCount, zval args[], bool* retVal )
{
    return callPhpFunction( phpFunctionName, argsCount, args, consumeBoolRetVal, /* consumeRetValCtx: */ retVal );
}

bool callPhpFunctionRetVoid( std::string_view phpFunctionName, uint32_t argsCount, zval args[] )
{
    return callPhpFunction( phpFunctionName, argsCount, args, /* consumeRetValCtx: */ NULL, /* consumeRetValCtx: */ NULL );
}

static
void consumeZvalRetVal( void* ctx, const zval* pZval )
{

    ZVAL_COPY( ((zval*)ctx), pZval );
}

bool callPhpFunctionRetZval( std::string_view phpFunctionName, uint32_t argsCount, zval args[], zval* retVal )
{
    return callPhpFunction( phpFunctionName, argsCount, args, consumeZvalRetVal, retVal );
}


int call_internal_function(zval *object, const char *functionName, zval parameters[], int32_t parametersCount, zval *returnValue) {
	zval funcName;
	ZVAL_STRING(&funcName, functionName);

	int result = ZEND_RESULT_CODE::FAILURE;
	zend_try {
#if PHP_VERSION_ID >= 80000
		result = _call_user_function_impl(object, &funcName, returnValue, parametersCount, parameters, NULL);
#else
		result = _call_user_function_ex(object, &funcName, returnValue, parametersCount, parameters, 0);
#endif
	} zend_catch {
        // ELASTIC_APM_LOG_ERROR("Call of '%s' failed", functionName);
	} zend_end_try();

	zval_ptr_dtor(&funcName);
	return result;
}

