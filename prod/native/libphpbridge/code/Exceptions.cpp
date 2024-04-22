#include "PhpBridge.h"
#include "Helpers.h"

#include <Zend/zend_exceptions.h>
#include <Zend/zend_globals.h>
#include <Zend/zend_types.h>
#include <optional>

namespace elasticapm::php {

using namespace std::literals;

SavedException saveExceptionState() {
    SavedException savedException;
    savedException.exception = EG(exception);
    savedException.prev_exception = EG(prev_exception);
    savedException.opline_before_exception = EG(opline_before_exception);

    EG(exception) = nullptr;
    EG(prev_exception) = nullptr;
    EG(opline_before_exception) = nullptr;

    if (EG(current_execute_data)) {
        savedException.opline = EG(current_execute_data)->opline;
    }
    return savedException;
}

void restoreExceptionState(SavedException savedException) {
    EG(exception) = savedException.exception;
    EG(prev_exception) = savedException.prev_exception;
    EG(opline_before_exception) = savedException.opline_before_exception;

    if (EG(current_execute_data) && savedException.opline.has_value()) {
        EG(current_execute_data)->opline = savedException.opline.value();
    }
}


std::string_view getExceptionMessage(zend_object *exception) {
    return zvalToStringView(getClassPropertyValue(zend_ce_exception, exception, "message"sv));
}

std::string_view getExceptionFileName(zend_object *exception) {
    return zvalToStringView(getClassPropertyValue(zend_ce_exception, exception, "file"sv));
}

long getExceptionLine(zend_object *exception) {
    auto value = getClassPropertyValue(zend_ce_exception, exception, "line"sv);
    if (Z_TYPE_P(value) == IS_LONG) {
        return Z_LVAL_P(value);
    }
    return -1;
}

std::string_view getExceptionClass(zend_object *exception) {
    return zvalToStringView(getClassPropertyValue(zend_ce_exception, exception, "class"sv));
}

std::string_view getExceptionFunction(zend_object *exception) {
    return zvalToStringView(getClassPropertyValue(zend_ce_exception, exception, "function"sv));
}

std::string_view getExceptionName(zend_object *exception) {
    zend_string *str = exception->handlers->get_class_name(exception);
    if (!str) {
        return {};
    }
    return {ZSTR_VAL(str), ZSTR_LEN(str)};
}


}
