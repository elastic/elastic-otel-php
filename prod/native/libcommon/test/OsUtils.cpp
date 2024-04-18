
#include "os/OsUtils.h"

#include <gtest/gtest.h>
#include <gmock/gmock.h>
#include <pthread.h>

#include <string_view>

using namespace std::literals;

namespace elasticapm::osutils {

TEST(OsUtilsTest, getCommandLine) {
    ASSERT_NE(osutils::getCommandLine().find("common_test"), std::string::npos);
}

TEST(OsUtilsTest, getProcessEnvironment) {
    ASSERT_NE(osutils::getProcessEnvironment().find("PATH="), std::string::npos);
}


} // namespace elasticapm::osutils