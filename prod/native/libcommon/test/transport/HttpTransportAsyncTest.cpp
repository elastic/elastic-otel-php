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

// set ELASTIC_OTEL_DEBUG_LOG_TESTS envrionment variable to enable trace log to stderr

#include "transport/HttpTransportAsync.h"
#include "Logger.h"

#include <gtest/gtest.h>
#include <gmock/gmock.h>
#include <pthread.h>

namespace elasticapm::php::transport {

TEST(HttpEndpointTest, Construction) {
    HttpEndpoint::enpointHeaders_t headers = {{"Cookie", "Rookie"}, {"Auth", "1234"}};

    HttpEndpoint endpoint("https://localhost/traces", "super-trace", headers, 10, 1s);

    ASSERT_EQ(endpoint.getEndpoint(), "https://localhost/traces"s);
    ASSERT_EQ(endpoint.getMaxRetries(), 10u);
    ASSERT_EQ(endpoint.getRetryDelay(), 1000ms);

    auto cheaders = endpoint.getHeaders();
    ASSERT_NE(cheaders, nullptr);
    ASSERT_NE(cheaders->data, nullptr);
    ASSERT_NE(cheaders->next, nullptr);
    ASSERT_STREQ(cheaders->data, "Content-Type: super-trace");

    cheaders = cheaders->next;
    ASSERT_NE(cheaders->data, nullptr);
    ASSERT_NE(cheaders->next, nullptr);
    ASSERT_STREQ(cheaders->data, "Cookie: Rookie");

    cheaders = cheaders->next;
    ASSERT_NE(cheaders->data, nullptr);
    ASSERT_EQ(cheaders->next, nullptr);
    ASSERT_STREQ(cheaders->data, "Auth: 1234");
}

TEST(HttpEndpointTest, EndpointParseException) {
    HttpEndpoint::enpointHeaders_t headers = {{"Cookie", "Rookie"}, {"Auth", "1234"}};

    ASSERT_THROW(HttpEndpoint endpoint("localhost", "super-trace", headers, 10, 1s), std::runtime_error);
}

TEST(HttpEndpointTest, MissingContentType) {
    HttpEndpoint::enpointHeaders_t headers = {};
    HttpEndpoint endpoint("https://localhost/traces", {}, headers, 10, 1s);

    auto cheaders = endpoint.getHeaders();
    ASSERT_EQ(cheaders, nullptr);
}

class CurlSenderMock : public boost::noncopyable {
public:
    CurlSenderMock(std::shared_ptr<LoggerInterface> logger, std::chrono::milliseconds timeout, bool verifyCert) {
    }

