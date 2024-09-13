/*
 * Copyright Elasticsearch B.V. and/or licensed to Elasticsearch B.V. under one
 * or more contributor license agreements. See the NOTICE file distributed with
 * this work for additional information regarding copyright
 * ownership. Elasticsearch B.V. licenses this file to you under
 * the Apache License, Version 2.0 (the "License"); you may
 * not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *  http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied.  See the License for the
 * specific language governing permissions and limitations
 * under the License.
 */

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
#include <algorithm>

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

uint32_t getFunctionArgumentIndex(zend_string *name, zend_function *function) {
	uint32_t numArgs = function->common.num_args;
	if (function->type == ZEND_USER_FUNCTION || (function->common.fn_flags & ZEND_ACC_USER_ARG_INFO)) {
		for (uint32_t i = 0; i < numArgs; i++) {
			if (zend_string_equals(name, function->op_array.arg_info[i].name)) {
				return i;
			}
		}
	} else {
        std::string_view nameSv(ZSTR_VAL(name), ZSTR_LEN(name));
		for (uint32_t i = 0; i < numArgs; i++) {
            std::string_view argName(function->internal_function.arg_info[i].name);
            if (nameSv == argName) {
                return i;
            }
		}
	}
    throw std::runtime_error("argument not found");
}

void argsPostProcessing(AutoZval &functionArgs, AutoZval &returnValue) {
    if (!returnValue.isArray()) {
        return;
    }
    if (zend_is_identical(returnValue.get(), functionArgs.get())) {
        return;
    }

    zend_ulong argIndex = 0;
    zend_string *argStrKey = nullptr;
    zval *argValue = nullptr;

    zend_execute_data *execute_data = EG(current_execute_data);

    uint32_t requiredArgsCount = execute_data->func->type == ZEND_INTERNAL_FUNCTION ? ZEND_CALL_NUM_ARGS(execute_data) : execute_data->func->op_array.last_var;
    uint32_t initalCallNumArgs = ZEND_CALL_NUM_ARGS(execute_data);

    ELOG_DEBUG(EAPM_GL(logger_), "argsPostProcessing requiredArgsCount: %d initialCallNumArgs: %d", requiredArgsCount, initalCallNumArgs);

    uint32_t highestArgIdx = 0;
    ZEND_HASH_FOREACH_KEY_VAL(Z_ARR_P(returnValue.get()), argIndex, argStrKey, argValue) {
        if (!argStrKey) {
            highestArgIdx = std::max(highestArgIdx, (uint32_t)argIndex);
        }
    } ZEND_HASH_FOREACH_END();

    ELOG_DEBUG(EAPM_GL(logger_), "argsPostProcessing highestArgIdx: %d vm_stack free: %d", highestArgIdx, EG(vm_stack_end) - EG(vm_stack_top));

    // extending stack and undefining potential gaps
    if (highestArgIdx + 1 > initalCallNumArgs) {
        uint32_t howManyArgsToAdd = highestArgIdx + 1 - initalCallNumArgs;
        ELOG_DEBUG(EAPM_GL(logger_), "postProcessing trying extend stack frame with %d arguments", howManyArgsToAdd);

        zend_vm_stack_extend_call_frame(&execute_data, initalCallNumArgs, howManyArgsToAdd);

        for (uint32_t idx = 0; idx < howManyArgsToAdd; ++idx) {
            zval *target = ZEND_CALL_ARG(execute_data, execute_data->func->type == ZEND_INTERNAL_FUNCTION ? initalCallNumArgs + idx + 1 :  initalCallNumArgs + idx + 1 + execute_data->func->op_array.T);
            ZVAL_UNDEF(target);
        }
        ZEND_CALL_NUM_ARGS(execute_data) += howManyArgsToAdd;
        ZEND_ADD_CALL_FLAG(execute_data, ZEND_CALL_FREE_EXTRA_ARGS);
        ZEND_ADD_CALL_FLAG(execute_data, ZEND_CALL_MAY_HAVE_UNDEF);
    }

    ZEND_HASH_FOREACH_KEY_VAL(Z_ARR_P(returnValue.get()), argIndex, argStrKey, argValue) {
        if (argStrKey) {
            ELOG_DEBUG(EAPM_GL(logger_), "argsPostProcessing str: %s", ZSTR_VAL(argStrKey));

            try {
                argIndex = getFunctionArgumentIndex(argStrKey, execute_data->func);
            } catch (std::exception const &e) {
                ELOG_WARNING(EAPM_GL(logger_), "postProcessing argument index not found for: '%s'", ZSTR_VAL(argStrKey));
                continue;
            }
        }
        ELOG_DEBUG(EAPM_GL(logger_), "argsPostProcessing idx: %d", argIndex);

        zval *target = nullptr;
        if (argIndex < requiredArgsCount) {
            target = ZEND_CALL_ARG(execute_data, argIndex + 1);
        } else {
            target = ZEND_CALL_ARG(execute_data, execute_data->func->type == ZEND_INTERNAL_FUNCTION ? argIndex + 1 : argIndex + 1 + execute_data->func->op_array.T);

        }
        //TODO consider refs
        zval_ptr_dtor(target);
        ZVAL_COPY(target, argValue);
    } ZEND_HASH_FOREACH_END();
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

    argsPostProcessing(parameters[1], ret);
}

