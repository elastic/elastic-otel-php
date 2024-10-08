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

#include "transport/HttpEndpoint.h"

#include <gtest/gtest.h>
#include <gmock/gmock.h>
#include <pthread.h>

namespace elasticapm::php::transport {

TEST(HttpEndpointTest, Construction) {
    HttpEndpoint::enpointHeaders_t headers = {{"Cookie", "Rookie"}, {"Auth", "1234"}};

    HttpEndpoint endpoint("https://localhost/traces", "super-trace", headers, 10, 1s);

    ASSERT_EQ(endpoint.getEndpoint(), "https://localhost/traces"s);
    ASSERT_EQ(endpoint.getMaxRetries(), 10);
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

} // namespace elasticapm::php::transport