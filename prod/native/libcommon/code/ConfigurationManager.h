
#pragma once

#include "ConfigurationSnapshot.h"
#include "basic_macros.h"

#include <atomic>
#include <chrono>
#include <functional>
#include <map>
#include <optional>
#include <string>



namespace elasticapm::php {

using namespace std::string_literals;

//TODO Draft
// wrzucic do globalnego obiektu zarzadcy - tam dac, komunikacja, configstorage, etc
// czytanie konfigu  z pliku, z env, z ini, default
// możliwość zmiany priorytetu, by default
// 1 env, 2 ini, 3 plik, 4 default, 5 - remote
// czy to ma byc globalne czy globalne na workerze - jak globalna - mutexy
// jezeli tak to worker do globalsow bedzie mogl po prostu zaciagac snapshot na starcie requestu - kazdy worker na innym configu moze isc przez moment

//TODO default unit?
//TODO sign

class ConfigurationManager {
public:
    using readIniValue_t = std::function<std::optional<std::string>(std::string_view)>;
    struct OptionMetadata  {
        enum type {
            boolean,
            string,
            duration,
            loglevel
        } type;
        size_t offset;
        bool secret = false;
    };


    ConfigurationManager(readIniValue_t readIniValue) : readIniValue_(readIniValue) {
        current_.revision = getNextRevision();
    }

//TODO lock
    void update();

//TODO lock
    bool updateIfChanged(ConfigurationSnapshot &snapshot) {
        if (snapshot.revision != current_.revision) {
            snapshot = current_;
            return true;
        }
        return false;
    }

    std::map<std::string, OptionMetadata> const &getOptionMetadata() {
        return options_;
    }

    static std::string accessOptionStringValueByMetadata(OptionMetadata const &metadata, ConfigurationSnapshot const &snapshot);

private:
    std::optional<std::string> fetchStringValue(std::string_view name);
    uint64_t getNextRevision();


private:
    readIniValue_t readIniValue_;
    std::atomic_uint64_t upcomingConfigRevision_ = 0;
    ConfigurationSnapshot current_;

    #define BUILD_METADATA(optname, type, secret) { EL_STRINGIFY(optname), {type, offsetof(ConfigurationSnapshot, optname), secret}}

