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

#include "opentelemetry/proto/trace/v1/trace.pb.h"
#include "opentelemetry/proto/collector/trace/v1/trace_service.pb.h"

#include "AttributesConverter.h"
#include "ConverterHelpers.h"
#include "AutoZval.h"
#include "CiCharTraits.h"

#include <string>
#include <string_view>
#include <unordered_map>

namespace elasticapm::php {

using namespace std::string_view_literals;

class SpanConverter {
public:
    std::string getStringSerialized(AutoZval &batch) {
        return convert(batch).SerializeAsString();
    }

    opentelemetry::proto::collector::trace::v1::ExportTraceServiceRequest convert(AutoZval &spans) {
        opentelemetry::proto::collector::trace::v1::ExportTraceServiceRequest request;

        std::unordered_map<std::string, opentelemetry::proto::trace::v1::ResourceSpans *> resourceSpansMap;
        std::unordered_map<std::string, opentelemetry::proto::trace::v1::ScopeSpans *> scopeSpansMap;

        if (!spans.isArray()) {
            throw std::runtime_error("Invalid iterable passed to SpanConverter");
        }

        for (auto const &span : spans) {
            // auto internalSpan = span.readProperty("span");
            // auto resourceInfo = internalSpan.readProperty("resource");
            // auto instrumentationScope = internalSpan.readProperty("instrumentationScope");

            auto resourceInfo = span.assertObjectType("OpenTelemetry\\SDK\\Trace\\ImmutableSpan"sv).callMethod("getResource"sv); // ResourceInfo
            auto instrumentationScope = span.callMethod("getInstrumentationScope"sv);                                            // InstrumentationScopeInterface

            std::string resourceId = ConverterHelpers::getResourceId(resourceInfo);
            std::string scopeId = ConverterHelpers::getScopeId(instrumentationScope);

            opentelemetry::proto::trace::v1::ResourceSpans *resourceSpans;
            if (resourceSpansMap.count(resourceId) == 0) { // TODO?? find // find and insert
                resourceSpans = request.add_resource_spans();
                convertResourceSpans(resourceInfo, resourceSpans);
                resourceSpansMap[resourceId] = resourceSpans;
            } else {
                resourceSpans = resourceSpansMap[resourceId];
            }

            opentelemetry::proto::trace::v1::ScopeSpans *scopeSpans;
            std::string compositeKey = resourceId + "|" + scopeId;
            if (scopeSpansMap.count(compositeKey) == 0) {
                scopeSpans = resourceSpans->add_scope_spans();
                convertScopeSpans(instrumentationScope, scopeSpans);
                scopeSpansMap[compositeKey] = scopeSpans;
            } else {
                scopeSpans = scopeSpansMap[compositeKey];
            }

            opentelemetry::proto::trace::v1::Span *outSpan = scopeSpans->add_spans();

            convertSpan(span, outSpan);
        }

        return request;
    }

private:
    void convertResourceSpans(elasticapm::php::AutoZval &resourceInfo, opentelemetry::proto::trace::v1::ResourceSpans *out) {
        if (auto schemaUrl = resourceInfo.callMethod("getSchemaUrl"sv); schemaUrl.isString()) {
            out->set_schema_url(schemaUrl.getStringView());
        }

        opentelemetry::proto::resource::v1::Resource *resource = out->mutable_resource();
        auto attributes = resourceInfo.callMethod("getAttributes"sv);

        AttributesConverter::convertAttributes(attributes, resource->mutable_attributes());
        resource->set_dropped_attributes_count(attributes.callMethod("getDroppedAttributesCount"sv).getLong());
    }

