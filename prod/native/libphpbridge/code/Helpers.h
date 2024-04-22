#pragma once

#include <php.h>
#include <Zend/zend_types.h>
#include <string_view>

namespace elasticapm::php {

std::string_view zvalToStringView(zval *zv);

}
