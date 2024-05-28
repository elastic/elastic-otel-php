#include "BridgeModuleFunctions.h"
#include "BridgeModuleGlobals.h"
#include "AutoZval.h"
#include "PhpBridge.h"


#include <main/php.h>
#include <Zend/zend_API.h>

PHP_FUNCTION(detectOpcachePreload) {
    BRIDGE_G(globals)->logger->printf(LogLevel::logLevel_info, "detectOpcachePreload: %d", BRIDGE_G(globals)->bridge.detectOpcachePreload());
}

PHP_FUNCTION(isOpcacheEnabled) {
    BRIDGE_G(globals)->logger->printf(LogLevel::logLevel_info, "isOpcacheEnabled: %d", BRIDGE_G(globals)->bridge.isOpcacheEnabled());
}

PHP_FUNCTION(findClassEntry) {
    char *className = nullptr;
    size_t classNameLen = 0;

    ZEND_PARSE_PARAMETERS_START(1, 1)
    Z_PARAM_STRING(className, classNameLen)
    ZEND_PARSE_PARAMETERS_END();

    BRIDGE_G(globals)->logger->printf(LogLevel::logLevel_info, "findClassEntry found: %d", static_cast<bool>(elasticapm::php::findClassEntry(std::string_view(className, classNameLen))));
}

PHP_FUNCTION(getClassStaticPropertyValue) {
    char *className = nullptr;
    size_t classNameLen = 0;
    char *propName = nullptr;
    size_t propNameLen = 0;

    ZEND_PARSE_PARAMETERS_START(2, 2)
    Z_PARAM_STRING(className, classNameLen)
    Z_PARAM_STRING(propName, propNameLen)
    ZEND_PARSE_PARAMETERS_END();

    auto ce = elasticapm::php::findClassEntry(std::string_view(className, classNameLen));

    if (!ce) {
        BRIDGE_G(globals)->logger->printf(LogLevel::logLevel_info, "getClassStaticPropertyValue class not found");
        RETURN_NULL();
        return;
    }

    auto prop = elasticapm::php::getClassStaticPropertyValue(ce, std::string_view(propName, propNameLen));
    if (!prop) {
        BRIDGE_G(globals)->logger->printf(LogLevel::logLevel_info, "getClassStaticPropertyValue property not found");
        RETURN_NULL();
        return;
    }

    RETURN_COPY(prop);
}

PHP_FUNCTION(getClassPropertyValue) {
    char *className = nullptr;
    size_t classNameLen = 0;
    char *propName = nullptr;
    size_t propNameLen = 0;
    zval *object = nullptr;

    ZEND_PARSE_PARAMETERS_START(3, 3)
    Z_PARAM_STRING(className, classNameLen)
    Z_PARAM_STRING(propName, propNameLen)
    Z_PARAM_ZVAL(object)
    ZEND_PARSE_PARAMETERS_END();

    auto ce = elasticapm::php::findClassEntry(std::string_view(className, classNameLen));

    if (!ce) {
        BRIDGE_G(globals)->logger->printf(LogLevel::logLevel_info, "getClassPropertyValue class not found");
        RETURN_NULL();
        return;
    }

    auto prop = elasticapm::php::getClassPropertyValue(ce, object, std::string_view(propName, propNameLen));
    if (!prop) {
        BRIDGE_G(globals)->logger->printf(LogLevel::logLevel_info, "getClassPropertyValue property not found");
        RETURN_NULL();
        return;
    }

    RETURN_COPY(prop);
}


PHP_FUNCTION(callMethod) {
    char *methodName = nullptr;
    size_t methodNameLen = 0;
    zval *object = nullptr;
    HashTable *args = nullptr;
    zval returnValue;

    ZEND_PARSE_PARAMETERS_START(3, 3)
    Z_PARAM_ZVAL(object)
    Z_PARAM_STRING(methodName, methodNameLen)
    Z_PARAM_ARRAY_HT(args)
    ZEND_PARSE_PARAMETERS_END();

    zend_string *key;
    zval *value;

    std::vector<elasticapm::php::AutoZval> argsZval;
    argsZval.resize(zend_hash_num_elements(args));

    size_t index = 0;
    ZEND_HASH_FOREACH_STR_KEY_VAL(args, key, value) {
        (void)key;
        ZVAL_COPY(argsZval[index].get(), value);
        index++;
    }
    ZEND_HASH_FOREACH_END();

    bool rv = elasticapm::php::callMethod(object, std::string_view(methodName, methodNameLen), argsZval.data()->get(), argsZval.size(), &returnValue);
    BRIDGE_G(globals)->logger->printf(LogLevel::logLevel_info, "callMethod rv: %d", rv);

    RETURN_COPY(&returnValue);
}

ZEND_BEGIN_ARG_INFO(no_paramters_arginfo, 0)
ZEND_END_ARG_INFO()

// clang-format off
const zend_function_entry phpbridge_functions[] = {
    PHP_FE( detectOpcachePreload, no_paramters_arginfo )
    PHP_FE( isOpcacheEnabled, no_paramters_arginfo )
    PHP_FE( findClassEntry, no_paramters_arginfo )
    PHP_FE( getClassStaticPropertyValue, no_paramters_arginfo )
    PHP_FE( getClassPropertyValue, no_paramters_arginfo )
    PHP_FE( callMethod, no_paramters_arginfo )
    PHP_FE_END
};
// clang-format on

// std::string_view getExceptionName(zend_object *exception);
// bool isObjectOfClass(zval *object, std::string_view className);

// void getCallArguments(zval *zv, zend_execute_data *ex);
// void getScopeNameOrThis(zval *zv, zend_execute_data *execute_data);
// void getFunctionName(zval *zv, zend_execute_data *ex);
// void getFunctionDeclaringScope(zval *zv, zend_execute_data *ex);
// void getFunctionDeclarationFileName(zval *zv, zend_execute_data *ex);
// void getFunctionDeclarationLineNo(zval *zv, zend_execute_data *ex);
// void getFunctionReturnValue(zval *zv, zval *retval);
// void getCurrentException(zval *zv, zend_object *exception);
