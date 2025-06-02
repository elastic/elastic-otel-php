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
namespace os
{

/**
 * Unique identifier for a particular build or compilation of the operating system.
 */
static constexpr const char *kOsBuildId
 = "os.build_id";

/**
 * Human readable (not intended to be parsed) OS version information, like e.g. reported by @code ver @endcode or @code lsb_release -a @endcode commands.
 */
static constexpr const char *kOsDescription
 = "os.description";

/**
 * Human readable operating system name.
 */
static constexpr const char *kOsName
 = "os.name";

/**
 * The operating system type.
 */
static constexpr const char *kOsType
 = "os.type";

/**
 * The version string of the operating system as defined in <a href="/docs/resource/README.md#version-attributes">Version Attributes</a>.
 */
static constexpr const char *kOsVersion
 = "os.version";


namespace OsTypeValues
{
/**
 * Microsoft Windows
 */
static constexpr const char *
 kWindows
 = "windows";

/**
 * Linux
 */
static constexpr const char *
 kLinux
 = "linux";

/**
 * Apple Darwin
 */
static constexpr const char *
 kDarwin
 = "darwin";

/**
 * FreeBSD
 */
static constexpr const char *
 kFreebsd
 = "freebsd";

/**
 * NetBSD
 */
static constexpr const char *
 kNetbsd
 = "netbsd";

/**
 * OpenBSD
 */
static constexpr const char *
 kOpenbsd
 = "openbsd";

/**
 * DragonFly BSD
 */
static constexpr const char *
 kDragonflybsd
 = "dragonflybsd";

/**
 * HP-UX (Hewlett Packard Unix)
 */
static constexpr const char *
 kHpux
 = "hpux";

/**
 * AIX (Advanced Interactive eXecutive)
 */
static constexpr const char *
 kAix
 = "aix";

/**
 * SunOS, Oracle Solaris
 */
static constexpr const char *
 kSolaris
 = "solaris";

/**
 * IBM z/OS
 */
static constexpr const char *
 kZOs
 = "z_os";

}


}
}
}