    void convertScopeSpans(elasticapm::php::AutoZval &instrumentationScope, opentelemetry::proto::trace::v1::ScopeSpans *out) {
        opentelemetry::proto::common::v1::InstrumentationScope *scope = out->mutable_scope();

        scope->set_name(instrumentationScope.callMethod("getName"sv).getStringView());

        if (auto version = instrumentationScope.callMethod("getVersion"sv); version.isString()) {
            scope->set_version(version.getStringView());
        }

        auto attributes = instrumentationScope.callMethod("getAttributes"sv);
        AttributesConverter::convertAttributes(attributes, scope->mutable_attributes());
        scope->set_dropped_attributes_count(attributes.callMethod("getDroppedAttributesCount"sv).getLong());

        if (auto schemaUrl = instrumentationScope.callMethod("getSchemaUrl"sv); schemaUrl.isString()) {
            out->set_schema_url(schemaUrl.getStringView());
        }
    }

    opentelemetry::proto::trace::v1::Span_SpanKind convertSpanKind(int kind) {
        using opentelemetry::proto::trace::v1::Span_SpanKind;

        switch (kind) {
            case 1: // TODO magic numbers
                return Span_SpanKind::Span_SpanKind_SPAN_KIND_INTERNAL;
            case 2:
                return Span_SpanKind::Span_SpanKind_SPAN_KIND_CLIENT;
            case 3:
                return Span_SpanKind::Span_SpanKind_SPAN_KIND_SERVER;
            case 4:
                return Span_SpanKind::Span_SpanKind_SPAN_KIND_PRODUCER;
            case 5:
                return Span_SpanKind::Span_SpanKind_SPAN_KIND_CONSUMER;
            default:
                return Span_SpanKind::Span_SpanKind_SPAN_KIND_UNSPECIFIED;
        }
    }
    opentelemetry::proto::trace::v1::Status_StatusCode convertStatusCode(std::string_view status) {
        using opentelemetry::proto::trace::v1::Status_StatusCode;
        using namespace elasticapm::utils::string_view_literals;
        auto iStatus = elasticapm::utils::traits_cast<elasticapm::utils::CiCharTraits>(status);

        if (iStatus == "Unset"_cisv) {
            return Status_StatusCode::Status_StatusCode_STATUS_CODE_UNSET;
        } else if (iStatus == "Ok"_cisv) {
            return Status_StatusCode::Status_StatusCode_STATUS_CODE_OK;
        } else if (iStatus == "Error"_cisv) {
            return Status_StatusCode::Status_StatusCode_STATUS_CODE_ERROR;
        }

        return Status_StatusCode::Status_StatusCode_STATUS_CODE_UNSET;
    }

    int addRemoteFlags(elasticapm::php::AutoZval &spanContext, int baseFlags) {
        using namespace std::string_view_literals;
        int flags = baseFlags;
        flags |= opentelemetry::proto::trace::v1::SpanFlags::SPAN_FLAGS_CONTEXT_HAS_IS_REMOTE_MASK;

        if (spanContext.callMethod("isRemote"sv).getBoolean()) {
            flags |= opentelemetry::proto::trace::v1::SpanFlags::SPAN_FLAGS_CONTEXT_IS_REMOTE_MASK;
        }
        return flags;
    }

    int buildFlagsForSpan(elasticapm::php::AutoZval &spanContext, elasticapm::php::AutoZval &parentSpanContext) {
        int flags = spanContext.callMethod("getTraceFlags"sv, {}).getLong();
        /**
         * @see https://github.com/open-telemetry/opentelemetry-proto/blob/v1.5.0/opentelemetry/proto/trace/v1/trace.proto#L122
         *
         * Bits 8 and 9 represent the 3 states of whether a span's parent is remote.
         *                                                         ^^^^^^
         * That is why we pass parent span's context.
         */
        return addRemoteFlags(parentSpanContext, flags);
    }

    int buildFlagsForLink(elasticapm::php::AutoZval &linkSpanContext) {
        int flags = linkSpanContext.callMethod("getTraceFlags"sv, {}).getLong();
        /**
         * @see https://github.com/open-telemetry/opentelemetry-proto/blob/v1.5.0/opentelemetry/proto/trace/v1/trace.proto#L279
         *
         * Bits 8 and 9 represent the 3 states of whether the link is remote.
         *                                                    ^^^^
         * That is why we pass link span's context.
         */
        return addRemoteFlags(linkSpanContext, flags);
    }

