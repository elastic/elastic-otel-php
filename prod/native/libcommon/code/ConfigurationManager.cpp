
#include "ConfigurationManager.h"
#include "CommonUtils.h"

#include <string_view>
#include <cstdlib>

namespace elasticapm::php {

using namespace std::string_literals;
using namespace std::string_view_literals;


std::string ConfigurationManager::accessOptionStringValueByMetadata(OptionMetadata const &metadata, ConfigurationSnapshot const &snapshot) {
    switch (metadata.type) {
        case OptionMetadata::type::string: {
            std::string *value = reinterpret_cast<std::string *>((std::byte *)&snapshot + metadata.offset);
            return *value;
        }
        case OptionMetadata::type::boolean: {
            bool *value = reinterpret_cast<bool *>((std::byte *)&snapshot + metadata.offset);
            return *value ? "true"s : "false"s;
        }
        case OptionMetadata::type::duration: {
            auto value = reinterpret_cast<std::chrono::milliseconds *>((std::byte *)&snapshot + metadata.offset);
            return std::to_string(value->count());
        }
        case OptionMetadata::type::loglevel: {
           LogLevel *value = reinterpret_cast<LogLevel *>((std::byte *)&snapshot + metadata.offset);
           std::string_view level = utils::trim(getLogLevelName(*value));
           return {level.data(), level.length()};
        }
        default:
            return {};
    }
}

void ConfigurationManager::update() {
    ConfigurationSnapshot newConfig;
    newConfig.revision = getNextRevision();

    for (auto const &entry : options_) {
        auto optionVal = fetchStringValue(entry.first);
        if (!optionVal.has_value()) {
            continue; // keep default from snapshot
        }
        auto &optionValue = optionVal.value();

        try {
            switch (entry.second.type) {
                case OptionMetadata::type::string: {
                    std::string *value = (std::string *)((std::byte *)&newConfig + entry.second.offset);
                    value->swap(optionValue);
                    break;
                }
                case OptionMetadata::type::boolean: {
                    bool *value = (bool *)((std::byte *)&newConfig + entry.second.offset);
                    if (!optionValue.empty()) {
                        *value = utils::parseBoolean(optionValue);
                    }
                    break;
                }
                case OptionMetadata::type::duration: {
                    auto value = reinterpret_cast<std::chrono::milliseconds *>((std::byte *)&newConfig + entry.second.offset);
                    *value = utils::convertDurationWithUnit(optionValue);
                    break;
                }
                case OptionMetadata::type::loglevel: {
                    LogLevel *value = (LogLevel *)((std::byte *)&newConfig + entry.second.offset);
                    *value = utils::parseLogLevel(optionValue);
                    break;
                }
            }

        } catch (std::invalid_argument const &e) {
            // fprintf(stderr, "\n\n=============== ERROR %s\n", e.what());
            // TODO log
        }
    }

    //TODO lock
    current_ = std::move(newConfig);
}

std::optional<std::string> ConfigurationManager::fetchStringValue(std::string_view name) {
    auto iniName = utils::getIniName(name);
    auto value = readIniValue_(iniName);
    if (value.has_value()) {
        return value;
    }

    auto envValue = getenv(utils::getEnvName(name).c_str());

    if (!envValue) {
        return std::nullopt;
    }
    return envValue;
}

uint64_t ConfigurationManager::getNextRevision() {
    return (++upcomingConfigRevision_);
}



} // namespace elasticapm::php
