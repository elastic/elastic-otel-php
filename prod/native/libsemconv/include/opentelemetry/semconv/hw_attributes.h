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
namespace hw
{

/**
 * An identifier for the hardware component, unique within the monitored host
 */
static constexpr const char *kHwId
 = "hw.id";

/**
 * An easily-recognizable name for the hardware component
 */
static constexpr const char *kHwName
 = "hw.name";

/**
 * Unique identifier of the parent component (typically the @code hw.id @endcode attribute of the enclosure, or disk controller)
 */
static constexpr const char *kHwParent
 = "hw.parent";

/**
 * The current state of the component
 */
static constexpr const char *kHwState
 = "hw.state";

/**
 * Type of the component
 * <p>
 * Describes the category of the hardware component for which @code hw.state @endcode is being reported. For example, @code hw.type=temperature @endcode along with @code hw.state=degraded @endcode would indicate that the temperature of the hardware component has been reported as @code degraded @endcode.
 */
static constexpr const char *kHwType
 = "hw.type";


namespace HwStateValues
{
/**
 * Ok
 */
static constexpr const char *
 kOk
 = "ok";

/**
 * Degraded
 */
static constexpr const char *
 kDegraded
 = "degraded";

/**
 * Failed
 */
static constexpr const char *
 kFailed
 = "failed";

}

namespace HwTypeValues
{
/**
 * Battery
 */
static constexpr const char *
 kBattery
 = "battery";

/**
 * CPU
 */
static constexpr const char *
 kCpu
 = "cpu";

/**
 * Disk controller
 */
static constexpr const char *
 kDiskController
 = "disk_controller";

/**
 * Enclosure
 */
static constexpr const char *
 kEnclosure
 = "enclosure";

/**
 * Fan
 */
static constexpr const char *
 kFan
 = "fan";

/**
 * GPU
 */
static constexpr const char *
 kGpu
 = "gpu";

/**
 * Logical disk
 */
static constexpr const char *
 kLogicalDisk
 = "logical_disk";

/**
 * Memory
 */
static constexpr const char *
 kMemory
 = "memory";

/**
 * Network
 */
static constexpr const char *
 kNetwork
 = "network";

/**
 * Physical disk
 */
static constexpr const char *
 kPhysicalDisk
 = "physical_disk";

/**
 * Power supply
 */
static constexpr const char *
 kPowerSupply
 = "power_supply";

/**
 * Tape drive
 */
static constexpr const char *
 kTapeDrive
 = "tape_drive";

/**
 * Temperature
 */
static constexpr const char *
 kTemperature
 = "temperature";

/**
 * Voltage
 */
static constexpr const char *
 kVoltage
 = "voltage";

}


}
}
}
