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

#include "DependencyAutoLoaderGuard.h"
#include "PhpBridgeMock.h"
#include "Logger.h"

#include <string_view>
#include <gtest/gtest.h>
#include <gmock/gmock.h>

using namespace std::literals;

namespace elasticapm::php::test {

using namespace std::string_view_literals;

class DependencyAutoLoaderGuardTest : public ::testing::Test {
public:
    DependencyAutoLoaderGuardTest() {
        if (std::getenv("ELASTIC_OTEL_DEBUG_LOG_TESTS")) {
            auto serr = std::make_shared<elasticapm::php::LoggerSinkStdErr>();
            serr->setLevel(logLevel_trace);
            reinterpret_cast<elasticapm::php::Logger *>(log_.get())->attachSink(serr);
        }
    }
    std::shared_ptr<LoggerInterface> log_ = std::make_shared<elasticapm::php::Logger>(std::vector<std::shared_ptr<LoggerSinkInterface>>());
    std::shared_ptr<PhpBridgeMock> bridge_{std::make_shared<::testing::StrictMock<PhpBridgeMock>>()};
    DependencyAutoLoaderGuard guard_{bridge_, log_};
};

TEST_F(DependencyAutoLoaderGuardTest, discardAppFileBecauseItWasDeliveredByEDOT) {
    EXPECT_CALL(*bridge_, getPhpVersionMajorMinor()).Times(::testing::Exactly(1)).WillOnce(::testing::Return(std::pair<int, int>(8, 4)));

    guard_.setBootstrapPath("/elatic/prod/php/bootstrap.php");

    ASSERT_FALSE(guard_.shouldDiscardFileCompilation("/elatic/prod/php/vendor_per_PHP_version/84/first-package/test.php"));  // file from elastic scope - no action
    ASSERT_FALSE(guard_.shouldDiscardFileCompilation("/elatic/prod/php/vendor_per_PHP_version/84/second-package/test.php")); // file from elastic scope - no action

    // clang-format off
    EXPECT_CALL(*bridge_, getNewlyCompiledFiles(::testing::_, 0, 0)).Times(::testing::Exactly(1)).WillOnce(
        ::testing::DoAll(
            ::testing::InvokeArgument<0>("/elatic/prod/php/vendor_per_PHP_version/84/first-package/test.php"sv), // we have that file in cache
            ::testing::InvokeArgument<0>("/elatic/prod/php/vendor_per_PHP_version/84/second-package/test.php"sv), // we have that file in cache
            ::testing::Return(std::pair<int, int>(2, 1)))); // returns index in class/file hashmaps
    // clang-format on

    ASSERT_TRUE(guard_.shouldDiscardFileCompilation("/app/vendor/first-package/test.php")); // file from app scope - test it - should discard - file is EDOT delivered
}

TEST_F(DependencyAutoLoaderGuardTest, discardSecondAppFileBecauseItWasDeliveredByEDOT) {
    EXPECT_CALL(*bridge_, getPhpVersionMajorMinor()).Times(::testing::Exactly(1)).WillOnce(::testing::Return(std::pair<int, int>(8, 4)));

    guard_.setBootstrapPath("/elatic/prod/php/bootstrap.php");

    ASSERT_FALSE(guard_.shouldDiscardFileCompilation("/elatic/prod/php/vendor_per_PHP_version/84/first-package/test.php"));  // file from elastic scope - no action
    ASSERT_FALSE(guard_.shouldDiscardFileCompilation("/elatic/prod/php/vendor_per_PHP_version/84/second-package/test.php")); // file from elastic scope - no action

    // clang-format off
    EXPECT_CALL(*bridge_, getNewlyCompiledFiles(::testing::_, 0, 0)).Times(::testing::Exactly(1)).WillOnce(
        ::testing::DoAll(
            ::testing::InvokeArgument<0>("/elatic/prod/php/vendor_per_PHP_version/84/first-package/test.php"sv), // we have that file in cache
            ::testing::InvokeArgument<0>("/elatic/prod/php/vendor_per_PHP_version/84/second-package/test.php"sv), // we have that file in cache
            ::testing::Return(std::pair<int, int>(2, 1)))); // returns index in class/file hashmaps
    // clang-format on

    ASSERT_TRUE(guard_.shouldDiscardFileCompilation("/app/vendor/second-package/test.php")); // file from app scope - test it - should discard - file is EDOT delivered
}

TEST_F(DependencyAutoLoaderGuardTest, getCompiledFilesListProgressively) {
    EXPECT_CALL(*bridge_, getPhpVersionMajorMinor()).Times(::testing::Exactly(1)).WillOnce(::testing::Return(std::pair<int, int>(8, 4)));

    guard_.setBootstrapPath("/elatic/prod/php/bootstrap.php");

    ASSERT_FALSE(guard_.shouldDiscardFileCompilation("/elatic/prod/php/vendor_per_PHP_version/84/first-package/test.php"));  // file from elastic scope - no action
    ASSERT_FALSE(guard_.shouldDiscardFileCompilation("/elatic/prod/php/vendor_per_PHP_version/84/second-package/test.php")); // file from elastic scope - no action

    ::testing::InSequence s;

    // clang-format off
    EXPECT_CALL(*bridge_, getNewlyCompiledFiles(::testing::_, 0, 0)).Times(::testing::Exactly(1)).WillOnce(
        ::testing::DoAll(
            ::testing::InvokeArgument<0>("/elatic/prod/php/vendor_per_PHP_version/84/first-package/test.php"sv), // we have that file in cache
            ::testing::Return(std::pair<int, int>(10, 20)))); // returns index in class/file hashmaps
    // clang-format on

    ASSERT_TRUE(guard_.shouldDiscardFileCompilation("/app/vendor/first-package/test.php")); // file from app scope - test it - should discard - file is EDOT delivered

    // clang-format off
    EXPECT_CALL(*bridge_, getNewlyCompiledFiles(::testing::_, 10, 20)).Times(::testing::Exactly(1)).WillOnce(
        ::testing::DoAll(
            ::testing::InvokeArgument<0>("/elatic/prod/php/vendor_per_PHP_version/84/second-package/test.php"sv), // we have that file in cache
            ::testing::Return(std::pair<int, int>(11, 21)))); // returns index in class/file hashmaps
    // clang-format on

    ASSERT_TRUE(guard_.shouldDiscardFileCompilation("/app/vendor/second-package/test.php")); // file from app scope - test it - should discard - file is EDOT delivered

    // clang-format off
    EXPECT_CALL(*bridge_, getNewlyCompiledFiles(::testing::_, 11, 21)).Times(::testing::Exactly(1)).WillOnce(
        ::testing::DoAll(
            ::testing::Return(std::pair<int, int>(11, 21)))); // returns index in class/file hashmaps
    // clang-format on

    ASSERT_FALSE(guard_.shouldDiscardFileCompilation("/app/vendor/third-package/test.php")); // file from app scope - test it - should NOT discard - file is NOT EDOT delivered
}

TEST_F(DependencyAutoLoaderGuardTest, fileNotInVendorFolder) {
    EXPECT_CALL(*bridge_, getPhpVersionMajorMinor()).Times(::testing::Exactly(1)).WillOnce(::testing::Return(std::pair<int, int>(8, 4)));
    guard_.setBootstrapPath("/elatic/prod/php/bootstrap.php");

    ASSERT_FALSE(guard_.shouldDiscardFileCompilation("/elatic/prod/php/vendor_per_PHP_version/84/first-package/test.php")); // file from elastic scope - no action

    ::testing::InSequence s;

    // clang-format off
    EXPECT_CALL(*bridge_, getNewlyCompiledFiles(::testing::_, 0, 0)).Times(::testing::Exactly(1)).WillOnce(
        ::testing::DoAll(
            ::testing::InvokeArgument<0>("/elatic/prod/php/vendor_per_PHP_version/84/first-package/test.php"sv), // we have that file in cache
            ::testing::Return(std::pair<int, int>(10, 20)))); // returns index in class/file hashmaps
    // clang-format on

    ASSERT_FALSE(guard_.shouldDiscardFileCompilation("/app/first-package/test.php")); // file from app scope - test it - should discard - file is EDOT delivered
}

TEST_F(DependencyAutoLoaderGuardTest, wrongVendorFolder_shouldntHappen) {
    EXPECT_CALL(*bridge_, getPhpVersionMajorMinor()).Times(::testing::Exactly(1)).WillOnce(::testing::Return(std::pair<int, int>(8, 4)));

    guard_.setBootstrapPath("/elatic/prod/php/bootstrap.php");

    ::testing::InSequence s;

    // clang-format off
    EXPECT_CALL(*bridge_, getNewlyCompiledFiles(::testing::_, 0, 0)).Times(::testing::Exactly(1)).WillOnce(
        ::testing::DoAll(
            ::testing::InvokeArgument<0>("/elatic/prod/php/vendor_per_PHP_version/80/first-package/test.php"sv), // we have that file in cache
            ::testing::Return(std::pair<int, int>(2, 1)))); // returns index in class/file hashmaps
    // clang-format on

    ASSERT_FALSE(guard_.shouldDiscardFileCompilation("/elatic/prod/php/vendor_per_PHP_version/80/first-package/test.php")); // file NOT from elastic scope - wrong vendor folder

    // clang-format off
    EXPECT_CALL(*bridge_, getNewlyCompiledFiles(::testing::_, 2, 1)).Times(::testing::Exactly(1)).WillOnce(
        ::testing::DoAll(
            ::testing::Return(std::pair<int, int>(2, 1)))); // returns index in class/file hashmaps
    // clang-format on

    ASSERT_FALSE(guard_.shouldDiscardFileCompilation("/app/vendor/first-package/test.php"));
}

} // namespace elasticapm::php::test
