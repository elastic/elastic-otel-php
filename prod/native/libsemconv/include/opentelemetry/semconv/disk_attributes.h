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
namespace disk
{

/**
 * The disk IO operation direction.
 */
static constexpr const char *kDiskIoDirection
 = "disk.io.direction";


namespace DiskIoDirectionValues
{
/**
 * none
 */
static constexpr const char *
 kRead
 = "read";

/**
 * none
 */
static constexpr const char *
 kWrite
 = "write";

}


}
}
}
