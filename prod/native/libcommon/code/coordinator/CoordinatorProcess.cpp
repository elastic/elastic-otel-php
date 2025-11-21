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

#include "CoordinatorProcess.h"

namespace elasticapm::php::coordinator {

void CoordinatorProcess::coordinatorLoop() {
    configProvider_->beginConfigurationFetching();
    setupPeriodicTasks();
    periodicTaskExecutor_->resumePeriodicTasks();

    char buffer[maxMqPayloadSize];
    while (working_.load()) {
        size_t receivedSize = 0;
        unsigned int priority = 0;

        try {
            if (commandQueue_->timed_receive(buffer, maxMqPayloadSize, receivedSize, priority, std::chrono::steady_clock::now() + std::chrono::milliseconds(10))) {
                processor_.processReceivedChunk(reinterpret_cast<const CoordinatorPayload *>(buffer), receivedSize);
            }
        } catch (std::exception &ex) {
            ELOG_DEBUG(logger_, COORDINATOR, "CoordinatorProcess: message_queue receive failed: '{}'", ex.what());
            continue;
        }
    }
    ELOG_DEBUG(logger_, COORDINATOR, "CoordinatorProcess coordinator loop exiting");
}

void CoordinatorProcess::setupPeriodicTasks() {
    periodicTaskExecutor_ = std::make_unique<PeriodicTaskExecutor>(std::vector<PeriodicTaskExecutor::task_t>{[this](PeriodicTaskExecutor::time_point_t now) {
        // Check parent process is alive
        if (getppid() != parentProcessId_) {
            ELOG_DEBUG(logger_, COORDINATOR, "CoordinatorProcess: parent process has exited, shutting down coordinator process");
            working_ = false;
        }

        static auto lastCleanupTime = std::chrono::steady_clock::now();
        if (now - lastCleanupTime >= cleanUpLostMessagesInterval) {
            processor_.cleanupAbandonedMessages(now, std::chrono::seconds(10));
            lastCleanupTime = now;
        }
    }});
    periodicTaskExecutor_->setInterval(std::chrono::milliseconds(100));
}

bool CoordinatorProcess::enqueueMessage(const void *data, size_t size) {
    try {
        commandQueue_->try_send(data, size, 0);
        return true;
    } catch (boost::interprocess::interprocess_exception &ex) {
        if (logger_) {
            ELOG_DEBUG(logger_, COORDINATOR, "CoordinatorProcess: message_queue send failed: {}", ex.what());
        }
        return false;
    }
}

} // namespace elasticapm::php::coordinator
