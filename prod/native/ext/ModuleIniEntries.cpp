
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


#define ELASTIC_APM_INI_ENTRY_IMPL( optName, isReloadableFlag ) \
    PHP_INI_ENTRY( \
        "elastic_apm." optName \
        , /* default value: */ NULL \
        , isReloadableFlag \
        , /* on_modify (validator): */ NULL )

#define ELASTIC_APM_INI_ENTRY( optName ) ELASTIC_APM_INI_ENTRY_IMPL( optName, PHP_INI_ALL )

#define ELASTIC_APM_NOT_RELOADABLE_INI_ENTRY( optName ) ELASTIC_APM_INI_ENTRY_IMPL( optName, PHP_INI_PERDIR )

PHP_INI_BEGIN() // expands to: static const zend_ini_entry_def ini_entries[] = {
    #ifdef PHP_WIN32
    ELASTIC_APM_INI_ENTRY( EL_STRINGIFY(ELASTIC_APM_CFG_OPT_NAME_ALLOW_ABORT_DIALOG) )
    #endif
    ELASTIC_APM_INI_ENTRY( EL_STRINGIFY(ELASTIC_APM_CFG_OPT_NAME_API_KEY) )
    ELASTIC_APM_INI_ENTRY( EL_STRINGIFY(ELASTIC_APM_CFG_OPT_NAME_BOOTSTRAP_PHP_PART_FILE) )
    ELASTIC_APM_INI_ENTRY( EL_STRINGIFY(ELASTIC_APM_CFG_OPT_NAME_BREAKDOWN_METRICS) )
    ELASTIC_APM_INI_ENTRY( EL_STRINGIFY(ELASTIC_APM_CFG_OPT_NAME_CAPTURE_ERRORS) )
    ELASTIC_APM_INI_ENTRY( EL_STRINGIFY(ELASTIC_APM_CFG_OPT_NAME_DEV_INTERNAL) )
    ELASTIC_APM_INI_ENTRY( EL_STRINGIFY(ELASTIC_APM_CFG_OPT_NAME_DISABLE_INSTRUMENTATIONS) )
    ELASTIC_APM_INI_ENTRY( EL_STRINGIFY(ELASTIC_APM_CFG_OPT_NAME_DISABLE_SEND) )
    ELASTIC_APM_NOT_RELOADABLE_INI_ENTRY( EL_STRINGIFY(ELASTIC_APM_CFG_OPT_NAME_ENABLED) )
    ELASTIC_APM_INI_ENTRY( EL_STRINGIFY(ELASTIC_APM_CFG_OPT_NAME_GLOBAL_LABELS) )
    ELASTIC_APM_INI_ENTRY( EL_STRINGIFY(ELASTIC_APM_CFG_OPT_NAME_ENVIRONMENT) )
    ELASTIC_APM_INI_ENTRY( EL_STRINGIFY(ELASTIC_APM_CFG_OPT_NAME_HOSTNAME) )
    ELASTIC_APM_INI_ENTRY( EL_STRINGIFY(ELASTIC_APM_CFG_OPT_NAME_LOG_FILE) )
    ELASTIC_APM_INI_ENTRY( EL_STRINGIFY(ELASTIC_APM_CFG_OPT_NAME_LOG_LEVEL) )
    ELASTIC_APM_INI_ENTRY( EL_STRINGIFY(ELASTIC_APM_CFG_OPT_NAME_LOG_LEVEL_FILE) )
    ELASTIC_APM_INI_ENTRY( EL_STRINGIFY(ELASTIC_APM_CFG_OPT_NAME_LOG_LEVEL_STDERR) )
    #ifndef PHP_WIN32
    ELASTIC_APM_INI_ENTRY( EL_STRINGIFY(ELASTIC_APM_CFG_OPT_NAME_LOG_LEVEL_SYSLOG) )
    #endif
    #ifdef PHP_WIN32
    ELASTIC_APM_INI_ENTRY( EL_STRINGIFY(ELASTIC_APM_CFG_OPT_NAME_LOG_LEVEL_WIN_SYS_DEBUG) )
    #endif
    ELASTIC_APM_INI_ENTRY( EL_STRINGIFY(ELASTIC_APM_CFG_OPT_NAME_NON_KEYWORD_STRING_MAX_LENGTH) )
    ELASTIC_APM_INI_ENTRY( EL_STRINGIFY(ELASTIC_APM_CFG_OPT_NAME_SANITIZE_FIELD_NAMES) )
    ELASTIC_APM_INI_ENTRY( EL_STRINGIFY(ELASTIC_APM_CFG_OPT_NAME_SECRET_TOKEN) )
    ELASTIC_APM_INI_ENTRY( EL_STRINGIFY(ELASTIC_APM_CFG_OPT_NAME_SERVER_TIMEOUT) )
    ELASTIC_APM_INI_ENTRY( EL_STRINGIFY(ELASTIC_APM_CFG_OPT_NAME_SERVER_URL) )
    ELASTIC_APM_INI_ENTRY( EL_STRINGIFY(ELASTIC_APM_CFG_OPT_NAME_SERVICE_NAME) )
    ELASTIC_APM_INI_ENTRY( EL_STRINGIFY(ELASTIC_APM_CFG_OPT_NAME_SERVICE_NODE_NAME) )
    ELASTIC_APM_INI_ENTRY( EL_STRINGIFY(ELASTIC_APM_CFG_OPT_NAME_SERVICE_VERSION) )
    ELASTIC_APM_INI_ENTRY( EL_STRINGIFY(ELASTIC_APM_CFG_OPT_NAME_SPAN_COMPRESSION_ENABLED) )
    ELASTIC_APM_INI_ENTRY( EL_STRINGIFY(ELASTIC_APM_CFG_OPT_NAME_SPAN_COMPRESSION_EXACT_MATCH_MAX_DURATION) )
    ELASTIC_APM_INI_ENTRY( EL_STRINGIFY(ELASTIC_APM_CFG_OPT_NAME_SPAN_COMPRESSION_SAME_KIND_MAX_DURATION) )
    ELASTIC_APM_INI_ENTRY( EL_STRINGIFY(ELASTIC_APM_CFG_OPT_NAME_SPAN_STACK_TRACE_MIN_DURATION) )
    ELASTIC_APM_INI_ENTRY( EL_STRINGIFY(ELASTIC_APM_CFG_OPT_NAME_STACK_TRACE_LIMIT) )
    ELASTIC_APM_INI_ENTRY( EL_STRINGIFY(ELASTIC_APM_CFG_OPT_NAME_TRANSACTION_IGNORE_URLS) )
    ELASTIC_APM_INI_ENTRY( EL_STRINGIFY(ELASTIC_APM_CFG_OPT_NAME_TRANSACTION_MAX_SPANS) )
    ELASTIC_APM_INI_ENTRY( EL_STRINGIFY(ELASTIC_APM_CFG_OPT_NAME_TRANSACTION_SAMPLE_RATE) )
    ELASTIC_APM_INI_ENTRY( EL_STRINGIFY(ELASTIC_APM_CFG_OPT_NAME_URL_GROUPS) )
    ELASTIC_APM_INI_ENTRY( EL_STRINGIFY(ELASTIC_APM_CFG_OPT_NAME_VERIFY_SERVER_CERT) )

    ELASTIC_APM_INI_ENTRY( EL_STRINGIFY(ELASTIC_APM_CFG_OPT_NAME_DEBUG_DIAGNOSTICS_FILE) )
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
            ELOG_WARNING(log, "zend_ini_register_displayer() failed; iniName: " PRsv, PRsvArg(iniName));
        }

    }
    return true;
}

}
