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


#include "CommonUtils.h"
#include "LogFeature.h"
#include "Logger.h"

#include <gtest/gtest.h>
#include <gmock/gmock.h>
#include <pthread.h>

#include <chrono>
#include <memory>
#include <string_view>


using namespace std::literals;

namespace elasticapm::utils {

class CommonUtilsTest : public ::testing::Test {
public:
    CommonUtilsTest() {

        if (std::getenv("ELASTIC_OTEL_DEBUG_LOG_TESTS")) {
            auto serr = std::make_shared<elasticapm::php::LoggerSinkStdErr>();
            serr->setLevel(logLevel_trace);
            reinterpret_cast<elasticapm::php::Logger *>(log_.get())->attachSink(serr);
        }
    }

protected:
    std::shared_ptr<elasticapm::php::LoggerInterface> log_ = std::make_shared<elasticapm::php::Logger>(std::vector<std::shared_ptr<elasticapm::php::LoggerSinkInterface>>());
};

TEST_F(CommonUtilsTest, convertDurationWithUnit) {
    EXPECT_THROW(convertDurationWithUnit("1  s  s"), std::invalid_argument);
    EXPECT_THROW(convertDurationWithUnit("1xd"), std::invalid_argument);
    EXPECT_THROW(convertDurationWithUnit("1h"), std::invalid_argument);

    ASSERT_EQ(convertDurationWithUnit("1ms"), 1ms);
    ASSERT_EQ(convertDurationWithUnit("1.6ms"), 1ms);
    ASSERT_EQ(convertDurationWithUnit(" 10 ms"), 10ms);
    ASSERT_EQ(convertDurationWithUnit("   \t 10000 m s\t\n "), 10000ms);

    ASSERT_EQ(convertDurationWithUnit("0s"), 0ms);
    ASSERT_EQ(convertDurationWithUnit("1s"), 1000ms);
    ASSERT_EQ(convertDurationWithUnit("0.1s"), 100ms);
    ASSERT_EQ(convertDurationWithUnit("0.01s"), 10ms);
    ASSERT_EQ(convertDurationWithUnit("0.001s"), 1ms);
    ASSERT_EQ(convertDurationWithUnit("0.0001s"), 0ms);

    ASSERT_EQ(convertDurationWithUnit("1m"), 60000ms);
    ASSERT_EQ(convertDurationWithUnit("10m"), 600000ms);
    ASSERT_EQ(convertDurationWithUnit("10.5m"), 630000ms);

    ASSERT_EQ(convertDurationWithUnit("  1234  \t"), 1234ms);
}

TEST_F(CommonUtilsTest, parseByteUnits) {
    EXPECT_THROW(parseByteUnits("1  s  s"), std::invalid_argument);
    EXPECT_THROW(parseByteUnits("1xd"), std::invalid_argument);
    EXPECT_THROW(parseByteUnits("1h"), std::invalid_argument);
    EXPECT_THROW(parseByteUnits("-1"), std::invalid_argument);

    ASSERT_EQ(parseByteUnits("1"), 1u);
    ASSERT_EQ(parseByteUnits("1b"), 1u);
    ASSERT_EQ(parseByteUnits(" 1kb "), 1024u);
    ASSERT_EQ(parseByteUnits("   \t  10  k b \t\n "), 10240u);
    ASSERT_EQ(parseByteUnits("1mb "), 1048576u);
    ASSERT_EQ(parseByteUnits("1MB "), 1048576u);
    ASSERT_EQ(parseByteUnits("20mb "), 20971520u);
    ASSERT_EQ(parseByteUnits("1gb "), 1073741824u);
    ASSERT_EQ(parseByteUnits("10 G B"), 10737418240u);
    ASSERT_EQ(parseByteUnits("0"), 0u);
}

TEST_F(CommonUtilsTest, parseBoolean) {
    ASSERT_TRUE(parseBoolean("true"));
    ASSERT_TRUE(parseBoolean("on"));
    ASSERT_TRUE(parseBoolean("yes"));
    ASSERT_TRUE(parseBoolean(" on "));
    ASSERT_TRUE(parseBoolean(" \nyes"));
    ASSERT_TRUE(parseBoolean("  1"));
    ASSERT_TRUE(parseBoolean("1"));
    ASSERT_TRUE(parseBoolean("  1"));
    ASSERT_TRUE(parseBoolean("-1"));
    ASSERT_TRUE(parseBoolean("123"));

    ASSERT_FALSE(parseBoolean("false"));
    ASSERT_FALSE(parseBoolean("off"));
    ASSERT_FALSE(parseBoolean("no"));

    ASSERT_FALSE(parseBoolean("unknown"));
    ASSERT_FALSE(parseBoolean("0"));

    ASSERT_FALSE(parseBoolean(""));
    ASSERT_FALSE(parseBoolean(" "));
}

TEST_F(CommonUtilsTest, parseLogLevel) {
    ASSERT_THROW(parseLogLevel("some unknown"), std::invalid_argument);
    ASSERT_EQ(parseLogLevel("   critical"), LogLevel::logLevel_critical);
    ASSERT_EQ(parseLogLevel("   CriticaL"), LogLevel::logLevel_critical);
    ASSERT_EQ(parseLogLevel("\r\n\roff\n"), LogLevel::logLevel_off);
    ASSERT_EQ(parseLogLevel("ERROR"), LogLevel::logLevel_error);
    ASSERT_EQ(parseLogLevel("WARNINg"), LogLevel::logLevel_warning);
    ASSERT_EQ(parseLogLevel("INfo"), LogLevel::logLevel_info);
    ASSERT_EQ(parseLogLevel("DEBUG"), LogLevel::logLevel_debug);
    ASSERT_EQ(parseLogLevel("  TRACE"), LogLevel::logLevel_trace);
    ASSERT_THROW(parseLogLevel("TRACER"), std::invalid_argument);
}

TEST_F(CommonUtilsTest, getParameterizedString) {
    ASSERT_EQ(getParameterizedString("example_name"), "example_name");
    ASSERT_EQ(getParameterizedString("example_name_%p"), std::string("example_name_") + std::to_string(getpid()));
    ASSERT_EQ(getParameterizedString("%p_example_name"), std::to_string(getpid()) + std::string("_example_name"));

    ASSERT_TRUE(getParameterizedString("example_name_%t").starts_with("example_name_"));
    ASSERT_EQ(getParameterizedString("example_name_%t").length(), 23u);

    ASSERT_EQ(getParameterizedString("example_name%%"), "example_name%%");

    ASSERT_EQ(getParameterizedString("example_name%"), "example_name%");

    ASSERT_EQ(getParameterizedString("example_%X_name"), "example_%X_name");
}

TEST_F(CommonUtilsTest, trim) {
    using namespace std::string_view_literals;
    ASSERT_EQ(trim("example_name"sv), "example_name"sv);
    ASSERT_EQ(trim("\nexample_name"sv), "example_name"sv);
    ASSERT_EQ(trim("\nexample_name\r"sv), "example_name"sv);
    ASSERT_EQ(trim("example_name           "sv), "example_name"sv);
    ASSERT_EQ(trim("\t\v\r\nexample name           "sv), "example name"sv);
    ASSERT_EQ(trim(""sv), ""sv);
    ASSERT_EQ(trim("   "sv), ""sv);
}

TEST_F(CommonUtilsTest, sanitizeKeyValueString) {
    using namespace std::literals;

    ASSERT_EQ(sanitizeKeyValueString("ELASTIC_OTEL_API_KEY"s, "ELASTIC_OTEL_API_KEY=supersecret"s), "ELASTIC_OTEL_API_KEY=***"s);
    ASSERT_EQ(sanitizeKeyValueString("ELASTIC_OTEL_API_KEY"s, "ELASTIC_OTEL_API_KEY=\"aaa\""s), "ELASTIC_OTEL_API_KEY=***"s);
    ASSERT_EQ(sanitizeKeyValueString("ELASTIC_OTEL_API_KEY"s, "THIS IS A TEXT ELASTIC_OTEL_API_KEY=\"aaa\" EXAMPLE"s), "THIS IS A TEXT ELASTIC_OTEL_API_KEY=*** EXAMPLE"s);
    ASSERT_EQ(sanitizeKeyValueString("ELASTIC_OTEL_API_KEY"s, "THIS IS A TEXT ELASTIC_OTEL_API_KEY=\"aaa with spaces\" EXAMPLE"s), "THIS IS A TEXT ELASTIC_OTEL_API_KEY=*** EXAMPLE"s);
}

TEST_F(CommonUtilsTest, stringPrintf) {
    ASSERT_EQ(stringPrintf("%s is %d years old", "Mark", 12), "Mark is 12 years old"s);
}

TEST_F(CommonUtilsTest, getIniName) {
    ASSERT_EQ(getIniName("basic_option"), "elastic_otel.basic_option"s);
    ASSERT_EQ(getIniName("OtherOption"), "elastic_otel.OtherOption"s);
}

TEST_F(CommonUtilsTest, getEnvName) {
    ASSERT_EQ(getEnvName("basic_option"), "ELASTIC_OTEL_BASIC_OPTION"s);
    ASSERT_EQ(getEnvName("OtherOption"), "ELASTIC_OTEL_OTHEROPTION"s);
}

TEST_F(CommonUtilsTest, getConnectionDetailsFromURL) {
    ASSERT_EQ(getConnectionDetailsFromURL("https://localhost/?query=asdsad").value_or(""), "https://localhost"s);
    ASSERT_EQ(getConnectionDetailsFromURL("http://localhost/?query=asdsad").value_or(""), "http://localhost"s);
    ASSERT_EQ(getConnectionDetailsFromURL("http://localhost:8080/?query=asdsad").value_or(""), "http://localhost:8080"s);
    ASSERT_EQ(getConnectionDetailsFromURL("http://localhost:8080/").value_or(""), "http://localhost:8080"s);
    ASSERT_EQ(getConnectionDetailsFromURL("http://localhost:8080").value_or(""), "http://localhost:8080"s);

    ASSERT_NE(getConnectionDetailsFromURL("https://localhost").value_or(""), "http://localhost"s);
    ASSERT_EQ(getConnectionDetailsFromURL("localhost"), std::nullopt);
    ASSERT_EQ(getConnectionDetailsFromURL("ftp:://localhost"), std::nullopt);
}

TEST_F(CommonUtilsTest, parseLogFeatures_Empty) {
    auto features = parseLogFeatures(log_, "");
    ASSERT_TRUE(features.empty());
}

TEST_F(CommonUtilsTest, parseLogFeatures_UnknownFeature) {
    auto features = parseLogFeatures(log_, "UNKNOWNFEATURE=debug");
    ASSERT_TRUE(features.empty());
}

TEST_F(CommonUtilsTest, parseLogFeatures_UnknownLevel) {
    auto features = parseLogFeatures(log_, "ALL=unknown");
    ASSERT_TRUE(features.empty());
}

TEST_F(CommonUtilsTest, parseLogFeatures_UnknownAndCorrectFeature) {
    auto features = parseLogFeatures(log_, "UNKNOWNFEATURE=debug,ALL=info");
    ASSERT_EQ(features.size(), 1u);
    auto found = features.find(elasticapm::php::LogFeature::ALL);
    ASSERT_NE(found, std::end(features));
    ASSERT_EQ(found->second, LogLevel::logLevel_info);
}

TEST_F(CommonUtilsTest, parseLogFeatures_MultipleFeatures) {
    auto features = parseLogFeatures(log_, "ALL=info,TRANSPORT=debug");
    ASSERT_EQ(features.size(), 2u);
    {
        auto found = features.find(elasticapm::php::LogFeature::ALL);
        ASSERT_NE(found, std::end(features));
        ASSERT_EQ(found->second, LogLevel::logLevel_info);
    }
    {
        auto found = features.find(elasticapm::php::LogFeature::TRANSPORT);
        ASSERT_NE(found, std::end(features));
        ASSERT_EQ(found->second, LogLevel::logLevel_debug);
    }
}

TEST_F(CommonUtilsTest, ValidUtf8Strings) {
    EXPECT_TRUE(isUtf8("hello"));
    EXPECT_TRUE(isUtf8("witaj świecie"));
    EXPECT_TRUE(isUtf8("こんにちは")); // japanise
    EXPECT_TRUE(isUtf8("🚀🌟✨"));     // emoji
}

TEST_F(CommonUtilsTest, InvalidUtf8Strings) {
    // overlong encoding
    std::string overlong = "\xC0\xAF";
    EXPECT_FALSE(isUtf8(overlong));

    // continuation byte without a starter
    std::string orphan = "\x80";
    EXPECT_FALSE(isUtf8(orphan));

    // truncated 2-byte sequence
    std::string truncated2 = "\xC2";
    EXPECT_FALSE(isUtf8(truncated2));

    // truncated 3-byte sequence
    std::string truncated3 = "\xE0\xA0";
    EXPECT_FALSE(isUtf8(truncated3));

    // invalid 4-byte (outside Unicode range)
    std::string invalid4 = "\xF5\x80\x80\x80";
    EXPECT_FALSE(isUtf8(invalid4));
}

TEST_F(CommonUtilsTest, EmptyString) {
    EXPECT_TRUE(isUtf8(""));
}
}

