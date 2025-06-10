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
namespace error
{

/**
 * A message providing more detail about an error in human-readable form.
 * <p>
 * @code error.message @endcode should provide additional context and detail about an error.
 * It is NOT RECOMMENDED to duplicate the value of @code error.type @endcode in @code error.message @endcode.
 * It is also NOT RECOMMENDED to duplicate the value of @code exception.message @endcode in @code error.message @endcode.
 * <p>
 * @code error.message @endcode is NOT RECOMMENDED for metrics or spans due to its unbounded cardinality and overlap with span status.
 */
static constexpr const char *kErrorMessage
 = "error.message";

/**
 * Describes a class of error the operation ended with.
 * <p>
 * The @code error.type @endcode SHOULD be predictable, and SHOULD have low cardinality.
 * <p>
 * When @code error.type @endcode is set to a type (e.g., an exception type), its
 * canonical class name identifying the type within the artifact SHOULD be used.
 * <p>
 * Instrumentations SHOULD document the list of errors they report.
 * <p>
 * The cardinality of @code error.type @endcode within one instrumentation library SHOULD be low.
 * Telemetry consumers that aggregate data from multiple instrumentation libraries and applications
 * should be prepared for @code error.type @endcode to have high cardinality at query time when no
 * additional filters are applied.
 * <p>
 * If the operation has completed successfully, instrumentations SHOULD NOT set @code error.type @endcode.
 * <p>
 * If a specific domain defines its own set of error identifiers (such as HTTP or gRPC status codes),
 * it's RECOMMENDED to:
 * <ul>
 *   <li>Use a domain-specific attribute</li>
 *   <li>Set @code error.type @endcode to capture all errors, regardless of whether they are defined within the domain-specific set or not.</li>
 * </ul>
 */
static constexpr const char *kErrorType
 = "error.type";


namespace ErrorTypeValues
{
/**
 * A fallback error value to be used when the instrumentation doesn't define a custom value.
 */
static constexpr const char *
 kOther
 = "_OTHER";

}


}
}
}
