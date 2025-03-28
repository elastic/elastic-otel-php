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

#include <main/php.h>
#include <Zend/zend_API.h>
#include <Zend/zend_closures.h>
#include <Zend/zend_interfaces.h>
#include <Zend/zend_exceptions.h>
#include <ext/standard/php_var.h>
#include <Zend/zend_smart_str.h>

#include "opentelemetry/proto/trace/v1/trace.pb.h"
#include "opentelemetry/proto/collector/trace/v1/trace_service.pb.h"

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

zend_class_entry *span_exporter_ce = NULL;
zend_class_entry *span_exporter_iface_ce = NULL;
zend_class_entry *cancellation_iface_ce = NULL;

PHP_METHOD(SpanExporter, __construct) {
    zval *transport;

    if (zend_parse_parameters(ZEND_NUM_ARGS(), "z", &transport) == FAILURE) {
        RETURN_THROWS();
    }
    zend_update_property(span_exporter_ce, Z_OBJ_P(ZEND_THIS), "transport", sizeof("transport") - 1, transport);
}

PHP_METHOD(SpanExporter, shutdown) {
    zval *cancellation = NULL;
    zval *transport;
    zval retval;

    if (zend_parse_parameters(ZEND_NUM_ARGS(), "|O!", &cancellation, cancellation_iface_ce) == FAILURE) {
        RETURN_THROWS();
    }

    transport = zend_read_property(span_exporter_ce, Z_OBJ_P(ZEND_THIS), "transport", sizeof("transport") - 1, 0, NULL);

    if (!transport || Z_TYPE_P(transport) != IS_OBJECT) {
        zend_throw_exception(NULL, "Invalid transport", 0);
        RETURN_THROWS();
    }

    zend_call_method(Z_OBJ_P(transport), Z_OBJCE_P(transport), NULL, "shutdown", strlen("shutdown"), &retval, 1, cancellation ? cancellation : &EG(uninitialized_zval), NULL);
    RETURN_ZVAL(&retval, 1, 1);
}

PHP_METHOD(SpanExporter, forceFlush) {
    zval *cancellation = NULL;
    zval *transport;
    zval retval;

    if (zend_parse_parameters(ZEND_NUM_ARGS(), "|O!", &cancellation, cancellation_iface_ce) == FAILURE) {
        RETURN_THROWS();
    }

    transport = zend_read_property(span_exporter_ce, Z_OBJ_P(ZEND_THIS), "transport", sizeof("transport") - 1, 0, NULL);

    if (!transport || Z_TYPE_P(transport) != IS_OBJECT) {
        zend_throw_exception(NULL, "Invalid transport", 0);
        RETURN_THROWS();
    }

    zend_call_method(Z_OBJ_P(transport), Z_OBJCE_P(transport), NULL, "forceFlush", strlen("forceFlush"), &retval, 1, cancellation ? cancellation : &EG(uninitialized_zval), NULL);
    RETURN_ZVAL(&retval, 1, 1);
}

std::string php_serialize_zval(zval *zv) {
    zval retval;
    zval fname;
    ZVAL_STRING(&fname, "serialize");

    // serialize($zv)
    if (call_user_function(EG(function_table), nullptr, &fname, &retval, 1, zv) != SUCCESS || Z_TYPE(retval) != IS_STRING) {
        zval_ptr_dtor(&fname);
        return {}; // empty string if serialization fails
    }

    std::string result(Z_STRVAL(retval), Z_STRLEN(retval));

    zval_ptr_dtor(&fname);
    zval_ptr_dtor(&retval);

    return result;
}

