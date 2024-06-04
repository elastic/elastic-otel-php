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

#include <Zend/zend_API.h>
#include <Zend/zend_types.h>
#include <Zend/zend_variables.h>

#include <array>
#include <stdexcept>
#include <type_traits>
#include <iostream>
namespace elasticapm::php {

template<typename T>
concept NotZvalPointer = !std::same_as<T, zval *>;

class AutoZval {
public:
    AutoZval(const AutoZval &) = delete;

    AutoZval &operator=(const AutoZval &other) { // copy
    // TODO implement copy constructor or safer - copy_full() and copy_ref() methods
        ZVAL_COPY(&value, &other.value);
        return *this;
    }

    AutoZval &operator=(AutoZval &&other) { // move
        memcpy(&value, &other.value, sizeof(zval));
        ZVAL_UNDEF(&other.value); // prevent destructor
        return *this;
    }

    AutoZval(AutoZval &&other) { // move
        memcpy(&value, &other.value, sizeof(zval));
        ZVAL_UNDEF(&other.value); // prevent destructor
    }

    AutoZval() {
        ZVAL_UNDEF(&value);
    }

    explicit AutoZval(zval *zv) { // copy from pointer (add reference)
        if (!zv) {
            setNull();
            return;
        }
        ZVAL_COPY(&value, zv);
    }

    AutoZval(auto &&value) {
        set(value);
    }

    ~AutoZval() {
        zval_ptr_dtor(&value);
    }

    constexpr zval &operator*() noexcept {
        return value;
    }

    constexpr zval *get() noexcept {
        return &value;
    }

    zval *data() {
        return &value;
    }


    void setString(std::string_view str) {
        ZVAL_STRINGL(&value, str.data(), str.length());
    }

    template<typename T, std::enable_if_t< std::is_convertible<T, zend_long>::value, bool> = true >
    constexpr void setLong(T val) {
        ZVAL_LONG(&value, val);
    }

    constexpr void setNull() {
        ZVAL_NULL(&value);
    }

    constexpr void setDouble(double val) {
        ZVAL_DOUBLE(&value, val);
    }

    constexpr void set(NotZvalPointer auto &&val) {
        if constexpr (std::is_same_v<decltype(val), bool>) {
            ZVAL_BOOL(&value, val);
        } else if constexpr (std::is_floating_point_v<std::remove_reference_t<decltype(val)>>) {
            ZVAL_DOUBLE(&value, val);
        } else if constexpr (!std::is_null_pointer_v<std::remove_reference_t<decltype(val)>> && std::is_convertible_v<decltype(val), std::string_view>) {
            std::string_view sv{val};
            ZVAL_STRINGL(&value, sv.data(), sv.length());
        } else if constexpr (std::is_null_pointer_v<std::remove_reference_t<decltype(val)>>) {
            ZVAL_NULL(&value);
        } else {
            ZVAL_LONG(&value, val);
        }
    }

    bool isNull() const {
        return Z_TYPE_P(&value) == IS_NULL;
    }
    bool isUndef() const {
        return Z_TYPE_P(&value) == IS_UNDEF;
    }
    bool isString() const {
        return Z_TYPE_P(&value) == IS_STRING;
    }
    bool isLong() const {
        return Z_TYPE_P(&value) == IS_LONG;
    }
    bool isDouble() const {
        return Z_TYPE_P(&value) == IS_DOUBLE;
    }
    bool isBoolean() const {
        return Z_TYPE_P(&value) == IS_TRUE || Z_TYPE_P(&value) == IS_FALSE;
    }
    bool isArray() const {
        return Z_TYPE_P(&value) == IS_ARRAY;
    }
    bool isObject() const {
        return Z_TYPE_P(&value) == IS_OBJECT;
    }
    bool isResource() const {
        return Z_TYPE_P(&value) == IS_RESOURCE;
    }

    uint8_t getType() const {
        return Z_TYPE_P(&value);
    }

private:
    zval value;
};

static_assert(sizeof(zval) == sizeof(AutoZval));
static_assert(sizeof(zval[10]) == sizeof(std::array<AutoZval, 10>));

} // namespace elasticapm::php