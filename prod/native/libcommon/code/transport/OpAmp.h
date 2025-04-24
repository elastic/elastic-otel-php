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

#include <boost/asio/io_context.hpp>
#include "ForkableInterface.h"
#include "LoggerInterface.h"
#include "ConfigurationStorage.h"
#include "WebSocketClientInterface.h"

#include <memory>
#include <thread>
#include <iostream>

using namespace std::literals;

namespace opentelemetry::php::transport {

class OpAmp : public elasticapm::php::ForkableInterface, public boost::noncopyable {
public:
    OpAmp(std::shared_ptr<elasticapm::php::LoggerInterface> log, std::shared_ptr<elasticapm::php::ConfigurationStorage> config) : log_(std::move(log)), config_(std::move(config)) {
    }

    ~OpAmp() {
        shutdownThread();
    }

    void prefork() final {
        ELOGF_DEBUG(log_, OPAMP, "OpAmp prefork");
        shutdownThread();
        ioContext_.notify_fork(boost::asio::execution_context::fork_prepare);
    }

    void postfork([[maybe_unused]] bool child) final {
        ioContext_.notify_fork(child ? boost::asio::execution_context::fork_child : boost::asio::execution_context::fork_parent);

        ioContext_.restart();

        if (child) {
            ELOGF_DEBUG(log_, OPAMP, "OpAmp postfork (child)");
            workGuard_.emplace(boost::asio::make_work_guard(ioContext_));
        } else {
            ELOGF_DEBUG(log_, OPAMP, "OpAmp postfork (parent)");
        }
        startThread();
    }

    // "echo.websocket.org", "443", "/.sse"
    void startWebSocketClient(std::string url);
    void sayHello() {
        client_->send("InitialHello");
    }

    void sendInitialAgentToServer();

protected:
    void setHeartbeat(std::chrono::seconds interval);

    void startThread() {
        std::lock_guard<std::mutex> lock(mutex_);
        if (!thread_) {
            ELOGF_DEBUG(log_, OPAMP, "OpAmp startThread");
            thread_ = std::make_unique<std::thread>([this]() {
                ELOGF_DEBUG(log_, OPAMP, "OpAmp starting io");
                ioContext_.run();
            });
        }
    }

    void shutdownThread() {
        ELOGF_DEBUG(log_, OPAMP, "OpAmp shutdownThread");
        if (client_) {
            client_->stop();
        }

        ELOGF_DEBUG(log_, OPAMP, "OpAmp shutdownThread stopping io");
        if (workGuard_) {
            workGuard_->reset();
            workGuard_.reset();
        }
        ioContext_.stop();

        ELOGF_DEBUG(log_, OPAMP, "OpAmp shutdownThread waiting for joining thread");

        if (thread_ && thread_->joinable()) {
            thread_->join();
        }
        thread_.reset();
        ioContext_.restart();
    }

    void onConnected();

    void handleServerToAgent(const char *data, std::size_t size);

private:
    boost::asio::io_context ioContext_;
    std::optional<boost::asio::executor_work_guard<boost::asio::io_context::executor_type>> workGuard_{boost::asio::make_work_guard(ioContext_)};

    std::mutex mutex_;
    std::unique_ptr<std::thread> thread_;

    std::shared_ptr<elasticapm::php::LoggerInterface> log_;
    std::shared_ptr<elasticapm::php::ConfigurationStorage> config_;
    std::shared_ptr<WebSocketClientInterface> client_;
};

} // namespace opentelemetry::php::transport