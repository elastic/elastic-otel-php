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
namespace deployment
{

/**
 * 'Deprecated, use @code deployment.environment.name @endcode instead.'
 *
 * @deprecated
 * {"note": "Deprecated, use @code deployment.environment.name @endcode instead.", "reason": "uncategorized"}
 */
OPENTELEMETRY_DEPRECATED
static constexpr const char *kDeploymentEnvironment
 = "deployment.environment";

/**
 * Name of the <a href="https://wikipedia.org/wiki/Deployment_environment">deployment environment</a> (aka deployment tier).
 * <p>
 * @code deployment.environment.name @endcode does not affect the uniqueness constraints defined through
 * the @code service.namespace @endcode, @code service.name @endcode and @code service.instance.id @endcode resource attributes.
 * This implies that resources carrying the following attribute combinations MUST be
 * considered to be identifying the same service:
 * <ul>
 *   <li>@code service.name=frontend @endcode, @code deployment.environment.name=production @endcode</li>
 *   <li>@code service.name=frontend @endcode, @code deployment.environment.name=staging @endcode.</li>
 * </ul>
 */
static constexpr const char *kDeploymentEnvironmentName
 = "deployment.environment.name";

/**
 * The id of the deployment.
 */
static constexpr const char *kDeploymentId
 = "deployment.id";

/**
 * The name of the deployment.
 */
static constexpr const char *kDeploymentName
 = "deployment.name";

/**
 * The status of the deployment.
 */
static constexpr const char *kDeploymentStatus
 = "deployment.status";


namespace DeploymentStatusValues
{
/**
 * failed
 */
static constexpr const char *
 kFailed
 = "failed";

/**
 * succeeded
 */
static constexpr const char *
 kSucceeded
 = "succeeded";

}


}
}
}
