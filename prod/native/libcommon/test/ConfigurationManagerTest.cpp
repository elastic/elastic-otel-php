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


#include "ConfigurationManager.h"

#include <string_view>
#include <gtest/gtest.h>
#include <gmock/gmock.h>

using namespace std::literals;

namespace elasticapm::php {

class MockIniReader {
public:
    MOCK_METHOD(std::optional<std::string>, getIniValue, (std::string_view));
};

class ConfigurationManagerTest : public ::testing::Test {
public:
    MockIniReader iniMock_;
    ConfigurationManager cfg_{[&](std::string_view name) {
        return iniMock_.getIniValue(name);
    }};
};

TEST_F(ConfigurationManagerTest, update) {
    ON_CALL(iniMock_, getIniValue(::testing::_)).WillByDefault(::testing::Return(std::nullopt));
    EXPECT_CALL(iniMock_, getIniValue(::testing::_)).Times(::testing::AtLeast(1));
    //.WillRepeatedly(::testing::Return("Category 5"));


    ConfigurationSnapshot snapshot;
    cfg_.updateIfChanged(snapshot);
    ASSERT_EQ(snapshot.revision, 1u);
    cfg_.update();
    ASSERT_EQ(snapshot.revision, 1u);
    cfg_.update();
    ASSERT_EQ(snapshot.revision, 1u);
    cfg_.update();
    cfg_.updateIfChanged(snapshot);
    ASSERT_EQ(snapshot.revision, 4u);
}

TEST_F(ConfigurationManagerTest, updateSomeOption) {
    EXPECT_CALL(iniMock_, getIniValue(::testing::_)).Times(::testing::AnyNumber()).WillRepeatedly(::testing::Return(std::nullopt));
    // EXPECT_CALL(iniMock_, getIniValue("elastic_otel.api_key")).Times(1).WillOnce(::testing::Return("secret_api_key"s)).RetiresOnSaturation();
    // EXPECT_CALL(iniMock_, getIniValue("elastic_otel.server_timeout")).Times(1).WillOnce(::testing::Return("10s"s)).RetiresOnSaturation();
    EXPECT_CALL(iniMock_, getIniValue("elastic_otel.enabled")).Times(1).WillOnce(::testing::Return("off")).RetiresOnSaturation();

    ConfigurationSnapshot snapshot;
    ASSERT_EQ(snapshot.revision, 0u);

    cfg_.updateIfChanged(snapshot);

    // ASSERT_TRUE(snapshot.api_key.empty());
    // ASSERT_EQ(snapshot.server_timeout, ConfigurationSnapshot().server_timeout); // default value
    ASSERT_EQ(snapshot.enabled, ConfigurationSnapshot().enabled); // default value
    ASSERT_EQ(snapshot.revision, 1u);

    cfg_.update();
    cfg_.updateIfChanged(snapshot);

    ASSERT_EQ(snapshot.revision, 2u);
    // ASSERT_EQ(snapshot.api_key, "secret_api_key"s);
    ASSERT_NE(snapshot.enabled, ConfigurationSnapshot().enabled); // default value
    // ASSERT_EQ(snapshot.server_timeout.count(), 10000ul);
    ASSERT_NE(snapshot.enabled, ConfigurationSnapshot().enabled); // default value
    ASSERT_FALSE(snapshot.enabled);

    EXPECT_CALL(iniMock_, getIniValue("elastic_otel.enabled")).Times(1).WillOnce(::testing::Return("on")).RetiresOnSaturation();
    cfg_.update();
    cfg_.updateIfChanged(snapshot);
}

TEST_F(ConfigurationManagerTest, getOptionValue) {
    EXPECT_CALL(iniMock_, getIniValue(::testing::_)).Times(::testing::AnyNumber()).WillRepeatedly(::testing::Return(std::nullopt));
    // EXPECT_CALL(iniMock_, getIniValue("elastic_otel.api_key")).Times(1).WillOnce(::testing::Return("secret_api_key"s)).RetiresOnSaturation();
    // EXPECT_CALL(iniMock_, getIniValue("elastic_otel.server_timeout")).Times(1).WillOnce(::testing::Return("10s"s)).RetiresOnSaturation();
    EXPECT_CALL(iniMock_, getIniValue("elastic_otel.enabled")).Times(1).WillOnce(::testing::Return("off")).RetiresOnSaturation();

    ConfigurationSnapshot snapshot;
    ASSERT_EQ(snapshot.revision, 0u);

    cfg_.update();
    cfg_.updateIfChanged(snapshot);

    // ASSERT_EQ(std::get<std::string>(cfg_.getOptionValue("api_key"sv, snapshot)), "secret_api_key"s);
    // ASSERT_EQ(std::get<std::chrono::milliseconds>(cfg_.getOptionValue("server_timeout"sv, snapshot)), std::chrono::milliseconds(10000));
    ASSERT_EQ(std::get<bool>(cfg_.getOptionValue("enabled"sv, snapshot)), false);
    ASSERT_TRUE(std::holds_alternative<std::nullopt_t>(cfg_.getOptionValue("unknown"sv, snapshot)));
}


} // namespace elasticapm::php
