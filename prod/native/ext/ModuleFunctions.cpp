
#include "ModuleFunctions.h"
#include "ConfigurationStorage.h"
#include "LoggerInterface.h"
#include "ModuleGlobals.h"
#include "ModuleFunctionsImpl.h"
#include "InternalFunctionInstrumentation.h"

#include <main/php.h>
#include <Zend/zend_API.h>
#include <Zend/zend_closures.h>

// bool elastic_apm_is_enabled()
PHP_FUNCTION(elastic_apm_is_enabled) {
    RETVAL_BOOL(false);
    ZEND_PARSE_PARAMETERS_NONE();

    RETVAL_BOOL(EAPM_CFG(enabled));
}

ZEND_BEGIN_ARG_INFO_EX(elastic_apm_get_config_option_by_name_arginfo, 0, 0, 1)
ZEND_ARG_TYPE_INFO(/* pass_by_ref: */ 0, optionName, IS_STRING, /* allow_null: */ 0)
ZEND_END_ARG_INFO()

/* elastic_apm_get_config_option_by_name( string $optionName ): mixed */
PHP_FUNCTION(elastic_apm_get_config_option_by_name) {
    ZVAL_NULL(return_value);
    char *optionName = nullptr;
    size_t optionNameLength = 0;

    ZEND_PARSE_PARAMETERS_START(1, 1)
    Z_PARAM_STRING(optionName, optionNameLength)
    ZEND_PARSE_PARAMETERS_END();

    elasticApmGetConfigOption({optionName, optionNameLength}, /* out */ return_value);
}

/* elastic_apm_get_number_of_dynamic_config_options(): int */
PHP_FUNCTION(elastic_apm_get_number_of_dynamic_config_options) {
    ZEND_PARSE_PARAMETERS_NONE();
    //TODO implement dynamic tag in config manager
    RETURN_LONG(0);
}

ZEND_BEGIN_ARG_INFO_EX(elastic_apm_send_to_server_arginfo, /* _unused: */ 0, /* return_reference: */ 0, /* required_num_args: */ 2)
ZEND_ARG_TYPE_INFO(/* pass_by_ref: */ 0, userAgentHttpHeader, IS_STRING, /* allow_null: */ 0)
ZEND_ARG_TYPE_INFO(/* pass_by_ref: */ 0, serializedEvents, IS_STRING, /* allow_null: */ 0)
ZEND_END_ARG_INFO()

/* {{{ elastic_apm_send_to_server(
 *          string userAgentHttpHeader,
 *          string $serializedEvents ): bool
 */
PHP_FUNCTION(elastic_apm_send_to_server) {
    char *userAgentHttpHeader = nullptr;
    size_t userAgentHttpHeaderLength = 0;
    char *serializedEvents = nullptr;
    size_t serializedEventsLength = 0;

    ZEND_PARSE_PARAMETERS_START(/* min_num_args: */ 2, /* max_num_args: */ 2)
    Z_PARAM_STRING(userAgentHttpHeader, userAgentHttpHeaderLength)
    Z_PARAM_STRING(serializedEvents, serializedEventsLength)
    ZEND_PARSE_PARAMETERS_END();

    // if (elasticApmSendToServer({ userAgentHttpHeader, userAgentHttpHeaderLength } , { serializedEvents, serializedEventsLength }) != resultSuccess) {
    RETURN_BOOL(false);
    // }

    // RETURN_BOOL(true);
}
/* }}} */

ZEND_BEGIN_ARG_INFO_EX(elastic_apm_log_arginfo, /* _unused: */ 0, /* return_reference: */ 0, /* required_num_args: */ 7)
ZEND_ARG_TYPE_INFO(/* pass_by_ref: */ 0, isForced, IS_LONG, /* allow_null: */ 0)
ZEND_ARG_TYPE_INFO(/* pass_by_ref: */ 0, level, IS_LONG, /* allow_null: */ 0)
ZEND_ARG_TYPE_INFO(/* pass_by_ref: */ 0, category, IS_STRING, /* allow_null: */ 0)
ZEND_ARG_TYPE_INFO(/* pass_by_ref: */ 0, file, IS_STRING, /* allow_null: */ 0)
ZEND_ARG_TYPE_INFO(/* pass_by_ref: */ 0, line, IS_LONG, /* allow_null: */ 0)
ZEND_ARG_TYPE_INFO(/* pass_by_ref: */ 0, func, IS_STRING, /* allow_null: */ 0)
ZEND_ARG_TYPE_INFO(/* pass_by_ref: */ 0, message, IS_STRING, /* allow_null: */ 0)
ZEND_END_ARG_INFO()

/* {{{ elastic_apm_log(
 *      int $isForced,
 *      int $level,
 *      string $category,
 *      string $file,
 *      int $line,
 *      string $func,
 *      string $message
 *  ): void
 */
