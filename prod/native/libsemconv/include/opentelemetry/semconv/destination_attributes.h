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
namespace destination
{

/**
 * Destination address - domain name if available without reverse DNS lookup; otherwise, IP address or Unix domain socket name.
 * <p>
 * When observed from the source side, and when communicating through an intermediary, @code destination.address @endcode SHOULD represent the destination address behind any intermediaries, for example proxies, if it's available.
 */
static constexpr const char *kDestinationAddress
 = "destination.address";

/**
 * Destination port number
 */
static constexpr const char *kDestinationPort
 = "destination.port";



}
}
}
