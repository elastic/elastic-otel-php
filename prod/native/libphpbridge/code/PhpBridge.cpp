#include "PhpBridge.h"

#include "AutoZval.h"
#include "AutoZendString.h"
#include "CallOnScopeExit.h"

#include <Zend/zend_API.h>
#include <Zend/zend_alloc.h>
#include <Zend/zend_exceptions.h>
#include <Zend/zend_globals.h>
#include <Zend/zend_stream.h>
#include <Zend/zend_types.h>

#include <main/SAPI.h>
#include <main/php_main.h>
#include <main/php_streams.h>

#include <array>
#include <chrono>
#include <optional>
#include <string_view>

namespace elasticapm::php {

using namespace std::string_view_literals;
using namespace std::string_literals;

std::optional<std::string_view> PhpBridge::getCurrentExceptionMessage() const {
    if (!EG(exception)) {
        return std::nullopt;
    }
    return getExceptionMessage(EG(exception));
}

bool PhpBridge::callInferredSpans(std::chrono::milliseconds duration) const {
    auto phpPartFacadeClass = findClassEntry("elastic\\apm\\impl\\autoinstrument\\phppartfacade"sv);
    if (!phpPartFacadeClass) {
        return false;
    }

    auto objectOfPhpPartFacade = getClassStaticPropertyValue(phpPartFacadeClass, "singletonInstance"sv);
    if (!objectOfPhpPartFacade || Z_TYPE_P(objectOfPhpPartFacade) != IS_OBJECT) {
        return false;
    }

    auto transactionForExtensionRequest = getClassPropertyValue(phpPartFacadeClass, objectOfPhpPartFacade, "transactionForExtensionRequest"sv);
    if (!isObjectOfClass(transactionForExtensionRequest, "Elastic\\Apm\\Impl\\AutoInstrument\\TransactionForExtensionRequest")) {
        return false;
    }

    zend_class_entry *ceTransactionForExtensionRequest = Z_OBJCE_P(transactionForExtensionRequest);
    if (!ceTransactionForExtensionRequest) {
        return false;
    }

    zval *inferredSpansManager = getClassPropertyValue(ceTransactionForExtensionRequest, transactionForExtensionRequest, "inferredSpansManager"sv);
    if (!isObjectOfClass(inferredSpansManager, "Elastic\\Apm\\Impl\\InferredSpansManager")) {
        return false;
    }

    AutoZval rv;
    AutoZval params;
    params.setLong(duration.count());

    return callMethod(inferredSpansManager, "handleAutomaticCapturing"sv, params.get(), 1, rv.get());
}

std::string_view PhpBridge::getPhpSapiName() const {
    return sapi_module.name;
}

void PhpBridge::compileAndExecuteFile(std::string_view fileName) const {

#if PHP_VERSION_ID < 80100
    AutoZval fn{fileName};
#else
    AutoZendString fn(fileName);
#endif

    // zend_error_handling zeh;
    // zend_replace_error_handling(EH_THROW, nullptr, &zeh);
    // utils::callOnScopeExit callOnExit([&zeh]() { zend_restore_error_handling(&zeh); });

    auto exceptionState = saveExceptionState();

    utils::callOnScopeExit releaseException([exceptionState]() {
        if (EG(exception) && EG(exception) != (zend_object *)-1) {
            zend_object_release(EG(exception));
            EG(exception) = nullptr;
        }
        restoreExceptionState(exceptionState);
    });

	zend_op_array *opArray = nullptr;

    {
        zend_file_handle file_handle;
#if PHP_VERSION_ID >= 80100
        zend_stream_init_filename_ex(&file_handle, fn.get());
        utils::callOnScopeExit releaseFileHandle([fh = &file_handle]() { zend_destroy_file_handle(fh); });
        if (php_stream_open_for_zend_ex(&file_handle, USE_PATH|STREAM_OPEN_FOR_INCLUDE) == zend_result::FAILURE) {
#else
        if (php_stream_open_for_zend_ex(Z_STRVAL_P(fn.get()), &file_handle, USE_PATH|STREAM_OPEN_FOR_INCLUDE) == zend_result::FAILURE) {
#endif
            std::string msg = "Unable to open file for compilation '"s;
            msg.append(fileName);
            throw std::runtime_error(msg);
        }

        opArray = zend_compile_file(&file_handle, ZEND_INCLUDE);

        if (opArray && file_handle.handle.stream.handle) {
            zend_string *opened_path = nullptr;
            if (!file_handle.opened_path) {
#if PHP_VERSION_ID >= 80100
                file_handle.opened_path = opened_path = zend_string_copy(fn.get());
#else
                file_handle.opened_path = opened_path = zend_string_copy(Z_STR_P(fn.get()));
#endif
            }
            zend_hash_add_empty_element(&EG(included_files), file_handle.opened_path);
            if (opened_path) {
                zend_string_release_ex(opened_path, 0);
            }
        }
#if PHP_VERSION_ID < 80100
    zend_destroy_file_handle(&file_handle);
#endif
    }

    if (!opArray) {
        std::string msg = "Error during compilation of file '"s;
        msg.append(fileName);
        msg.append("' ");
        msg.append(exceptionToString(EG(exception)));

        throw std::runtime_error(msg);
    }

    AutoZval returnValue;
    zend_execute(opArray, returnValue.get());

    destroy_op_array(opArray);
    efree(opArray);

    if (EG(exception) && EG(exception) != (zend_object *)-1) {
        std::string msg = "Error during execution of file '"s;
        msg.append(fileName);
        msg.append("'. ");
        msg.append(exceptionToString(EG(exception)));

        throw std::runtime_error(msg);
    }
}

bool PhpBridge::callPHPSideEntryPoint(LogLevel logLevel, std::chrono::time_point<std::chrono::system_clock> requestInitStart) const {
    auto phpPartFacadeClass = findClassEntry("elastic\\apm\\impl\\autoinstrument\\phppartfacade"sv);
    if (!phpPartFacadeClass) {
        return false;
    }

    std::array<AutoZval, 2> arguments{logLevel, (double)std::chrono::duration_cast<std::chrono::microseconds>(requestInitStart.time_since_epoch()).count()};
    AutoZval rv;
    return callMethod(nullptr, "\\Elastic\\Apm\\Impl\\AutoInstrument\\PhpPartFacade::bootstrap"sv, arguments.data()->get(), arguments.size(), rv.get());
}

bool PhpBridge::callPHPSideExitPoint() const {
    auto phpPartFacadeClass = findClassEntry("elastic\\apm\\impl\\autoinstrument\\phppartfacade"sv);
    if (!phpPartFacadeClass) {
        return false;
    }

    AutoZval rv;
    return callMethod(nullptr, "\\Elastic\\Apm\\Impl\\AutoInstrument\\PhpPartFacade::shutdown"sv, nullptr, 0, rv.get());
}

bool PhpBridge::callPHPSideErrorHandler(int type, std::string_view errorFilename, uint32_t errorLineno, std::string_view message) const {
    auto phpPartFacadeClass = findClassEntry("elastic\\apm\\impl\\autoinstrument\\phppartfacade"sv);
    if (!phpPartFacadeClass) {
        return false;
    }

    std::array<AutoZval, 4> arguments{type, errorFilename, errorLineno, message};

    AutoZval rv;
    return callMethod(nullptr, "\\Elastic\\Apm\\Impl\\AutoInstrument\\PhpPartFacade::handle_error"sv, arguments.data()->get(), arguments.size(), rv.get());
}


zend_class_entry *findClassEntry(std::string_view className) {
    return static_cast<zend_class_entry *>(zend_hash_str_find_ptr(EG(class_table), className.data(), className.length()));
}

zval *getClassStaticPropertyValue(zend_class_entry *ce, std::string_view propertyName) {
    if (!ce) {
        return nullptr;
    }

    return zend_read_static_property(ce, propertyName.data(), propertyName.length(), true);
}

zval *getClassPropertyValue(zend_class_entry *ce, zval *object, std::string_view propertyName) {
    AutoZval rv;
    // TODO check with allocated on stack

    if (Z_TYPE_P(object) != IS_OBJECT) {
        return nullptr;
    }

#if PHP_VERSION_ID >= 80000
    return zend_read_property(ce, Z_OBJ_P(object), propertyName.data(), propertyName.length(), 1, rv.get());
#else
    return zend_read_property(ce, object, propertyName.data(), propertyName.length(), 1, rv.get());
#endif
}

zval *getClassPropertyValue(zend_class_entry *ce, zend_object *object, std::string_view propertyName) {
    AutoZval rv;

#if PHP_VERSION_ID >= 80000
    return zend_read_property(ce, object, propertyName.data(), propertyName.length(), 1, rv.get());
#else
    zval zvObj;
    ZVAL_OBJ(&zvObj, object);
    return zend_read_property(ce, &zvObj, propertyName.data(), propertyName.length(), 1, rv.get());
#endif
}



bool callMethod(zval *object, std::string_view methodName, zval arguments[], int32_t argCount, zval *returnValue) {
    elasticapm::utils::callOnScopeExit callOnExit([exceptionState = saveExceptionState()]() { restoreExceptionState(exceptionState); });

    AutoZval zMethodName;
    ZVAL_STRINGL(zMethodName.get(), methodName.data(), methodName.length());

#if PHP_VERSION_ID >= 80000
    return _call_user_function_impl(object, zMethodName.get(), returnValue, argCount, arguments, nullptr) == SUCCESS;
#else
    return _call_user_function_ex(object, zMethodName.get(), returnValue, argCount, arguments, 0) == SUCCESS;
#endif
}

bool isObjectOfClass(zval *object, std::string_view className) {
    if (!object || Z_TYPE_P(object) != IS_OBJECT) {
        return false;
    }

    if (!Z_OBJCE_P(object)->name) {
        return false;
    }

    return std::string_view{Z_OBJCE_P(object)->name->val, Z_OBJCE_P(object)->name->len} == className;
}



void getCallArguments(zval *zv, zend_execute_data *ex) {
    zval *p, *q;
    uint32_t i, first_extra_arg;
    uint32_t arg_count = ZEND_CALL_NUM_ARGS(ex);

    // @see
    // https://github.com/php/php-src/blob/php-8.1.0/Zend/zend_builtin_functions.c#L235
    if (!arg_count) {
        ZVAL_EMPTY_ARRAY(zv);
        return;
    }

    array_init_size(zv, arg_count);
    if (ex->func->type == ZEND_INTERNAL_FUNCTION) {
        first_extra_arg = arg_count;
    } else {
        first_extra_arg = ex->func->op_array.num_args;
    }
    zend_hash_real_init_packed(Z_ARRVAL_P(zv));
    ZEND_HASH_FILL_PACKED(Z_ARRVAL_P(zv)) {
        i = 0;
        p = ZEND_CALL_ARG(ex, 1);
        if (arg_count > first_extra_arg) {
            while (i < first_extra_arg) {
                q = p;
                if (EXPECTED(Z_TYPE_INFO_P(q) != IS_UNDEF)) {
                    ZVAL_DEREF(q);
                    if (Z_OPT_REFCOUNTED_P(q)) {
                        Z_ADDREF_P(q);
                    }
                    ZEND_HASH_FILL_SET(q);
                } else {
                    ZEND_HASH_FILL_SET_NULL();
                }
                ZEND_HASH_FILL_NEXT();
                p++;
                i++;
            }
            p = ZEND_CALL_VAR_NUM(ex, ex->func->op_array.last_var +
                                            ex->func->op_array.T);
        }
        while (i < arg_count) {
            q = p;
            if (EXPECTED(Z_TYPE_INFO_P(q) != IS_UNDEF)) {
                ZVAL_DEREF(q);
                if (Z_OPT_REFCOUNTED_P(q)) {
                    Z_ADDREF_P(q);
                }
                ZEND_HASH_FILL_SET(q);
            } else {
                ZEND_HASH_FILL_SET_NULL();
            }
            ZEND_HASH_FILL_NEXT();
            p++;
            i++;
        }
    }
    ZEND_HASH_FILL_END();
    Z_ARRVAL_P(zv)->nNumOfElements = arg_count;
}


void getScopeNameOrThis(zval *zv, zend_execute_data *execute_data) {
    if (execute_data->func->op_array.scope) {
        if (execute_data->func->op_array.fn_flags & ZEND_ACC_STATIC) {
            ZVAL_STR(zv, zend_get_called_scope(execute_data)->name);
        } else {
            ZVAL_OBJ_COPY(zv, zend_get_this_object(execute_data));
        }
    } else {
        ZVAL_NULL(zv);
    }
}

void getFunctionName(zval *zv, zend_execute_data *ex) {
    ZVAL_STR_COPY(zv, ex->func->op_array.function_name);
}

void getFunctionDeclaringScope(zval *zv, zend_execute_data *ex) {
    if (ex->func->op_array.scope) {
        ZVAL_STR_COPY(zv, ex->func->op_array.scope->name);
    } else {
        ZVAL_NULL(zv);
    }
}

void getFunctionDeclarationFileName(zval *zv, zend_execute_data *ex) {
    if (ex->func->type != ZEND_INTERNAL_FUNCTION) {
        ZVAL_STR_COPY(zv, ex->func->op_array.filename);
    } else {
        ZVAL_NULL(zv);
    }
}
void getFunctionDeclarationLineNo(zval *zv, zend_execute_data *ex) {
    if (ex->func->type != ZEND_INTERNAL_FUNCTION) {
        ZVAL_LONG(zv, ex->func->op_array.line_start);
    } else {
        ZVAL_NULL(zv);
    }
}

void getFunctionReturnValue(zval *zv, zval *retval) {
    if (UNEXPECTED(!retval || Z_ISUNDEF_P(retval))) {
        ZVAL_NULL(zv);
    } else {
        ZVAL_COPY(zv, retval);
    }
}

void getCurrentException(zval *zv, zend_object *exception) {
    if (exception && zend_is_unwind_exit(exception)) {
        ZVAL_NULL(zv);
    } else if (UNEXPECTED(exception)) {
        ZVAL_OBJ_COPY(zv, exception);
    } else {
        ZVAL_NULL(zv);
    }
}


} // namespace elasticapm::php
