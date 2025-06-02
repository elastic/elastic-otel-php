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

/*
 * Copyright The OpenTelemetry Authors
 * SPDX-License-Identifier: Apache-2.0
 */

/*
 * DO NOT EDIT, this is an Auto-generated file from:
 * buildscripts/semantic-convention/templates/registry/semantic_attributes-h.j2
 */












#pragma once


namespace opentelemetry {
namespace semconv
{
namespace test
{

/**
 * The fully qualified human readable name of the <a href="https://wikipedia.org/wiki/Test_case">test case</a>.
 */
static constexpr const char *kTestCaseName
 = "test.case.name";

/**
 * The status of the actual test case result from test execution.
 */
static constexpr const char *kTestCaseResultStatus
 = "test.case.result.status";

/**
 * The human readable name of a <a href="https://wikipedia.org/wiki/Test_suite">test suite</a>.
 */
static constexpr const char *kTestSuiteName
 = "test.suite.name";

/**
 * The status of the test suite run.
 */
static constexpr const char *kTestSuiteRunStatus
 = "test.suite.run.status";


namespace TestCaseResultStatusValues
{
/**
 * pass
 */
static constexpr const char *
 kPass
 = "pass";

/**
 * fail
 */
static constexpr const char *
 kFail
 = "fail";

}

namespace TestSuiteRunStatusValues
{
/**
 * success
 */
static constexpr const char *
 kSuccess
 = "success";

/**
 * failure
 */
static constexpr const char *
 kFailure
 = "failure";

/**
 * skipped
 */
static constexpr const char *
 kSkipped
 = "skipped";

/**
 * aborted
 */
static constexpr const char *
 kAborted
 = "aborted";

/**
 * timed_out
 */
static constexpr const char *
 kTimedOut
 = "timed_out";

/**
 * in_progress
 */
static constexpr const char *
 kInProgress
 = "in_progress";

}


}
}
}
