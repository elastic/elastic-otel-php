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
#include "ForkableInterface.h"
#include "PeriodicTaskExecutor.h"
#include "ChunkedMessageProcessor.h"
#include "CoordinatorTelemetrySignalsSender.h"
#include "CoordinatorMessagesDispatcher.h"

#include <boost/interprocess/ipc/message_queue.hpp>
#include <boost/noncopyable.hpp>

#include <atomic>
#include <chrono>
#include <functional>
#include <memory>
#include <string>

namespace elasticapm::php::coordinator {

namespace {
constexpr static size_t maxMqPayloadSize = sizeof(CoordinatorPayload);
constexpr static size_t maxQueueSize = 100;
constexpr static std::chrono::minutes cleanUpLostMessagesInterval(1);
} // namespace

class CoordinatorProcess : public boost::noncopyable, public ForkableInterface {

public:
    CoordinatorProcess(std::shared_ptr<LoggerInterface> logger, std::shared_ptr<CoordinatorMessagesDispatcher> messagesDispatcher)
        : logger_(std::move(logger)), messagesDispatcher_(std::move(messagesDispatcher)) {
    }
    ~CoordinatorProcess() {
    }

    void prefork() final {
        periodicTaskExecutor_->prefork();
    }

    void postfork([[maybe_unused]] bool child) final {
        periodicTaskExecutor_->postfork(child);
    }

    // returns true in scope of forked CoordinatorProcess
    bool start() {
        parentProcessId_ = getpid();
        processId_ = fork();
        if (processId_ < 0) {
            if (logger_) {
                ELOG_DEBUG(logger_, COORDINATOR, "CoordinatorProcess: fork() failed: {} ({})", strerror(errno), errno);
            }
        } else if (processId_ == 0) {
            ELOG_DEBUG(logger_, COORDINATOR, "CoordinatorProcess starting collector process");
            coordinatorLoop();
            ELOG_DEBUG(logger_, COORDINATOR, "CoordinatorProcess: collector process is going to finish");
            return true;
        } else {
            if (logger_) {
                ELOG_DEBUG(logger_, COORDINATOR, "CoordinatorProcess parent process continues initialization");
            }
        }
        return false;
    }

    CoordinatorTelemetrySignalsSender &getCoordinatorSender() {
        return coordinatorSender_;
    }

private:
    void coordinatorLoop();
    void setupPeriodicTasks();
    bool enqueueMessage(const void *data, size_t size);

private:
    std::atomic_bool working_ = true;
    std::shared_ptr<LoggerInterface> logger_;
    std::unique_ptr<PeriodicTaskExecutor> periodicTaskExecutor_;

    std::shared_ptr<boost::interprocess::message_queue> commandQueue_{std::make_shared<boost::interprocess::message_queue>(maxQueueSize, maxMqPayloadSize)};
    ChunkedMessageProcessor processor_{logger_, maxMqPayloadSize, [this](const void *data, size_t size) { return enqueueMessage(data, size); }, [this](const std::span<const std::byte> data) { messagesDispatcher_->processRecievedMessage(data); }};

    CoordinatorTelemetrySignalsSender coordinatorSender_{logger_, [this](const std::string &payload) { return processor_.sendPayload(payload); }};
    std::shared_ptr<CoordinatorMessagesDispatcher> messagesDispatcher_;

    int processId_ = 0;
    int parentProcessId_ = 0;
};

}
