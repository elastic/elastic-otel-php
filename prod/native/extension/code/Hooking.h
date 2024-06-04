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

#pragma once

#include <main/php_version.h>
#include <Zend/zend_execute.h>
#include <Zend/zend_exceptions.h>
#include <Zend/zend_types.h>

#include <optional>

namespace elasticapm::php {

class Hooking {
public:

#if PHP_VERSION_ID < 80100
    using zend_error_cb_t = void (*)(int type, const char *error_filename, const uint32_t error_lineno, zend_string *message); // 8.0
#else
    using zend_error_cb_t = void (*)(int type, zend_string *error_filename, const uint32_t error_lineno, zend_string *message); // 8.1+
#endif
    using zend_throw_exception_hook_t = void(*)(zend_object *ex);


    // using zend_execute_internal_t = void (*)(zend_execute_data *execute_data, zval *return_value);
    using zend_interrupt_function_t = void (*)(zend_execute_data *execute_data);

    static Hooking &getInstance() {
        static Hooking instance;
        return instance;
    }

    void fetchOriginalHooks() {
        // original_execute_internal_ = zend_execute_internal;
        // original_zend_interrupt_function_ = zend_interrupt_function;
        // original_zend_error_cb_ = zend_error_cb;
        // original_zend_throw_exception_hook_ = zend_throw_exception_hook;
    }

    void restoreOriginalHooks() {
        // zend_execute_internal = original_execute_internal_;
        // zend_interrupt_function = original_zend_interrupt_function_;
        // zend_error_cb = original_zend_error_cb_;
        // zend_throw_exception_hook = original_zend_throw_exception_hook_;
    }

    void replaceHooks();

    // zend_execute_internal_t getOriginalExecuteInternal() {
    //     return original_execute_internal_;
    // }

    // zend_interrupt_function_t getOriginalZendInterruptFunction() {
    //     return original_zend_interrupt_function_;
    // }

    // zend_error_cb_t getOriginalZendErrorCb() {
    //     return original_zend_error_cb_;
    // }

    // zend_throw_exception_hook_t getOriginalZendThrowExceptionHook() {
    //     return original_zend_throw_exception_hook_;
    // }

private:
    Hooking(Hooking const &) = delete;
    void operator=(Hooking const &) = delete;
    Hooking() = default;

    // zend_execute_internal_t original_execute_internal_ = nullptr;
    // zend_interrupt_function_t original_zend_interrupt_function_ = nullptr;
    // zend_error_cb_t original_zend_error_cb_ = nullptr;
    // zend_throw_exception_hook_t original_zend_throw_exception_hook_ = nullptr;
};

} // namespace elasticapm::php