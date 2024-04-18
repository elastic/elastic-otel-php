
#include "CommonUtils.h"

#include <gtest/gtest.h>
#include <gmock/gmock.h>
#include <pthread.h>

#include <chrono>
#include <string_view>


using namespace std::literals;

namespace elasticapm::utils {

TEST(CommunUtilsTest, convertDurationWithUnit) {
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


TEST(CommunUtilsTest, parseBoolean) {
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

    EXPECT_ANY_THROW(parseBoolean(""));
    EXPECT_ANY_THROW(parseBoolean(" "));
}

TEST(CommunUtilsTest, parseLogLevel) {
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


TEST(CommunUtilsTest, getParameterizedString) {
    ASSERT_EQ(getParameterizedString("example_name"), "example_name");
    ASSERT_EQ(getParameterizedString("example_name_%p"), std::string("example_name_") + std::to_string(getpid()));
    ASSERT_EQ(getParameterizedString("%p_example_name"), std::to_string(getpid()) + std::string("_example_name"));

    ASSERT_TRUE(getParameterizedString("example_name_%t").starts_with("example_name_"));
    ASSERT_EQ(getParameterizedString("example_name_%t").length(), 23);

    ASSERT_EQ(getParameterizedString("example_name%%"), "example_name%%");

    ASSERT_EQ(getParameterizedString("example_name%"), "example_name%");

    ASSERT_EQ(getParameterizedString("example_%X_name"), "example_%X_name");
}

TEST(CommunUtilsTest, trim) {
    using namespace std::string_view_literals;
    ASSERT_EQ(trim("example_name"sv), "example_name"sv);
    ASSERT_EQ(trim("\nexample_name"sv), "example_name"sv);
    ASSERT_EQ(trim("\nexample_name\r"sv), "example_name"sv);
    ASSERT_EQ(trim("example_name           "sv), "example_name"sv);
    ASSERT_EQ(trim("\t\v\r\nexample name           "sv), "example name"sv);
    ASSERT_EQ(trim(""sv), ""sv);
    ASSERT_EQ(trim("   "sv), ""sv);
}

TEST(CommunUtilsTest, sanitizeKeyValueString) {
    using namespace std::literals;

    ASSERT_EQ(sanitizeKeyValueString("ELASTIC_APM_API_KEY"s, "ELASTIC_APM_API_KEY=supersecret"s), "ELASTIC_APM_API_KEY=***"s);
    ASSERT_EQ(sanitizeKeyValueString("ELASTIC_APM_API_KEY"s, "ELASTIC_APM_API_KEY=\"aaa\""s), "ELASTIC_APM_API_KEY=***"s);
    ASSERT_EQ(sanitizeKeyValueString("ELASTIC_APM_API_KEY"s, "THIS IS A TEXT ELASTIC_APM_API_KEY=\"aaa\" EXAMPLE"s), "THIS IS A TEXT ELASTIC_APM_API_KEY=*** EXAMPLE"s);
    ASSERT_EQ(sanitizeKeyValueString("ELASTIC_APM_API_KEY"s, "THIS IS A TEXT ELASTIC_APM_API_KEY=\"aaa with spaces\" EXAMPLE"s), "THIS IS A TEXT ELASTIC_APM_API_KEY=*** EXAMPLE"s);
}

TEST(CommunUtilsTest, stringPrintf) {
    ASSERT_EQ(stringPrintf("%s is %d years old", "Mark", 12), "Mark is 12 years old"s);
}

TEST(CommunUtilsTest, getIniName) {
    ASSERT_EQ(getIniName("basic_option"), "elastic_apm.basic_option"s);
    ASSERT_EQ(getIniName("OtherOption"), "elastic_apm.OtherOption"s);
}

TEST(CommunUtilsTest, getEnvName) {
    ASSERT_EQ(getEnvName("basic_option"), "ELASTIC_APM_BASIC_OPTION"s);
    ASSERT_EQ(getEnvName("OtherOption"), "ELASTIC_APM_OTHEROPTION"s);
}

}

