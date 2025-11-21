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

#include "LoggerInterface.h"
#include "transport/OpAmp.h"

#include <memory>
#include <span>
#include <string>
#include <utility>

#include <boost/interprocess/anonymous_shared_memory.hpp>
#include <boost/interprocess/mapped_region.hpp>
#include <boost/interprocess/managed_external_buffer.hpp>
#include <boost/interprocess/allocators/allocator.hpp>
#include <boost/interprocess/sync/interprocess_upgradable_mutex.hpp>
#include <boost/interprocess/sync/scoped_lock.hpp>
#include <boost/interprocess/sync/sharable_lock.hpp>
#include <boost/container/map.hpp>
#include <boost/container/string.hpp>

namespace elasticapm::php::coordinator {

class CoordinatorConfigurationProvider {
public:
    using configFiles_t = std::unordered_map<std::string, std::string>; // filename->content
    using configUpdated_t = boost::signals2::signal<void(configFiles_t const &)>;

    using SegmentManager = boost::interprocess::managed_external_buffer::segment_manager;
    using ShmemString = boost::container::basic_string<char, std::char_traits<char>, boost::interprocess::allocator<char, SegmentManager>>;
    using ShmemAllocator = boost::interprocess::allocator<std::pair<const ShmemString, ShmemString>, SegmentManager>;
    using ConfigFilesMap = boost::container::map<ShmemString, ShmemString, std::less<ShmemString>, ShmemAllocator>;

    struct SharedData {
        boost::interprocess::interprocess_upgradable_mutex mutex;
        uint64_t configRevision;
        ConfigFilesMap configFiles;
        SharedData(const ShmemAllocator &alloc) : configFiles(std::less<ShmemString>(), alloc) {
            configRevision = 1;
        }
    };

    CoordinatorConfigurationProvider(std::shared_ptr<LoggerInterface> logger, std::shared_ptr<opentelemetry::php::transport::OpAmp> opAmp) : logger_(std::move(logger)), opAmp_(std::move(opAmp)), region_(boost::interprocess::anonymous_shared_memory(1024 * 1024)), managedRegion_(boost::interprocess::create_only, region_.get_address(), region_.get_size()) {
        sharedData_ = managedRegion_.construct<SharedData>("SharedData")(managedRegion_.get_segment_manager());

        ELOG_DEBUG(logger_, COORDINATOR, "CoordinatorConfigurationProvider initialized with shared memory region of size {}", region_.get_size());
        ELOG_DEBUG(logger_, COORDINATOR, "CoordinatorConfigurationProvider initialized with managed memory region of size {}", managedRegion_.get_size());

        opAmp_->addConfigUpdateWatcher([this](opentelemetry::php::transport::OpAmp::configFiles_t const &configFiles) {
            this->storeConfigFiles(configFiles);
        });
    }

    ~CoordinatorConfigurationProvider() {
        opAmp_->removeAllConfigUpdateWatchers();
    }

    bool triggerUpdateIfChanged() {
        ELOG_DEBUG(logger_, COORDINATOR, "CoordinatorConfigurationProvider: checking for config updates");
        boost::interprocess::sharable_lock<boost::interprocess::interprocess_upgradable_mutex> lock(sharedData_->mutex);
        if (sharedData_->configRevision != localConfigRevision_) {
            localConfigRevision_ = sharedData_->configRevision;
            configFiles_t configFiles = getConfigurationNoLock();
            ELOG_DEBUG(logger_, COORDINATOR, "CoordinatorConfigurationProvider: detected config update to revision {}, notifying {} watchers. Config files: {}", localConfigRevision_, configUpdatedWatchers_.num_slots(), configFiles.size());

            configUpdatedWatchers_(configFiles);

            return true;
        }
        return false;
    }

    boost::signals2::connection addConfigUpdateWatcher(configUpdated_t::slot_function_type watcher) {
        return configUpdatedWatchers_.connect(std::move(watcher));
    }

    void removeConfigUpdateWatcher(boost::signals2::connection watcher) {
        watcher.disconnect();
    }

    void removeAllConfigUpdateWatchers() {
        configUpdatedWatchers_.disconnect_all_slots();
    }

    void beginConfigurationFetching() {
        opAmp_->startCommunication();
    }

    std::unordered_map<std::string, std::string> getConfiguration() {
        boost::interprocess::sharable_lock<boost::interprocess::interprocess_upgradable_mutex> lock(sharedData_->mutex);
        return getConfigurationNoLock();
    }

private:

    std::unordered_map<std::string, std::string> getConfigurationNoLock() {
        std::unordered_map<std::string, std::string> result;
        for (const auto &pair : sharedData_->configFiles) {
            result.emplace(std::string(pair.first.c_str()), std::string(pair.second.c_str()));
        }
        return result;
    }

    // store config files on coordinator process side
    void storeConfigFiles(std::unordered_map<std::string, std::string> const &configFiles) {
        boost::interprocess::scoped_lock<boost::interprocess::interprocess_upgradable_mutex> lock(sharedData_->mutex);
        sharedData_->configRevision++;
        for (const auto &pair : configFiles) {
            ShmemAllocator alloc = managedRegion_.get_segment_manager();
            ShmemString shmemFileName(std::string_view(pair.first), alloc);
            ShmemString shmemFileContent(std::string_view(pair.second), alloc);
            sharedData_->configFiles.insert_or_assign(std::move(shmemFileName), std::move(shmemFileContent));
        }
        ELOG_DEBUG(logger_, COORDINATOR, "CoordinatorConfigurationProvider: stored {} config files, revision: {}", configFiles.size(), sharedData_->configRevision);
    }


    std::shared_ptr<LoggerInterface> logger_;
    std::shared_ptr<opentelemetry::php::transport::OpAmp> opAmp_;

    boost::interprocess::mapped_region region_;
    boost::interprocess::managed_external_buffer managedRegion_;
    SharedData *sharedData_{nullptr};
    uint64_t localConfigRevision_ = 0;
    configUpdated_t configUpdatedWatchers_;

};

} // namespace elasticapm::php::coordinator