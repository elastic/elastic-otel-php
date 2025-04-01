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

#pragma once

#include "opentelemetry/proto/collector/logs/v1/logs_service.pb.h"
#include "opentelemetry/proto/logs/v1/logs.pb.h"
#include "opentelemetry/proto/common/v1/common.pb.h"
#include "opentelemetry/proto/resource/v1/resource.pb.h"

#include "AutoZval.h"
#include "AttributesConverter.h"
#include "ConverterHelpers.h"

#include <string>
#include <string_view>
#include <unordered_map>

namespace elasticapm::php {
using namespace std::string_view_literals;

class LogsConverter {
public:
    std::string getStringSerialized(AutoZval &batch) {
        return convert(batch).SerializeAsString();
    }

private:
    opentelemetry::proto::collector::logs::v1::ExportLogsServiceRequest convert(AutoZval &logs) {
        opentelemetry::proto::collector::logs::v1::ExportLogsServiceRequest request;

        std::unordered_map<std::string, opentelemetry::proto::logs::v1::ResourceLogs *> resourceLogsMap;
        std::unordered_map<std::string, opentelemetry::proto::logs::v1::ScopeLogs *> scopeLogsMap;

        if (!logs.isArray()) {
            throw std::runtime_error("Invalid iterable passed to LogsConverter");
        }

        for (auto const &log : logs) {
            auto resourceInfo = log.callMethod("getResource"sv);
            auto instrumentationScope = log.callMethod("getInstrumentationScope"sv);

            std::string resourceId = ConverterHelpers::getResourceId(resourceInfo);
            std::string scopeId = ConverterHelpers::getScopeId(instrumentationScope);

            opentelemetry::proto::logs::v1::ResourceLogs *resourceLogs;
            if (resourceLogsMap.count(resourceId) == 0) {
                resourceLogs = request.add_resource_logs();
                convertResourceLogs(resourceInfo, resourceLogs);
                resourceLogsMap[resourceId] = resourceLogs;
            } else {
                resourceLogs = resourceLogsMap[resourceId];
            }

            opentelemetry::proto::logs::v1::ScopeLogs *scopeLogs;
            std::string compositeKey = resourceId + "|" + scopeId;
            if (scopeLogsMap.count(compositeKey) == 0) {
                scopeLogs = resourceLogs->add_scope_logs();
                convertInstrumentationScope(instrumentationScope, scopeLogs);
                scopeLogsMap[compositeKey] = scopeLogs;
            } else {
                scopeLogs = scopeLogsMap[compositeKey];
            }

            convertLogRecord(log, scopeLogs->add_log_records());
        }

        return request;
    }

    void convertResourceLogs(AutoZval &resourceInfo, opentelemetry::proto::logs::v1::ResourceLogs *out) {
        auto attributes = resourceInfo.callMethod("getAttributes"sv);
        auto resource = out->mutable_resource();
        AttributesConverter::convertAttributes(attributes, resource->mutable_attributes());
        resource->set_dropped_attributes_count(attributes.callMethod("getDroppedAttributesCount"sv).getLong());
    }

    void convertInstrumentationScope(AutoZval &scopeInfo, opentelemetry::proto::logs::v1::ScopeLogs *out) {
        auto scope = out->mutable_scope();
        scope->set_name(scopeInfo.callMethod("getName"sv).getStringView());
        if (auto version = scopeInfo.callMethod("getVersion"sv); version.isString()) {
            scope->set_version(version.getStringView());
        }
        auto attributes = scopeInfo.callMethod("getAttributes"sv);
        AttributesConverter::convertAttributes(attributes, scope->mutable_attributes());
        scope->set_dropped_attributes_count(attributes.callMethod("getDroppedAttributesCount"sv).getLong());
        if (auto schemaUrl = scopeInfo.callMethod("getSchemaUrl"sv); schemaUrl.isString()) {
            out->set_schema_url(schemaUrl.getStringView());
        }
    }

    void convertLogRecord(AutoZval const &log, opentelemetry::proto::logs::v1::LogRecord *out) {
        using namespace std::string_view_literals;

        auto body = log.callMethod("getBody"sv);
        if (!body.isNull() && !body.isUndef()) {
            auto value = AttributesConverter::convertAnyValue(body);
            *out->mutable_body() = std::move(value);
        }

        out->set_time_unix_nano(log.callMethod("getTimestamp"sv).getOptLong().value_or(0));
        out->set_observed_time_unix_nano(log.callMethod("getObservedTimestamp"sv).getOptLong().value_or(0));

        auto spanContext = log.callMethod("getSpanContext"sv);
        if (!spanContext.isNull() && spanContext.callMethod("isValid"sv).getBoolean()) {
            out->set_trace_id(spanContext.callMethod("getTraceIdBinary"sv).getStringView());
            out->set_span_id(spanContext.callMethod("getSpanIdBinary"sv).getStringView());
            out->set_flags(spanContext.callMethod("getTraceFlags"sv).getLong());
        }

        auto severityNumber = log.callMethod("getSeverityNumber"sv);
        if (severityNumber.isLong()) {
            out->set_severity_number(static_cast<opentelemetry::proto::logs::v1::SeverityNumber>(severityNumber.getLong()));
        }

        auto severityText = log.callMethod("getSeverityText"sv);
        if (severityText.isString()) {
            out->set_severity_text(severityText.getStringView());
        }

        auto attributes = log.callMethod("getAttributes"sv);
        AttributesConverter::convertAttributes(attributes, out->mutable_attributes());
        out->set_dropped_attributes_count(attributes.callMethod("getDroppedAttributesCount"sv).getLong());
    }
};

} // namespace elasticapm::php
