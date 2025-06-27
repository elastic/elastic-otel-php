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


#include "AutoZval.h"

#include <php.h>
#include <Zend/zend_exceptions.h>
#include <functional>

static zend_class_entry *autozval_ce;

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_getStringView, 0, 1, IS_STRING, 0)
ZEND_ARG_INFO(0, arg)
ZEND_END_ARG_INFO()
PHP_METHOD(AutoZval, getStringView) {
    zval *arg;
    ZEND_PARSE_PARAMETERS_START(1, 1)
    Z_PARAM_ZVAL(arg)
    ZEND_PARSE_PARAMETERS_END();

    try {
        auto str = elasticapm::php::AutoZval(arg).getStringView();
        RETURN_STRINGL(str.data(), str.length());
    } catch (std::runtime_error const &e) {
        zend_throw_exception_ex(NULL, 0, "getStringView exception: %s", e.what());
        RETURN_THROWS();
    }
}

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_getLong, 0, 1, IS_LONG, 0)
ZEND_ARG_INFO(0, arg)
ZEND_END_ARG_INFO()
PHP_METHOD(AutoZval, getLong) {
    zval *arg;
    ZEND_PARSE_PARAMETERS_START(1, 1)
    Z_PARAM_ZVAL(arg)
    ZEND_PARSE_PARAMETERS_END();

    try {
        RETURN_LONG(elasticapm::php::AutoZval(arg).getLong());
    } catch (std::runtime_error const &e) {
        zend_throw_exception_ex(NULL, 0, "getLong exception: %s", e.what());
        RETURN_THROWS();
    }
}

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_getDouble, 0, 1, IS_DOUBLE, 0)
ZEND_ARG_INFO(0, arg)
ZEND_END_ARG_INFO()
PHP_METHOD(AutoZval, getDouble) {
    zval *arg;
    ZEND_PARSE_PARAMETERS_START(1, 1)
    Z_PARAM_ZVAL(arg)
    ZEND_PARSE_PARAMETERS_END();

    try {
        RETURN_DOUBLE(elasticapm::php::AutoZval(arg).getDouble());
    } catch (std::runtime_error const &e) {
        zend_throw_exception_ex(NULL, 0, "getDouble exception: %s", e.what());
        RETURN_THROWS();
    }
}

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_getNumberAsDouble, 0, 1, IS_DOUBLE, 0)
ZEND_ARG_INFO(0, arg)
ZEND_END_ARG_INFO()
PHP_METHOD(AutoZval, getNumberAsDouble) {
    zval *arg;
    ZEND_PARSE_PARAMETERS_START(1, 1)
    Z_PARAM_ZVAL(arg)
    ZEND_PARSE_PARAMETERS_END();

    try {
        RETURN_DOUBLE(elasticapm::php::AutoZval(arg).getNumberAsDouble());
    } catch (std::runtime_error const &e) {
        zend_throw_exception_ex(NULL, 0, "getNumberAsDouble exception: %s", e.what());
        RETURN_THROWS();
    }
}

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_getNumberAsLong, 0, 1, IS_LONG, 0)
ZEND_ARG_INFO(0, arg)
ZEND_END_ARG_INFO()
PHP_METHOD(AutoZval, getNumberAsLong) {
    zval *arg;
    ZEND_PARSE_PARAMETERS_START(1, 1)
    Z_PARAM_ZVAL(arg)
    ZEND_PARSE_PARAMETERS_END();

    try {
        RETURN_LONG(elasticapm::php::AutoZval(arg).getNumberAsLong());
    } catch (std::runtime_error const &e) {
        zend_throw_exception_ex(NULL, 0, "getNumberAsLong exception: %s", e.what());
        RETURN_THROWS();
    }
}

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_getBoolean, 0, 1, _IS_BOOL, 0)
ZEND_ARG_INFO(0, arg)
ZEND_END_ARG_INFO()
PHP_METHOD(AutoZval, getBoolean) {
    zval *arg;
    ZEND_PARSE_PARAMETERS_START(1, 1)
    Z_PARAM_ZVAL(arg)
    ZEND_PARSE_PARAMETERS_END();

    try {
        RETURN_BOOL(elasticapm::php::AutoZval(arg).getBoolean());
    } catch (std::runtime_error const &e) {
        zend_throw_exception_ex(NULL, 0, "getBoolean exception: %s", e.what());
        RETURN_THROWS();
    }
}

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_getArrayCount, 0, 1, IS_LONG, 0)
ZEND_ARG_INFO(0, arg)
ZEND_END_ARG_INFO()
PHP_METHOD(AutoZval, getArrayCount) {
    zval *arg;
    ZEND_PARSE_PARAMETERS_START(1, 1)
    Z_PARAM_ZVAL(arg)
    ZEND_PARSE_PARAMETERS_END();

    if (Z_TYPE_P(arg) != IS_ARRAY) {
        zend_throw_exception(NULL, "Expected array", 0);
        RETURN_THROWS();
    }
    RETURN_LONG(elasticapm::php::AutoZval(arg).getArrayCount());
}

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_setString, 0, 1, IS_STRING, 0)
ZEND_ARG_TYPE_INFO(0, arg, IS_STRING, 0)
ZEND_END_ARG_INFO()
PHP_METHOD(AutoZval, setString) {
    zend_string *arg;
    ZEND_PARSE_PARAMETERS_START(1, 1)
    Z_PARAM_STR(arg)
    ZEND_PARSE_PARAMETERS_END();

    elasticapm::php::AutoZval az;
    az.setString({ZSTR_VAL(arg), ZSTR_LEN(arg)});
    RETURN_ZVAL(az.get(), 1, 0);
}

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_setLong, 0, 1, IS_LONG, 0)
ZEND_ARG_TYPE_INFO(0, arg, IS_LONG, 0)
ZEND_END_ARG_INFO()
PHP_METHOD(AutoZval, setLong) {
    zend_long arg;
    ZEND_PARSE_PARAMETERS_START(1, 1)
    Z_PARAM_LONG(arg)
    ZEND_PARSE_PARAMETERS_END();

    elasticapm::php::AutoZval az;
    az.setLong(arg);
    RETURN_ZVAL(az.get(), 1, 0);
}

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_setNull, 0, 0, IS_NULL, 0)
ZEND_END_ARG_INFO()
PHP_METHOD(AutoZval, setNull) {
    elasticapm::php::AutoZval az;
    az.setNull();
#pragma GCC diagnostic push
#pragma GCC diagnostic ignored "-Wuninitialized"
    RETURN_ZVAL(az.get(), 1, 0);
#pragma GCC diagnostic pop
}

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_setDouble, 0, 1, IS_DOUBLE, 0)
ZEND_ARG_TYPE_INFO(0, arg, IS_DOUBLE, 0)
ZEND_END_ARG_INFO()
PHP_METHOD(AutoZval, setDouble) {
    double arg;
    ZEND_PARSE_PARAMETERS_START(1, 1)
    Z_PARAM_DOUBLE(arg)
    ZEND_PARSE_PARAMETERS_END();

    elasticapm::php::AutoZval az;
    az.setDouble(arg);
    RETURN_ZVAL(az.get(), 1, 0);
}

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_arrayInit, 0, 0, IS_ARRAY, 0)
ZEND_END_ARG_INFO()
PHP_METHOD(AutoZval, arrayInit) {
    elasticapm::php::AutoZval az;
    az.arrayInit();
    RETURN_ZVAL(az.get(), 1, 0);
}

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_arrayAddNextWithRef, 0, 2, IS_ARRAY, 0)
ZEND_ARG_ARRAY_INFO(0, array, 0)
ZEND_ARG_INFO(0, value)
ZEND_END_ARG_INFO()
PHP_METHOD(AutoZval, arrayAddNextWithRef) {
    zval *array;
    zval *value;
    ZEND_PARSE_PARAMETERS_START(2, 2)
    Z_PARAM_ARRAY(array)
    Z_PARAM_ZVAL(value)
    ZEND_PARSE_PARAMETERS_END();

    elasticapm::php::AutoZval az(array);
    az.arrayAddNextWithRef(value);
    RETURN_ZVAL(array, 1, 0);
}

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_arrayAddAssocWithRef, 0, 3, IS_ARRAY, 0)
ZEND_ARG_ARRAY_INFO(0, array, 0)
ZEND_ARG_TYPE_INFO(0, key, IS_STRING, 0)
ZEND_ARG_INFO(0, value)
ZEND_END_ARG_INFO()
PHP_METHOD(AutoZval, arrayAddAssocWithRef) {
    zval *array;
    zend_string *keyzs;
    zval *value;
    ZEND_PARSE_PARAMETERS_START(3, 3)
    Z_PARAM_ARRAY(array)
    Z_PARAM_STR(keyzs)
    Z_PARAM_ZVAL(value)
    ZEND_PARSE_PARAMETERS_END();

    std::string_view key({ZSTR_VAL(keyzs), ZSTR_LEN(keyzs)});

    elasticapm::php::AutoZval az(array);
    az.arrayAddAssocWithRef(key, value);
    RETURN_ZVAL(array, 1, 0);
}

