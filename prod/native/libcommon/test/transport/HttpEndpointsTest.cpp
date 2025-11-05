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

#include "transport/HttpEndpoints.h"
#include "ConfigurationStorage.h"
#include "Logger.h"

#include <gtest/gtest.h>
#include <gmock/gmock.h>
#include <pthread.h>

namespace elasticapm::php::transport {

class HttpEndpointsTests : public ::testing::Test {
public:
    HttpEndpointsTests() {

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

class TestableHttpEndpoints : public HttpEndpoints {
public:
    template <typename... Args>
    TestableHttpEndpoints(Args &&...args) : HttpEndpoints(std::forward<Args>(args)...) {
    }
    FRIEND_TEST(HttpEndpointsTests, add_parseError);
    FRIEND_TEST(HttpEndpointsTests, add_SameServer);
    FRIEND_TEST(HttpEndpointsTests, getConnection);
};

TEST_F(HttpEndpointsTests, add_parseError) {
    HttpEndpoint::enpointHeaders_t headers;
    TestableHttpEndpoints endpoints(log_);

    EXPECT_THROW(endpoints.add("local", 1234, "some-type", headers, 100ms, 3, 100ms, HttpEndpointSSLOptions()), std::runtime_error);
    ASSERT_TRUE(endpoints.endpoints_.empty());
    ASSERT_TRUE(endpoints.connections_.empty());
}

TEST_F(HttpEndpointsTests, add_SameServer) {
    HttpEndpoint::enpointHeaders_t headers;
    TestableHttpEndpoints endpoints(log_);

    HttpEndpointSSLOptions options;

    endpoints.add("http://local/traces", 1234, "some-type", headers, 100ms, 3, 100ms, options);
    endpoints.add("http://local/metrics", 5678, "some-type", headers, 100ms, 3, 100ms, options);
    endpoints.add("https://local/logs", 9898, "some-type", headers, 100ms, 3, 100ms, options);
    ASSERT_EQ(endpoints.endpoints_.size(), 3u);
    ASSERT_EQ(endpoints.connections_.size(), 2u);
}

TEST_F(HttpEndpointsTests, getConnection) {
    HttpEndpoint::enpointHeaders_t cheaders;
    TestableHttpEndpoints endpoints(log_);

    HttpEndpointSSLOptions options;
    endpoints.add("http://local/traces", 1234, "some-type", cheaders, 100ms, 3, 100ms, options);
    endpoints.add("http://local/metrics", 5678, "some-type", cheaders, 100ms, 3, 100ms, options);
    endpoints.add("https://local/logs", 9898, "some-type", cheaders, 100ms, 3, 100ms, options);

    auto [endpointUrl, headers, connId, conn, maxRetries, retryDelay] = endpoints.getConnection(1234);
    ASSERT_EQ(endpointUrl, "http://local/traces");

    auto [endpointUrl2, headers2, connId2, conn2, maxRetries2, retryDelay2] = endpoints.getConnection(5678);
    ASSERT_EQ(endpointUrl2, "http://local/metrics");

    ASSERT_EQ(connId, connId2);

    auto [endpointUrl3, headers3, connId3, conn3, maxRetries3, retryDelay3] = endpoints.getConnection(9898);
    ASSERT_EQ(endpointUrl3, "https://local/logs");

    ASSERT_NE(connId, connId3);
}

} // namespace elasticapm::php::transport