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
        case OptionMetadata::type::bytes: {
            std::size_t *value = reinterpret_cast<std::size_t *>((std::byte *)&snapshot + metadata.offset);
            return std::to_string(*value);
        }
        default:
            return {};
    }
}

ConfigurationManager::optionValue_t ConfigurationManager::getOptionValue(std::string_view optionName, ConfigurationSnapshot const &snapshot) const {
    auto option = options_.find(std::string(optionName));
    if (option == std::end(options_)) {
        return std::nullopt;
    }

    auto const &metadata = option->second;
    switch (metadata.type) {
        case elasticapm::php::ConfigurationManager::OptionMetadata::type::string: {
            std::string *value = reinterpret_cast<std::string *>((std::byte *)&snapshot + metadata.offset);
            return std::string(value->data(), value->length());
        }
        case elasticapm::php::ConfigurationManager::OptionMetadata::type::boolean: {
            bool *value = reinterpret_cast<bool *>((std::byte *)&snapshot + metadata.offset);
            return *value;
        }
        case elasticapm::php::ConfigurationManager::OptionMetadata::type::duration: {
            auto value = reinterpret_cast<std::chrono::milliseconds *>((std::byte *)&snapshot + metadata.offset);
            return *value;
        }
        case elasticapm::php::ConfigurationManager::OptionMetadata::type::loglevel: {
            LogLevel *value = reinterpret_cast<LogLevel *>((std::byte *)&snapshot + metadata.offset);
            return *value;
        }
        case elasticapm::php::ConfigurationManager::OptionMetadata::type::bytes: {
            size_t *value = reinterpret_cast<size_t *>((std::byte *)&snapshot + metadata.offset);
            return *value;
        }
        default:
            return std::nullopt;
    }
}

void ConfigurationManager::update(configFiles_t configFiles) {
    ConfigurationSnapshot newConfig;
    newConfig.revision = getNextRevision();
    newConfig.remoteConfigFiles = std::move(configFiles);
    ELOG_DEBUG(logger_, CONFIG, "ConfigurationManager::update new revision: {} configFiles: {}", newConfig.revision, newConfig.remoteConfigFiles.size());

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
                    *value = utils::parseBoolean(optionValue);
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
                case OptionMetadata::type::bytes: {
                    std::size_t *value = (std::size_t *)((std::byte *)&newConfig + entry.second.offset);
                    *value = utils::parseByteUnits(optionValue);
                    break;
                }
            }

        } catch (std::invalid_argument const &e) {
            ELOGF_NF_ERROR(logger_, "ConfigurationManager::update exception: '%s'", e.what());
        }
    }

    //TODO lock
    current_ = std::move(newConfig);
}

std::optional<std::string> ConfigurationManager::fetchStringValue(std::string_view name) {
    if (readDynamicOptionValue_) {
        auto dynamicValue = readDynamicOptionValue_(name);
        if (dynamicValue.has_value()) {
            return dynamicValue;
        }
    }

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
