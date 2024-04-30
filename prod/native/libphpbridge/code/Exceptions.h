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


class AutomaticExceptionStateRestorer {
public:
    AutomaticExceptionStateRestorer() : savedException(saveExceptionState()) {
    }
    ~AutomaticExceptionStateRestorer() {
        restoreExceptionState(savedException);
    }
    auto getException() {
        return savedException.exception;
    }

private:
    SavedException savedException;
};

std::optional<std::string_view> getExceptionClass(zend_object *exception);
std::optional<std::string_view> getExceptionFileName(zend_object *exception);
std::optional<std::string_view> getExceptionFunction(zend_object *exception);
std::optional<long> getExceptionLine(zend_object *exception);
std::optional<std::string_view> getExceptionMessage(zend_object *exception);
std::optional<std::string_view> getExceptionStringStackTrace(zend_object *exception);

std::string exceptionToString(zend_object *exception);
}
