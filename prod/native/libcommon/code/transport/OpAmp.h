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

#include "HttpTransportAsyncInterface.h"
#include "ForkableInterface.h"
#include "LoggerInterface.h"
#include "ConfigurationStorage.h"
#include "CommonUtils.h"

#include <boost/core/noncopyable.hpp>
#include <boost/uuid.hpp>
#include <condition_variable>
#include <memory>
#include <string>
#include <thread>
#include <unordered_map>

using namespace std::literals;

namespace opentelemetry::php {
class ResourceDetector;
}

namespace opentelemetry::php::transport {

class OpAmp : public elasticapm::php::ForkableInterface, public boost::noncopyable, public std::enable_shared_from_this<OpAmp> {
public:
    OpAmp(std::shared_ptr<elasticapm::php::LoggerInterface> log, std::shared_ptr<elasticapm::php::ConfigurationStorage> config, std::shared_ptr<elasticapm::php::transport::HttpTransportAsyncInterface> transport, std::shared_ptr<opentelemetry::php::ResourceDetector> resourceDetector) : log_(std::move(log)), config_(std::move(config)), transport_(std::move(transport)), resourceDetector_(std::move(resourceDetector)) {
    }

    ~OpAmp() {
        ELOG_DEBUG(log_, OPAMP, "going down");
        shutdownThread();
    }

    void prefork() final {
        ELOG_DEBUG(log_, OPAMP, "prefork");
        shutdownThread();
    }

    void postfork([[maybe_unused]] bool child) final {
        ELOG_DEBUG(log_, OPAMP, "postfork in {}", child ? "child"sv : "parent"sv);
        working_ = true;
        startThread();
        pauseCondition_.notify_all();
    }

    void init(std::string endpointUrl, std::vector<std::pair<std::string_view, std::string_view>> const &endpointHeaders, std::chrono::milliseconds timeout, std::size_t maxRetries, std::chrono::milliseconds retryDelay);

protected:
    void sendInitialAgentToServer();
    void handleServerToAgent(const char *data, std::size_t size);

    void startThread() {
        std::lock_guard<std::mutex> lock(mutex_);
        if (!thread_) {
            ELOG_DEBUG(log_, OPAMP, "startThread");
            thread_ = std::make_unique<std::thread>([this]() { opAmpHeartbeatTask(); });
        }
    }

    void shutdownThread() {
        {
            std::lock_guard<std::mutex> lock(mutex_);
            if (thread_) {
                ELOG_DEBUG(log_, OPAMP, "shutdownThread");
            }

            working_ = false;
        }
        pauseCondition_.notify_all();

        if (thread_ && thread_->joinable()) {
            thread_->join();
        }
        thread_.reset();
    }

    void opAmpHeartbeatTask() {
        ELOGF_DEBUG(log_, OPAMP, "opAmpHeartbeat blocking signals and starting work");

        elasticapm::utils::blockApacheAndPHPSignals();

        std::unique_lock<std::mutex> lock(mutex_);
        while (working_) {
            pauseCondition_.wait_for(lock, heartbeatInterval_.load(), [this]() -> bool { return !working_; });

            if (!working_ && !forceFlushOnDestruction_) {
                break;
            }

            try {
                sendHeartbeat();
            } catch (std::exception const &e) {
                ELOG_WARNING(log_, OPAMP, "Unable to send heartbeat {}", e.what());
            }
        }
    }

    void sendHeartbeat();

private:
    std::atomic<std::chrono::seconds> heartbeatInterval_ = 30s;

    std::mutex mutex_;
    std::unique_ptr<std::thread> thread_;
    std::condition_variable pauseCondition_;
    bool working_ = true;
    std::atomic_bool forceFlushOnDestruction_ = false;
    std::size_t endpointHash_ = 0;

    std::shared_ptr<elasticapm::php::LoggerInterface> log_;
    std::shared_ptr<elasticapm::php::ConfigurationStorage> config_;
    std::shared_ptr<elasticapm::php::transport::HttpTransportAsyncInterface> transport_;
    boost::uuids::uuid agentUid_{boost::uuids::random_generator()()};

    std::string currentConfigHash_;
    std::unordered_map<std::string, std::string> configFiles_;
    std::shared_ptr<opentelemetry::php::ResourceDetector> resourceDetector_;
};

} // namespace opentelemetry::php::transport