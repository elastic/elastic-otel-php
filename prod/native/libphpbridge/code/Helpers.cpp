#include "Helpers.h"

namespace elasticapm::php {

std::string_view zvalToStringView(zval *zv) {
    if (!zv || Z_TYPE_P(zv) != IS_STRING) {
        return {};
    }
    return {Z_STRVAL_P(zv), Z_STRLEN_P(zv)};
}

}
