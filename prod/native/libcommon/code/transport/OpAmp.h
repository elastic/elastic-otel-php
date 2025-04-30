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

#include <boost/core/noncopyable.hpp>
#include <memory>
#include <thread>

using namespace std::literals;

namespace opentelemetry::php::transport {

class OpAmp : public elasticapm::php::ForkableInterface, public boost::noncopyable {
public:
    OpAmp(std::shared_ptr<elasticapm::php::LoggerInterface> log, std::shared_ptr<elasticapm::php::ConfigurationStorage> config, std::shared_ptr<elasticapm::php::transport::HttpTransportAsyncInterface> transport) : log_(std::move(log)), config_(std::move(config)), transport_(std::move(transport)) {
        std::vector<std::pair<std::string_view, std::string_view>> headers{{"Authorization", "Franek"}};
        transport_->initializeConnection("http://localhost/v1/opamp", 1244, "application/x-protobuf"s, headers, std::chrono::duration_cast<std::chrono::milliseconds>(std::chrono::duration<double>(10)), static_cast<std::size_t>(10), std::chrono::milliseconds(10s));
        startThread();
    }
    // [](int16_t responseCode, std::span<std::byte> data) { std::cout << "== code: " << (int)responseCode << "======= size:" << data.size() << "================\n" << reinterpret_cast<const char *>(data.data()) << "\n==================\n"; }

    ~OpAmp() {
        shutdownThread();
    }

    void prefork() final {
        ELOG_DEBUG(log_, OPAMP, "prefork");
        shutdownThread();
    }

    void postfork([[maybe_unused]] bool child) final {
        ELOG_DEBUG(log_, OPAMP, "postfork in {}", child ? "child"sv : "parent"sv);
        // if (child && !payloadsToSend_.empty()) {
        //     ELOGF_DEBUG(log_, TRANSPORT, "HttpTransportAsync::postfork child emptying payloads queue. %zu will be sent from parent", payloadsToSend_.size());
        //     decltype(payloadsToSend_) q;
        //     payloadsToSend_.swap(q);
        // }
        working_ = true;
        startThread();
        pauseCondition_.notify_all();
    }

protected:
    void startThread() {
        std::lock_guard<std::mutex> lock(mutex_);
        if (!thread_) {
            ELOG_DEBUG(log_, OPAMP, "OpAmp startThread");
            thread_ = std::make_unique<std::thread>([this]() { opAmpHeartbeat(); });
        }
    }

    void shutdownThread() {
        {
            std::lock_guard<std::mutex> lock(mutex_);
            if (thread_) {
                ELOG_DEBUG(log_, OPAMP, "OpAmp shutdownThread");
            }

            working_ = false;
        }
        pauseCondition_.notify_all();

        if (thread_ && thread_->joinable()) {
            thread_->join();
        }
        thread_.reset();
    }

    void opAmpHeartbeat() {
        ELOGF_DEBUG(log_, OPAMP, "opAmpHeartbeat blocking signals and starting work");

        elasticapm::utils::blockApacheAndPHPSignals();

        std::unique_lock<std::mutex> lock(mutex_);
        while (working_) {
            pauseCondition_.wait_for(lock, heartbeatInterval_, [this]() -> bool {
                //  return !payloadsToSend_.empty() || !working_;
                return !working_;
            });

            if (!working_ && !forceFlushOnDestruction_) {
                break;
            }

            // sendHeartbeat();
        }
    }

private:
    static constexpr std::chrono::seconds heartbeatInterval_{1};

    std::mutex mutex_;
    std::unique_ptr<std::thread> thread_;
    std::condition_variable pauseCondition_;
    bool working_ = true;
    std::atomic_bool forceFlushOnDestruction_ = false;

    std::shared_ptr<elasticapm::php::LoggerInterface> log_;
    std::shared_ptr<elasticapm::php::ConfigurationStorage> config_;
    std::shared_ptr<elasticapm::php::transport::HttpTransportAsyncInterface> transport_;
};

} // namespace opentelemetry::php::transport