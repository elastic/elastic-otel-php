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
namespace peer
{

/**
 * The <a href="/docs/resource/README.md#service">@code service.name @endcode</a> of the remote service. SHOULD be equal to the actual @code service.name @endcode resource attribute of the remote service if any.
 */
static constexpr const char *kPeerService
 = "peer.service";



}
}
}
