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


#include "ModuleIniEntries.h"
#include "ConfigurationManager.h"
#include "ConfigurationSnapshot.h"
#include "CommonUtils.h"
#include "basic_macros.h"

#include <php.h>
#include <main/php_ini.h>
#include <main/SAPI.h>
#include <Zend/zend_ini.h>
#include <Zend/zend_types.h>
#include <Zend/zend_string.h>
#include <Zend/zend_hash.h>


extern elasticapm::php::ConfigurationManager configManager;


#define ELASTIC_OTEL_INI_ENTRY_IMPL( optName, isReloadableFlag ) \
    PHP_INI_ENTRY( \
        "elastic_otel." optName \
        , /* default value: */ NULL \
        , isReloadableFlag \
        , /* on_modify (validator): */ NULL )

#define ELASTIC_OTEL_INI_ENTRY( optName ) ELASTIC_OTEL_INI_ENTRY_IMPL( optName, PHP_INI_ALL )

#define ELASTIC_OTEL_NOT_RELOADABLE_INI_ENTRY( optName ) ELASTIC_OTEL_INI_ENTRY_IMPL( optName, PHP_INI_PERDIR )

PHP_INI_BEGIN() // expands to: static const zend_ini_entry_def ini_entries[] = {
ELASTIC_OTEL_INI_ENTRY(EL_STRINGIFY(ELASTIC_OTEL_BOOTSTRAP_PHP_PART_FILE))
ELASTIC_OTEL_NOT_RELOADABLE_INI_ENTRY(EL_STRINGIFY(ELASTIC_OTEL_ENABLED))
ELASTIC_OTEL_INI_ENTRY(EL_STRINGIFY(ELASTIC_OTEL_LOG_FILE))
ELASTIC_OTEL_INI_ENTRY(EL_STRINGIFY(ELASTIC_OTEL_LOG_LEVEL))
ELASTIC_OTEL_INI_ENTRY(EL_STRINGIFY(ELASTIC_OTEL_LOG_LEVEL_FILE))
ELASTIC_OTEL_INI_ENTRY(EL_STRINGIFY(ELASTIC_OTEL_LOG_LEVEL_STDERR))
#ifndef PHP_WIN32
ELASTIC_OTEL_INI_ENTRY(EL_STRINGIFY(ELASTIC_OTEL_LOG_LEVEL_SYSLOG))
#endif
#ifdef PHP_WIN32
ELASTIC_OTEL_INI_ENTRY(EL_STRINGIFY(ELASTIC_OTEL_LOG_LEVEL_WIN_SYS_DEBUG))
#endif

ELASTIC_OTEL_INI_ENTRY(EL_STRINGIFY(ELASTIC_OTEL_DEBUG_DIAGNOSTICS_FILE))
ELASTIC_OTEL_INI_ENTRY(EL_STRINGIFY(ELASTIC_OTEL_MAX_SEND_QUEUE_SIZE))
ELASTIC_OTEL_INI_ENTRY(EL_STRINGIFY(ELASTIC_OTEL_ASYNC_TRANSPORT))
ELASTIC_OTEL_INI_ENTRY(EL_STRINGIFY(ELASTIC_OTEL_DEBUG_INSTRUMENT_ALL))
ELASTIC_OTEL_INI_ENTRY(EL_STRINGIFY(ELASTIC_OTEL_DEBUG_PHP_HOOKS_ENABLED))

ELASTIC_OTEL_INI_ENTRY(EL_STRINGIFY(ELASTIC_OTEL_INFERRED_SPANS_ENABLED))
ELASTIC_OTEL_INI_ENTRY(EL_STRINGIFY(ELASTIC_OTEL_INFERRED_SPANS_REDUCTION_ENABLED))
ELASTIC_OTEL_INI_ENTRY(EL_STRINGIFY(ELASTIC_OTEL_INFERRED_SPANS_STACKTRACE_ENABLED))
ELASTIC_OTEL_INI_ENTRY(EL_STRINGIFY(ELASTIC_OTEL_INFERRED_SPANS_SAMPLING_INTERVAL))
ELASTIC_OTEL_INI_ENTRY(EL_STRINGIFY(ELASTIC_OTEL_INFERRED_SPANS_MIN_DURATION))
PHP_INI_END()

namespace elasticapm::php {


constexpr const zend_string *iniEntryValue(zend_ini_entry *iniEntry, int type) {
    return (type == ZEND_INI_DISPLAY_ORIG) ? (iniEntry->modified ? iniEntry->orig_value : iniEntry->value) : iniEntry->value;
}

void displaySecretIniValue(zend_ini_entry *iniEntry, int type) {
    auto value = iniEntryValue(iniEntry, type);
    const char *valueToPrint = value && ZSTR_LEN(value) ? "***" : "no value";

    php_printf(sapi_module.phpinfo_as_text ? "%s" : "<i>%s</i>", valueToPrint);
}

bool registerElasticApmIniEntries(elasticapm::php::LoggerInterface *log, int module_number) {
    if (zend_register_ini_entries(ini_entries, module_number) != ZEND_RESULT_CODE::SUCCESS) {
        return false;
    }

    // register custom displayer for secret options
    auto options = configManager.getOptionMetadata();
    for (auto const &option : options) {
        if (!option.second.secret) {
            continue;
        }

        auto iniName = elasticapm::utils::getIniName(option.first);

        if (zend_ini_register_displayer(iniName.data(), iniName.length(), displaySecretIniValue) != ZEND_RESULT_CODE::SUCCESS) {
            ELOGF_WARNING(log, MODULE, "zend_ini_register_displayer() failed; iniName: " PRsv, PRsvArg(iniName));
        }

    }
    return true;
}

}