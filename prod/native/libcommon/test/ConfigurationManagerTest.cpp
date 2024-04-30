
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
    ASSERT_EQ(snapshot.revision, 1);
    cfg_.update();
    ASSERT_EQ(snapshot.revision, 1);
    cfg_.update();
    ASSERT_EQ(snapshot.revision, 1);
    cfg_.update();
    cfg_.updateIfChanged(snapshot);
    ASSERT_EQ(snapshot.revision, 4);
}

TEST_F(ConfigurationManagerTest, updateSomeOption) {
    EXPECT_CALL(iniMock_, getIniValue(::testing::_)).Times(::testing::AnyNumber()).WillRepeatedly(::testing::Return(std::nullopt));
    EXPECT_CALL(iniMock_, getIniValue("elastic_apm.api_key")).Times(1).WillOnce(::testing::Return("secret_api_key"s)).RetiresOnSaturation();
    EXPECT_CALL(iniMock_, getIniValue("elastic_apm.server_timeout")).Times(1).WillOnce(::testing::Return("10s"s)).RetiresOnSaturation();
    EXPECT_CALL(iniMock_, getIniValue("elastic_apm.enabled")).Times(1).WillOnce(::testing::Return("off")).RetiresOnSaturation();

    ConfigurationSnapshot snapshot;
    ASSERT_EQ(snapshot.revision, 0);

    cfg_.updateIfChanged(snapshot);

    ASSERT_TRUE(snapshot.api_key.empty());
    ASSERT_EQ(snapshot.server_timeout, ConfigurationSnapshot().server_timeout); // default value
    ASSERT_EQ(snapshot.enabled, ConfigurationSnapshot().enabled); // default value
    ASSERT_EQ(snapshot.revision, 1);

    cfg_.update();
    cfg_.updateIfChanged(snapshot);

    ASSERT_EQ(snapshot.revision, 2);
    ASSERT_EQ(snapshot.api_key, "secret_api_key"s);
    ASSERT_NE(snapshot.enabled, ConfigurationSnapshot().enabled); // default value
    ASSERT_EQ(snapshot.server_timeout.count(), 10000ul);
    ASSERT_NE(snapshot.enabled, ConfigurationSnapshot().enabled); // default value
    ASSERT_FALSE(snapshot.enabled);

    EXPECT_CALL(iniMock_, getIniValue("elastic_apm.enabled")).Times(1).WillOnce(::testing::Return("on")).RetiresOnSaturation();
    cfg_.update();
    cfg_.updateIfChanged(snapshot);
}

} // namespace elasticapm::php