using RecursiveArrayVisitor_t = std::function<void(elasticapm::php::AutoZval const &array, std::function<void(elasticapm::php::AutoZval const &)> const &processElement)>;

void iteratePrintAutoZval(elasticapm::php::AutoZval const &val, RecursiveArrayVisitor_t const &arrayVisitor) {
    auto recurse = [&](elasticapm::php::AutoZval const &v) { iteratePrintAutoZval(v, arrayVisitor); };

    switch (val.getType()) {
        case IS_ARRAY: {
            std::cout << "array(" << val.getArrayCount() << "): {" << std::endl;
            arrayVisitor(val, recurse);
            // for (auto const &element : val) {
            //     iteratePrintAutoZval(element, arrayVisitor);
            // }
            std::cout << '}' << std::endl;
            break;
        }
        case IS_LONG:
            std::cout << "long: " << val.getLong() << std::endl;
            break;
        case IS_DOUBLE:
            std::cout << "double: " << val.getDouble() << std::endl;
            break;
        case IS_TRUE:
        case IS_FALSE:
            std::cout << "bool: " << val.getBoolean() << std::endl;
            break;
        case IS_STRING:
            std::cout << '\'' << val.getStringView() << '\'' << std::endl;
            break;
        case IS_NULL:
            std::cout << "isNull: " << val.isNull() << std::endl;
            break;
        case IS_OBJECT:
            std::cout << "isObject: " << val.isObject() << std::endl;
            break;
        case IS_RESOURCE:
            std::cout << "isResource: " << val.isResource() << std::endl;
            break;
        case IS_UNDEF:
            std::cout << "isUndef: " << val.isUndef() << std::endl;
            break;
        default:
            std::cout << "unknown type: " << val.getType() << std::endl;
    }
}

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_iterateArray, 0, 1, IS_VOID, 0)
ZEND_ARG_INFO(0, array)
ZEND_END_ARG_INFO()
PHP_METHOD(AutoZval, iterateArray) {
    zval *val;
    ZEND_PARSE_PARAMETERS_START(1, 1)
    Z_PARAM_ZVAL(val)
    ZEND_PARSE_PARAMETERS_END();

    elasticapm::php::AutoZval value(val);

    RecursiveArrayVisitor_t arrayVisitor = [](auto const &array, auto const &processElement) {
        for (auto const &element : array) {
            processElement(element);
        }
    };

    iteratePrintAutoZval(value, arrayVisitor);
}

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_iterateKeyValueArray, 0, 1, IS_VOID, 0)
ZEND_ARG_INFO(0, array)
ZEND_END_ARG_INFO()
PHP_METHOD(AutoZval, iterateKeyValueArray) {
    zval *val;
    ZEND_PARSE_PARAMETERS_START(1, 1)
    Z_PARAM_ZVAL(val)
    ZEND_PARSE_PARAMETERS_END();

    elasticapm::php::AutoZval value(val);

    RecursiveArrayVisitor_t arrayVisitor = [](auto const &array, auto const &processElement) {
        for (auto it = array.kvbegin(); it != array.kvend(); ++it) {
            auto [key, val] = *it;
            std::cout << "key: ";
            if (std::holds_alternative<std::string_view>(key)) {
                std::cout << '\'' << std::get<std::string_view>(key) << '\'';
            } else {
                std::cout << static_cast<zend_long>(std::get<zend_ulong>(key));
            }
            std::cout << " => ";
            processElement(val);
        }
    };

    iteratePrintAutoZval(value, arrayVisitor);
}