void convertAttributes(zval *attributes_zv, google::protobuf::RepeatedPtrField<opentelemetry::proto::common::v1::KeyValue> *out, uint32_t *dropped) {
    using opentelemetry::proto::common::v1::AnyValue;
    using opentelemetry::proto::common::v1::KeyValue;

    // getDroppedAttributesCount()
    zval dropped_ret, dropped_fn;
    ZVAL_STRING(&dropped_fn, "getDroppedAttributesCount");
    if (call_user_function(EG(function_table), attributes_zv, &dropped_fn, &dropped_ret, 0, nullptr) == SUCCESS && Z_TYPE(dropped_ret) == IS_LONG) {
        *dropped = Z_LVAL(dropped_ret);
    } else {
        *dropped = 0;
    }
    zval_ptr_dtor(&dropped_fn);
    zval_ptr_dtor(&dropped_ret);

    // toArray()
    zval array_ret, toarray_fn;
    ZVAL_STRING(&toarray_fn, "toArray");
    if (call_user_function(EG(function_table), attributes_zv, &toarray_fn, &array_ret, 0, nullptr) == SUCCESS && Z_TYPE(array_ret) == IS_ARRAY) {
        HashTable *ht = Z_ARRVAL(array_ret);
        zend_string *key;
        zval *val;

        ZEND_HASH_FOREACH_STR_KEY_VAL(ht, key, val) {
            if (!key)
                continue;

            KeyValue *kv = out->Add();
            kv->set_key(ZSTR_VAL(key));

            AnyValue *any = kv->mutable_value();
            switch (Z_TYPE_P(val)) {
                case IS_STRING:
                    any->set_string_value(Z_STRVAL_P(val));
                    break;
                case IS_LONG:
                    any->set_int_value(Z_LVAL_P(val));
                    break;
                case IS_DOUBLE:
                    any->set_double_value(Z_DVAL_P(val));
                    break;
                case IS_TRUE:
                case IS_FALSE:
                    any->set_bool_value(Z_TYPE_P(val) == IS_TRUE);
                    break;
                default:
                    break; // opcjonalnie log warning
            }
        }
        ZEND_HASH_FOREACH_END();
    }
    zval_ptr_dtor(&toarray_fn);
    zval_ptr_dtor(&array_ret);
}

void convertResourceSpans(zval *resource_zv, opentelemetry::proto::trace::v1::ResourceSpans *out) {
    // Set schema_url
    zval schema_ret;
    zval schema_fn;
    ZVAL_STRING(&schema_fn, "getSchemaUrl");
    if (call_user_function(EG(function_table), resource_zv, &schema_fn, &schema_ret, 0, nullptr) == SUCCESS && Z_TYPE(schema_ret) == IS_STRING) {
        out->set_schema_url(Z_STRVAL(schema_ret), Z_STRLEN(schema_ret));
    }
    zval_ptr_dtor(&schema_fn);
    zval_ptr_dtor(&schema_ret);

    // Get attributes
    zval attr_ret;
    zval attr_fn;
    ZVAL_STRING(&attr_fn, "getAttributes");
    if (call_user_function(EG(function_table), resource_zv, &attr_fn, &attr_ret, 0, nullptr) == SUCCESS && Z_TYPE(attr_ret) == IS_OBJECT) {
        opentelemetry::proto::resource::v1::Resource *resource = out->mutable_resource();
        uint32_t dropped = 0;
        convertAttributes(&attr_ret, resource->mutable_attributes(), &dropped);
        resource->set_dropped_attributes_count(dropped);
    }
    zval_ptr_dtor(&attr_fn);
    zval_ptr_dtor(&attr_ret);
}

void convertScopeSpans(zval *scope_zv, opentelemetry::proto::trace::v1::ScopeSpans *out) {
    using opentelemetry::proto::common::v1::InstrumentationScope;

    InstrumentationScope *scope = out->mutable_scope();

    // getName()
    zval name_ret, name_fn;
    ZVAL_STRING(&name_fn, "getName");
    if (call_user_function(EG(function_table), scope_zv, &name_fn, &name_ret, 0, nullptr) == SUCCESS && Z_TYPE(name_ret) == IS_STRING) {
        scope->set_name(Z_STRVAL(name_ret), Z_STRLEN(name_ret));
    }
    zval_ptr_dtor(&name_fn);
    zval_ptr_dtor(&name_ret);

    // getVersion()
    zval version_ret, version_fn;
    ZVAL_STRING(&version_fn, "getVersion");
    if (call_user_function(EG(function_table), scope_zv, &version_fn, &version_ret, 0, nullptr) == SUCCESS && Z_TYPE(version_ret) == IS_STRING) {
        scope->set_version(Z_STRVAL(version_ret), Z_STRLEN(version_ret));
    }
    zval_ptr_dtor(&version_fn);
    zval_ptr_dtor(&version_ret);

    // getAttributes()
    zval attr_ret, attr_fn;
    ZVAL_STRING(&attr_fn, "getAttributes");
    if (call_user_function(EG(function_table), scope_zv, &attr_fn, &attr_ret, 0, nullptr) == SUCCESS && Z_TYPE(attr_ret) == IS_OBJECT) {
        google::protobuf::RepeatedPtrField<opentelemetry::proto::common::v1::KeyValue> *attr_field = scope->mutable_attributes();
        uint32_t dropped_count = 0;
        convertAttributes(&attr_ret, attr_field, &dropped_count);
        scope->set_dropped_attributes_count(dropped_count);
    }
    zval_ptr_dtor(&attr_fn);
    zval_ptr_dtor(&attr_ret);

    // getSchemaUrl()
    zval schema_ret, schema_fn;
    ZVAL_STRING(&schema_fn, "getSchemaUrl");
    if (call_user_function(EG(function_table), scope_zv, &schema_fn, &schema_ret, 0, nullptr) == SUCCESS && Z_TYPE(schema_ret) == IS_STRING) {
        out->set_schema_url(Z_STRVAL(schema_ret), Z_STRLEN(schema_ret));
    }
    zval_ptr_dtor(&schema_fn);
    zval_ptr_dtor(&schema_ret);
}

