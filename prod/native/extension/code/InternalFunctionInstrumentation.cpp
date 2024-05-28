#include "ModuleGlobals.h"
#include "InternalFunctionInstrumentation.h"
#include "AutoZval.h"
#include "PhpBridge.h"
#include "LoggerInterface.h"

#include "Zend/zend.h"
#include "Zend/zend_exceptions.h"
#include "Zend/zend_hash.h"
#include "Zend/zend_globals.h"
#include <Zend/zend_observer.h>


#include "InternalFunctionInstrumentationStorage.h"
#include "RequestScope.h"
#include "InstrumentedFunctionHooksStorage.h"

#include <array>

namespace elasticapm::php {

using namespace std::literals;

using InternalStorage_t = InternalFunctionInstrumentationStorage<zend_ulong, zif_handler>;

void handleAndReleaseHookException(zend_object *exception) {
    if (!exception || !instanceof_function(exception->ce, zend_ce_throwable)) {
        return;
    }

    ELOG_ERROR(EAPM_GL(logger_), "Instrumentation hook error: %s", exceptionToString(exception).c_str());
    OBJ_RELEASE(exception);
}

void callPreHook(AutoZval &prehook) {
    zend_fcall_info fci = empty_fcall_info;
    zend_fcall_info_cache fcc = empty_fcall_info_cache;

    if (zend_fcall_info_init(const_cast<zval *>(prehook.get()), 0, &fci, &fcc, nullptr, nullptr) == ZEND_RESULT_CODE::FAILURE) {
        throw std::runtime_error("Unable to initialize prehook fcall");
    }

    std::array<AutoZval, 6> parameters;
    getScopeNameOrThis(parameters[0].get(), EG(current_execute_data));
    getCallArguments(parameters[1].get(), EG(current_execute_data));
    getFunctionDeclaringScope(parameters[2].get(), EG(current_execute_data));
    getFunctionName(parameters[3].get(), EG(current_execute_data));
    getFunctionDeclarationFileName(parameters[4].get(), EG(current_execute_data));
    getFunctionDeclarationLineNo(parameters[5].get(), EG(current_execute_data));

    AutoZval ret;
    fci.param_count = parameters.size();
    fci.params = parameters[0].get();
    fci.named_params = nullptr;
    fci.retval = ret.get();
    if (zend_call_function(&fci, &fcc) != SUCCESS) {
        throw std::runtime_error("Unable to call prehook function");
    }
}
//TODO arguments post processing

void callPostHook(AutoZval &hook, zval *return_value, zend_object *exception) {
    zend_fcall_info fci = empty_fcall_info;
    zend_fcall_info_cache fcc = empty_fcall_info_cache;

    if (zend_fcall_info_init(const_cast<zval *>(hook.get()), 0, &fci, &fcc, nullptr, nullptr) == ZEND_RESULT_CODE::FAILURE) {
        throw std::runtime_error("Unable to initialize posthook fcall");
    }

    std::array<AutoZval, 8> parameters;
    getScopeNameOrThis(parameters[0].get(), EG(current_execute_data));
    getCallArguments(parameters[1].get(), EG(current_execute_data));
    getFunctionReturnValue(parameters[2].get(), return_value);
    getCurrentException(parameters[3].get(), exception);
    getFunctionDeclaringScope(parameters[4].get(), EG(current_execute_data));
    getFunctionName(parameters[5].get(), EG(current_execute_data));
    getFunctionDeclarationFileName(parameters[6].get(), EG(current_execute_data));
    getFunctionDeclarationLineNo(parameters[7].get(), EG(current_execute_data));

    AutoZval ret;
    fci.param_count = parameters.size();
    fci.params = parameters[0].get();
    fci.named_params = nullptr;
    fci.retval = ret.get();
    if (zend_call_function(&fci, &fcc) != SUCCESS) {
        throw std::runtime_error("Unable to call posthook function");
    }
}

inline void callOriginalHandler(zif_handler handler, INTERNAL_FUNCTION_PARAMETERS) {
    zend_try {
        handler(INTERNAL_FUNCTION_PARAM_PASSTHRU);
    } zend_catch {
        if (*EG(bailout)) {
            LONGJMP(*EG(bailout), FAILURE);
        } else {
            zend_bailout();
        }
    } zend_end_try();
}


void ZEND_FASTCALL internal_function_handler(INTERNAL_FUNCTION_PARAMETERS) {
    auto hash = getClassAndFunctionHashFromExecuteData(execute_data);

    auto originalHandler = InternalStorage_t::getInstance().get(hash);
    if (!originalHandler) {
        auto [cls, func] = getClassAndFunctionName(execute_data);
        ELOG_CRITICAL(EAPM_GL(logger_), "Unable to find function handler " PRsv "::" PRsv, PRsvArg(cls), PRsvArg(func));
        return;
    }

    if (!EAPM_GL(requestScope_)->isFunctional()) {
        callOriginalHandler(originalHandler, INTERNAL_FUNCTION_PARAM_PASSTHRU);
        return;
    }

    auto callbacks = reinterpret_cast<InstrumentedFunctionHooksStorage_t *>(EAPM_GL(hooksStorage_).get())->find(hash);
    if (!callbacks) {
        callOriginalHandler(originalHandler, INTERNAL_FUNCTION_PARAM_PASSTHRU);
        ELOG_WARNING(EAPM_GL(logger_), "Unable to find function callbacks");
        return;
    }

    for (auto &callback : *callbacks) {
        try {
            AutomaticExceptionStateRestorer restorer;
            callPreHook(callback.first);
            handleAndReleaseHookException(EG(exception));
        } catch (std::exception const &e) {
            auto [cls, func] = getClassAndFunctionName(execute_data);
            ELOG_CRITICAL(EAPM_GL(logger_), "%s hash: 0x%X " PRsv "::" PRsv, e.what(), hash, PRsvArg(cls), PRsvArg(func));
        }
    }

    callOriginalHandler(originalHandler, INTERNAL_FUNCTION_PARAM_PASSTHRU);

    for (auto &callback : *callbacks) {
        try {
            AutomaticExceptionStateRestorer restorer;
            callPostHook(callback.second, return_value, restorer.getException());

            handleAndReleaseHookException(EG(exception));
        } catch (std::exception const &e) {
            auto [cls, func] = getClassAndFunctionName(execute_data);
            ELOG_CRITICAL(EAPM_GL(logger_), "%s hash: 0x%X " PRsv "::" PRsv, e.what(), hash, PRsvArg(cls), PRsvArg(func));
        }
    }

}


bool instrumentFunction(LoggerInterface *log, std::string_view className, std::string_view functionName, zval *callableOnEntry, zval *callableOnExit) {
    //TODO if called from other place that MINIT - make it thread safe in ZTS
    //TODO use hash struct instead of combined to prevent collisions

    HashTable *table = nullptr;
    zend_ulong classHash = 0;

    if (className.empty()) { // looking for function
        table = EG(function_table);
    } else {
        if (!EG(class_table)) {
            ELOG_DEBUG(log, "instrumentFunction Class table is empty. Function " PRsv "::" PRsv " not found and cannot be instrumented.", PRsvArg(className), PRsvArg(functionName));
            return false;
        }

        auto ce = static_cast<zend_class_entry *>(zend_hash_str_find_ptr(EG(class_table), className.data(), className.length()));
        if (!ce) {
            ELOG_DEBUG(log, "instrumentFunction Class not found. Function " PRsv "::" PRsv " not found and cannot be instrumented.", PRsvArg(className), PRsvArg(functionName));
            return false;
        }

        table = &ce->function_table;
        classHash = ZSTR_HASH(ce->name);
    }

    if (!table) {
        return false;
    }

   	zend_function *func = reinterpret_cast<zend_function *>(zend_hash_str_find_ptr(table, functionName.data(), functionName.length()));
    if (!func) {
        ELOG_DEBUG(log, "instrumentFunction " PRsv "::" PRsv " not found and cannot be instrumented.", PRsvArg(className), PRsvArg(functionName));
        return false;
    }

    zend_ulong funcHash = ZSTR_HASH(func->common.function_name);
    zend_ulong hash = classHash ^ (funcHash << 1);

    ELOG_DEBUG(log, "instrumentFunction 0x%X " PRsv "::" PRsv " type: %s is instrumented", hash, PRsvArg(className), PRsvArg(functionName), func->common.type == ZEND_INTERNAL_FUNCTION ? "internal" : "user");

    reinterpret_cast<InstrumentedFunctionHooksStorage_t *>(EAPM_GL(hooksStorage_).get())->store(hash, AutoZval{callableOnEntry}, AutoZval{callableOnExit});

    // we only keep original handler for internal (native) functions
    if (func->common.type == ZEND_INTERNAL_FUNCTION) {
        if (func->internal_function.handler != internal_function_handler) {
            InternalStorage_t::getInstance().store(hash, func->internal_function.handler);
            func->internal_function.handler = internal_function_handler;
        }
        ELOG_DEBUG(log, PRsv "::" PRsv " instrumented, key: 0x%X", PRsvArg(className), PRsvArg(functionName), hash);
    }

    return true;
}



void elasticObserverFcallBeginHandler(zend_execute_data *execute_data) {
    auto hash = getClassAndFunctionHashFromExecuteData(execute_data);
    ELOG_TRACE(EAPM_GL(logger_), "elasticObserverFcallBeginHandler hash 0x%X", hash);

    auto callbacks = reinterpret_cast<InstrumentedFunctionHooksStorage_t *>(EAPM_GL(hooksStorage_).get())->find(hash);
    if (!callbacks) {
        auto [cls, func] = getClassAndFunctionName(execute_data);
        ELOG_ERROR(EAPM_GL(logger_), "Unable to find prehook handler for 0x%X " PRsv "::" PRsv, hash, PRsvArg(cls), PRsvArg(func));
        return;
    }

    for (auto &callback : *callbacks) {
        try {
            AutomaticExceptionStateRestorer restorer;
            callPreHook(callback.first);

            handleAndReleaseHookException(EG(exception));
        } catch (std::exception const &e) {
            auto [cls, func] = getClassAndFunctionName(execute_data);
            ELOG_CRITICAL(EAPM_GL(logger_), "elasticObserverFcallBeginHandler. Unable to call prehook for 0x%X " PRsv "::" PRsv, hash, PRsvArg(cls), PRsvArg(func));
        }
    }
}

void elasticObserverFcallEndHandler(zend_execute_data *execute_data, zval *retval) {
    auto hash = getClassAndFunctionHashFromExecuteData(execute_data);
    ELOG_TRACE(EAPM_GL(logger_), "elasticObserverFcallEndHandler hash 0x%X", hash);

    auto callbacks = reinterpret_cast<InstrumentedFunctionHooksStorage_t *>(EAPM_GL(hooksStorage_).get())->find(hash);
    if (!callbacks) {
        auto [cls, func] = getClassAndFunctionName(execute_data);
        ELOG_ERROR(EAPM_GL(logger_), "Unable to find posthook handler for 0x%X " PRsv "::" PRsv, hash, PRsvArg(cls), PRsvArg(func));
        return;
    }

    for (auto &callback : *callbacks) {
        try {
            AutomaticExceptionStateRestorer restorer;
            callPostHook(callback.second, retval, restorer.getException());
            handleAndReleaseHookException(EG(exception));
        } catch (std::exception const &e) {
            auto [cls, func] = getClassAndFunctionName(execute_data);
            ELOG_CRITICAL(EAPM_GL(logger_), "elasticObserverFcallBeginHandler. Unable to call posthook for 0x%X " PRsv "::" PRsv, hash, PRsvArg(cls), PRsvArg(func));
        }
    }
}

zend_observer_fcall_handlers elasticRegisterObserver(zend_execute_data *execute_data) {
    if (execute_data->func->common.type == ZEND_INTERNAL_FUNCTION) {
        return {nullptr, nullptr};
    }

    auto hash = getClassAndFunctionHashFromExecuteData(execute_data);
    if (hash == 0) {
        ELOG_TRACE(EAPM_GL(logger_), "elasticRegisterObserver main scope");
        return {nullptr, nullptr};
    }

    auto callbacks = reinterpret_cast<InstrumentedFunctionHooksStorage_t *>(EAPM_GL(hooksStorage_).get())->find(hash);
    if (!callbacks) {
        ELOG_TRACE(EAPM_GL(logger_), "elasticRegisterObserver hash: 0x%X, not instrumented", hash);
        return {nullptr, nullptr};
    }
    ELOG_TRACE(EAPM_GL(logger_), "elasticRegisterObserver hash: 0x%X", hash);

    bool havePreHook = false;
    bool havePostHook = false;
    for (auto const &item : *callbacks) {
        if (!item.first.isNull()) {
            havePreHook = true;
        }
        if (!item.second.isNull()) {
            havePostHook = true;
        }
        if (havePreHook && havePostHook) {
            break;
        }
    }
    ELOG_TRACE(EAPM_GL(logger_), "elasticRegisterObserver hash: 0x%X, havePreHooks: %d havePostHooks: %d", hash, havePreHook, havePostHook);

    return {havePreHook ? elasticObserverFcallBeginHandler : nullptr, havePostHook ? elasticObserverFcallEndHandler : nullptr};
}



}
