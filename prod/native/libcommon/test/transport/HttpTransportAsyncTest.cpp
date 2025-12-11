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

#include <tuple>
#include <gtest/gtest.h>
#include <gmock/gmock.h>
#include <pthread.h>

namespace elasticapm::php::transport {

class CurlSenderMock : public boost::noncopyable {
public:
    CurlSenderMock(std::shared_ptr<LoggerInterface> logger, std::chrono::milliseconds timeout, bool verifyCert) {
    }

    MOCK_METHOD(int16_t, sendPayload, (std::string const &endpointUrl, struct curl_slist *headers, std::vector<std::byte> const &payload, std::function<void(std::string_view)> headerCallback, std::string *responseBuffer), (const));
};

class HttpEndpointsMock : public boost::noncopyable {
public:
    using endpointUrlHash_t = std::size_t;

    HttpEndpointsMock(std::shared_ptr<LoggerInterface> logger) {
    }

    MOCK_METHOD(bool, add, (std::string endpointUrl, endpointUrlHash_t endpointHash, std::string contentType, HttpEndpoint::enpointHeaders_t const &endpointHeaders, std::chrono::milliseconds timeout, std::size_t maxRetries, std::chrono::milliseconds retryDelay, HttpEndpointSSLOptions sslOptions));
    MOCK_METHOD((std::tuple<std::string, curl_slist *, HttpEndpoint::connectionId_t, CurlSenderMock &, std::size_t, std::chrono::milliseconds>), getConnection, (std::size_t endpointHash));
    MOCK_METHOD(void, updateRetryDelay, (size_t endpointHash, std::chrono::milliseconds retryDelay));
};

class TestableHttpTransportAsync : public HttpTransportAsync<::testing::StrictMock<CurlSenderMock>, ::testing::StrictMock<HttpEndpointsMock>> {
public:
    template <typename... Args>
    TestableHttpTransportAsync(Args &&...args) : HttpTransportAsync<::testing::StrictMock<CurlSenderMock>, ::testing::StrictMock<HttpEndpointsMock>>(std::forward<Args>(args)...) {
    }

private:
    FRIEND_TEST(HttpTransportAsyncTest, initializeConnection_ParseError);
    FRIEND_TEST(HttpTransportAsyncTest, initializeConnection_SameServer);
    FRIEND_TEST(HttpTransportAsyncTest, enqueue);
    FRIEND_TEST(HttpTransportAsyncTest, enqueueOverLimit);
    FRIEND_TEST(HttpTransportAsyncTest, enqueueAndSend);
    FRIEND_TEST(HttpTransportAsyncTest, enqueueAndSendWithResponseCallback);
    FRIEND_TEST(HttpTransportAsyncTest, enqueueAndSendRetry);
    FRIEND_TEST(HttpTransportAsyncTest, enqueueAndSendRetryUntilMaxRetriesAndDropPayload);
    FRIEND_TEST(HttpTransportAsyncTest, enqueueAndSendNoRetryOnClientError);
    FRIEND_TEST(HttpTransportAsyncTest, destructorSendTimeout);
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
    bool configUpdater(elasticapm::php::ConfigurationSnapshot &cfg) {
        cfg = configForUpdate_;
        return true;
    }