#include "opentelemetry/proto/trace/v1/trace.pb.h"

void convertSpan(zval *span_zv, opentelemetry::proto::trace::v1::Span *out) {
    using namespace opentelemetry::proto::trace::v1;
    using opentelemetry::proto::trace::v1::Status;

    // --- getContext() ---
    zval context_ret, context_fn;
    ZVAL_STRING(&context_fn, "getContext");
    if (call_user_function(EG(function_table), span_zv, &context_fn, &context_ret, 0, nullptr) == SUCCESS && Z_TYPE(context_ret) == IS_OBJECT) {

        // trace_id
        zval trace_id_fn, trace_id_ret;
        ZVAL_STRING(&trace_id_fn, "getTraceIdBinary");
        if (call_user_function(EG(function_table), &context_ret, &trace_id_fn, &trace_id_ret, 0, nullptr) == SUCCESS && Z_TYPE(trace_id_ret) == IS_STRING) {
            out->set_trace_id(Z_STRVAL(trace_id_ret), Z_STRLEN(trace_id_ret));
        }
        zval_ptr_dtor(&trace_id_fn);
        zval_ptr_dtor(&trace_id_ret);

        // span_id
        zval span_id_fn, span_id_ret;
        ZVAL_STRING(&span_id_fn, "getSpanIdBinary");
        if (call_user_function(EG(function_table), &context_ret, &span_id_fn, &span_id_ret, 0, nullptr) == SUCCESS && Z_TYPE(span_id_ret) == IS_STRING) {
            out->set_span_id(Z_STRVAL(span_id_ret), Z_STRLEN(span_id_ret));
        }
        zval_ptr_dtor(&span_id_fn);
        zval_ptr_dtor(&span_id_ret);

        // trace_flags
        zval flags_fn, flags_ret;
        ZVAL_STRING(&flags_fn, "getTraceFlags");
        if (call_user_function(EG(function_table), &context_ret, &flags_fn, &flags_ret, 0, nullptr) == SUCCESS && Z_TYPE(flags_ret) == IS_LONG) {
            out->set_flags(Z_LVAL(flags_ret));
        }
        zval_ptr_dtor(&flags_fn);
        zval_ptr_dtor(&flags_ret);

        // trace_state
        zval trace_state_fn, trace_state_ret;
        ZVAL_STRING(&trace_state_fn, "getTraceState");
        if (call_user_function(EG(function_table), &context_ret, &trace_state_fn, &trace_state_ret, 0, nullptr) == SUCCESS && Z_TYPE(trace_state_ret) == IS_STRING) {
            out->set_trace_state(Z_STRVAL(trace_state_ret), Z_STRLEN(trace_state_ret));
        }
        zval_ptr_dtor(&trace_state_fn);
        zval_ptr_dtor(&trace_state_ret);

        zval_ptr_dtor(&context_ret);
    }
    zval_ptr_dtor(&context_fn);

    // --- getParentContext() ---
    zval parent_ctx_ret, parent_ctx_fn;
    ZVAL_STRING(&parent_ctx_fn, "getParentContext");
    if (call_user_function(EG(function_table), span_zv, &parent_ctx_fn, &parent_ctx_ret, 0, nullptr) == SUCCESS && Z_TYPE(parent_ctx_ret) == IS_OBJECT) {

        zval valid_fn, valid_ret;
        ZVAL_STRING(&valid_fn, "isValid");
        if (call_user_function(EG(function_table), &parent_ctx_ret, &valid_fn, &valid_ret, 0, nullptr) == SUCCESS && zend_is_true(&valid_ret)) {

            zval parent_span_id_fn, parent_span_id_ret;
            ZVAL_STRING(&parent_span_id_fn, "getSpanIdBinary");
            if (call_user_function(EG(function_table), &parent_ctx_ret, &parent_span_id_fn, &parent_span_id_ret, 0, nullptr) == SUCCESS && Z_TYPE(parent_span_id_ret) == IS_STRING) {
                out->set_parent_span_id(Z_STRVAL(parent_span_id_ret), Z_STRLEN(parent_span_id_ret));
            }
            zval_ptr_dtor(&parent_span_id_fn);
            zval_ptr_dtor(&parent_span_id_ret);
        }
        zval_ptr_dtor(&valid_fn);
        zval_ptr_dtor(&valid_ret);
        zval_ptr_dtor(&parent_ctx_ret);
    }
    zval_ptr_dtor(&parent_ctx_fn);

    // --- getName() ---
    zval name_ret, name_fn;
    ZVAL_STRING(&name_fn, "getName");
    if (call_user_function(EG(function_table), span_zv, &name_fn, &name_ret, 0, nullptr) == SUCCESS && Z_TYPE(name_ret) == IS_STRING) {
        out->set_name(Z_STRVAL(name_ret), Z_STRLEN(name_ret));
    }
    zval_ptr_dtor(&name_fn);
    zval_ptr_dtor(&name_ret);

    // --- getKind() ---
    zval kind_ret, kind_fn;
    ZVAL_STRING(&kind_fn, "getKind");
    if (call_user_function(EG(function_table), span_zv, &kind_fn, &kind_ret, 0, nullptr) == SUCCESS && Z_TYPE(kind_ret) == IS_LONG) {
        out->set_kind(static_cast<Span_SpanKind>(Z_LVAL(kind_ret)));
    }
    zval_ptr_dtor(&kind_fn);
    zval_ptr_dtor(&kind_ret);

    // --- getStartEpochNanos() ---
    zval start_ret, start_fn;
    ZVAL_STRING(&start_fn, "getStartEpochNanos");
    if (call_user_function(EG(function_table), span_zv, &start_fn, &start_ret, 0, nullptr) == SUCCESS && Z_TYPE(start_ret) == IS_LONG) {
        out->set_start_time_unix_nano(Z_LVAL(start_ret));
    }
    zval_ptr_dtor(&start_fn);
    zval_ptr_dtor(&start_ret);

    // --- getEndEpochNanos() ---
    zval end_ret, end_fn;
    ZVAL_STRING(&end_fn, "getEndEpochNanos");
    if (call_user_function(EG(function_table), span_zv, &end_fn, &end_ret, 0, nullptr) == SUCCESS && Z_TYPE(end_ret) == IS_LONG) {
        out->set_end_time_unix_nano(Z_LVAL(end_ret));
    }
    zval_ptr_dtor(&end_fn);
    zval_ptr_dtor(&end_ret);

    // --- getAttributes() ---
    zval attr_ret, attr_fn;
    ZVAL_STRING(&attr_fn, "getAttributes");
    if (call_user_function(EG(function_table), span_zv, &attr_fn, &attr_ret, 0, nullptr) == SUCCESS && Z_TYPE(attr_ret) == IS_OBJECT) {
        auto *field = out->mutable_attributes();
        uint32_t dropped = 0;
        convertAttributes(&attr_ret, field, &dropped);
        out->set_dropped_attributes_count(dropped);
    }
    zval_ptr_dtor(&attr_fn);
    zval_ptr_dtor(&attr_ret);

    // --- getEvents() ---
    zval events_ret, events_fn;
    ZVAL_STRING(&events_fn, "getEvents");
    if (call_user_function(EG(function_table), span_zv, &events_fn, &events_ret, 0, nullptr) == SUCCESS && Z_TYPE(events_ret) == IS_ARRAY) {
        HashTable *ht = Z_ARRVAL(events_ret);
        zval *zv_event;
        ZEND_HASH_FOREACH_VAL(ht, zv_event) {
            opentelemetry::proto::trace::v1::Span::Event *e = out->add_events();
            // time
            zval time_fn, time_ret;
            ZVAL_STRING(&time_fn, "getEpochNanos");
            if (call_user_function(EG(function_table), zv_event, &time_fn, &time_ret, 0, nullptr) == SUCCESS && Z_TYPE(time_ret) == IS_LONG) {
                e->set_time_unix_nano(Z_LVAL(time_ret));
            }
            zval_ptr_dtor(&time_fn);
            zval_ptr_dtor(&time_ret);

            // name
            zval name_fn, name_ret;
            ZVAL_STRING(&name_fn, "getName");
            if (call_user_function(EG(function_table), zv_event, &name_fn, &name_ret, 0, nullptr) == SUCCESS && Z_TYPE(name_ret) == IS_STRING) {
                e->set_name(Z_STRVAL(name_ret), Z_STRLEN(name_ret));
            }
            zval_ptr_dtor(&name_fn);
            zval_ptr_dtor(&name_ret);

            // attributes
            zval attr_fn, attr_ret;
            ZVAL_STRING(&attr_fn, "getAttributes");
            if (call_user_function(EG(function_table), zv_event, &attr_fn, &attr_ret, 0, nullptr) == SUCCESS && Z_TYPE(attr_ret) == IS_OBJECT) {
                uint32_t dropped = 0;
                convertAttributes(&attr_ret, e->mutable_attributes(), &dropped);
                e->set_dropped_attributes_count(dropped);
            }
            zval_ptr_dtor(&attr_fn);
            zval_ptr_dtor(&attr_ret);
        }
        ZEND_HASH_FOREACH_END();
    }
    zval_ptr_dtor(&events_fn);
    zval_ptr_dtor(&events_ret);

    // --- getLinks() ---
    zval links_ret, links_fn;
    ZVAL_STRING(&links_fn, "getLinks");
    if (call_user_function(EG(function_table), span_zv, &links_fn, &links_ret, 0, nullptr) == SUCCESS && Z_TYPE(links_ret) == IS_ARRAY) {
        HashTable *ht = Z_ARRVAL(links_ret);
        zval *zv_link;
        ZEND_HASH_FOREACH_VAL(ht, zv_link) {
            opentelemetry::proto::trace::v1::Span::Link *link = out->add_links();
            // spanContext
            zval ctx_fn, ctx_ret;
            ZVAL_STRING(&ctx_fn, "getSpanContext");
            if (call_user_function(EG(function_table), zv_link, &ctx_fn, &ctx_ret, 0, nullptr) == SUCCESS && Z_TYPE(ctx_ret) == IS_OBJECT) {
                // trace_id
                zval trace_id_fn, trace_id_ret;
                ZVAL_STRING(&trace_id_fn, "getTraceIdBinary");
                if (call_user_function(EG(function_table), &ctx_ret, &trace_id_fn, &trace_id_ret, 0, nullptr) == SUCCESS && Z_TYPE(trace_id_ret) == IS_STRING) {
                    link->set_trace_id(Z_STRVAL(trace_id_ret), Z_STRLEN(trace_id_ret));
                }
                zval_ptr_dtor(&trace_id_fn);
                zval_ptr_dtor(&trace_id_ret);

                // span_id
                zval span_id_fn, span_id_ret;
                ZVAL_STRING(&span_id_fn, "getSpanIdBinary");
                if (call_user_function(EG(function_table), &ctx_ret, &span_id_fn, &span_id_ret, 0, nullptr) == SUCCESS && Z_TYPE(span_id_ret) == IS_STRING) {
                    link->set_span_id(Z_STRVAL(span_id_ret), Z_STRLEN(span_id_ret));
                }
                zval_ptr_dtor(&span_id_fn);
                zval_ptr_dtor(&span_id_ret);

                // flags
                zval flags_fn, flags_ret;
                ZVAL_STRING(&flags_fn, "getTraceFlags");
                if (call_user_function(EG(function_table), &ctx_ret, &flags_fn, &flags_ret, 0, nullptr) == SUCCESS && Z_TYPE(flags_ret) == IS_LONG) {
                    link->set_flags(Z_LVAL(flags_ret));
                }
                zval_ptr_dtor(&flags_fn);
                zval_ptr_dtor(&flags_ret);

                // trace_state
                zval state_fn, state_ret;
                ZVAL_STRING(&state_fn, "getTraceState");
                if (call_user_function(EG(function_table), &ctx_ret, &state_fn, &state_ret, 0, nullptr) == SUCCESS && Z_TYPE(state_ret) == IS_STRING) {
                    link->set_trace_state(Z_STRVAL(state_ret), Z_STRLEN(state_ret));
                }
                zval_ptr_dtor(&state_fn);
                zval_ptr_dtor(&state_ret);
            }
            zval_ptr_dtor(&ctx_fn);
            zval_ptr_dtor(&ctx_ret);

            // attributes
            zval attr_fn, attr_ret;
            ZVAL_STRING(&attr_fn, "getAttributes");
            if (call_user_function(EG(function_table), zv_link, &attr_fn, &attr_ret, 0, nullptr) == SUCCESS && Z_TYPE(attr_ret) == IS_OBJECT) {
                uint32_t dropped = 0;
                convertAttributes(&attr_ret, link->mutable_attributes(), &dropped);
                link->set_dropped_attributes_count(dropped);
            }
            zval_ptr_dtor(&attr_fn);
            zval_ptr_dtor(&attr_ret);
        }
        ZEND_HASH_FOREACH_END();
    }
    zval_ptr_dtor(&links_fn);
    zval_ptr_dtor(&links_ret);

    // --- getStatus() ---
    zval status_fn, status_ret;
    ZVAL_STRING(&status_fn, "getStatus");
    if (call_user_function(EG(function_table), span_zv, &status_fn, &status_ret, 0, nullptr) == SUCCESS && Z_TYPE(status_ret) == IS_OBJECT) {
        Status *status = out->mutable_status();

        zval msg_fn, msg_ret;
        ZVAL_STRING(&msg_fn, "getDescription");
        if (call_user_function(EG(function_table), &status_ret, &msg_fn, &msg_ret, 0, nullptr) == SUCCESS && Z_TYPE(msg_ret) == IS_STRING) {
            status->set_message(Z_STRVAL(msg_ret), Z_STRLEN(msg_ret));
        }
        zval_ptr_dtor(&msg_fn);
        zval_ptr_dtor(&msg_ret);

        zval code_fn, code_ret;
        ZVAL_STRING(&code_fn, "getCode");
        if (call_user_function(EG(function_table), &status_ret, &code_fn, &code_ret, 0, nullptr) == SUCCESS && Z_TYPE(code_ret) == IS_LONG) {
            status->set_code(static_cast<Status_StatusCode>(Z_LVAL(code_ret)));
        }
        zval_ptr_dtor(&code_fn);
        zval_ptr_dtor(&code_ret);
    }
    zval_ptr_dtor(&status_fn);
    zval_ptr_dtor(&status_ret);
}

