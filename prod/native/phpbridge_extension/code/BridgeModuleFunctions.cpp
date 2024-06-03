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

PHP_FUNCTION(getExtensionList) {
    auto extensions = BRIDGE_G(globals)->bridge.getExtensionList();
    for (auto const &extension : extensions) {
        BRIDGE_G(globals)->logger->printf(LogLevel::logLevel_info, "name: '%s' version: '%s'", extension.name.c_str(), extension.version.c_str());
    }
}

PHP_FUNCTION(getPhpInfo) {
    auto info = BRIDGE_G(globals)->bridge.getPhpInfo();
    BRIDGE_G(globals)->logger->printf(LogLevel::logLevel_info, "PHP-INFO: %s", info.c_str());
}

PHP_FUNCTION(getPhpSapiName) {
    auto info = BRIDGE_G(globals)->bridge.getPhpSapiName();
    RETURN_STRINGL(info.data(), info.length());
}


PHP_FUNCTION(compileAndExecuteFile) {
    char *fileName = nullptr;
    size_t fileNameLen = 0;

    ZEND_PARSE_PARAMETERS_START(1, 1)
    Z_PARAM_STRING(fileName, fileNameLen)
    ZEND_PARSE_PARAMETERS_END();

    try {
        BRIDGE_G(globals)->bridge.compileAndExecuteFile(std::string_view(fileName, fileNameLen));
    } catch (std::exception const &msg) {
        BRIDGE_G(globals)->logger->printf(LogLevel::logLevel_error, "Native exception caught: '%s'", msg.what());
    }
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

PHP_FUNCTION(isObjectOfClass) {
    char *className = nullptr;
    size_t classNameLen = 0;
    zval *object = nullptr;

    ZEND_PARSE_PARAMETERS_START(2, 2)
    Z_PARAM_STRING(className, classNameLen)
    Z_PARAM_ZVAL(object)
    ZEND_PARSE_PARAMETERS_END();

    RETURN_BOOL(elasticapm::php::isObjectOfClass(object, std::string_view(className, classNameLen)));
}

PHP_FUNCTION(getFunctionName) {
    elasticapm::php::AutoZval zv;
    long framesBack = 0;

    ZEND_PARSE_PARAMETERS_START(1, 1)
    Z_PARAM_LONG(framesBack)
    ZEND_PARSE_PARAMETERS_END();

    auto execData = EG(current_execute_data);
    for (auto frame = 0; frame < framesBack; ++frame) {
        execData = execData->prev_execute_data;
        if (!execData) {
            BRIDGE_G(globals)->logger->printf(LogLevel::logLevel_error, "test failure, can't go %d frames back", framesBack);
            RETURN_NULL();
            return;
        }
    }

    elasticapm::php::getFunctionName(zv.get(), execData);

    RETURN_COPY(zv.get());
}


PHP_FUNCTION(getFunctionDeclaringScope) {
    elasticapm::php::AutoZval zv;
    long framesBack = 0;

    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_LONG(framesBack)
    ZEND_PARSE_PARAMETERS_END();

    auto execData = EG(current_execute_data);
    for (auto frame = 0; frame < framesBack; ++frame) {
        execData = execData->prev_execute_data;
        if (!execData) {
            BRIDGE_G(globals)->logger->printf(LogLevel::logLevel_error, "test failure, can't go %d frames back", framesBack);
            RETURN_NULL();
            return;
        }
    }

    elasticapm::php::getFunctionDeclaringScope(zv.get(), execData);

    RETURN_COPY(zv.get());
}

PHP_FUNCTION(getFunctionDeclarationFileName) {
    elasticapm::php::AutoZval zv;
    long framesBack = 0;

    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_LONG(framesBack)
    ZEND_PARSE_PARAMETERS_END();

    auto execData = EG(current_execute_data);
    for (auto frame = 0; frame < framesBack; ++frame) {
        execData = execData->prev_execute_data;
        if (!execData) {
            BRIDGE_G(globals)->logger->printf(LogLevel::logLevel_error, "test failure, can't go %d frames back", framesBack);
            RETURN_NULL();
            return;
        }
    }

    elasticapm::php::getFunctionDeclarationFileName(zv.get(), execData);

    RETURN_COPY(zv.get());
}

PHP_FUNCTION(getFunctionDeclarationLineNo) {
    elasticapm::php::AutoZval zv;
    long framesBack = 0;

    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_LONG(framesBack)
    ZEND_PARSE_PARAMETERS_END();

    auto execData = EG(current_execute_data);
    for (auto frame = 0; frame < framesBack; ++frame) {
        execData = execData->prev_execute_data;
        if (!execData) {
            BRIDGE_G(globals)->logger->printf(LogLevel::logLevel_error, "test failure, can't go %d frames back", framesBack);
            RETURN_NULL();
            return;
        }
    }

    elasticapm::php::getFunctionDeclarationLineNo(zv.get(), execData);

    RETURN_COPY(zv.get());
}

PHP_FUNCTION(getCallArguments) {
    elasticapm::php::AutoZval zv;
    long framesBack = 0;

    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_LONG(framesBack)
    ZEND_PARSE_PARAMETERS_END();

    auto execData = EG(current_execute_data);
    for (auto frame = 0; frame < framesBack; ++frame) {
        execData = execData->prev_execute_data;
        if (!execData) {
            BRIDGE_G(globals)->logger->printf(LogLevel::logLevel_error, "test failure, can't go %d frames back", framesBack);
            RETURN_NULL();
            return;
        }
    }

    elasticapm::php::getCallArguments(zv.get(), execData);

    RETURN_COPY(zv.get());
}


PHP_FUNCTION(getScopeNameOrThis) {
    elasticapm::php::AutoZval zv;
    long framesBack = 0;

    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_LONG(framesBack)
    ZEND_PARSE_PARAMETERS_END();

    auto execData = EG(current_execute_data);
    for (auto frame = 0; frame < framesBack; ++frame) {
        execData = execData->prev_execute_data;
        if (!execData) {
            BRIDGE_G(globals)->logger->printf(LogLevel::logLevel_error, "test failure, can't go %d frames back", framesBack);
            RETURN_NULL();
            return;
        }
    }

    elasticapm::php::getScopeNameOrThis(zv.get(), execData);

    RETURN_COPY(zv.get());
}

PHP_FUNCTION(getExceptionName) {
    zval *object = nullptr;

    ZEND_PARSE_PARAMETERS_START(1, 1)
    Z_PARAM_ZVAL(object)
    ZEND_PARSE_PARAMETERS_END();

    auto name = elasticapm::php::getExceptionName(Z_OBJ_P(object));
    RETURN_STRINGL(name.data(), name.length());
}

PHP_FUNCTION(getCurrentException) {
    zend_object *object = nullptr;

    ZEND_PARSE_PARAMETERS_START(1, 1)
    Z_PARAM_OBJ(object)
    ZEND_PARSE_PARAMETERS_END();

    elasticapm::php::AutoZval zv;

    elasticapm::php::getCurrentException(zv.get(), object);
    RETURN_COPY(zv.get());
}

ZEND_BEGIN_ARG_INFO(no_paramters_arginfo, 0)
ZEND_END_ARG_INFO()

// clang-format off
const zend_function_entry phpbridge_functions[] = {
    PHP_FE( detectOpcachePreload, no_paramters_arginfo )
    PHP_FE( isOpcacheEnabled, no_paramters_arginfo )

    PHP_FE( getExtensionList, no_paramters_arginfo )
    PHP_FE( getPhpInfo, no_paramters_arginfo )
    PHP_FE( getPhpSapiName, no_paramters_arginfo )
    PHP_FE( compileAndExecuteFile, no_paramters_arginfo )

    PHP_FE( findClassEntry, no_paramters_arginfo )
    PHP_FE( getClassStaticPropertyValue, no_paramters_arginfo )
    PHP_FE( getClassPropertyValue, no_paramters_arginfo )
    PHP_FE( callMethod, no_paramters_arginfo )
    PHP_FE( isObjectOfClass, no_paramters_arginfo )
    PHP_FE( getFunctionName, no_paramters_arginfo )
    PHP_FE( getFunctionDeclaringScope, no_paramters_arginfo )
    PHP_FE( getFunctionDeclarationFileName, no_paramters_arginfo )
    PHP_FE( getFunctionDeclarationLineNo, no_paramters_arginfo )
    PHP_FE( getCallArguments, no_paramters_arginfo )
    PHP_FE( getScopeNameOrThis, no_paramters_arginfo )
    PHP_FE( getExceptionName, no_paramters_arginfo )
    PHP_FE( getCurrentException, no_paramters_arginfo )

    PHP_FE_END
};
// clang-format on
