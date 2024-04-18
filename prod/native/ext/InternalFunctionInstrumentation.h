#pragma once

#include "AutoZval.h"
#include "PhpBridgeInterface.h"
#include "LoggerInterface.h"
#include "InternalFunctionInstrumentationStorage.h"
#include "InstrumentedFunctionHooksStorage.h"
#include <string_view>
#include <Zend/zend_observer.h>

namespace elasticapm::php {

using InstrumentedFunctionHooksStorage_t = InstrumentedFunctionHooksStorage<zend_ulong, AutoZval<1>>;

bool instrumentFunction(LoggerInterface *log, std::string_view className, std::string_view functionName, zval *callableOnEntry, zval *callableOnExit);
zend_observer_fcall_handlers elasticRegisterObserver(zend_execute_data *execute_data);


}

