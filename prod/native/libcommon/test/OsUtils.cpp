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