    MOCK_METHOD(int16_t, sendPayload, (std::string const &endpointUrl, struct curl_slist *headers, std::vector<std::byte> const &payload), (const));
};

class TestableHttpTransportAsync : public HttpTransportAsync<::testing::StrictMock<CurlSenderMock>> {
public:
    template <typename... Args>
    TestableHttpTransportAsync(Args &&...args) : HttpTransportAsync<::testing::StrictMock<CurlSenderMock>>(std::forward<Args>(args)...) {
    }

private:
    FRIEND_TEST(HttpTransportAsyncTest, initializeConnection_ParseError);
    FRIEND_TEST(HttpTransportAsyncTest, initializeConnection_SameServer);
    FRIEND_TEST(HttpTransportAsyncTest, enqueue);
    FRIEND_TEST(HttpTransportAsyncTest, enqueueOverLimit);
    FRIEND_TEST(HttpTransportAsyncTest, enqueueAndSend);
    FRIEND_TEST(HttpTransportAsyncTest, enqueueAndSendRetry);
    FRIEND_TEST(HttpTransportAsyncTest, enqueueAndSendRetryUntilMaxRetriesAndDropPayload);
    FRIEND_TEST(HttpTransportAsyncTest, enqueueAndSendNoRetryOnClientError);
};

class HttpTransportAsyncTest : public ::testing::Test {
public:
    HttpTransportAsyncTest() {

        if (std::getenv("ELASTIC_OTEL_DEBUG_LOG_TESTS")) {
            auto serr = std::make_shared<elasticapm::php::LoggerSinkStdErr>();
            serr->setLevel(logLevel_trace);
            reinterpret_cast<elasticapm::php::Logger *>(log_.get())->attachSink(serr);
        }
    }

protected:
    std::shared_ptr<LoggerInterface> log_ = std::make_shared<elasticapm::php::Logger>(std::vector<std::shared_ptr<LoggerSinkInterface>>());
    std::shared_ptr<ConfigurationStorage> config_ = std::make_shared<ConfigurationStorage>([](elasticapm::php::ConfigurationSnapshot &cfg) { return true; });
    TestableHttpTransportAsync transport_{log_, config_};
};

TEST_F(HttpTransportAsyncTest, initializeConnection_ParseError) {
    HttpEndpoint::enpointHeaders_t headers;
    transport_.initializeConnection("local", 1234, "some-type", headers, 100ms, 3, 100ms);
    ASSERT_TRUE(transport_.endpoints_.empty());
    ASSERT_TRUE(transport_.connections_.empty());
}

TEST_F(HttpTransportAsyncTest, initializeConnection_SameServer) {
    HttpEndpoint::enpointHeaders_t headers;
    transport_.initializeConnection("http://local/traces", 1234, "some-type", headers, 100ms, 3, 100ms);
    transport_.initializeConnection("http://local/metrics", 5678, "some-type", headers, 100ms, 3, 100ms);
    transport_.initializeConnection("https://local/logs", 9898, "some-type", headers, 100ms, 3, 100ms);
    ASSERT_EQ(transport_.endpoints_.size(), 3u);
    ASSERT_EQ(transport_.connections_.size(), 2u);
}

TEST_F(HttpTransportAsyncTest, enqueue) {
    HttpEndpoint::enpointHeaders_t headers;

    std::vector<std::byte> data(120);
    ASSERT_EQ(transport_.payloadsToSend_.size(), 0ul);
    transport_.enqueue(1234, {data.begin(), data.end()});
    ASSERT_EQ(transport_.payloadsToSend_.size(), 1ul);
}

TEST_F(HttpTransportAsyncTest, enqueueOverLimit) {
    HttpEndpoint::enpointHeaders_t headers;

    auto limit = config_->get().max_send_queue_size;
    std::vector<std::byte> data(limit / 4);

    ASSERT_EQ(transport_.payloadsToSend_.size(), 0ul);
    transport_.enqueue(1234, {data.begin(), data.end()});
    ASSERT_EQ(transport_.payloadsToSend_.size(), 1ul);

    transport_.enqueue(1234, {data.begin(), data.end()});
    ASSERT_EQ(transport_.payloadsToSend_.size(), 2ul);

    transport_.enqueue(1234, {data.begin(), data.end()});
    ASSERT_EQ(transport_.payloadsToSend_.size(), 3ul);
    transport_.enqueue(1234, {data.begin(), data.end()});
    ASSERT_EQ(transport_.payloadsToSend_.size(), 4ul);
    transport_.enqueue(1234, {data.begin(), data.end()});
    transport_.enqueue(1234, {data.begin(), data.end()});
    transport_.enqueue(1234, {data.begin(), data.end()});
    transport_.enqueue(1234, {data.begin(), data.end()});
    transport_.enqueue(1234, {data.begin(), data.end()});
    ASSERT_EQ(transport_.payloadsToSend_.size(), 4ul);
}

TEST_F(HttpTransportAsyncTest, enqueueAndSend) {
    HttpEndpoint::enpointHeaders_t headers;

    transport_.initializeConnection("http://local/traces", 1234, "some-type", headers, 100ms, 3, 100ms);
    // initializeConnection starts thread - we need to shutdown thread to avoid race condition between test
    transport_.shutdownThread();

    std::vector<std::byte> data(1024);

    ASSERT_EQ(transport_.payloadsToSend_.size(), 0ul);
    transport_.enqueue(1234, {data.begin(), data.end()});
    ASSERT_EQ(transport_.payloadsToSend_.size(), 1ul);

    {
        std::mutex mutex;
        std::unique_lock<std::mutex> lock(mutex);

        EXPECT_CALL(transport_.connections_.begin()->second, sendPayload("http://local/traces", ::testing::_, ::testing::_)).Times(::testing::AnyNumber()).WillRepeatedly(::testing::Return(200));
        transport_.send(lock);
    }

    ASSERT_EQ(transport_.payloadsToSend_.size(), 0ul);
}

TEST_F(HttpTransportAsyncTest, enqueueAndSendRetry) {
    HttpEndpoint::enpointHeaders_t headers;

    transport_.initializeConnection("http://local/traces", 1234, "some-type", headers, 100ms, 3, 100ms);
    // initializeConnection starts thread - we need to shutdown thread to avoid race condition between test
    transport_.shutdownThread();

    std::vector<std::byte> data(1024);

    ASSERT_EQ(transport_.payloadsToSend_.size(), 0ul);
    transport_.enqueue(1234, {data.begin(), data.end()});
    ASSERT_EQ(transport_.payloadsToSend_.size(), 1ul);

    {
        std::mutex mutex;
        std::unique_lock<std::mutex> lock(mutex);

        ::testing::InSequence s;
        EXPECT_CALL(transport_.connections_.begin()->second, sendPayload("http://local/traces", ::testing::_, ::testing::_)).Times(1).WillOnce(::testing::Return(429));
        EXPECT_CALL(transport_.connections_.begin()->second, sendPayload("http://local/traces", ::testing::_, ::testing::_)).Times(1).WillOnce(::testing::Return(200));
        transport_.send(lock);
    }

    ASSERT_EQ(transport_.payloadsToSend_.size(), 0ul);
}

TEST_F(HttpTransportAsyncTest, enqueueAndSendRetryUntilMaxRetriesAndDropPayload) {
    HttpEndpoint::enpointHeaders_t headers;

    int maxReties = 3;

    transport_.initializeConnection("http://local/traces", 1234, "some-type", headers, 100ms, maxReties, 100ms);
    // initializeConnection starts thread - we need to shutdown thread to avoid race condition between test
    transport_.shutdownThread();

    std::vector<std::byte> data(1024);

    ASSERT_EQ(transport_.payloadsToSend_.size(), 0ul);
    transport_.enqueue(1234, {data.begin(), data.end()});
    ASSERT_EQ(transport_.payloadsToSend_.size(), 1ul);

    {
        std::mutex mutex;
        std::unique_lock<std::mutex> lock(mutex);

        ::testing::InSequence s;
        EXPECT_CALL(transport_.connections_.begin()->second, sendPayload("http://local/traces", ::testing::_, ::testing::_)).Times(maxReties).WillRepeatedly(::testing::Return(429));
        transport_.send(lock);
    }

    ASSERT_EQ(transport_.payloadsToSend_.size(), 0ul);
}

TEST_F(HttpTransportAsyncTest, enqueueAndSendNoRetryOnClientError) {
    HttpEndpoint::enpointHeaders_t headers;

    transport_.initializeConnection("http://local/traces", 1234, "some-type", headers, 100ms, 3, 100ms);
    // initializeConnection starts thread - we need to shutdown thread to avoid race condition between test
    transport_.shutdownThread();

    std::vector<std::byte> data(1024);
    ASSERT_EQ(transport_.payloadsToSend_.size(), 0ul);
    transport_.enqueue(1234, {data.begin(), data.end()});
    ASSERT_EQ(transport_.payloadsToSend_.size(), 1ul);

    {
        ::testing::InSequence s;
        EXPECT_CALL(transport_.connections_.begin()->second, sendPayload("http://local/traces", ::testing::_, ::testing::_)).Times(1).WillOnce(::testing::Return(400));

        std::mutex mutex;
        std::unique_lock<std::mutex> lock(mutex);
        transport_.send(lock);
    }

    ASSERT_EQ(transport_.payloadsToSend_.size(), 0ul);
}

} // namespace elasticapm::php::transport