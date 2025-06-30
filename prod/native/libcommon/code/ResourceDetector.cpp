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

#include "ResourceDetector.h"
#include <opentelemetry/semconv/service_attributes.h>
#include <opentelemetry/semconv/os_attributes.h>
#include <opentelemetry/semconv/host_attributes.h>

namespace opentelemetry::php {

void ResourceDetector::getFromEnvironment() {
    auto resourceAttributes = std::getenv(OTEL_RESOURCE_ATTRIBUTES);
    if (resourceAttributes) {
        resourceAttributes_.merge(elasticapm::utils::parseUrlEncodedKeyValueString(resourceAttributes));
    }

    auto serviceName = std::getenv(OTEL_SERVICE_NAME);
    if (serviceName && *serviceName != 0) {
        resourceAttributes_[opentelemetry::semconv::service::kServiceName] = serviceName;
    }
}

void ResourceDetector::getHostAndOsAttributes() {
#ifdef PHP_WIN32
    resourceAttributes_[opentelemetry::semconv::os::kOsType] = "windows";
#elif defined(BSD) || defined(__DragonFly__) || defined(__FreeBSD__) || defined(__NetBSD__) || defined(__OpenBSD__)
    resourceAttributes_[opentelemetry::semconv::os::kOsType] = "bsd";
#elif defined(__APPLE__) || defined(__MACH__)
    resourceAttributes_[opentelemetry::semconv::os::kOsType] = "darwin";
#elif defined(__sun__)
    resourceAttributes_[opentelemetry::semconv::os::kOsType] = "solaris";
#elif defined(__linux__)
    resourceAttributes_[opentelemetry::semconv::os::kOsType] = "linux";
#else
    resourceAttributes_[opentelemetry::semconv::os::kOsType] = "unknown";
#endif

    if (auto value = bridge_->phpUname('s'); !value.empty()) {
        resourceAttributes_[opentelemetry::semconv::os::kOsName] = value;
    }

    if (auto value = bridge_->phpUname('r'); !value.empty()) {
        resourceAttributes_[opentelemetry::semconv::os::kOsDescription] = value;
    }

    if (auto value = bridge_->phpUname('v'); !value.empty()) {
        resourceAttributes_[opentelemetry::semconv::os::kOsVersion] = value;
    }

    if (auto value = bridge_->phpUname('n'); !value.empty()) {
        resourceAttributes_[opentelemetry::semconv::host::kHostName] = value;
    }

    if (auto value = bridge_->phpUname('m'); !value.empty()) {
        resourceAttributes_[opentelemetry::semconv::host::kHostArch] = value;
    }
}

} // namespace opentelemetry::php
