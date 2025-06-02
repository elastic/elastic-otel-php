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
namespace telemetry
{

/**
 * The name of the auto instrumentation agent or distribution, if used.
 * <p>
 * Official auto instrumentation agents and distributions SHOULD set the @code telemetry.distro.name @endcode attribute to
 * a string starting with @code opentelemetry- @endcode, e.g. @code opentelemetry-java-instrumentation @endcode.
 */
static constexpr const char *kTelemetryDistroName
 = "telemetry.distro.name";

/**
 * The version string of the auto instrumentation agent or distribution, if used.
 */
static constexpr const char *kTelemetryDistroVersion
 = "telemetry.distro.version";

/**
 * The language of the telemetry SDK.
 */
static constexpr const char *kTelemetrySdkLanguage
 = "telemetry.sdk.language";

/**
 * The name of the telemetry SDK as defined above.
 * <p>
 * The OpenTelemetry SDK MUST set the @code telemetry.sdk.name @endcode attribute to @code opentelemetry @endcode.
 * If another SDK, like a fork or a vendor-provided implementation, is used, this SDK MUST set the
 * @code telemetry.sdk.name @endcode attribute to the fully-qualified class or module name of this SDK's main entry point
 * or another suitable identifier depending on the language.
 * The identifier @code opentelemetry @endcode is reserved and MUST NOT be used in this case.
 * All custom identifiers SHOULD be stable across different versions of an implementation.
 */
static constexpr const char *kTelemetrySdkName
 = "telemetry.sdk.name";

/**
 * The version string of the telemetry SDK.
 */
static constexpr const char *kTelemetrySdkVersion
 = "telemetry.sdk.version";


namespace TelemetrySdkLanguageValues
{
/**
 * none
 */
static constexpr const char *
 kCpp
 = "cpp";

/**
 * none
 */
static constexpr const char *
 kDotnet
 = "dotnet";

/**
 * none
 */
static constexpr const char *
 kErlang
 = "erlang";

/**
 * none
 */
static constexpr const char *
 kGo
 = "go";

/**
 * none
 */
static constexpr const char *
 kJava
 = "java";

/**
 * none
 */
static constexpr const char *
 kNodejs
 = "nodejs";

/**
 * none
 */
static constexpr const char *
 kPhp
 = "php";

/**
 * none
 */
static constexpr const char *
 kPython
 = "python";

/**
 * none
 */
static constexpr const char *
 kRuby
 = "ruby";

/**
 * none
 */
static constexpr const char *
 kRust
 = "rust";

/**
 * none
 */
static constexpr const char *
 kSwift
 = "swift";

/**
 * none
 */
static constexpr const char *
 kWebjs
 = "webjs";

}


}
}
}
