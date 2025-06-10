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
namespace message
{

/**
 * Deprecated, use @code rpc.message.compressed_size @endcode instead.
 *
 * @deprecated
 * {"note": "Replaced by @code rpc.message.compressed_size @endcode.", "reason": "uncategorized"}
 */
OPENTELEMETRY_DEPRECATED
static constexpr const char *kMessageCompressedSize
 = "message.compressed_size";

/**
 * Deprecated, use @code rpc.message.id @endcode instead.
 *
 * @deprecated
 * {"note": "Replaced by @code rpc.message.id @endcode.", "reason": "uncategorized"}
 */
OPENTELEMETRY_DEPRECATED
static constexpr const char *kMessageId
 = "message.id";

/**
 * Deprecated, use @code rpc.message.type @endcode instead.
 *
 * @deprecated
 * {"note": "Replaced by @code rpc.message.type @endcode.", "reason": "uncategorized"}
 */
OPENTELEMETRY_DEPRECATED
static constexpr const char *kMessageType
 = "message.type";

/**
 * Deprecated, use @code rpc.message.uncompressed_size @endcode instead.
 *
 * @deprecated
 * {"note": "Replaced by @code rpc.message.uncompressed_size @endcode.", "reason": "uncategorized"}
 */
OPENTELEMETRY_DEPRECATED
static constexpr const char *kMessageUncompressedSize
 = "message.uncompressed_size";


namespace MessageTypeValues
{
/**
 * none
 */
static constexpr const char *
 kSent
 = "SENT";

/**
 * none
 */
static constexpr const char *
 kReceived
 = "RECEIVED";

}


}
}
}
