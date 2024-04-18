#pragma once

#include "LoggerInterface.h"

namespace elasticapm::php {
bool registerElasticApmIniEntries(elasticapm::php::LoggerInterface *log, int module_number);
}