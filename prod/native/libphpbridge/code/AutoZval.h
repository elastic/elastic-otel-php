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
#include <format>
#include <iostream>
#include <optional>
#include <stdexcept>
#include <type_traits>
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

    constexpr const zval *get() const noexcept {
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

    constexpr void arrayInit() {
        array_init(&value);
    }

    constexpr void arrayAddNextWithRef(zval *val) {
        Z_TRY_ADDREF_P(val);
        add_next_index_zval(&value, val);
    }

    constexpr void arrayAddNextWithRef(AutoZval const &val) {
        arrayAddNextWithRef(const_cast<zval *>(val.get()));
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

    bool isStringValidUtf8() const {
        return (GC_FLAGS(Z_STR(value)) & IS_STR_VALID_UTF8);
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

    bool instanceOf(std::string_view type) const {
        if (!isObject()) {
            throw std::runtime_error("Non-object");
        }
        auto objectType = std::string_view{ZSTR_VAL(Z_OBJCE(value)->name), ZSTR_LEN(Z_OBJCE(value)->name)};
        return type == objectType;
    }

    AutoZval const &assertObjectType(std::string_view type) const {
        if (!instanceOf(type)) {
            auto objectType = std::string_view{ZSTR_VAL(Z_OBJCE(value)->name), ZSTR_LEN(Z_OBJCE(value)->name)};
            throw std::runtime_error(std::format("Invalid object type: expected '{}', but got '{}'", type, objectType));
        }

        return *this;
    }

    std::optional<std::string_view> getOptStringView() const {
        if (!isString()) {
            return std::nullopt;
        }
        return std::string_view{Z_STRVAL(value), Z_STRLEN(value)};
    }

    std::string_view getStringView() const {
        if (!isString()) {
            throw std::runtime_error("Not a string");
        }
        return {Z_STRVAL(value), Z_STRLEN(value)};
    }

    std::optional<zend_long> getOptLong() const {
        if (isLong()) {
            return Z_LVAL(value);
        }
        return std::nullopt;
    }

    double getNumberAsDouble() const {
        if (isDouble()) {
            return Z_DVAL(value);
        } else if (isLong()) {
            return static_cast<double>(Z_LVAL(value));
        }
        throw std::runtime_error("Not a number");
    }

    zend_long getNumberAsLong() const {
        if (isLong()) {
            return Z_LVAL(value);
        } else if (isDouble()) {
            return static_cast<zend_long>(Z_DVAL(value));
        }
        throw std::runtime_error("Not a number");
    }

    zend_long getLong() const {
        if (!isLong()) {
            throw std::runtime_error("Not a long");
        }
        return Z_LVAL(value);
    }

    double getDouble() const {
        if (!isDouble()) {
            throw std::runtime_error("Not a double");
        }
        return Z_DVAL(value);
    }

    bool getBoolean() const {
        if (Z_TYPE_P(&value) == IS_TRUE) {
            return true;
        } else if (Z_TYPE_P(&value) == IS_FALSE) {
            return false;
        } else {
            throw std::runtime_error("Not a boolean");
        }
    }

    uint32_t getArrayCount() const {
        return zend_array_count(Z_ARRVAL(value));
    }
    // =============================
    // Iterator for values only
    // =============================
    template <typename TP = AutoZval>
    class IteratorImpl {
    public:
        using value_type = TP;

        IteratorImpl(HashTable *ht, bool end = false) : ht_(ht), end_(end) {
            if (!end_) {
                if (!ht_ || zend_array_count(ht_) == 0) {
                    end_ = true;
                } else {
                    zend_hash_internal_pointer_reset(ht_);
                    moveToValid();
                }
            }
        }

        value_type operator*() const {
            zval *val = zend_hash_get_current_data(ht_);
            return val ? AutoZval(val) : AutoZval();
        }

        IteratorImpl &operator++() {
            zend_hash_move_forward(ht_);
            if (zend_hash_has_more_elements(ht_) != SUCCESS) {
                end_ = true;
            } else {
                moveToValid();
            }
            return *this;
        }

        bool operator!=(const IteratorImpl &other) const {
            return ht_ != other.ht_ || end_ != other.end_;
        }

    private:
        void moveToValid() {
            while (zend_hash_has_more_elements(ht_) == SUCCESS) {
                zval *val = zend_hash_get_current_data(ht_);
                if (val && !Z_ISUNDEF_P(val)) {
                    return;
                }
                zend_hash_move_forward(ht_);
            }
            end_ = true;
        }

        HashTable *ht_;
        bool end_;
    };

    using iterator = IteratorImpl<AutoZval>;
    using const_iterator = IteratorImpl<const AutoZval>;

    iterator begin() {
        if (!isArray())
            throw std::runtime_error("Zval is not an array");
        return iterator(Z_ARRVAL(value), false);
    }

    iterator end() {
        if (!isArray())
            throw std::runtime_error("Zval is not an array");
        return iterator(Z_ARRVAL(value), true);
    }

    const_iterator begin() const {
        return cbegin();
    }
    const_iterator end() const {
        return cend();
    }

    const_iterator cbegin() const {
        if (!isArray())
            throw std::runtime_error("Zval is not an array");
        return const_iterator(Z_ARRVAL(value), false);
    }

    const_iterator cend() const {
        if (!isArray())
            throw std::runtime_error("Zval is not an array");
        return const_iterator(Z_ARRVAL(value), true);
    }

    // =============================
    // Iterator for key-value pairs
    // =============================
    class KeyValueIterator {
    public:
        using Key = std::variant<std::string_view, zend_ulong>;
        using Value = AutoZval;
        using Pair = std::pair<Key, Value>;

        explicit KeyValueIterator(HashTable *ht, bool end = false) : ht_(ht), end_(end) {
            if (!end_) {
                if (!ht_ || zend_array_count(ht_) == 0) {
                    end_ = true;
                } else {
                    zend_hash_internal_pointer_reset(ht_);
                    moveToValid();
                }
            }
        }

        Pair operator*() const {
            zend_string *key_str = nullptr;
            zend_ulong key_index = 0;

            int key_type = zend_hash_get_current_key(ht_, &key_str, &key_index);
            Key key;
            if (key_type == HASH_KEY_IS_STRING && key_str) {
                key = std::string_view(ZSTR_VAL(key_str), ZSTR_LEN(key_str));
            } else {
                key = key_index;
            }

            zval *val = zend_hash_get_current_data(ht_);
            return {key, val ? AutoZval(val) : AutoZval()};
        }

        KeyValueIterator &operator++() {
            zend_hash_move_forward(ht_);
            if (zend_hash_has_more_elements(ht_) != SUCCESS) {
                end_ = true;
            } else {
                moveToValid();
            }
            return *this;
        }

        bool operator!=(const KeyValueIterator &other) const {
            return ht_ != other.ht_ || end_ != other.end_;
        }

    private:
        void moveToValid() {
            while (zend_hash_has_more_elements(ht_) == SUCCESS) {
                zval *val = zend_hash_get_current_data(ht_);
                if (val && !Z_ISUNDEF_P(val)) {
                    return;
                }
                zend_hash_move_forward(ht_);
            }
            end_ = true;
        }

        HashTable *ht_;
        bool end_;
    };

    KeyValueIterator kvbegin() const {
        if (!isArray())
            throw std::runtime_error("Zval is not an array");
        return KeyValueIterator(Z_ARRVAL(value), false);
    }

    KeyValueIterator kvend() const {
        if (!isArray())
            throw std::runtime_error("Zval is not an array");
        return KeyValueIterator(Z_ARRVAL(value), true);
    }

private:
    zval value;
};

static_assert(sizeof(zval) == sizeof(AutoZval));
static_assert(sizeof(zval[10]) == sizeof(std::array<AutoZval, 10>));

} // namespace elasticapm::php