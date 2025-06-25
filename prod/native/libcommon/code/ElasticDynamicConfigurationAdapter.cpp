#include "ElasticDynamicConfigurationAdapter.h"

#include <nlohmann/json.hpp>

namespace opentelemetry::php::config {

std::unordered_map<std::string, std::string> ElasticDynamicConfigurationAdapter::parseJsonConfigFile(const std::string &jsonStr) const {
    std::unordered_map<std::string, std::string> result;

    nlohmann::json doc;
    try {
        doc = nlohmann::json::parse(jsonStr);
    } catch (const nlohmann::json::parse_error &e) {
        throw std::runtime_error("Error parsing json config: " + std::string(e.what()));
    }

    if (!doc.is_object()) {
        throw std::runtime_error("Expected top-level JSON object");
    }

    for (auto &[key, value] : doc.items()) {
        if (value.is_string()) {
            result[key] = value.get<std::string>();
        } else if (value.is_number_integer()) {
            result[key] = std::to_string(value.get<int>());
        } else if (value.is_number_float()) {
            result[key] = std::to_string(value.get<float>());
        } else if (value.is_boolean()) {
            result[key] = value.get<bool>() ? "true" : "false";
        }
    }

    return result;
}

void ElasticDynamicConfigurationAdapter::update(configFiles_t const &files) {
    auto elasticConfig = files.find("elastic"s);
    if (elasticConfig == std::end(files)) {
        options_.clear();
    }

    options_ = remapOptions(parseJsonConfigFile(elasticConfig->second));
}

ElasticDynamicConfigurationAdapter::optionsMap_t ElasticDynamicConfigurationAdapter::remapOptions(optionsMap_t remoteOptions) const {
    optionsMap_t result;
    for (auto const &opt : remoteOptions) {
        if (opt.first == "logging_level"sv) {
            std::string loglevel;
            if (opt.second == "trace"s) {
                loglevel = opt.second;
            } else if (opt.second == "debug"s) {
                loglevel = opt.second;
            } else if (opt.second == "info"s) {
                loglevel = opt.second;
            } else if (opt.second == "error"s) {
                loglevel = opt.second;
            } else if (opt.second == "off"s) {
                loglevel = opt.second;
            } else if (opt.second == "warn"s) {
                loglevel = "warning"s;
            } else if (opt.second == "fatal"s) {
                loglevel = "critical"s;
            } else {
                loglevel = opt.second; // log level parser with emit warning
            }

            result["log_level"s] = loglevel;
        }
    }
    return result;
}

} // namespace opentelemetry::php::config
