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


#include "SharedMemoryState.h"

#include <atomic>
#include <thread>
#include <gtest/gtest.h>

namespace elasticapm::php {

class SharedMemoryStateTest : public ::testing::Test {
public:
    SharedMemoryState state_;
};


TEST_F(SharedMemoryStateTest, shouldExecuteOneTimeTaskAmongWorkers) {
    ASSERT_TRUE(state_.shouldExecuteOneTimeTaskAmongWorkers());
    ASSERT_FALSE(state_.shouldExecuteOneTimeTaskAmongWorkers());
    ASSERT_FALSE(state_.shouldExecuteOneTimeTaskAmongWorkers());
    ASSERT_FALSE(state_.shouldExecuteOneTimeTaskAmongWorkers());
}

TEST_F(SharedMemoryStateTest, shouldExecuteOneTimeTaskAmongWorkersFromThreads) {
    std::atomic_int32_t counter = 0;

    auto test = [&]() {
        if (state_.shouldExecuteOneTimeTaskAmongWorkers()) {
            counter++;
        }
    };

    std::thread  t1{test}, t2{test}, t3{test}, t4{test}, t5{test}, t6{test}, t7{test};

    t1.join();
    t2.join();
    t3.join();
    t4.join();
    t5.join();
    t6.join();
    t7.join();

    EXPECT_EQ(counter.load(), 1);
}


}
