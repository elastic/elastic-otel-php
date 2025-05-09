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
namespace linux
{

/**
 * The Linux Slab memory state
 */
static constexpr const char *kLinuxMemorySlabState
 = "linux.memory.slab.state";


namespace LinuxMemorySlabStateValues
{
/**
 * none
 */
static constexpr const char *
 kReclaimable
 = "reclaimable";

/**
 * none
 */
static constexpr const char *
 kUnreclaimable
 = "unreclaimable";

}


}
}
}
