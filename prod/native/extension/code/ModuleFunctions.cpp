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


#include "ModuleFunctions.h"
#include "ConfigurationStorage.h"
#include "LoggerInterface.h"
#include "LogFeature.h"
#include "RequestScope.h"
#include "ModuleGlobals.h"
#include "ModuleFunctionsImpl.h"
#include "InternalFunctionInstrumentation.h"
#include "transport/HttpTransportAsync.h"
#include "PhpBridge.h"
#include "OtlpExporter/LogsConverter.h"
#include "OtlpExporter/MetricConverter.h"
#include "OtlpExporter/SpanConverter.h"

#include <main/php.h>
#include <Zend/zend_API.h>
#include <Zend/zend_closures.h>
#include <Zend/zend_exceptions.h>

// bool elastic_otel_is_enabled()
PHP_FUNCTION(elastic_otel_is_enabled) {
    RETVAL_BOOL(false);
    ZEND_PARSE_PARAMETERS_NONE();

    RETVAL_BOOL(EAPM_CFG(enabled));
}

ZEND_BEGIN_ARG_INFO_EX(elastic_otel_get_config_option_by_name_arginfo, 0, 0, 1)
ZEND_ARG_TYPE_INFO(/* pass_by_ref: */ 0, optionName, IS_STRING, /* allow_null: */ 0)
ZEND_END_ARG_INFO()

/* elastic_otel_get_config_option_by_name( string $optionName ): mixed */
PHP_FUNCTION(elastic_otel_get_config_option_by_name) {
    ZVAL_NULL(return_value);
    char *optionName = nullptr;
    size_t optionNameLength = 0;

    ZEND_PARSE_PARAMETERS_START(1, 1)
    Z_PARAM_STRING(optionName, optionNameLength)
    ZEND_PARSE_PARAMETERS_END();

    elasticApmGetConfigOption({optionName, optionNameLength}, /* out */ return_value);
}

ZEND_BEGIN_ARG_INFO_EX(elastic_otel_log_feature_arginfo, /* _unused: */ 0, /* return_reference: */ 0, /* required_num_args: */ 7)
ZEND_ARG_TYPE_INFO(/* pass_by_ref: */ 0, isForced, IS_LONG, /* allow_null: */ 0)
ZEND_ARG_TYPE_INFO(/* pass_by_ref: */ 0, level, IS_LONG, /* allow_null: */ 0)
ZEND_ARG_TYPE_INFO(/* pass_by_ref: */ 0, feature, IS_LONG, /* allow_null: */ 0)
ZEND_ARG_TYPE_INFO(/* pass_by_ref: */ 0, file, IS_STRING, /* allow_null: */ 0)
ZEND_ARG_TYPE_INFO(/* pass_by_ref: */ 0, line, IS_LONG, /* allow_null: */ 1)
ZEND_ARG_TYPE_INFO(/* pass_by_ref: */ 0, func, IS_STRING, /* allow_null: */ 0)
ZEND_ARG_TYPE_INFO(/* pass_by_ref: */ 0, message, IS_STRING, /* allow_null: */ 0)
ZEND_END_ARG_INFO()

/* {{{ elastic_otel_log_feature(
 *      int $isForced,
 *      int $level,
 *      int $feature,
 *      string $file,
 *      ?int $line,
 *      string $func,
 *      string $message
 *  ): void
 */
