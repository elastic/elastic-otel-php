#pragma once

#include "AutoZval.h"
#include "PhpBridgeInterface.h"
#include "LoggerInterface.h"
#include "InternalFunctionInstrumentationStorage.h"
#include "InstrumentedFunctionHooksStorage.h"
#include <string_view>

namespace elasticapm::php {

using InstrumentedFunctionHooksStorage_t = InstrumentedFunctionHooksStorage<zend_ulong, AutoZval<1>>;

bool instrumentInternalFunction(LoggerInterface *log, std::string_view className, std::string_view functionName, zval *callableOnEntry, zval *callableOnExit);


}