    std::map<std::string, OptionMetadata> options_ = {
        BUILD_METADATA(ELASTIC_APM_CFG_OPT_NAME_API_KEY, OptionMetadata::type::string, true),
        BUILD_METADATA(ELASTIC_APM_CFG_OPT_NAME_BOOTSTRAP_PHP_PART_FILE, OptionMetadata::type::string, false),
        BUILD_METADATA(ELASTIC_APM_CFG_OPT_NAME_BREAKDOWN_METRICS, OptionMetadata::type::boolean, false),
        BUILD_METADATA(ELASTIC_APM_CFG_OPT_NAME_CAPTURE_ERRORS, OptionMetadata::type::boolean, false),
        BUILD_METADATA(ELASTIC_APM_CFG_OPT_NAME_DEV_INTERNAL, OptionMetadata::type::string, false),
        BUILD_METADATA(ELASTIC_APM_CFG_OPT_NAME_DISABLE_INSTRUMENTATIONS, OptionMetadata::type::string, false),
        BUILD_METADATA(ELASTIC_APM_CFG_OPT_NAME_DISABLE_SEND, OptionMetadata::type::boolean, false),
        BUILD_METADATA(ELASTIC_APM_CFG_OPT_NAME_ENABLED, OptionMetadata::type::boolean, false),
        BUILD_METADATA(ELASTIC_APM_CFG_OPT_NAME_ENVIRONMENT, OptionMetadata::type::string, false),
        BUILD_METADATA(ELASTIC_APM_CFG_OPT_NAME_GLOBAL_LABELS, OptionMetadata::type::string, false),
        BUILD_METADATA(ELASTIC_APM_CFG_OPT_NAME_HOSTNAME, OptionMetadata::type::string, false),
        BUILD_METADATA(ELASTIC_APM_CFG_OPT_NAME_LOG_FILE, OptionMetadata::type::string, false),
        BUILD_METADATA(ELASTIC_APM_CFG_OPT_NAME_LOG_LEVEL, OptionMetadata::type::loglevel, false),
        BUILD_METADATA(ELASTIC_APM_CFG_OPT_NAME_LOG_LEVEL_FILE, OptionMetadata::type::loglevel, false),
        BUILD_METADATA(ELASTIC_APM_CFG_OPT_NAME_LOG_LEVEL_STDERR, OptionMetadata::type::loglevel, false),
        BUILD_METADATA(ELASTIC_APM_CFG_OPT_NAME_LOG_LEVEL_SYSLOG, OptionMetadata::type::loglevel, false),
        BUILD_METADATA(ELASTIC_APM_CFG_OPT_NAME_LOG_LEVEL_WIN_SYS_DEBUG, OptionMetadata::type::loglevel, false),
        BUILD_METADATA(ELASTIC_APM_CFG_OPT_NAME_NON_KEYWORD_STRING_MAX_LENGTH, OptionMetadata::type::string, false),
        BUILD_METADATA(ELASTIC_APM_CFG_OPT_NAME_SANITIZE_FIELD_NAMES, OptionMetadata::type::string, true),
        BUILD_METADATA(ELASTIC_APM_CFG_OPT_NAME_SECRET_TOKEN, OptionMetadata::type::string, true),
        BUILD_METADATA(ELASTIC_APM_CFG_OPT_NAME_SERVER_TIMEOUT, OptionMetadata::type::duration, false),
        BUILD_METADATA(ELASTIC_APM_CFG_OPT_NAME_SERVER_URL, OptionMetadata::type::string, false),
        BUILD_METADATA(ELASTIC_APM_CFG_OPT_NAME_SERVICE_NAME, OptionMetadata::type::string, false),
        BUILD_METADATA(ELASTIC_APM_CFG_OPT_NAME_SERVICE_NODE_NAME, OptionMetadata::type::string, false),
        BUILD_METADATA(ELASTIC_APM_CFG_OPT_NAME_SERVICE_VERSION, OptionMetadata::type::string, false),
        BUILD_METADATA(ELASTIC_APM_CFG_OPT_NAME_SPAN_COMPRESSION_ENABLED, OptionMetadata::type::boolean, false),
        BUILD_METADATA(ELASTIC_APM_CFG_OPT_NAME_SPAN_COMPRESSION_EXACT_MATCH_MAX_DURATION, OptionMetadata::type::string, false),
        BUILD_METADATA(ELASTIC_APM_CFG_OPT_NAME_SPAN_COMPRESSION_SAME_KIND_MAX_DURATION, OptionMetadata::type::string, false),
        BUILD_METADATA(ELASTIC_APM_CFG_OPT_NAME_SPAN_STACK_TRACE_MIN_DURATION, OptionMetadata::type::string, false),
        BUILD_METADATA(ELASTIC_APM_CFG_OPT_NAME_STACK_TRACE_LIMIT, OptionMetadata::type::string, false),
        BUILD_METADATA(ELASTIC_APM_CFG_OPT_NAME_TRANSACTION_IGNORE_URLS, OptionMetadata::type::string, false),
        BUILD_METADATA(ELASTIC_APM_CFG_OPT_NAME_TRANSACTION_MAX_SPANS, OptionMetadata::type::string, false),
        BUILD_METADATA(ELASTIC_APM_CFG_OPT_NAME_TRANSACTION_SAMPLE_RATE, OptionMetadata::type::string, false),
        BUILD_METADATA(ELASTIC_APM_CFG_OPT_NAME_URL_GROUPS, OptionMetadata::type::string, false),
        BUILD_METADATA(ELASTIC_APM_CFG_OPT_NAME_VERIFY_SERVER_CERT, OptionMetadata::type::boolean, false),
        BUILD_METADATA(ELASTIC_APM_CFG_OPT_NAME_DEBUG_DIAGNOSTICS_FILE, OptionMetadata::type::string, false)
    };
};

} // namespace elasticapm::php
