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
namespace graphql
{

/**
 * The GraphQL document being executed.
 * <p>
 * The value may be sanitized to exclude sensitive information.
 */
static constexpr const char *kGraphqlDocument
 = "graphql.document";

/**
 * The name of the operation being executed.
 */
static constexpr const char *kGraphqlOperationName
 = "graphql.operation.name";

/**
 * The type of the operation being executed.
 */
static constexpr const char *kGraphqlOperationType
 = "graphql.operation.type";


namespace GraphqlOperationTypeValues
{
/**
 * GraphQL query
 */
static constexpr const char *
 kQuery
 = "query";

/**
 * GraphQL mutation
 */
static constexpr const char *
 kMutation
 = "mutation";

/**
 * GraphQL subscription
 */
static constexpr const char *
 kSubscription
 = "subscription";

}


}
}
}