PHP_FUNCTION(elastic_apm_log) {
    zend_long isForced = 0;
    zend_long level = 0;
    char *file = nullptr;
    size_t fileLength = 0;
    char *category = nullptr;
    size_t categoryLength = 0;
    zend_long line = 0;
    char *func = nullptr;
    size_t funcLength = 0;
    char *message = nullptr;
    size_t messageLength = 0;

    ZEND_PARSE_PARAMETERS_START(/* min_num_args: */ 7, /* max_num_args: */ 7)
    Z_PARAM_LONG(isForced)
    Z_PARAM_LONG(level)
    Z_PARAM_STRING(category, categoryLength)
    Z_PARAM_STRING(file, fileLength)
    Z_PARAM_LONG(line)
    Z_PARAM_STRING(func, funcLength)
    Z_PARAM_STRING(message, messageLength)
    ZEND_PARSE_PARAMETERS_END();

    // TODO fix/avoid double sv creation
    ELASTICAPM_G(globals)->logger_->printf(static_cast<LogLevel>(level), PRsv " " PRsv " %d " PRsv " " PRsv, PRsvArg(std::string_view(category, categoryLength)), PRsvArg(std::string_view(file, fileLength)), line, PRsvArg(std::string_view(func, funcLength)), PRsvArg(std::string_view(message, messageLength)));
}
/* }}} */

ZEND_BEGIN_ARG_INFO_EX(elastic_apm_get_last_thrown_arginfo, /* _unused */ 0, /* return_reference: */ 0, /* required_num_args: */ 0)
ZEND_END_ARG_INFO()
/* {{{ elastic_apm_get_last_thrown(): mixed
 */
PHP_FUNCTION(elastic_apm_get_last_thrown) {
    ZVAL_NULL(/* out */ return_value);
    //TODO elasticApmGetLastThrown(/* out */ return_value);
}
/* }}} */

ZEND_BEGIN_ARG_INFO_EX(elastic_apm_get_last_php_error_arginfo, /* _unused */ 0, /* return_reference: */ 0, /* required_num_args: */ 0)
ZEND_END_ARG_INFO()
/* {{{ elastic_apm_get_last_error(): array
 */
PHP_FUNCTION(elastic_apm_get_last_php_error) {
    ZVAL_NULL(/* out */ return_value);

    //TODO elasticApmGetLastPhpError(/* out */ return_value);
}
/* }}} */

ZEND_BEGIN_ARG_INFO(elastic_apm_no_paramters_arginfo, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(elastic_apm_hook_arginfo, 0, 2, _IS_BOOL, 0)
ZEND_ARG_TYPE_INFO(0, class, IS_STRING, 1)
ZEND_ARG_TYPE_INFO(0, function, IS_STRING, 0)
ZEND_ARG_OBJ_INFO_WITH_DEFAULT_VALUE(0, pre, Closure, 1, "null")
ZEND_ARG_OBJ_INFO_WITH_DEFAULT_VALUE(0, post, Closure, 1, "null")
ZEND_END_ARG_INFO()

PHP_FUNCTION(elastic_apm_hook) {
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

    if (elasticapm::php::instrumentInternalFunction(EAPM_GL(logger_).get(), className, functionName, pre, post)) {
        // ELOG_WARNING(ELASTICAPM_G(globals)->logger_, "FUNCTION INSTTRUMENTED");
    }
}

// clang-format off
const zend_function_entry elastic_apm_functions[] = {
    PHP_FE( elastic_apm_is_enabled, elastic_apm_no_paramters_arginfo )
    PHP_FE( elastic_apm_get_config_option_by_name, elastic_apm_get_config_option_by_name_arginfo )
    PHP_FE( elastic_apm_get_number_of_dynamic_config_options, elastic_apm_no_paramters_arginfo )
    // PHP_FE( elastic_apm_intercept_calls_to_internal_method, elastic_apm_intercept_calls_to_internal_method_arginfo )
    // PHP_FE( elastic_apm_intercept_calls_to_internal_function, elastic_apm_intercept_calls_to_internal_function_arginfo )
    PHP_FE( elastic_apm_send_to_server, elastic_apm_send_to_server_arginfo )
    PHP_FE( elastic_apm_log, elastic_apm_log_arginfo )
    PHP_FE( elastic_apm_get_last_thrown, elastic_apm_get_last_thrown_arginfo )
    PHP_FE( elastic_apm_get_last_php_error, elastic_apm_get_last_php_error_arginfo )
    PHP_FE( elastic_apm_hook, elastic_apm_hook_arginfo )
    // ZEND_NS_FE("OpenTelemetry\\Instrumentation", hook, arginfo_OpenTelemetry_Instrumentation_hook) ZEND_FE_END,

    PHP_FE_END
};
// clang-format on
