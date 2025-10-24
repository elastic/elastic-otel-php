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

#include <array>
#include <chrono>
#include <cstring>
#include <functional>
#include <memory>
#include <mutex>
#include <unordered_map>
#include <stdexcept>
#include <vector>

#include "CoordinatorMessagesDispatcher.h"

namespace elasticapm::php::coordinator {

class ChunkedMessage {
public:
    ChunkedMessage(std::size_t totalSize) : totalSize_(totalSize) {
        data_.reserve(totalSize_);
    }

    // return true if message is complete
    bool addNextChunk(const std::span<const std::byte> chunkData) {
        if (data_.size() + chunkData.size_bytes() > totalSize_) {
            throw std::runtime_error("ChunkedMessage: chunk exceeds total size");
        }

        data_.insert(data_.end(), chunkData.begin(), chunkData.end());
        lastUpdated_ = std::chrono::steady_clock::now();
        return data_.size() == totalSize_;
    }

    const std::vector<std::byte> &getData() const {
        return data_;
    }

    void swapData(std::vector<std::byte> &second) {
        data_.swap(second);
    }

    const std::chrono::steady_clock::time_point &getLastUpdated() const {
        return lastUpdated_;
    }

private:
    std::size_t totalSize_;
    std::vector<std::byte> data_;
    std::chrono::steady_clock::time_point lastUpdated_;
};

struct CoordinatorPayload {
    pid_t senderProcessId;
    uint64_t msgId;
    std::size_t payloadTotalSize;
    std::size_t payloadOffset;
    std::array<std::byte, 4064> payload; // it must be last field in the struct. sizeof(CoordinatorPayload) = 4096 bytes with current payload size
};

class ChunkedMessageProcessor {
public:
    using sendBuffer_t = std::function<bool(const void *, size_t)>;
    using processMessage_t = std::function<void(const std::span<const std::byte>)>;

    using msgId_t = uint64_t;

    ChunkedMessageProcessor(std::shared_ptr<LoggerInterface> logger, std::size_t maxChunkSize, sendBuffer_t sendBuffer, processMessage_t processMessage) : logger_(logger), maxChunkSize_(maxChunkSize), sendBuffer_(std::move(sendBuffer)), processMessage_(std::move(processMessage)) {
    }

    bool sendPayload(const std::string &payload);
    void processReceivedChunk(const CoordinatorPayload *chunk, size_t chunkSize);
    void cleanupAbandonedMessages(std::chrono::steady_clock::time_point now, std::chrono::seconds maxAge);

private:
    std::mutex mutex_;
    std::shared_ptr<LoggerInterface> logger_;
    pid_t senderProcessId_ = getpid();
    std::size_t maxChunkSize_;
    sendBuffer_t sendBuffer_;
    processMessage_t processMessage_;
    std::unordered_map<pid_t, std::unordered_map<msgId_t, ChunkedMessage>> recievedMessages_;
    msgId_t msgId_ = 0;
};

} // namespace elasticapm::php::coordinator