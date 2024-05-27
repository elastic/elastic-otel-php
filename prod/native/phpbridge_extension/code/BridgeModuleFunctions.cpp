#include "BridgeModuleFunctions.h"
#include "BridgeModuleGlobals.h"

#include <main/php.h>
#include <Zend/zend_API.h>

PHP_FUNCTION(detectOpcachePreload) {
    BRIDGE_G(globals)->logger->printf(LogLevel::logLevel_info, "detectOpcachePreload: %d", BRIDGE_G(globals)->bridge.detectOpcachePreload());
}

PHP_FUNCTION(isOpcacheEnabled) {
    BRIDGE_G(globals)->logger->printf(LogLevel::logLevel_info, "isOpcacheEnabled: %d", BRIDGE_G(globals)->bridge.isOpcacheEnabled());
}

ZEND_BEGIN_ARG_INFO(no_paramters_arginfo, 0)
ZEND_END_ARG_INFO()

// clang-format off
const zend_function_entry phpbridge_functions[] = {
    PHP_FE( detectOpcachePreload, no_paramters_arginfo )
    PHP_FE( isOpcacheEnabled, no_paramters_arginfo )
    PHP_FE_END
};
// clang-format on
