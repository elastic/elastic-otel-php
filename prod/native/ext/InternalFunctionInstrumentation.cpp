#include "ModuleGlobals.h"
#include "InternalFunctionInstrumentation.h"
#include "AutoZval.h"
#include "PhpBridge.h"
#include "LoggerInterface.h"

#include "Zend/zend.h"
#include "Zend/zend_exceptions.h"
#include "Zend/zend_hash.h"
#include "Zend/zend_globals.h"


#include "InternalFunctionInstrumentationStorage.h"
#include "RequestScope.h"
#include "InstrumentedFunctionHooksStorage.h"

namespace elasticapm::php {

using InternalStorage_t = InternalFunctionInstrumentationStorage<zend_ulong, zif_handler, AutoZval<1>>;

struct SavedException {
    zend_object *exception = nullptr;
    zend_object *prev_exception = nullptr;
    const zend_op *opline_before_exception = nullptr;
    std::optional<const zend_op *> opline;
};

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

void handleAndReleaseHookException(zend_object *exception) {
    if (exception) {
        //TODO log exception from hook

        ELOG_CRITICAL(EAPM_GL(logger_), "TODO exception was thrown in instrumentation hook");

        OBJ_RELEASE(EG(exception));
    }
}



void callPreHook(AutoZval<> &prehook) {
    zend_fcall_info fci = empty_fcall_info;
    zend_fcall_info_cache fcc = empty_fcall_info_cache;

    if (zend_fcall_info_init(const_cast<zval *>(prehook.get()), 0, &fci, &fcc, nullptr, nullptr) == ZEND_RESULT_CODE::FAILURE) {
        throw std::runtime_error("Unable to initialize prehook fcall");
    }

    AutoZval<6> parameters;
    getScopeNameOrThis(parameters.get(0), EG(current_execute_data));
    getCallArguments(parameters.get(1), EG(current_execute_data));
    getFunctionDeclaringScope(parameters.get(2), EG(current_execute_data));
    getFunctionName(parameters.get(3), EG(current_execute_data));
    getFunctionDeclarationFileName(parameters.get(4), EG(current_execute_data));
    getFunctionDeclarationLineNo(parameters.get(5), EG(current_execute_data));

    AutoZval ret;
    fci.param_count = parameters.size();
    fci.params = parameters.get();
    fci.named_params = NULL;
    fci.retval = ret.get();
    if (zend_call_function(&fci, &fcc) != SUCCESS) {
        throw std::runtime_error("Unable to call prehook function");
    }
}

void callPostHook(AutoZval<> &hook, zval *return_value, zend_object *exception) {
    zend_fcall_info fci = empty_fcall_info;
    zend_fcall_info_cache fcc = empty_fcall_info_cache;

    if (zend_fcall_info_init(const_cast<zval *>(hook.get()), 0, &fci, &fcc, nullptr, nullptr) == ZEND_RESULT_CODE::FAILURE) {
        throw std::runtime_error("Unable to initialize posthook fcall");
    }

    AutoZval<8> parameters;
    getScopeNameOrThis(parameters.get(0), EG(current_execute_data));
    getCallArguments(parameters.get(1), EG(current_execute_data));
    getFunctionReturnValue(parameters.get(2), return_value);
    getCurrentException(parameters.get(3), exception);
    getFunctionDeclaringScope(parameters.get(4), EG(current_execute_data));
    getFunctionName(parameters.get(5), EG(current_execute_data));
    getFunctionDeclarationFileName(parameters.get(6), EG(current_execute_data));
    getFunctionDeclarationLineNo(parameters.get(7), EG(current_execute_data));

    AutoZval ret;
    fci.param_count = parameters.size();
    fci.params = parameters.get();
    fci.named_params = NULL;
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
    zend_ulong classHash = 0;
    if (execute_data->func->common.scope && execute_data->func->common.scope->name) {
        classHash = ZSTR_HASH(execute_data->func->common.scope->name);
    }
    zend_ulong funcHash = ZSTR_HASH(execute_data->func->common.function_name);
    zend_ulong hash = classHash ^ (funcHash << 1);


    auto data = InternalStorage_t::getInstance().get(hash);
    if (!data) {
        std::string_view cls;
        if (execute_data->func->common.scope && execute_data->func->common.scope->name) {
            cls = {ZSTR_VAL(execute_data->func->common.scope->name), ZSTR_LEN(execute_data->func->common.scope->name)};
        }
        std::string_view func(ZSTR_VAL(execute_data->func->common.function_name), ZSTR_LEN(execute_data->func->common.function_name));
        ELOG_CRITICAL(EAPM_GL(logger_), "Unable to find function metadata " PRsv ":" PRsv, PRsvArg(cls), PRsvArg(func));
    }

    if (!EAPM_GL(requestScope_)->isFunctional()) {
        callOriginalHandler(data->originalHandler, INTERNAL_FUNCTION_PARAM_PASSTHRU);
        return;
    }

    try {

        auto &callbacks = reinterpret_cast<InstrumentedFunctionHooksStorage_t *>(EAPM_GL(hooksStorage_).get())->find(hash);

        for (auto &callback : callbacks) {
            try {
                auto exceptionState = saveExceptionState();
                callPreHook(callback.first);

                handleAndReleaseHookException(EG(exception));
                restoreExceptionState(std::move(exceptionState));
            } catch (std::exception const &e) {
                ELOG_CRITICAL(EAPM_GL(logger_), "Unable to call prehook");
            }
        }

        callOriginalHandler(data->originalHandler, INTERNAL_FUNCTION_PARAM_PASSTHRU);

        auto exceptionFromInstrumentedFunction = EG(exception);

        for (auto &callback : callbacks) {
            try {
                auto exceptionState = saveExceptionState();
                callPostHook(callback.second, return_value, exceptionFromInstrumentedFunction);

                handleAndReleaseHookException(EG(exception));
                restoreExceptionState(std::move(exceptionState));
            } catch (std::exception const &e) {
                ELOG_CRITICAL(EAPM_GL(logger_), "Unable to call posthook");
            }
        }
    } catch (std::exception const &e) {
        ELOG_WARNING(EAPM_GL(logger_), e.what());
        callOriginalHandler(data->originalHandler, INTERNAL_FUNCTION_PARAM_PASSTHRU);
    }

}

bool instrumentInternalFunction(LoggerInterface *log, std::string_view className, std::string_view functionName, zval *callableOnEntry, zval *callableOnExit) {
    //TODO if called from other place that MINIT - make ot thread safe in ZTS
    //TODO return hash and map on php side? phpside::enter(hash, args), phpside:exit(hash, rv) ?
    //TODO use hash struct instead of combined to prevent collisions

    HashTable *table = nullptr;
    zend_ulong classHash = 0;

    if (className.empty()) { // looking for function
        table = EG(function_table);
    } else {
        if (!EG(class_table)) {
            ELOG_DEBUG(log, "Class table is empty. Function " PRsv "::" PRsv " not found and cannot be instrumented.", PRsvArg(className), PRsvArg(functionName));
            return false;
        }

        auto ce = static_cast<zend_class_entry *>(zend_hash_str_find_ptr(EG(class_table), className.data(), className.length()));
        if (!ce) {
            ELOG_DEBUG(log, "Class not found. Function " PRsv "::" PRsv " not found and cannot be instrumented.", PRsvArg(className), PRsvArg(functionName));
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
        ELOG_DEBUG(log, "Function " PRsv "::" PRsv " not found and cannot be instrumented.", PRsvArg(className), PRsvArg(functionName));
        return false;
    }

    zend_ulong funcHash = ZSTR_HASH(func->common.function_name);
    zend_ulong hash = classHash ^ (funcHash << 1);

    reinterpret_cast<InstrumentedFunctionHooksStorage_t *>(EAPM_GL(hooksStorage_).get())->store(hash, AutoZval{callableOnEntry}, AutoZval{callableOnExit});

    InternalStorage_t::getInstance().store(hash, AutoZval{callableOnEntry}, AutoZval{callableOnExit}, func->internal_function.handler == internal_function_handler ? std::optional<zif_handler>{} : func->internal_function.handler);
    if (func->internal_function.handler != internal_function_handler) {
        func->internal_function.handler = internal_function_handler;
    }


    ELOG_DEBUG(log, PRsv "::" PRsv " instrumented, key: %d", PRsvArg(className), PRsvArg(functionName), hash);

    return true;
}
}
