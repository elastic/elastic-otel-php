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
namespace exception
{

/**
 * Indicates that the exception is escaping the scope of the span.
 *
 * @deprecated
 * {"note": "It's no longer recommended to record exceptions that are handled and do not escape the scope of a span.", "reason": "uncategorized"}
 */
OPENTELEMETRY_DEPRECATED
static constexpr const char *kExceptionEscaped
 = "exception.escaped";

/**
 * The exception message.
 */
static constexpr const char *kExceptionMessage
 = "exception.message";

/**
 * A stacktrace as a string in the natural representation for the language runtime. The representation is to be determined and documented by each language SIG.
 */
static constexpr const char *kExceptionStacktrace
 = "exception.stacktrace";

/**
 * The type of the exception (its fully-qualified class name, if applicable). The dynamic type of the exception should be preferred over the static type in languages that support it.
 */
static constexpr const char *kExceptionType
 = "exception.type";



}
}
}