    elasticapm::php::ConfigurationSnapshot configForUpdate_;
    std::shared_ptr<LoggerInterface> log_ = std::make_shared<elasticapm::php::Logger>(std::vector<std::shared_ptr<LoggerSinkInterface>>());
    std::shared_ptr<ConfigurationStorage> config_ = std::make_shared<ConfigurationStorage>([this](elasticapm::php::ConfigurationSnapshot &cfg) { return configUpdater(cfg); });
};

TEST_F(HttpTransportAsyncTest, enqueue) {
    HttpEndpoint::enpointHeaders_t headers;
    TestableHttpTransportAsync transport_{log_, config_};

    std::vector<std::byte> data(120);
    ASSERT_EQ(transport_.payloadsToSend_.size(), 0ul);
    transport_.enqueue(1234, {data.begin(), data.end()});
    ASSERT_EQ(transport_.payloadsToSend_.size(), 1ul);
}

TEST_F(HttpTransportAsyncTest, enqueueOverLimit) {
    HttpEndpoint::enpointHeaders_t headers;
    TestableHttpTransportAsync transport_{log_, config_};

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
    TestableHttpTransportAsync transport_{log_, config_};

    std::vector<std::byte> data(1024);

    ASSERT_EQ(transport_.payloadsToSend_.size(), 0ul);
    transport_.enqueue(1234, {data.begin(), data.end()});
    ASSERT_EQ(transport_.payloadsToSend_.size(), 1ul);

    CurlSenderMock sender(log_, 100ms, false);

    {
        std::mutex mutex;
        std::unique_lock<std::mutex> lock(mutex);

        EXPECT_CALL(transport_.endpoints_, getConnection(1234)).Times(1).WillRepeatedly(::testing::Return(::testing::ByMove(std::make_tuple("http://local/traces"s, static_cast<curl_slist *>(nullptr), HttpEndpoint::connectionId_t(900), std::ref(sender), static_cast<std::size_t>(2), 100ms))));
        EXPECT_CALL(sender, sendPayload("http://local/traces", ::testing::_, ::testing::_, ::testing::_, ::testing::_)).Times(::testing::AnyNumber()).WillRepeatedly(::testing::Return(200));
        transport_.send(lock);
    }

    ASSERT_EQ(transport_.payloadsToSend_.size(), 0ul);
}

TEST_F(HttpTransportAsyncTest, enqueueAndSendWithResponseCallback) {
    HttpEndpoint::enpointHeaders_t headers;
    TestableHttpTransportAsync transport_{log_, config_};

    std::vector<std::byte> data(1024);

    bool callbackCalled = false;

    auto callback = [&callbackCalled](int16_t responseCode, std::span<std::byte> data) {
        callbackCalled = true;
        ASSERT_EQ(responseCode, 200);
        ASSERT_EQ(data.size(), 0ul);
    };

    ASSERT_EQ(transport_.payloadsToSend_.size(), 0ul);
    transport_.enqueue(1234, {data.begin(), data.end()}, callback);
    ASSERT_EQ(transport_.payloadsToSend_.size(), 1ul);

    CurlSenderMock sender(log_, 100ms, false);

    {
        std::mutex mutex;
        std::unique_lock<std::mutex> lock(mutex);

        EXPECT_CALL(transport_.endpoints_, getConnection(1234)).Times(1).WillRepeatedly(::testing::Return(::testing::ByMove(std::make_tuple("http://local/traces"s, static_cast<curl_slist *>(nullptr), HttpEndpoint::connectionId_t(900), std::ref(sender), static_cast<std::size_t>(2), 100ms))));
        EXPECT_CALL(sender, sendPayload("http://local/traces", ::testing::_, ::testing::_, ::testing::_, ::testing::_)).Times(::testing::AnyNumber()).WillRepeatedly(::testing::Return(200));
        transport_.send(lock);
    }

    ASSERT_TRUE(callbackCalled);
    ASSERT_EQ(transport_.payloadsToSend_.size(), 0ul);
}

TEST_F(HttpTransportAsyncTest, enqueueAndSendRetry) {
    HttpEndpoint::enpointHeaders_t headers;
    TestableHttpTransportAsync transport_{log_, config_};

    std::vector<std::byte> data(1024);

    ASSERT_EQ(transport_.payloadsToSend_.size(), 0ul);
    transport_.enqueue(1234, {data.begin(), data.end()});
    ASSERT_EQ(transport_.payloadsToSend_.size(), 1ul);

    CurlSenderMock sender(log_, 100ms, false);
    {
        std::mutex mutex;
        std::unique_lock<std::mutex> lock(mutex);

        EXPECT_CALL(transport_.endpoints_, getConnection(1234)).WillRepeatedly(::testing::Return(::testing::ByMove(std::make_tuple("http://local/traces"s, static_cast<curl_slist *>(nullptr), HttpEndpoint::connectionId_t(900), std::ref(sender), static_cast<std::size_t>(2), 100ms))));

        ::testing::InSequence s;
        EXPECT_CALL(sender, sendPayload("http://local/traces", ::testing::_, ::testing::_, ::testing::_, ::testing::_)).Times(1).WillOnce(::testing::Return(429));
        EXPECT_CALL(sender, sendPayload("http://local/traces", ::testing::_, ::testing::_, ::testing::_, ::testing::_)).Times(1).WillOnce(::testing::Return(200));

        transport_.send(lock);
    }

    ASSERT_EQ(transport_.payloadsToSend_.size(), 0ul);
}

TEST_F(HttpTransportAsyncTest, enqueueAndSendRetryUntilMaxRetriesAndDropPayload) {
    HttpEndpoint::enpointHeaders_t headers;
    TestableHttpTransportAsync transport_{log_, config_};
    CurlSenderMock sender(log_, 100ms, false);

    int maxReties = 3;

    std::vector<std::byte> data(1024);

    ASSERT_EQ(transport_.payloadsToSend_.size(), 0ul);
    transport_.enqueue(1234, {data.begin(), data.end()});
    ASSERT_EQ(transport_.payloadsToSend_.size(), 1ul);

    {
        std::mutex mutex;
        std::unique_lock<std::mutex> lock(mutex);

        EXPECT_CALL(transport_.endpoints_, getConnection(1234)).WillRepeatedly(::testing::Return(::testing::ByMove(std::make_tuple("http://local/traces"s, static_cast<curl_slist *>(nullptr), HttpEndpoint::connectionId_t(900), std::ref(sender), static_cast<std::size_t>(maxReties), 100ms))));
        EXPECT_CALL(sender, sendPayload("http://local/traces", ::testing::_, ::testing::_, ::testing::_, ::testing::_)).Times(maxReties).WillRepeatedly(::testing::Return(429));
        transport_.send(lock);
    }

    ASSERT_EQ(transport_.payloadsToSend_.size(), 0ul);
}

TEST_F(HttpTransportAsyncTest, enqueueAndSendNoRetryOnClientError) {
    HttpEndpoint::enpointHeaders_t headers;
    TestableHttpTransportAsync transport{log_, config_};
    CurlSenderMock sender(log_, 100ms, false);

    std::vector<std::byte> data(1024);
    ASSERT_EQ(transport.payloadsToSend_.size(), 0ul);
    transport.enqueue(1234, {data.begin(), data.end()});
    ASSERT_EQ(transport.payloadsToSend_.size(), 1ul);

    {
        EXPECT_CALL(transport.endpoints_, getConnection(1234)).WillRepeatedly(::testing::Return(::testing::ByMove(std::make_tuple("http://local/traces"s, static_cast<curl_slist *>(nullptr), HttpEndpoint::connectionId_t(900), std::ref(sender), static_cast<std::size_t>(3), 100ms))));
        EXPECT_CALL(sender, sendPayload("http://local/traces", ::testing::_, ::testing::_, ::testing::_, ::testing::_)).Times(1).WillOnce(::testing::Return(400));

        std::mutex mutex;
        std::unique_lock<std::mutex> lock(mutex);
        transport.send(lock);
    }

    ASSERT_EQ(transport.payloadsToSend_.size(), 0ul);
}

TEST_F(HttpTransportAsyncTest, destructorSendTimeout) {
    HttpEndpoint::enpointHeaders_t headers;
    CurlSenderMock sender(log_, 100ms, false);

    configForUpdate_.async_transport_shutdown_timeout = 5ms;
    config_->update();

    {
        TestableHttpTransportAsync transport{log_, config_};

        HttpEndpointSSLOptions sslOptions;

        EXPECT_CALL(transport.endpoints_, add("http://local/traces", 1234u, "some-type", headers, 100ms, 3, 100ms, ::testing::_)).Times(1).WillRepeatedly(::testing::Return(true));
        transport.initializeConnection("http://local/traces", 1234u, "some-type", headers, 100ms, 3, 100ms, sslOptions);

        std::this_thread::sleep_for(5ms); // give thread a bit of time to go into sleep condition

        EXPECT_CALL(transport.endpoints_, getConnection(1234u)).WillRepeatedly(::testing::Return(::testing::ByMove(std::make_tuple("http://local/traces"s, static_cast<curl_slist *>(nullptr), HttpEndpoint::connectionId_t(900), std::ref(sender), static_cast<std::size_t>(3), 100ms))));

        EXPECT_CALL(sender, sendPayload("http://local/traces", ::testing::_, ::testing::_, ::testing::_, ::testing::_)).Times(::testing::Exactly(1)).WillRepeatedly(::testing::DoAll(::testing::Invoke([]() { std::this_thread::sleep_for(10ms); }), ::testing::Return(200)));

        // We're enqueuing 4 payloads, but sending will take at least 10ms. The destructor timeout is set for 5ms, so only the first payload will be sent. Times(::testing::Exactly(1)) will do the job and will fail if it tries to send any further payloads.
        std::vector<std::byte> data(1024);
        transport.enqueue(1234, {data.begin(), data.end()});
        transport.enqueue(1234, {data.begin(), data.end()});
        transport.enqueue(1234, {data.begin(), data.end()});
        transport.enqueue(1234, {data.begin(), data.end()});
    }
}

} // namespace elasticapm::php::transport