// clang-format off
static const zend_function_entry autozval_methods[] = {
    PHP_ME(AutoZval, getStringView, arginfo_getStringView, ZEND_ACC_PUBLIC | ZEND_ACC_STATIC)
    PHP_ME(AutoZval, getLong, arginfo_getLong, ZEND_ACC_PUBLIC | ZEND_ACC_STATIC)
    PHP_ME(AutoZval, getDouble, arginfo_getDouble, ZEND_ACC_PUBLIC | ZEND_ACC_STATIC)
    PHP_ME(AutoZval, getNumberAsDouble, arginfo_getNumberAsDouble, ZEND_ACC_PUBLIC | ZEND_ACC_STATIC)
    PHP_ME(AutoZval, getNumberAsLong, arginfo_getNumberAsLong, ZEND_ACC_PUBLIC | ZEND_ACC_STATIC)
    PHP_ME(AutoZval, getBoolean, arginfo_getBoolean, ZEND_ACC_PUBLIC | ZEND_ACC_STATIC)
    PHP_ME(AutoZval, getArrayCount, arginfo_getArrayCount, ZEND_ACC_PUBLIC | ZEND_ACC_STATIC)
    PHP_ME(AutoZval, setString, arginfo_setString, ZEND_ACC_PUBLIC | ZEND_ACC_STATIC)
    PHP_ME(AutoZval, setLong, arginfo_setLong, ZEND_ACC_PUBLIC | ZEND_ACC_STATIC)
    PHP_ME(AutoZval, setNull, arginfo_setNull, ZEND_ACC_PUBLIC | ZEND_ACC_STATIC)
    PHP_ME(AutoZval, setDouble, arginfo_setDouble, ZEND_ACC_PUBLIC | ZEND_ACC_STATIC)
    PHP_ME(AutoZval, arrayInit, arginfo_arrayInit, ZEND_ACC_PUBLIC | ZEND_ACC_STATIC)
    PHP_ME(AutoZval, arrayAddNextWithRef, arginfo_arrayAddNextWithRef, ZEND_ACC_PUBLIC | ZEND_ACC_STATIC)
    PHP_ME(AutoZval, arrayAddAssocWithRef, arginfo_arrayAddNextWithRef, ZEND_ACC_PUBLIC | ZEND_ACC_STATIC)
    PHP_ME(AutoZval, iterateArray, arginfo_iterateArray, ZEND_ACC_PUBLIC | ZEND_ACC_STATIC)
    PHP_ME(AutoZval, iterateKeyValueArray, arginfo_iterateKeyValueArray, ZEND_ACC_PUBLIC | ZEND_ACC_STATIC)
    PHP_FE_END
};
// clang-format on

void register_AutoZval_class() {
    zend_class_entry ce;
    INIT_CLASS_ENTRY(ce, "AutoZval", autozval_methods);
    autozval_ce = zend_register_internal_class(&ce);
}
