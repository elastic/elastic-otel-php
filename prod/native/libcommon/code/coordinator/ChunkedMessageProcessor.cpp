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

#include "ChunkedMessageProcessor.h"

namespace elasticapm::php::coordinator {

bool ChunkedMessageProcessor::sendPayload(const std::string &payload) {
    msgId_++;
    std::size_t dataPayloadSize = sizeof(CoordinatorPayload::payload);

    CoordinatorPayload chunk;
    chunk.senderProcessId = senderProcessId_;
    chunk.msgId = msgId_;
    chunk.payloadTotalSize = payload.size();
    chunk.payloadOffset = 0;

    while (chunk.payloadOffset < payload.size()) {
        size_t chunkSize = std::min(dataPayloadSize, payload.size() - chunk.payloadOffset);

        ELOG_TRACE(logger_, COORDINATOR, "ChunkedMessageProcessor: sending chunked message. msgId: {}, offset: {}, size: {}, totalSize: {}, data size in chunk: {}", msgId_, chunk.payloadOffset, chunkSize, payload.size(), chunkSize + offsetof(CoordinatorPayload, payload));

        std::memcpy(chunk.payload.data(), payload.data() + chunk.payloadOffset, chunkSize);

        if (!sendBuffer_(&chunk, chunkSize + offsetof(CoordinatorPayload, payload))) {
            ELOG_WARNING(logger_, COORDINATOR, "ChunkedMessageProcessor: failed to send chunked message. msgId: {}, offset: {}", msgId_, chunk.payloadOffset);
            return false;
        }

        chunk.payloadOffset += chunkSize;
    }
    return true;
}

void ChunkedMessageProcessor::processReceivedChunk(const CoordinatorPayload *chunk, size_t chunkSize) {
    ELOG_TRACE(logger_, COORDINATOR, "ChunkedMessageProcessor: received chunked message. pid: {}, msgId: {}, offset: {}, chunkSize: {}, totalSize: {}", chunk->senderProcessId, chunk->msgId, chunk->payloadOffset, chunkSize, chunk->payloadTotalSize);
    std::unique_lock<std::mutex> lock(mutex_);

    auto &messagesForSender = recievedMessages_[chunk->senderProcessId];
    auto it = messagesForSender.find(chunk->msgId);
    if (it == messagesForSender.end()) {
        it = messagesForSender.emplace(chunk->msgId, ChunkedMessage(chunk->payloadTotalSize)).first;
    }

    ChunkedMessage &message = it->second;

    std::size_t payloadSize = chunkSize - offsetof(CoordinatorPayload, payload); // actual payload size in this chunk
    std::span<const std::byte> chunkData(chunk->payload.data(), payloadSize);

    // Validate offset
    if (message.getCurrentSize() != chunk->payloadOffset) {
        throw std::runtime_error(std::format("ChunkedMessageProcessor: received chunk with unexpected offset: {}, expected: {}", chunk->payloadOffset, message.getCurrentSize()));
    }

    // Validate offset + size does not exceed total size
    if (chunk->payloadOffset + payloadSize > chunk->payloadTotalSize) {
        throw std::runtime_error(std::format("ChunkedMessageProcessor: received chunk exceeds total payload size. Size: {}, offset: {},  expected: {}", payloadSize, chunk->payloadOffset, chunk->payloadTotalSize));
    }

    if (message.addNextChunk(chunkData)) {
        ELOG_TRACE(logger_, COORDINATOR, "ChunkedMessageProcessor: received chunked message. pid: {}, msgId: {}, offset: {}, receivedSize: {}, totalSize: {}. Message complete, processing.", chunk->senderProcessId, chunk->msgId, chunk->payloadOffset, message.getData().size(), chunk->payloadTotalSize);

        std::vector<std::byte> data;
        message.swapData(data);

        messagesForSender.erase(it);
        if (messagesForSender.empty()) {
            recievedMessages_.erase(chunk->senderProcessId);
        }

        lock.unlock();
        processMessage_(data);

    } else {
        ELOG_TRACE(logger_, COORDINATOR, "ChunkedMessageProcessor: received chunked message. msgId: {}, offset: {}, receivedSize: {}, totalSize: {}", chunk->msgId, chunk->payloadOffset, message.getData().size(), chunk->payloadTotalSize);
    }
}

void ChunkedMessageProcessor::cleanupAbandonedMessages(std::chrono::steady_clock::time_point now, std::chrono::milliseconds maxAge) {
    std::lock_guard<std::mutex> lock(mutex_);
    for (auto senderIt = recievedMessages_.begin(); senderIt != recievedMessages_.end();) {
        auto &messagesForSender = senderIt->second;
        for (auto msgIt = messagesForSender.begin(); msgIt != messagesForSender.end();) {
            if (now - msgIt->second.getLastUpdated() > maxAge) {
                ELOG_DEBUG(logger_, COORDINATOR, "ChunkedMessageProcessor: cleaning up old message from sender pid {} msgId {}", senderIt->first, msgIt->first);
                msgIt = messagesForSender.erase(msgIt);
            } else {
                ++msgIt;
            }
        }

        if (messagesForSender.empty()) {
            senderIt = recievedMessages_.erase(senderIt);
        } else {
            ++senderIt;
        }
    }
}

} // namespace elasticapm::php::coordinator
