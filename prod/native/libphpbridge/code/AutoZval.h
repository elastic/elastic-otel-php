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

template <std::size_t SIZE = 1> class AutoZval {
public:
    AutoZval(const AutoZval &) = delete;

    AutoZval &operator=(const AutoZval &other) { // copy
    // TODO implement copy constructor or safer - copy_full() and copy_ref() methods
        memcpy(&value, &other.value, sizeof(zval) * SIZE);
        for (std::size_t idx = 0; idx < SIZE; ++idx) {
            ZVAL_COPY(&value[idx], &other.value[idx]);
        }
        return *this;
    }

    AutoZval &operator=(AutoZval &&other) { // move
        memcpy(&value, &other.value, sizeof(zval) * SIZE);
        for (std::size_t idx = 0; idx < SIZE; ++idx) {
            ZVAL_COPY_VALUE(&value[idx], &other.value[idx]);
            ZVAL_UNDEF(&other.value[idx]); // prevent destructor
        }
        return *this;
    }

    AutoZval(AutoZval &&other) { // move
        memcpy(&value, &other.value, sizeof(zval) * SIZE);
        for (std::size_t idx = 0; idx < SIZE; ++idx) {
            ZVAL_UNDEF(&other.value[idx]); // prevent destructor
        }
    }

    AutoZval() {
        for (std::size_t idx = 0; idx < SIZE; ++idx) {
            ZVAL_UNDEF(&value[idx]);
        }
    }

    explicit AutoZval(zval *zv) { // copy from pointer (add reference)
        if (!zv) {
            setNull<0>();
            return;
        }
        memcpy(&value, zv, sizeof(zval));
        ZVAL_COPY(&value[0], zv);
    }


    template <typename... VALUES> AutoZval(VALUES &&...values) {
        static_assert(sizeof...(VALUES) == SIZE, "Initializer size must match array size");
        std::size_t index = 0;
        ([&] { set(index++, values); }(), ...);
    }

    ~AutoZval() {
        for (std::size_t idx = 0; idx < SIZE; ++idx) {
            zval_ptr_dtor(&value[idx]);
        }
    }

    constexpr zval &operator*() noexcept {
        return value[0];
    }

    constexpr zval *get() noexcept {
        return &value[0];
    }

    constexpr zval &at(std::size_t index) {
        if (index >= SIZE) {
            throw std::out_of_range("AutoZval index greater or equal capacity");
        }
        return value[index];
    }

    constexpr zval *get(std::size_t index) {
        if (index >= SIZE) {
            throw std::out_of_range("AutoZval index greater or equal capacity");
        }
        return &value[index];
    }

    zval *data() {
        return &value[0];
    }

    zval &operator[](std::size_t index) {
        if (index >= SIZE) {
            throw std::out_of_range("AutoZval index greater or equal capacity");
        }
        return value[index];
    }

    constexpr std::size_t size() const noexcept {
        return SIZE;
    }

    template <std::size_t INDEX> constexpr void setString(std::string_view str) {
        static_assert(INDEX < SIZE);
        ZVAL_STRINGL(&value[INDEX], str.data(), str.length());
    }

    template <std::size_t INDEX> constexpr void setLong(auto val) {
        static_assert(INDEX < SIZE);
        ZVAL_LONG(&value[INDEX], val);
    }

    template <std::size_t INDEX> constexpr void setNull() {
        static_assert(INDEX < SIZE);
        ZVAL_NULL(&value[INDEX]);
    }

    constexpr void set(std::size_t index, NotZvalPointer auto &&val) {
        if constexpr (std::is_same_v<decltype(val), bool>) {
            ZVAL_BOOL(&value[index], val);
        } else if constexpr (std::is_floating_point_v<std::remove_reference_t<decltype(val)>>) {
            ZVAL_DOUBLE(&value[index], val);
        } else if constexpr (!std::is_null_pointer_v<std::remove_reference_t<decltype(val)>> && std::is_convertible_v<decltype(val), std::string_view>) {
            std::string_view sv{val};
            ZVAL_STRINGL(&value[index], sv.data(), sv.length());
        } else if constexpr (std::is_null_pointer_v<std::remove_reference_t<decltype(val)>>) {
            ZVAL_NULL(&value[index]);
        } else {
            ZVAL_LONG(&value[index], val);
        }
    }

    template <std::size_t INDEX> constexpr void make(auto const &val) {
        set(INDEX, val);
    }

    template <std::size_t INDEX> bool isNull() const {
        static_assert(INDEX < SIZE);
        return Z_TYPE_P(&value[INDEX]) == IS_NULL;
    }

private:
    zval value[SIZE];
};

} // namespace elasticapm::php