void callPostHook(AutoZval &hook, zval *return_value, zend_object *exception, zend_execute_data *execute_data) {
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

    AutoZval hookRv;
    fci.param_count = parameters.size();
    fci.params = parameters[0].get();
    fci.named_params = nullptr;
    fci.retval = hookRv.get();

    if (zend_call_function(&fci, &fcc) != SUCCESS) {
        throw std::runtime_error("Unable to call posthook function");
    }

    if (Z_TYPE_P(hookRv.get()) == IS_UNDEF) {
        return;
    }

    if (!return_value) {
        return;
    }

    // thre is no way to distinguish if posthook returned NULL, becuase in PHP functions are always returning NULL, even if there is no return keyword
    // in that case we can only try to overwrite return value for posthooks with return value type specified explicitly
    if (!(fcc.function_handler->op_array.fn_flags & ZEND_ACC_HAS_RETURN_TYPE) || (ZEND_TYPE_PURE_MASK(fcc.function_handler->common.arg_info[-1].type) & MAY_BE_VOID)) {
        ELOG_TRACE(EAPM_GL(logger_), "callPostHook hook doesn't explicitly specify return type other than void");
        return;
    }

    if (execute_data->func->op_array.fn_flags & ZEND_ACC_HAS_RETURN_TYPE) {
        // uncomment if want to block possibility of adding rv to instrumented void-rv function
        // if ((ZEND_TYPE_PURE_MASK(execute_data->func->common.arg_info[-1].type) & MAY_BE_VOID)) {
        //     return;
        // }
        bool sameType = ZEND_TYPE_CONTAINS_CODE(execute_data->func->common.arg_info[-1].type, Z_TYPE_P(hookRv.get()));
        ELOG_DEBUG(EAPM_GL(logger_), "callPostHook hasRvType: %d, isVoid: %d sameType: %d, hookRvType: %d", execute_data->func->op_array.fn_flags & ZEND_ACC_HAS_RETURN_TYPE, static_cast<bool>(ZEND_TYPE_PURE_MASK(execute_data->func->common.arg_info[-1].type) & MAY_BE_VOID), sameType, hookRv.getType());
    }

    zval_ptr_dtor(return_value);
    ZVAL_COPY(return_value, hookRv.get());
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
        if (callback.first.isNull() || callback.first.isUndef()) {
            continue;
        }

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
        if (callback.second.isNull() || callback.second.isUndef()) {
            continue;
        }

        try {
            AutomaticExceptionStateRestorer restorer;
            callPostHook(callback.second, return_value, restorer.getException(), execute_data);

            handleAndReleaseHookException(EG(exception));
        } catch (std::exception const &e) {
            auto [cls, func] = getClassAndFunctionName(execute_data);
            ELOG_CRITICAL(EAPM_GL(logger_), "%s hash: 0x%X " PRsv "::" PRsv, e.what(), hash, PRsvArg(cls), PRsvArg(func));
        }
    }

}


