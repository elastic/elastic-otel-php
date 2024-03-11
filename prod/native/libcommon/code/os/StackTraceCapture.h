#pragma once

#include <string>

namespace elasticapm::osutils {
std::string getStackTrace(size_t numberOfFramesToSkip);
}