opentelemetry::proto::collector::trace::v1::ExportTraceServiceRequest convert(zval *spans_zval) {
    opentelemetry::proto::collector::trace::v1::ExportTraceServiceRequest request;

    std::unordered_map<std::string, opentelemetry::proto::trace::v1::ResourceSpans *> resourceSpansMap;
    std::unordered_map<std::string, opentelemetry::proto::trace::v1::ScopeSpans *> scopeSpansMap;

    // zval *span_zval;
    HashTable *ht = Z_TYPE_P(spans_zval) == IS_ARRAY ? Z_ARRVAL_P(spans_zval) : nullptr; // zend_get_traversable_hash_table(spans_zval);
    if (!ht) {
        php_error_docref(NULL, E_WARNING, "Invalid iterable passed to convert");
        return request;
    }

    zval *span_zv;
    ZEND_HASH_FOREACH_VAL(ht, span_zv) {
        zval resource_ret, scope_ret;

        // getResource()
        zval fname_resource;
        ZVAL_STRING(&fname_resource, "getResource");
        if (call_user_function(EG(function_table), span_zv, &fname_resource, &resource_ret, 0, nullptr) != SUCCESS) {
            zval_ptr_dtor(&fname_resource);
            continue;
        }
        zval_ptr_dtor(&fname_resource);

        // getInstrumentationScope()
        zval fname_scope;
        ZVAL_STRING(&fname_scope, "getInstrumentationScope");
        if (call_user_function(EG(function_table), span_zv, &fname_scope, &scope_ret, 0, nullptr) != SUCCESS) {
            zval_ptr_dtor(&fname_scope);
            zval_ptr_dtor(&resource_ret);
            continue;
        }
        zval_ptr_dtor(&fname_scope);

        std::string resourceId = php_serialize_zval(&resource_ret);
        std::string scopeId = php_serialize_zval(&scope_ret);

        opentelemetry::proto::trace::v1::ResourceSpans *resourceSpans;
        if (resourceSpansMap.count(resourceId) == 0) {
            resourceSpans = request.add_resource_spans();
            convertResourceSpans(&resource_ret, resourceSpans);
            resourceSpansMap[resourceId] = resourceSpans;
        } else {
            resourceSpans = resourceSpansMap[resourceId];
        }

        opentelemetry::proto::trace::v1::ScopeSpans *scopeSpans;
        std::string compositeKey = resourceId + "|" + scopeId;
        if (scopeSpansMap.count(compositeKey) == 0) {
            scopeSpans = resourceSpans->add_scope_spans();
            convertScopeSpans(&scope_ret, scopeSpans);
            scopeSpansMap[compositeKey] = scopeSpans;
        } else {
            scopeSpans = scopeSpansMap[compositeKey];
        }

        opentelemetry::proto::trace::v1::Span *span = scopeSpans->add_spans();
        convertSpan(span_zv, span);

        zval_ptr_dtor(&resource_ret);
        zval_ptr_dtor(&scope_ret);
    }
    ZEND_HASH_FOREACH_END();
    return request;
}

