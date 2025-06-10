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
namespace security_rule
{

/**
 * A categorization value keyword used by the entity using the rule for detection of this event
 */
static constexpr const char *kSecurityRuleCategory
 = "security_rule.category";

/**
 * The description of the rule generating the event.
 */
static constexpr const char *kSecurityRuleDescription
 = "security_rule.description";

/**
 * Name of the license under which the rule used to generate this event is made available.
 */
static constexpr const char *kSecurityRuleLicense
 = "security_rule.license";

/**
 * The name of the rule or signature generating the event.
 */
static constexpr const char *kSecurityRuleName
 = "security_rule.name";

/**
 * Reference URL to additional information about the rule used to generate this event.
 * <p>
 * The URL can point to the vendor’s documentation about the rule. If that’s not available, it can also be a link to a more general page describing this type of alert.
 */
static constexpr const char *kSecurityRuleReference
 = "security_rule.reference";

/**
 * Name of the ruleset, policy, group, or parent category in which the rule used to generate this event is a member.
 */
static constexpr const char *kSecurityRuleRulesetName
 = "security_rule.ruleset.name";

/**
 * A rule ID that is unique within the scope of a set or group of agents, observers, or other entities using the rule for detection of this event.
 */
static constexpr const char *kSecurityRuleUuid
 = "security_rule.uuid";

/**
 * The version / revision of the rule being used for analysis.
 */
static constexpr const char *kSecurityRuleVersion
 = "security_rule.version";



}
}
}
