
#include "Logger.h"

#include <string_view>
#include <gtest/gtest.h>
#include <gmock/gmock.h>

using namespace std::literals;

namespace elasticapm::php {

class LoggerSinkMock : public LoggerSinkInterface {
public:
    MOCK_METHOD(LogLevel, getLevel, (), (const, override));
    MOCK_METHOD(void, setLevel, (LogLevel), (override));
    MOCK_METHOD(void, writeLog, (std::string const &formattedOutput, std::string_view message, std::string_view time, std::string_view level, std::string_view process), (const, override));
};


class LoggerTest : public ::testing::Test {
public:

protected:
    std::shared_ptr<LoggerSinkMock> sink_ = std::make_shared<::testing::StrictMock<LoggerSinkMock>>();
    Logger log_{{sink_}};
};

TEST_F(LoggerTest, test) {
    EXPECT_CALL(*sink_, getLevel).WillOnce(::testing::Return(LogLevel::logLevel_info));
    EXPECT_CALL(*sink_, writeLog(::testing::_, ::testing::_, ::testing::_, "[INFO    ]"s, ::testing::_)).Times(1);

    log_.printf(LogLevel::logLevel_info, "very basic log %d", 1234);
}

TEST_F(LoggerTest, LogLevelConditionNotMeet) {
    EXPECT_CALL(*sink_, getLevel).WillOnce(::testing::Return(LogLevel::logLevel_info));
    EXPECT_CALL(*sink_, writeLog(::testing::_, ::testing::_, ::testing::_, ::testing::_, ::testing::_)).Times(0);

    log_.printf(LogLevel::logLevel_debug, "very basic log %d", 1234);
}

TEST_F(LoggerTest, LogLevelConditionAbove) {
    EXPECT_CALL(*sink_, getLevel).WillOnce(::testing::Return(LogLevel::logLevel_debug));
    EXPECT_CALL(*sink_, writeLog(::testing::_, ::testing::_, ::testing::_, ::testing::_, ::testing::_)).Times(1);

    log_.printf(LogLevel::logLevel_info, "very basic log %d", 1234);
}

TEST_F(LoggerTest, LogLevelConditionSame) {
    EXPECT_CALL(*sink_, getLevel).WillOnce(::testing::Return(LogLevel::logLevel_info));
    EXPECT_CALL(*sink_, writeLog(::testing::_, ::testing::_, ::testing::_, ::testing::_, ::testing::_)).Times(1);

    log_.printf(LogLevel::logLevel_info, "very basic log %d", 1234);
}

TEST_F(LoggerTest, Formatting) {
    EXPECT_CALL(*sink_, getLevel).WillOnce(::testing::Return(LogLevel::logLevel_info));

    auto testFormattedString = [](std::string const &str) {
        ASSERT_TRUE(str.ends_with("[INFO    ] very basic log 1234\n"s));
        ASSERT_NE(str.find("["s + std::to_string(getpid()) + "/"s), std::string::npos);
    };
    auto testFormattedPid = [](std::string_view str) {
        ASSERT_NE(str.find("["s + std::to_string(getpid()) + "/"s), std::string::npos);
    };
    auto testFormattedTime = [](std::string_view str) {
        ASSERT_EQ(str.length(), 32);
        ASSERT_TRUE(str.ends_with(" UTC]"sv));
    };

    EXPECT_CALL(*sink_, writeLog(::testing::_, "very basic log 1234", ::testing::_, "[INFO    ]"s, ::testing::_))
        .WillOnce(
            ::testing::DoAll(
                ::testing::WithArg<0>(::testing::Invoke(testFormattedString)),
                ::testing::WithArg<2>(::testing::Invoke(testFormattedTime)),
                ::testing::WithArg<4>(::testing::Invoke(testFormattedPid))
                ));

    log_.printf(LogLevel::logLevel_info, "very basic log %d", 1234);
}

} // namespace elasticapm::php
