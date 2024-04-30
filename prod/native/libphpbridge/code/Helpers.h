#pragma once

#include <php.h>
#include <Zend/zend_types.h>
#include <optional>
#include <string_view>

namespace elasticapm::php {

std::string_view zvalToStringView(zval *zv);
std::optional<std::string_view> zvalToOptionalStringView(zval *zv);

zend_ulong getClassAndFunctionHashFromExecuteData(zend_execute_data *execute_data);
std::tuple<std::string_view, std::string_view> getClassAndFunctionName(zend_execute_data *execute_data);

}
