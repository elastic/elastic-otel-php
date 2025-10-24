#pragma once

#include "LoggerInterface.h"

#include <chrono>
#include <vector>
#include <cstring>
#include <unordered_map>
#include <stdexcept>
#include <memory>
#include <functional>
#include <sys/types.h>
#include <unistd.h>

#include "CoordinatorMessagesDispatcher.h"

namespace elasticapm::php {


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

    bool sendPayload(const std::string &payload) {
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


    void processReceivedChunk(const CoordinatorPayload *chunk, size_t chunkSize) {
        ELOG_TRACE(logger_, COORDINATOR, "ChunkedMessageProcessor: received chunked message. msgId: {}, offset: {}, chunkSize: {}, totalSize: {}", chunk->msgId, chunk->payloadOffset, chunkSize, chunk->payloadTotalSize);
        std::unique_lock<std::mutex> lock(mutex_);

        auto &messagesForSender = recievedMessages_[chunk->senderProcessId];
        auto it = messagesForSender.find(chunk->msgId);
        if (it == messagesForSender.end()) {
            it = messagesForSender.emplace(chunk->msgId, ChunkedMessage(chunk->payloadTotalSize)).first;
        }

        ChunkedMessage &message = it->second;

        std::size_t payloadSize = chunkSize - offsetof(CoordinatorPayload, payload); // actual payload size in this chunk
        std::span<const std::byte> chunkData(chunk->payload.data(), payloadSize);

        if (message.addNextChunk(chunkData)) {
            ELOG_TRACE(logger_, COORDINATOR, "ChunkedMessageProcessor: received chunked message. msgId: {}, offset: {}, receivedSize: {}, totalSize: {}. Message complete, processing.", chunk->msgId, chunk->payloadOffset, message.getData().size(), chunk->payloadTotalSize);

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

    void cleanupAbandonedMessages(std::chrono::steady_clock::time_point now, std::chrono::seconds maxAge) {
        std::lock_guard<std::mutex> lock(mutex_);
        for (auto senderIt = recievedMessages_.begin(); senderIt != recievedMessages_.end(); ) {
            auto &messagesForSender = senderIt->second;
            for (auto msgIt = messagesForSender.begin(); msgIt != messagesForSender.end(); ) {
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

}