PHP_METHOD(SpanExporter, export) {

    zval *batch;
    zval *cancellation = NULL;

    ZEND_PARSE_PARAMETERS_START(1, 2)
    Z_PARAM_ZVAL(batch)
    Z_PARAM_OPTIONAL
    Z_PARAM_ZVAL(cancellation)
    ZEND_PARSE_PARAMETERS_END();

    zval *transport = zend_read_property(span_exporter_ce, Z_OBJ_P(ZEND_THIS), "transport", sizeof("transport") - 1, 0, NULL);
    if (!transport || Z_TYPE_P(transport) != IS_OBJECT) {
        zend_throw_exception(NULL, "Invalid transport", 0);
        RETURN_THROWS();
    }

    opentelemetry::proto::collector::trace::v1::ExportTraceServiceRequest request = convert(batch);

    std::string binary = request.SerializeAsString();

    zval arg;
    ZVAL_STRINGL(&arg, binary.data(), binary.size());

    // // Step 3: Pobierz $this->transport
    // zval *transport = zend_read_property(Z_OBJCE_P(getThis()), getThis(), "transport", sizeof("transport") - 1, 1, NULL);

    zval retval;
    // zval *params[2] = {&arg, cancellation ? cancellation : &EG(uninitialized_zval)};

    if (zend_call_method(Z_OBJ_P(transport), Z_OBJCE_P(transport), NULL, "send", strlen("send"), &retval, 2, &arg, cancellation ? cancellation : &EG(uninitialized_zval)) == NULL) {
        zend_throw_exception(NULL, "Failed to call send() on transport", 0);
        zval_ptr_dtor(&arg);
        RETURN_THROWS();
    }

    zval_ptr_dtor(&arg);

    RETURN_ZVAL(&retval, 1, 1);
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

    PHP_ME(SpanExporter, __construct, NULL, ZEND_ACC_PUBLIC)
    PHP_ME(SpanExporter, export, NULL, ZEND_ACC_PUBLIC)
    PHP_ME(SpanExporter, shutdown, NULL, ZEND_ACC_PUBLIC)
    PHP_ME(SpanExporter, forceFlush, NULL, ZEND_ACC_PUBLIC)

    PHP_FE_END
};
// clang-format on

void register_otel() {
    zend_class_entry ce;

    INIT_NS_CLASS_ENTRY(ce, "OpenTelemetry\\SDK\\Trace", "SpanExporterInterface", NULL);
    span_exporter_iface_ce = zend_register_internal_interface(&ce);

    INIT_NS_CLASS_ENTRY(ce, "OpenTelemetry\\SDK\\Trace", "CancellationInterface", NULL);
    cancellation_iface_ce = zend_register_internal_interface(&ce);

    INIT_NS_CLASS_ENTRY(ce, "OpenTelemetry\\Contrib\\Otlp", "SpanExporter", elastic_otel_functions);
    span_exporter_ce = zend_register_internal_class(&ce);
    zend_class_implements(span_exporter_ce, 1, span_exporter_iface_ce);
    span_exporter_ce->ce_flags |= ZEND_ACC_FINAL;

    zend_declare_property_null(span_exporter_ce, "transport", sizeof("transport") - 1, ZEND_ACC_PRIVATE);
}
