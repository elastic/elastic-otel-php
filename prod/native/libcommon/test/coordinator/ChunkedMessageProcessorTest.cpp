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

#include "coordinator/ChunkedMessageProcessor.h"
#include "Logger.h"
#include <algorithm>

#include <gtest/gtest.h>
#include <gmock/gmock.h>

namespace elasticapm::php::coordinator {

class ChunkedMessageProcessorActionsMock {
public:
    MOCK_METHOD(bool, sendBuffer, (const void *buffer, size_t size));
    MOCK_METHOD(void, processReceivedMessage, (const std::span<const std::byte> data));
};

class TestableChunkedMessageProcessor : public elasticapm::php::coordinator::ChunkedMessageProcessor {
public:
    template <typename... Args>
        TestableChunkedMessageProcessor(Args&&... args)
            : ChunkedMessageProcessor(std::forward<Args>(args)...) {}


    FRIEND_TEST(ChunkedMessageProcessorTest, ShortPayloadIsImmediatelyProcessedUponReception);
    FRIEND_TEST(ChunkedMessageProcessorTest, LongerPayloadIsStoredUntilCompleteUponReception);
    FRIEND_TEST(ChunkedMessageProcessorTest, cleanupAbandonedMessagesRemovesPartialMessage);
};

class ChunkedMessageProcessorTest : public ::testing::Test {
public:
    ChunkedMessageProcessorTest() {

        if (std::getenv("ELASTIC_OTEL_DEBUG_LOG_TESTS")) {
            auto serr = std::make_shared<elasticapm::php::LoggerSinkStdErr>();
            serr->setLevel(logLevel_trace);
            reinterpret_cast<elasticapm::php::Logger *>(log_.get())->attachSink(serr);
        }
    }

protected:
    ::testing::StrictMock<ChunkedMessageProcessorActionsMock> actionsMock_;
    std::shared_ptr<LoggerInterface> log_ = std::make_shared<elasticapm::php::Logger>(std::vector<std::shared_ptr<LoggerSinkInterface>>());
    std::shared_ptr<TestableChunkedMessageProcessor> processor_{std::make_shared<TestableChunkedMessageProcessor>(
        log_,
        [this](const void *buffer, size_t size) { return actionsMock_.sendBuffer(buffer, size); }, [this](const std::span<const std::byte> data) { actionsMock_.processReceivedMessage(data); })};
};



TEST_F(ChunkedMessageProcessorTest, sendPayload) {
    std::string testPayload(17000, 'A');

    ::testing::InSequence seq;
    EXPECT_CALL(actionsMock_, sendBuffer(testing::_, sizeof(CoordinatorPayload)))
        .Times(4)
        .WillRepeatedly(testing::Return(true));
    EXPECT_CALL(actionsMock_, sendBuffer(testing::_, sizeof(CoordinatorPayload) - (4064 - (17000 % 4064))))
        .Times(1)
        .WillRepeatedly(testing::Return(true));


    EXPECT_TRUE(processor_->sendPayload(testPayload));
}

TEST_F(ChunkedMessageProcessorTest, sendPayloadExactSize) {
    std::string testPayload(12192, 'A');

    ::testing::InSequence seq;
    EXPECT_CALL(actionsMock_, sendBuffer(testing::_, sizeof(CoordinatorPayload)))
        .Times(3)
        .WillRepeatedly(testing::Return(true));

    EXPECT_TRUE(processor_->sendPayload(testPayload));
}

TEST_F(ChunkedMessageProcessorTest, sendPayloadExceedSizeByOneByte) {
    std::string testPayload(12193, 'A');

    ::testing::InSequence seq;
    EXPECT_CALL(actionsMock_, sendBuffer(testing::_, sizeof(CoordinatorPayload)))
        .Times(3)
        .WillRepeatedly(testing::Return(true));
    EXPECT_CALL(actionsMock_, sendBuffer(testing::_, offsetof(CoordinatorPayload, payload) + 1))
        .Times(1)
        .WillRepeatedly(testing::Return(true));

    EXPECT_TRUE(processor_->sendPayload(testPayload));
}

TEST_F(ChunkedMessageProcessorTest, processReceivedChunk) {
    std::array<std::byte, 10000> randomData;
    std::generate(randomData.begin(), randomData.end(), []() { return std::byte(std::rand() % 256); });
    randomData.fill(std::byte{0});

    CoordinatorPayload chunk;
    chunk.senderProcessId = getpid();
    chunk.msgId = 1;
    chunk.payloadTotalSize = randomData.size();

    chunk.payloadOffset = 0;
    std::memcpy(chunk.payload.data() + chunk.payloadOffset, randomData.data() + chunk.payloadOffset, sizeof(CoordinatorPayload::payload));
    processor_->processReceivedChunk(&chunk, sizeof(CoordinatorPayload));

    chunk.payloadOffset += sizeof(CoordinatorPayload::payload);
    std::memcpy(chunk.payload.data() + chunk.payloadOffset, randomData.data() + chunk.payloadOffset, sizeof(CoordinatorPayload::payload));
    processor_->processReceivedChunk(&chunk, sizeof(CoordinatorPayload));

    EXPECT_CALL(actionsMock_, processReceivedMessage(::testing::_)).Times(1).WillOnce(::testing::WithArgs<0>(::testing::Invoke([&](std::span<const std::byte> data) {
        EXPECT_EQ(data.size(), randomData.size());
        ASSERT_TRUE(std::ranges::equal(data, randomData));
    })));

    chunk.payloadOffset += sizeof(CoordinatorPayload::payload);
    std::memcpy(chunk.payload.data() + chunk.payloadOffset, randomData.data() + chunk.payloadOffset, 10000 % sizeof(CoordinatorPayload::payload));
    processor_->processReceivedChunk(&chunk, (randomData.size() % sizeof(CoordinatorPayload::payload)) + offsetof(CoordinatorPayload, payload));
}

TEST_F(ChunkedMessageProcessorTest, processReceivedChunkExceedSize) {
    CoordinatorPayload chunk;
    chunk.senderProcessId = getpid();
    chunk.msgId = 1;
    chunk.payloadTotalSize = 10000;
    chunk.payloadOffset = 0;
    chunk.payload.fill(std::byte{'A'});

    processor_->processReceivedChunk(&chunk, 4096);
    chunk.payloadOffset += 4064;
    processor_->processReceivedChunk(&chunk, 4096);
    chunk.payloadOffset += 4064;

    EXPECT_THROW(processor_->processReceivedChunk(&chunk, 4096), std::runtime_error);
}


TEST_F(ChunkedMessageProcessorTest, DontSendEmptyPayload) {
    ::testing::InSequence seq;
    EXPECT_CALL(actionsMock_, sendBuffer(::testing::_, ::testing::_)).Times(0);
    EXPECT_TRUE(processor_->sendPayload(""));
}

TEST_F(ChunkedMessageProcessorTest, sendPayloadSmallSingleChunk) {
    std::string testPayload(1, 'Z');
    size_t expectedSize = offsetof(CoordinatorPayload, payload) + testPayload.size();
    EXPECT_CALL(actionsMock_, sendBuffer(::testing::_, expectedSize))
        .Times(1)
        .WillOnce(::testing::Return(true));
    EXPECT_TRUE(processor_->sendPayload(testPayload));
}

TEST_F(ChunkedMessageProcessorTest, ShortPayloadIsImmediatelyProcessedUponReception) {

    EXPECT_CALL(actionsMock_, sendBuffer(testing::_, testing::_)).Times(2).WillRepeatedly(::testing::Invoke([&](const void *buffer, size_t size) {
        processor_->processReceivedChunk(static_cast<const CoordinatorPayload *>(buffer), size);
        return true;
    }));


    {
    ::testing::InSequence seq;
    EXPECT_CALL(actionsMock_, processReceivedMessage(testing::_))
        .Times(1)
        .WillOnce(::testing::Invoke([&](std::span<const std::byte> data) {
            ASSERT_EQ(processor_->recievedMessages_.size(), 0u);
        }));

    EXPECT_CALL(actionsMock_, processReceivedMessage(testing::_))
        .Times(1)
        .WillOnce(::testing::Invoke([&](std::span<const std::byte> data) {
            ASSERT_EQ(processor_->recievedMessages_.size(), 0u);
        }));

    }

    ASSERT_EQ(processor_->msgId_, 0u);
    EXPECT_TRUE(processor_->sendPayload("A"));
    ASSERT_EQ(processor_->msgId_, 1u);
    EXPECT_TRUE(processor_->sendPayload(std::string(10, 'B')));
    ASSERT_EQ(processor_->msgId_, 2u);

}

TEST_F(ChunkedMessageProcessorTest, LongerPayloadIsStoredUntilCompleteUponReception) {

    size_t expectedMessagesCount[6] = {1, 1, 0, 1, 1, 0}; // expected number of stored messages after each chunk reception, 0 means message is complete and it is removed before processing
    size_t expectedStoredChunkSize[6] = {sizeof(CoordinatorPayload::payload), sizeof(CoordinatorPayload::payload) * 2, 0, sizeof(CoordinatorPayload::payload), sizeof(CoordinatorPayload::payload) * 2, 0}; // expected chunk size after each chunk reception, 0 means message is complete and it is removed before processing
    size_t invocationCnt = 0;

    EXPECT_CALL(actionsMock_, sendBuffer(testing::_, testing::_)).Times(6).WillRepeatedly(::testing::Invoke([&](const void *buffer, size_t size) -> bool {
        processor_->processReceivedChunk(static_cast<const CoordinatorPayload *>(buffer), size);
        EXPECT_EQ(expectedMessagesCount[invocationCnt], processor_->recievedMessages_.size());

        if (expectedMessagesCount[invocationCnt] == 0) {
            // message should be removed after processing
            invocationCnt++;
            return true;
        }

        auto messagesForPid = processor_->recievedMessages_.find(getpid());
        EXPECT_NE(messagesForPid, processor_->recievedMessages_.end());
        if (messagesForPid == processor_->recievedMessages_.end()) {
            return false;
        }

        auto chMsg = messagesForPid->second.find(processor_->msgId_);
        EXPECT_NE(chMsg, messagesForPid->second.end());
        if (chMsg == messagesForPid->second.end()) {
            return false;
        }


        auto currentChunkSize = chMsg->second.getData().size();
        EXPECT_EQ(expectedStoredChunkSize[invocationCnt], currentChunkSize);
        if (currentChunkSize != expectedStoredChunkSize[invocationCnt]) {
            return false;
        }

        invocationCnt++;
        return true;
    }));

    constexpr size_t payloadSize = 10000u;

    ::testing::InSequence seq;
    EXPECT_CALL(actionsMock_, processReceivedMessage(testing::_))
        .Times(2)
        .WillRepeatedly(::testing::Invoke([&](std::span<const std::byte> data) {
            ASSERT_EQ(data.size(), payloadSize);
            ASSERT_EQ(processor_->recievedMessages_.size(), 0u); // test if processed message is removed
        }));


    std::string data(payloadSize, 'C');

    ASSERT_EQ(processor_->msgId_, 0u);
    EXPECT_TRUE(processor_->sendPayload(data));
    ASSERT_EQ(processor_->msgId_, 1u);
    EXPECT_TRUE(processor_->sendPayload(data));
    ASSERT_EQ(processor_->msgId_, 2u);

}

TEST_F(ChunkedMessageProcessorTest, cleanupAbandonedMessagesRemovesPartialMessage) {
    constexpr size_t capacity = sizeof(CoordinatorPayload::payload);
    size_t totalSize = capacity * 2 + 10; // message needing 3 chunks
    // std::vector<std::byte> data(totalSize, std::byte{1});

    CoordinatorPayload chunk;
    chunk.senderProcessId = 1;
    chunk.msgId = 777;
    chunk.payloadTotalSize = totalSize;
    chunk.payloadOffset = 0;
    chunk.payload.fill(std::byte{1});

    ASSERT_TRUE(processor_->recievedMessages_.empty());

    EXPECT_NO_THROW(processor_->processReceivedChunk(&chunk, sizeof(CoordinatorPayload)));

    ASSERT_EQ(processor_->recievedMessages_.size(), 1u);

    std::this_thread::sleep_for(std::chrono::milliseconds(10));

    chunk.senderProcessId = 2;
    EXPECT_NO_THROW(processor_->processReceivedChunk(&chunk, sizeof(CoordinatorPayload)));

    auto now = std::chrono::steady_clock::now();
    processor_->cleanupAbandonedMessages(now, std::chrono::milliseconds(9)); // should cleanup only first message at first attempt
    ASSERT_EQ(processor_->recievedMessages_.size(), 1u);

    // Cleanup after large time advance
    processor_->cleanupAbandonedMessages(now + std::chrono::hours(1), std::chrono::seconds(1));

    ASSERT_TRUE(processor_->recievedMessages_.empty());
}


TEST_F(ChunkedMessageProcessorTest, sendPayloadLargeSingleFailureNoFurtherCalls) {
    std::string payload(10000, 'X'); // requires 3 chunks (2 full + last partial)
    {
        ::testing::InSequence seq;
        EXPECT_CALL(actionsMock_, sendBuffer(testing::_, sizeof(CoordinatorPayload)))
            .Times(1)
            .WillOnce(testing::Return(false));
        EXPECT_FALSE(processor_->sendPayload(payload));
    }
}

TEST_F(ChunkedMessageProcessorTest, sendPayloadMsgIdSequenceWithDifferentSizes) {
    std::vector<size_t> ids;
    EXPECT_CALL(actionsMock_, sendBuffer(testing::_, testing::_))
        .Times(4)
        .WillRepeatedly(::testing::Invoke([&](const void *buffer, size_t) {
            auto *p = static_cast<const CoordinatorPayload *>(buffer);
            ids.push_back(p->msgId);
            return true;
        }));
    EXPECT_TRUE(processor_->sendPayload(std::string(1, 'A')));          // single chunk
    EXPECT_TRUE(processor_->sendPayload(std::string(5000, 'B')));       // two chunks (first captured)
    EXPECT_TRUE(processor_->sendPayload(std::string(10, 'C')));         // single chunk
    ASSERT_EQ(ids.size(), 4u);
    EXPECT_EQ(ids[0], 1u);
    EXPECT_EQ(ids[1], 2u);
    EXPECT_EQ(ids[2], 2u);
    EXPECT_EQ(ids[3], 3u);
}

TEST_F(ChunkedMessageProcessorTest, processReceivedChunkWithInvalidSize) {
    CoordinatorPayload chunk;
    chunk.senderProcessId = getpid();
    chunk.msgId = 1;
    chunk.payloadTotalSize = 100;
    chunk.payloadOffset = 0;

    // Size smaller than header
    EXPECT_THROW(processor_->processReceivedChunk(&chunk, offsetof(CoordinatorPayload, payload) - 1), std::runtime_error);
}

TEST_F(ChunkedMessageProcessorTest, processReceivedChunkWithMismatchedOffset) {
    CoordinatorPayload chunk;
    chunk.senderProcessId = getpid();
    chunk.msgId = 1;
    chunk.payloadTotalSize = 10000;
    chunk.payloadOffset = 0;

    processor_->processReceivedChunk(&chunk, sizeof(CoordinatorPayload));

    // Send chunk with wrong offset (not sequential)
    chunk.payloadOffset = 8000; // skipping chunks
    EXPECT_THROW(processor_->processReceivedChunk(&chunk, sizeof(CoordinatorPayload)), std::runtime_error);
}

}