PHP_FUNCTION(elastic_otel_log_feature) {
    zend_long isForced = 0;
    zend_long level = 0;
    zend_long feature = 0;
    char *file = nullptr;
    size_t fileLength = 0;
    zend_long line = 0;
    bool lineNull = true;
    char *func = nullptr;
    size_t funcLength = 0;
    char *message = nullptr;
    size_t messageLength = 0;

    ZEND_PARSE_PARAMETERS_START(/* min_num_args: */ 7, /* max_num_args: */ 7)
    Z_PARAM_LONG(isForced)
    Z_PARAM_LONG(level)
    Z_PARAM_LONG(feature)
    Z_PARAM_STRING(file, fileLength)
    Z_PARAM_LONG_OR_NULL(line, lineNull)
    Z_PARAM_STRING(func, funcLength)
    Z_PARAM_STRING(message, messageLength)
    ZEND_PARSE_PARAMETERS_END();

    if (isForced || ELASTICAPM_G(globals)->logger_->doesFeatureMeetsLevelCondition(static_cast<LogLevel>(level), static_cast<elasticapm::php::LogFeature>(feature))) {
        if (lineNull) {
            ELASTICAPM_G(globals)->logger_->printf(static_cast<LogLevel>(level), "[" PRsv "] [" PRsv "] [" PRsv "] " PRsv, PRsvArg(elasticapm::php::getLogFeatureName(static_cast<elasticapm::php::LogFeature>(feature))), PRcsvArg(file, fileLength), PRcsvArg(func, funcLength), PRcsvArg(message, messageLength));
            return;
        }
        ELASTICAPM_G(globals)->logger_->printf(static_cast<LogLevel>(level), "[" PRsv "] [" PRsv ":%d] [" PRsv "] " PRsv, PRsvArg(elasticapm::php::getLogFeatureName(static_cast<elasticapm::php::LogFeature>(feature))), PRcsvArg(file, fileLength), line, PRcsvArg(func, funcLength), PRcsvArg(message, messageLength));
    }
}
/* }}} */

ZEND_BEGIN_ARG_INFO_EX(elastic_otel_get_last_thrown_arginfo, /* _unused */ 0, /* return_reference: */ 0, /* required_num_args: */ 0)
ZEND_END_ARG_INFO()
/* {{{ elastic_otel_get_last_thrown(): mixed
 */
PHP_FUNCTION(elastic_otel_get_last_thrown) {
    ZVAL_NULL(/* out */ return_value);
    //TODO elasticApmGetLastThrown(/* out */ return_value);
}
/* }}} */

ZEND_BEGIN_ARG_INFO_EX(elastic_otel_get_last_php_error_arginfo, /* _unused */ 0, /* return_reference: */ 0, /* required_num_args: */ 0)
ZEND_END_ARG_INFO()
/* {{{ elastic_otel_get_last_error(): array
 */
PHP_FUNCTION(elastic_otel_get_last_php_error) {
    ZVAL_NULL(/* out */ return_value);

    //TODO elasticApmGetLastPhpError(/* out */ return_value);
}
/* }}} */