    void convertSpan(elasticapm::php::AutoZval const &span, opentelemetry::proto::trace::v1::Span *out) {
        using namespace opentelemetry::proto::trace::v1;
        using opentelemetry::proto::trace::v1::Status;
        using namespace std::string_view_literals;

        auto context = span.callMethod<0>("getContext"sv, {});
        out->set_trace_id(context.callMethod<0>("getTraceIdBinary"sv, {}).getStringView());
        out->set_span_id(context.callMethod<0>("getSpanIdBinary"sv, {}).getStringView());

        auto parentSpanContext = span.callMethod("getParentContext"sv, {});
        out->set_flags(buildFlagsForSpan(context, parentSpanContext));

        auto traceState = context.callMethod<0>("getTraceState"sv, {});
        if (traceState.isObject()) {
            out->set_trace_state(traceState.callMethod("__toString"sv, {}).getStringView());
        }

        if (parentSpanContext.callMethod("isValid"sv).getBoolean()) {
            out->set_parent_span_id(parentSpanContext.callMethod("getSpanIdBinary"sv).getStringView());
        }

        out->set_name(span.callMethod("getName"sv).getStringView());
        out->set_kind(convertSpanKind(span.callMethod("getKind"sv).getLong()));

        out->set_start_time_unix_nano(span.callMethod("getStartEpochNanos"sv).getLong());
        out->set_end_time_unix_nano(span.callMethod("getEndEpochNanos"sv).getLong());

        {
            auto attributes = span.callMethod("getAttributes"sv);
            out->set_dropped_attributes_count(attributes.callMethod("getDroppedAttributesCount"sv).getLong());
            AttributesConverter::convertAttributes(attributes, out->mutable_attributes());
        }

        {
            auto events = span.callMethod("getEvents"sv);
            for (auto const &event : events) {
                opentelemetry::proto::trace::v1::Span::Event *outEvent = out->add_events();
                outEvent->set_time_unix_nano(event.callMethod("getEpochNanos"sv).getLong());
                outEvent->set_name(event.callMethod("getName"sv).getStringView());

                auto attributes = event.callMethod("getAttributes"sv);
                AttributesConverter::convertAttributes(attributes, outEvent->mutable_attributes());
                outEvent->set_dropped_attributes_count(attributes.callMethod("getDroppedAttributesCount"sv).getLong());
            }
            out->set_dropped_events_count(span.callMethod("getTotalDroppedEvents"sv).getLong());
        }

        auto links = span.callMethod("getLinks"sv);
        for (auto const &link : links) {
            opentelemetry::proto::trace::v1::Span::Link *outLink = out->add_links();
            {
                auto linkSpanContext = link.callMethod("getSpanContext"sv);
                outLink->set_trace_id(linkSpanContext.callMethod("getTraceIdBinary"sv).getStringView());
                outLink->set_span_id(linkSpanContext.callMethod("getSpanIdBinary"sv).getStringView());
                outLink->set_flags(buildFlagsForLink(linkSpanContext));

                if (auto traceState = linkSpanContext.callMethod("getTraceState"sv); traceState.isObject()) {
                    outLink->set_trace_state(traceState.callMethod("__toString"sv).getStringView());
                }
            }
            auto attributes = link.callMethod("getAttributes"sv);
            AttributesConverter::convertAttributes(attributes, outLink->mutable_attributes());
            outLink->set_dropped_attributes_count(attributes.callMethod("getDroppedAttributesCount"sv).getLong());
        }
        out->set_dropped_links_count(span.callMethod("getTotalDroppedLinks"sv).getLong());

        Status *outStatus = out->mutable_status();
        auto status = span.callMethod("getStatus"sv);

        outStatus->set_message(status.callMethod("getDescription"sv).getStringView());
        outStatus->set_code(convertStatusCode(status.callMethod("getCode"sv).getStringView()));
    }
};
} // namespace elasticapm::php