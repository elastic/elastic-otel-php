#pragma once

#include <Zend/zend_globals.h>
#include <Zend/zend_types.h>
#include <optional>
#include <string_view>

namespace elasticapm::php {

struct SavedException {
    zend_object *exception = nullptr;
    zend_object *prev_exception = nullptr;
    const zend_op *opline_before_exception = nullptr;
    std::optional<const zend_op *> opline;
};

SavedException saveExceptionState();
void restoreExceptionState(SavedException savedException);

std::string_view getExceptionMessage(zend_object *exception);
std::string_view getExceptionFileName(zend_object *exception);
long getExceptionLine(zend_object *exception);
std::string_view getExceptionFunction(zend_object *exception);
std::string_view getExceptionClass(zend_object *exception);

}
