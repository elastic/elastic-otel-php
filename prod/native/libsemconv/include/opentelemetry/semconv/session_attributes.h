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
namespace session
{

/**
 * A unique id to identify a session.
 */
static constexpr const char *kSessionId
 = "session.id";

/**
 * The previous @code session.id @endcode for this user, when known.
 */
static constexpr const char *kSessionPreviousId
 = "session.previous_id";



}
}
}
