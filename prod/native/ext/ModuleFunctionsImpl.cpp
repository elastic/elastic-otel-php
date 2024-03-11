/*
 * Licensed to Elasticsearch B.V. under one or more contributor
 * license agreements. See the NOTICE file distributed with
 * this work for additional information regarding copyright
 * ownership. Elasticsearch B.V. licenses this file to you under
 * the Apache License, Version 2.0 (the "License"); you may
 * not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied.  See the License for the
 * specific language governing permissions and limitations
 * under the License.
 */

#include "ModuleFunctionsImpl.h"
#include "ConfigurationManager.h"
#include "ConfigurationStorage.h"

#include <php.h>
#include "ModuleGlobals.h"

extern elasticapm::php::ConfigurationManager configManager;

//TODO move to internals? test with phpt? nonsense
//TODO implement in ConfigManager to return variant and then visitor to zval here
void convertOptionToZval(elasticapm::php::ConfigurationManager::OptionMetadata const &metadata, elasticapm::php::ConfigurationSnapshot const &snapshot, zval *outputZval) {
    switch (metadata.type) {
        case elasticapm::php::ConfigurationManager::OptionMetadata::type::string: {
            std::string *value = reinterpret_cast<std::string *>((std::byte *)&snapshot + metadata.offset);
            ZVAL_STRINGL(outputZval, value->c_str(), value->length());
            break;
        }
        case elasticapm::php::ConfigurationManager::OptionMetadata::type::boolean: {
            bool *value = reinterpret_cast<bool *>((std::byte *)&snapshot + metadata.offset);
            if (*value) {
                ZVAL_TRUE(outputZval);
            } else {
                ZVAL_FALSE(outputZval);
            }
            break;
        }
        case elasticapm::php::ConfigurationManager::OptionMetadata::type::duration: {
            auto value = reinterpret_cast<std::chrono::milliseconds *>((std::byte *)&snapshot + metadata.offset);
            ZVAL_DOUBLE(outputZval, value->count());
            break;
        }
        case elasticapm::php::ConfigurationManager::OptionMetadata::type::loglevel: {
            LogLevel *value = reinterpret_cast<LogLevel *>((std::byte *)&snapshot + metadata.offset);
            ZVAL_LONG(outputZval, *value);
            break;
        }
        default:
            ZVAL_NULL(outputZval);
            break;
    }
}

void elasticApmGetConfigOption(std::string_view optionName, zval *return_value) {
    auto const &options = configManager.getOptionMetadata();

    auto option = options.find(std::string(optionName));
    if (option == std::end(options)) {
        ZVAL_NULL(return_value);
        return;
    }
    convertOptionToZval(option->second, EAPM_GL(config_)->get(), return_value);
}
