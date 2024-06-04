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


#pragma once

#include "ConfigurationSnapshot.h"
#undef snprintf
#include <boost/signals2.hpp>
#undef snprintf

#include <functional>
#include <memory>

namespace elasticapm::php {

// local, per worker configuration holder - no need to be synchronized, need to be stored in worker globals
class ConfigurationStorage {
public:
    using configUpdate_t = std::function<bool(ConfigurationSnapshot &)>;
    using configUpdated_t = boost::signals2::signal<void(ConfigurationSnapshot const &)>;

    ConfigurationStorage(configUpdate_t configUpdate) : configUpdate_(configUpdate) {
    }

    // it will fetch configuration from global source
    void update() {
        std::lock_guard<std::mutex> lock(mutex_);
        bool changed = configUpdate_(config_);
        if (changed) {
            configUpdated_(config_);
        }
    }

    // fully thread safe, have no idea where to use it
    // example usage: get(&ConfigurationSnapshot::debug_diagnostic_file);
    template <typename Member> auto get(Member member) {
        std::lock_guard<std::mutex> lock(mutex_);
        return this->config_.*member;
    }

    ConfigurationSnapshot const *operator->() const noexcept {
        return &config_;
    }

    ConfigurationSnapshot const &get() {
        return config_;
    }

    boost::signals2::connection addConfigUpdateWatcher(configUpdated_t::slot_function_type watcher) {
        return configUpdated_.connect(std::move(watcher));
    }

    void removeConfigUpdateWatcher(boost::signals2::connection watcher) {
        watcher.disconnect();
    }

    void removeAllConfigUpdateWatchers() {
        configUpdated_.disconnect_all_slots();
    }

private:
    ConfigurationSnapshot config_;
    configUpdate_t configUpdate_;
    configUpdated_t configUpdated_;
    std::mutex mutex_;
};

}