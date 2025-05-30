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
namespace cpython
{

/**
 * Value of the garbage collector collection generation.
 */
static constexpr const char *kCpythonGcGeneration
 = "cpython.gc.generation";


namespace CpythonGcGenerationValues
{
/**
 * Generation 0
 */
static constexpr int
 kGeneration0
 = 0;

/**
 * Generation 1
 */
static constexpr int
 kGeneration1
 = 1;

/**
 * Generation 2
 */
static constexpr int
 kGeneration2
 = 2;

}


}
}
}
