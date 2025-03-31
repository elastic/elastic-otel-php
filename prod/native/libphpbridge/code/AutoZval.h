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
#include <format>
#include <type_traits>
#include <iostream>
#include <variant>

namespace elasticapm::php {

template<typename T>
concept NotZvalPointer = !std::same_as<T, zval *>;

class AutoZval {
public:
    AutoZval(const AutoZval &) = delete;

    AutoZval() {
        ZVAL_UNDEF(&value);
    }

    AutoZval &operator=(const AutoZval &other) {
        zval_ptr_dtor(&value);
        ZVAL_COPY(&value, &other.value); // add ref
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

    template <std::size_t ArgsNm = 0>
    AutoZval callMethod(std::string_view methodName, std::array<AutoZval, ArgsNm> params = {}) const { // TODO const? can modify object
        if (!isObject()) {
            throw std::runtime_error("Can't call method on non-object");
        }
        elasticapm::php::AutoZval zMethodName;
        elasticapm::php::AutoZval returnValue;
        ZVAL_STRINGL(zMethodName.get(), methodName.data(), methodName.length());

        if (_call_user_function_impl(const_cast<zval *>(&value), zMethodName.get(), returnValue.get(), params.size(), params.data()->get(), nullptr) != SUCCESS) {
            throw std::runtime_error("Unable to call user method");
        }
        return returnValue;
    }

    AutoZval readProperty(std::string_view propertyName) const {
        if (!isObject()) {
            throw std::runtime_error("Can't get property from non-object");
        }
        return AutoZval(zend_read_property(Z_OBJCE(value), Z_OBJ(value), propertyName.data(), propertyName.length(), 1, nullptr));
    }

    AutoZval const &assertObjectType(std::string_view type) const {
        if (!isObject()) {
            throw std::runtime_error("Non-object");
        }
        auto objectType = std::string_view{ZSTR_VAL(Z_OBJCE(value)->name), ZSTR_LEN(Z_OBJCE(value)->name)};
        if (type != objectType) {
            throw std::runtime_error(std::format("Invalid object type: expected '{}', but got '{}'", type, objectType));
        }

        return *this;
    }

    std::string_view getStringView() const {
        if (!isString()) {
            throw std::runtime_error("Not a string");
        }
        return {Z_STRVAL(value), Z_STRLEN(value)};
    }

    zend_long getLong() const {
        if (!isLong()) {
            throw std::runtime_error("Not an long");
        }
        return Z_LVAL(value);
    }

    double getDouble() const {
        if (!isDouble()) {
            throw std::runtime_error("Not an double");
        }
        return Z_DVAL(value);
    }

    bool getBoolean() const {
        if (Z_TYPE_P(&value) == IS_TRUE) {
            return true;
        } else if (Z_TYPE_P(&value) == IS_FALSE) {
            return false;
        } else {
            throw std::runtime_error("Not an boolean");
        }
    }

    class Iterator {
    public:
        using value_type = AutoZval;

        Iterator(HashTable *ht, uint32_t position) : ht_(ht), pos_(position) {
            moveToValid();
        }

        value_type operator*() const {
            zval *zv = zend_hash_index_find(ht_, pos_);
            return zv ? AutoZval(zv) : AutoZval(); // ZVAL_UNDEF if not exists
        }

        Iterator &operator++() {
            ++pos_;
            moveToValid();
            return *this;
        }

        bool operator!=(const Iterator &other) const {
            return ht_ != other.ht_ || pos_ != other.pos_;
        }

    private:
        void moveToValid() {
            // skip holes
            while (pos_ < ht_->nNumUsed) {
                if (!Z_ISUNDEF(ht_->arData[pos_].val)) {
                    break;
                }
                ++pos_;
            }
        }

        HashTable *ht_;
        uint32_t pos_;
    };

    Iterator begin() {
        if (!isArray())
            throw std::runtime_error("Zval is not an array");
        return Iterator(Z_ARRVAL(value), 0);
    }

    Iterator end() {
        if (!isArray())
            throw std::runtime_error("Zval is not an array");
        return Iterator(Z_ARRVAL(value), Z_ARRVAL(value)->nNumUsed);
    }

    Iterator cbegin() const {
        if (!isArray())
            throw std::runtime_error("Zval is not an array");
        return Iterator(Z_ARRVAL(value), 0);
    }

    Iterator cend() const {
        if (!isArray())
            throw std::runtime_error("Zval is not an array");
        return Iterator(Z_ARRVAL(value), Z_ARRVAL(value)->nNumUsed);
    }

    class KeyValueIterator {
    public:
        using Key = std::variant<std::string_view, zend_ulong>;
        using Value = AutoZval;
        using Pair = std::pair<Key, Value>;

        KeyValueIterator(HashTable *ht, uint32_t pos) : ht_(ht), pos_(pos) {
            moveToValid();
        }

        Pair operator*() const {
            Bucket *bucket = &ht_->arData[pos_];
            Key key;
            if (bucket->key) {
                key = std::string_view(ZSTR_VAL(bucket->key), ZSTR_LEN(bucket->key));
            } else {
                key = bucket->h;
            }
            return {key, AutoZval(&bucket->val)};
        }

        KeyValueIterator &operator++() {
            ++pos_;
            moveToValid();
            return *this;
        }

        bool operator!=(const KeyValueIterator &other) const {
            return pos_ != other.pos_ || ht_ != other.ht_;
        }

    private:
        void moveToValid() {
            while (pos_ < ht_->nNumUsed) {
                if (!Z_ISUNDEF(ht_->arData[pos_].val)) {
                    break;
                }
                ++pos_;
            }
        }

        HashTable *ht_;
        uint32_t pos_;
    };

    KeyValueIterator kvbegin() const {
        if (!isArray())
            throw std::runtime_error("Not an array");
        return KeyValueIterator(Z_ARRVAL(value), 0);
    }

    KeyValueIterator kvend() const {
        if (!isArray())
            throw std::runtime_error("Not an array");
        return KeyValueIterator(Z_ARRVAL(value), Z_ARRVAL(value)->nNumUsed);
    }

private:
    zval value;
};

static_assert(sizeof(zval) == sizeof(AutoZval));
static_assert(sizeof(zval[10]) == sizeof(std::array<AutoZval, 10>));

} // namespace elasticapm::php