ZEND_BEGIN_ARG_INFO(elastic_otel_no_paramters_arginfo, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(elastic_otel_hook_arginfo, 0, 2, _IS_BOOL, 0)
ZEND_ARG_TYPE_INFO(0, class, IS_STRING, 1)
ZEND_ARG_TYPE_INFO(0, function, IS_STRING, 0)
ZEND_ARG_OBJ_INFO_WITH_DEFAULT_VALUE(0, pre, Closure, 1, "null")
ZEND_ARG_OBJ_INFO_WITH_DEFAULT_VALUE(0, post, Closure, 1, "null")
ZEND_END_ARG_INFO()

PHP_FUNCTION(elastic_otel_hook) {
    zend_string *class_name = nullptr;
    zend_string *function_name = nullptr;
    zval *pre = NULL;
    zval *post = NULL;

    ZEND_PARSE_PARAMETERS_START(2, 4)
    Z_PARAM_STR_OR_NULL(class_name)
    Z_PARAM_STR(function_name)
    Z_PARAM_OPTIONAL
    Z_PARAM_OBJECT_OF_CLASS_OR_NULL(pre, zend_ce_closure)
    Z_PARAM_OBJECT_OF_CLASS_OR_NULL(post, zend_ce_closure)
    ZEND_PARSE_PARAMETERS_END();

    std::string_view className = class_name ? std::string_view{ZSTR_VAL(class_name), ZSTR_LEN(class_name)} : std::string_view{};
    std::string_view functionName = function_name ? std::string_view{ZSTR_VAL(function_name), ZSTR_LEN(function_name)} : std::string_view{};

    // if (!EAPM_GL(requestScope_)->isFunctional()) {
    //     ELOGF_DEBUG(EAPM_GL(logger_), MODULE, "elastic_otel_hook. Can't instrument " PRsv "::" PRsv " beacuse agent is not functional.", PRsvArg(className), PRsvArg(functionName));
    //     RETURN_BOOL(false);
    //     return;
    // }

    RETURN_BOOL(elasticapm::php::instrumentFunction(EAPM_GL(logger_).get(), className, functionName, pre, post));
}

ZEND_BEGIN_ARG_INFO_EX(ArgInfoInitialize, 0, 0, 3)
ZEND_ARG_TYPE_INFO(0, endpoint, IS_STRING, 1)
ZEND_ARG_TYPE_INFO(0, contentType, IS_STRING, 0)
ZEND_ARG_TYPE_INFO(0, headers, IS_ARRAY, 0)
ZEND_END_ARG_INFO()

PHP_FUNCTION(initialize) {
    zend_string *endpoint;
    zend_string *contentType;
    zval *headers;

    double timeout = 0.0; // s
    long retryDelay = 0;  // ms
    long maxRetries = 0;

    ZEND_PARSE_PARAMETERS_START(6, 6)
    Z_PARAM_STR(endpoint)
    Z_PARAM_STR(contentType)
    Z_PARAM_ARRAY(headers)
    Z_PARAM_DOUBLE(timeout)
    Z_PARAM_LONG(retryDelay)
    Z_PARAM_LONG(maxRetries)
    ZEND_PARSE_PARAMETERS_END();

    HashTable *ht = Z_ARRVAL_P(headers);

    zval *value = nullptr;
    zend_string *arrkey = nullptr;

    std::vector<std::pair<std::string_view, std::string_view>> endpointHeaders;

    ZEND_HASH_FOREACH_STR_KEY_VAL(ht, arrkey, value) {
        if (value && Z_TYPE_P(value) == IS_STRING) {
            endpointHeaders.emplace_back(std::make_pair(std::string_view(ZSTR_VAL(arrkey), ZSTR_LEN(arrkey)), std::string_view(Z_STRVAL_P(value), Z_STRLEN_P(value))));
        }
    }
    ZEND_HASH_FOREACH_END();

    EAPM_GL(httpTransportAsync_)->initializeConnection(std::string(ZSTR_VAL(endpoint), ZSTR_LEN(endpoint)), ZSTR_HASH(endpoint), std::string(ZSTR_VAL(contentType), ZSTR_LEN(contentType)), endpointHeaders, std::chrono::duration_cast<std::chrono::milliseconds>(std::chrono::duration<double>(timeout)), static_cast<std::size_t>(maxRetries), std::chrono::milliseconds(retryDelay));
}

ZEND_BEGIN_ARG_INFO_EX(ArgInfoSend, 0, 0, 2)
ZEND_ARG_TYPE_INFO(0, endpoint, IS_STRING, 1)
ZEND_ARG_TYPE_INFO(0, payload, IS_STRING, 1)
ZEND_END_ARG_INFO()

PHP_FUNCTION(enqueue) {
    zend_string *payload = nullptr;
    zend_string *endpoint = nullptr;
    ZEND_PARSE_PARAMETERS_START(2, 2)
    Z_PARAM_STR(endpoint)
    Z_PARAM_STR(payload)
    ZEND_PARSE_PARAMETERS_END();

    EAPM_GL(httpTransportAsync_)->enqueue(ZSTR_HASH(endpoint), std::span<std::byte>(reinterpret_cast<std::byte *>(ZSTR_VAL(payload)), ZSTR_LEN(payload)));
}

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(elastic_otel_force_set_object_property_value_arginfo, 0, 3, _IS_BOOL, 0)
ZEND_ARG_TYPE_INFO(0, object, IS_OBJECT, 0)
ZEND_ARG_TYPE_INFO(0, property_name, IS_STRING, 0)
ZEND_ARG_TYPE_INFO(0, value, IS_MIXED, 0)
ZEND_END_ARG_INFO()

PHP_FUNCTION(force_set_object_property_value) {
    zend_object *object = nullptr;
    zend_string *property_name = nullptr;
    zval *value = nullptr;

    ZEND_PARSE_PARAMETERS_START(3, 3)
    Z_PARAM_OBJ(object)
    Z_PARAM_STR(property_name)
    Z_PARAM_ZVAL(value)
    ZEND_PARSE_PARAMETERS_END();

    RETURN_BOOL(elasticapm::php::forceSetObjectPropertyValue(object, property_name, value));
}

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_elastic_convert_spans, 0, 1, IS_STRING, 0)
ZEND_ARG_TYPE_INFO(0, batch, IS_ITERABLE, 0)
ZEND_END_ARG_INFO()