bool instrumentFunction(LoggerInterface *log, std::string_view cName, std::string_view fName, zval *callableOnEntry, zval *callableOnExit) {
    //TODO if called from other place that MINIT - make it thread safe in ZTS
    //TODO use hash struct instead of combined to prevent collisions

    std::string className{cName.data(), cName.length()};
    std::string functionName{fName.data(), fName.length()};

    std::transform(className.begin(), className.end(), className.begin(), [](unsigned char c){ return std::tolower(c); });
    std::transform(functionName.begin(), functionName.end(), functionName.begin(), [](unsigned char c){ return std::tolower(c); });

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

            if (log->doesMeetsLevelCondition(logLevel_trace)) {
                zend_string *argStrKey = nullptr;
                ZEND_HASH_FOREACH_STR_KEY(EG(class_table), argStrKey) {
                    if (argStrKey) {
                        ELOG_DEBUG(log, "instrumentFunction Class not found. Function " PRsv "::" PRsv " not found and cannot be instrumented. %s", PRsvArg(className), PRsvArg(functionName), ZSTR_VAL(argStrKey));
                    }
                }
                ZEND_HASH_FOREACH_END();
            }

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

    ELOG_DEBUG(log, "instrumentFunction 0x%X " PRsv "::" PRsv " type: %s is marked to be instrumented", hash, PRsvArg(className), PRsvArg(functionName), func->common.type == ZEND_INTERNAL_FUNCTION ? "internal" : "user");

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
            ELOG_CRITICAL(EAPM_GL(logger_), "elasticObserverFcallBeginHandler. Unable to call prehook for 0x%X " PRsv "::" PRsv ": '%s'", hash, PRsvArg(cls), PRsvArg(func), e.what());
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
            callPostHook(callback.second, retval, restorer.getException(), execute_data);
            handleAndReleaseHookException(EG(exception));
        } catch (std::exception const &e) {
            auto [cls, func] = getClassAndFunctionName(execute_data);
            ELOG_CRITICAL(EAPM_GL(logger_), "elasticObserverFcallEndHandler. Unable to call posthook for 0x%X " PRsv "::" PRsv ": '%s'", hash, PRsvArg(cls), PRsvArg(func), e.what());
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
        if (EAPM_GL(logger_)->doesMeetsLevelCondition(LogLevel::logLevel_trace)) {
            auto [cls, func] = getClassAndFunctionName(execute_data);
            ELOG_TRACE(EAPM_GL(logger_), "elasticRegisterObserver hash: 0x%X " PRsv "::" PRsv ", not marked to be instrumented", hash, PRsvArg(cls), PRsvArg(func));
        }

        auto ce = execute_data->func->common.scope;
        if (!ce) {
            return {nullptr, nullptr};
        }

        // lookup for class interfaces
        for (uint32_t i = 0; i < ce->num_interfaces; ++i) {
            auto classHash = ZSTR_HASH(ce->interfaces[i]->name);
            zend_ulong funcHash = ZSTR_HASH(execute_data->func->common.function_name);
            zend_ulong ifaceHash = classHash ^ (funcHash << 1);

            callbacks = reinterpret_cast<InstrumentedFunctionHooksStorage_t *>(EAPM_GL(hooksStorage_).get())->find(ifaceHash);
            if (callbacks) {
                if (EAPM_GL(logger_)->doesMeetsLevelCondition(LogLevel::logLevel_trace)) {
                    auto [cls, func] = getClassAndFunctionName(execute_data);
                    ELOG_TRACE(EAPM_GL(logger_), "elasticRegisterObserver hash: 0x%X " PRsv "::" PRsv ", will be instrumented because interface 0x%X '" PRsv "' was marked to be instrumented", hash, PRsvArg(cls), PRsvArg(func), ifaceHash, PRzsArg(ce->interfaces[i]->name));
                }
                // copy callbacks from interface storage hash to implementation hash
                for (auto &item : *callbacks) {
                    reinterpret_cast<InstrumentedFunctionHooksStorage_t *>(EAPM_GL(hooksStorage_).get())->store(hash, AutoZval(item.first.get()), AutoZval(item.second.get()));
                }
                break;
            }
        }
    }

    if (!callbacks) {
        return {nullptr, nullptr};
    }

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