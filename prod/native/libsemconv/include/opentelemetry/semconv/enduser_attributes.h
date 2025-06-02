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
namespace enduser
{

/**
 * Unique identifier of an end user in the system. It maybe a username, email address, or other identifier.
 * <p>
 * Unique identifier of an end user in the system.
 * <blockquote>
 * [!Warning]
 * This field contains sensitive (PII) information.</blockquote>
 */
static constexpr const char *kEnduserId
 = "enduser.id";

/**
 * Pseudonymous identifier of an end user. This identifier should be a random value that is not directly linked or associated with the end user's actual identity.
 * <p>
 * Pseudonymous identifier of an end user.
 * <blockquote>
 * [!Warning]
 * This field contains sensitive (linkable PII) information.</blockquote>
 */
static constexpr const char *kEnduserPseudoId
 = "enduser.pseudo.id";

/**
 * Deprecated, use @code user.roles @endcode instead.
 *
 * @deprecated
 * {"note": "Replaced by @code user.roles @endcode attribute.", "reason": "uncategorized"}
 */
OPENTELEMETRY_DEPRECATED
static constexpr const char *kEnduserRole
 = "enduser.role";

/**
 * Deprecated, no replacement at this time.
 *
 * @deprecated
 * {"note": "Removed.", "reason": "uncategorized"}
 */
OPENTELEMETRY_DEPRECATED
static constexpr const char *kEnduserScope
 = "enduser.scope";



}
}
}
