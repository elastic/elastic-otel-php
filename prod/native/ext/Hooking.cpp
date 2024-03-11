
#include "Hooking.h"

#include <Zend/zend_API.h>

#include "ModuleGlobals.h"

#include "PhpBridge.h"
#include "PhpErrorData.h"

#include <memory>
#include <string_view>
#include "PeriodicTaskExecutor.h"
#include "RequestScope.h"
#include "os/OsUtils.h"

#include "Zend/zend_observer.h"

namespace elasticapm::php {

// #if PHP_VERSION_ID < 80000
// void elastic_apm_error_cb(int type, const char *error_filename, const Hooking::zend_error_cb_lineno_t error_lineno, const char *format, va_list args) { //<8.0
// #elif PHP_VERSION_ID < 80100
// void elastic_apm_error_cb(int type, const char *error_filename, const uint32_t error_lineno, zend_string *message) { // 8.0
// #else
// void elastic_apm_error_cb(int type, zend_string *error_filename, const uint32_t error_lineno, zend_string *message) { // 8.1+
// #endif
//     using namespace std::string_view_literals;

//     ELOG_DEBUG(ELASTICAPM_G(globals)->logger_, __FUNCTION__);

//     if (ELASTICAPM_G(captureErrors)) {
// #if PHP_VERSION_ID < 80100
//         ELASTICAPM_G(lastErrorData) = std::make_unique<elasticapm::php::PhpErrorData>(type, error_filename ? error_filename : ""sv, error_lineno, message ? std::string_view{ZSTR_VAL(message), ZSTR_LEN(message)} : ""sv);
// #else
//         ELASTICAPM_G(lastErrorData) = nullptr;
//         ELASTICAPM_G(lastErrorData) = std::make_unique<elasticapm::php::PhpErrorData>(type, error_filename ? std::string_view{ZSTR_VAL(error_filename), ZSTR_LEN(error_filename)} : ""sv, error_lineno, message ? std::string_view{ZSTR_VAL(message), ZSTR_LEN(message)} : ""sv);
// #endif

//     }

//     auto original = Hooking::getInstance().getOriginalZendErrorCb();
//     if (original == elastic_apm_error_cb) {
//         ELOG_CRITICAL(ELASTICAPM_G(globals)->logger_, "originalZendErrorCallback == elasticApmZendErrorCallback dead loop detected");
//         return;
//     }

//     if (original) {
//         ELOG_DEBUG(ELASTICAPM_G(globals)->logger_, "elastic_apm_error_cb calling original error_cb %p", original);


// #if PHP_VERSION_ID < 80000
//         original(type, error_filename, error_lineno, format, args);
// #else
//         original(type, error_filename, error_lineno, message);
// #endif
//     } else {
//         ELOG_DEBUG(ELASTICAPM_G(globals)->logger_, "elastic_apm_error_cb missing original error_cb");
//     }
// }

// static void elastic_execute_internal(INTERNAL_FUNCTION_PARAMETERS) {

//     zend_try {
//         if (Hooking::getInstance().getOriginalExecuteInternal()) {
//             Hooking::getInstance().getOriginalExecuteInternal()(INTERNAL_FUNCTION_PARAM_PASSTHRU);
//         } else {
//             execute_internal(INTERNAL_FUNCTION_PARAM_PASSTHRU);
//         }
//     } zend_catch {
//         ELASTIC_APM_LOG_DIRECT_DEBUG("%s: original call error; parent PID: %d", __FUNCTION__, static_cast<int>(elasticapm::osutils::getParentProcessId()));
//     } zend_end_try();

//     // ELASTICAPM_G(globals)->inferredSpans_->attachBacktraceIfInterrupted();
// }

static void elastic_interrupt_function(zend_execute_data *execute_data) {
    ELOG_DEBUG(EAPM_GL(logger_), "%s: interrupt; parent PID: %d", __FUNCTION__, static_cast<int>(elasticapm::osutils::getParentProcessId()));

    // ELASTICAPM_G(globals)->inferredSpans_->attachBacktraceIfInterrupted();

    zend_try {
        if (Hooking::getInstance().getOriginalZendInterruptFunction()) {
            Hooking::getInstance().getOriginalZendInterruptFunction()(execute_data);
        }
    }
    zend_catch {
        ELOG_DEBUG(EAPM_GL(logger_), "%s: original call error; parent PID: %d", __FUNCTION__, static_cast<int>(elasticapm::osutils::getParentProcessId()));
    }
    zend_end_try();
}

#if PHP_VERSION_ID < 80100
void elastic_observer_error_cb(int type, const char *error_filename, uint32_t error_lineno, zend_string *message) {
    std::string_view fileName = error_filename ? std::string_view{error_filename} : std::string_view{};
#else
void elastic_observer_error_cb(int type, zend_string *error_filename, uint32_t error_lineno, zend_string *message) {
    std::string_view fileName = error_filename ? std::string_view{ZSTR_VAL(error_filename), ZSTR_LEN(error_filename)} : std::string_view{};
#endif
    std::string_view msg = message && ZSTR_VAL(message) ? std::string_view{ZSTR_VAL(message), ZSTR_LEN(message)} : std::string_view{};
    ELOG_DEBUG(ELASTICAPM_G(globals)->logger_, "elastic_observer_error_cb type: %d, fn: %s:%d, msg: %s", type, fileName.data(), error_lineno,  msg.data());

    static bool errorHandling = false;
    if (errorHandling) {
        ELOG_WARNING(ELASTICAPM_G(globals)->logger_, "elastic_observer_error_cb detected error handler loop, skipping error handler");
        return;
    }

    errorHandling = true;
    ELASTICAPM_G(globals)->requestScope_->handleError(type, fileName, error_lineno, msg);
    errorHandling = false;

}

void Hooking::replaceHooks() {
        // zend_execute_internal = elastic_execute_internal;
        zend_interrupt_function = elastic_interrupt_function;
        // zend_error_cb = elastic_apm_error_cb;

        zend_observer_error_register(elastic_observer_error_cb);

}

}
