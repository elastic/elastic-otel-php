
#include "CallOnScopeExit.h"

#include <gtest/gtest.h>
#include <gmock/gmock.h>

using namespace std::literals;

namespace elasticapm::utils {

TEST(CallOnScopeExitTest, justACall) {

    bool callConditionMeet = false;
    {
        callOnScopeExit call([&callConditionMeet]() { callConditionMeet = true; });
    }
    EXPECT_TRUE(callConditionMeet);
}

}