PHP_FUNCTION(convert_spans) {
    zval *batch;

    ZEND_PARSE_PARAMETERS_START(1, 1)
    Z_PARAM_ZVAL(batch)
    ZEND_PARSE_PARAMETERS_END();

    try {
        elasticapm::php::SpanConverter converter;
        auto res = converter.getStringSerialized(elasticapm::php::AutoZval(batch));
        RETURN_STRINGL(res.c_str(), res.length());
    } catch (std::exception const &e) {
        ELOGF_WARNING(EAPM_GL(logger_).get(), OTLPEXPORT, "Failed to serialize spans batch: '%s'", e.what());
        zend_throw_exception_ex(NULL, 0, "Failed to serialize spans batch: '%s'", e.what());
        RETURN_THROWS();
    }
}

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_elastic_convert_logs, 0, 1, IS_STRING, 0)
ZEND_ARG_TYPE_INFO(0, batch, IS_ITERABLE, 0)
ZEND_END_ARG_INFO()

PHP_FUNCTION(convert_logs) {
    zval *batch;

    ZEND_PARSE_PARAMETERS_START(1, 1)
    Z_PARAM_ZVAL(batch)
    ZEND_PARSE_PARAMETERS_END();

    try {
        elasticapm::php::LogsConverter converter;
        auto res = converter.getStringSerialized(elasticapm::php::AutoZval(batch));
        RETURN_STRINGL(res.c_str(), res.length());
    } catch (std::exception const &e) {
        ELOGF_WARNING(EAPM_GL(logger_).get(), OTLPEXPORT, "Failed to serialize logs batch: '%s'", e.what());
        zend_throw_exception_ex(NULL, 0, "Failed to serialize logs batch: '%s'", e.what());
        RETURN_THROWS();
    }
}

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_elastic_convert_metrics, 0, 1, IS_STRING, 0)
ZEND_ARG_TYPE_INFO(0, batch, IS_ITERABLE, 0)
ZEND_END_ARG_INFO()

PHP_FUNCTION(convert_metrics) {
    zval *batch;

    ZEND_PARSE_PARAMETERS_START(1, 1)
    Z_PARAM_ZVAL(batch)
    ZEND_PARSE_PARAMETERS_END();

    try {
        elasticapm::php::MetricConverter converter;
        auto res = converter.getStringSerialized(elasticapm::php::AutoZval(batch));
        RETURN_STRINGL(res.c_str(), res.length());
    } catch (std::exception const &e) {
        ELOGF_WARNING(EAPM_GL(logger_).get(), OTLPEXPORT, "Failed to serialize metrics batch: '%s'", e.what());
        zend_throw_exception_ex(NULL, 0, "Failed to serialize metrics batch: '%s'", e.what());
        RETURN_THROWS();
    }
}

// clang-format off
const zend_function_entry elastic_otel_functions[] = {
    PHP_FE( elastic_otel_is_enabled, elastic_otel_no_paramters_arginfo )
    PHP_FE( elastic_otel_get_config_option_by_name, elastic_otel_get_config_option_by_name_arginfo )
    PHP_FE( elastic_otel_log_feature, elastic_otel_log_feature_arginfo )
    PHP_FE( elastic_otel_get_last_thrown, elastic_otel_get_last_thrown_arginfo )
    PHP_FE( elastic_otel_get_last_php_error, elastic_otel_get_last_php_error_arginfo )
    PHP_FE( elastic_otel_hook, elastic_otel_hook_arginfo )

    ZEND_NS_FE( "Elastic\\OTel\\HttpTransport", initialize, ArgInfoInitialize)
    ZEND_NS_FE( "Elastic\\OTel\\HttpTransport", enqueue, elastic_otel_no_paramters_arginfo)
    ZEND_NS_FE( "Elastic\\OTel\\InferredSpans", force_set_object_property_value, elastic_otel_force_set_object_property_value_arginfo)

    ZEND_NS_FE( "Elastic\\OTel\\OtlpExporters", convert_spans, arginfo_elastic_convert_spans)
    ZEND_NS_FE( "Elastic\\OTel\\OtlpExporters", convert_logs, arginfo_elastic_convert_logs)
    ZEND_NS_FE( "Elastic\\OTel\\OtlpExporters", convert_metrics, arginfo_elastic_convert_metrics)


    PHP_FE_END
};
// clang-format on
