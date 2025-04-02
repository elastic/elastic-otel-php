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

#include "opentelemetry/proto/collector/metrics/v1/metrics_service.pb.h"
#include "opentelemetry/proto/metrics/v1/metrics.pb.h"
#include "opentelemetry/proto/common/v1/common.pb.h"
#include "opentelemetry/proto/resource/v1/resource.pb.h"

#include "ConverterHelpers.h"
#include "AutoZval.h"
#include "AttributesConverter.h"
#include "CiCharTraits.h"

#include <boost/algorithm/hex.hpp>

#include <string>
#include <string_view>
#include <unordered_map>

namespace elasticapm::php {
using namespace std::string_view_literals;

class MetricConverter {
public:
    std::string getStringSerialized(AutoZval const &batch) {
        return convert(batch).SerializeAsString();
    }

    opentelemetry::proto::collector::metrics::v1::ExportMetricsServiceRequest convert(AutoZval const &metrics) {
        if (!metrics.isArray()) {
            throw std::runtime_error("Invalid iterable passed to MetricsConverter");
        }

        opentelemetry::proto::collector::metrics::v1::ExportMetricsServiceRequest request;

        std::unordered_map<std::string, opentelemetry::proto::metrics::v1::ResourceMetrics *> resourceMetricsMap;
        std::unordered_map<std::string, opentelemetry::proto::metrics::v1::ScopeMetrics *> scopeMetricsMap;

        for (auto const &metric : metrics) {
            auto resource = metric.readProperty("resource");
            auto scope = metric.readProperty("instrumentationScope");

            std::string resourceId = ConverterHelpers::getResourceId(resource);
            std::string scopeId = ConverterHelpers::getScopeId(scope);

            auto *resourceMetrics = resourceMetricsMap[resourceId];
            if (!resourceMetrics) {
                resourceMetrics = request.add_resource_metrics();
                convertResourceMetrics(resource, resourceMetrics);
                resourceMetricsMap[resourceId] = resourceMetrics;
            }

            std::string key = resourceId + '|' + scopeId;
            auto *scopeMetrics = scopeMetricsMap[key];
            if (!scopeMetrics) {
                scopeMetrics = resourceMetrics->add_scope_metrics();
                convertScopeMetrics(scope, scopeMetrics);
                scopeMetricsMap[key] = scopeMetrics;
            }

            convertMetric(metric, scopeMetrics->add_metrics());
        }

        return request;
    }

private:
    void convertResourceMetrics(AutoZval &resource, opentelemetry::proto::metrics::v1::ResourceMetrics *out) {
        auto attributes = resource.callMethod("getAttributes"sv);
        auto resMetrics = out->mutable_resource();
        AttributesConverter::convertAttributes(attributes, resMetrics->mutable_attributes());
        resMetrics->set_dropped_attributes_count(attributes.callMethod("getDroppedAttributesCount"sv).getLong());
        if (auto schemaUrl = resource.callMethod("getSchemaUrl"sv); schemaUrl.isString()) {
            out->set_schema_url(schemaUrl.getStringView()); // TODO ??? no value at all or empty string? (in php null is casted to empty string)
        }
    }

    void convertScopeMetrics(AutoZval &scopeMetrics, opentelemetry::proto::metrics::v1::ScopeMetrics *out) {
        auto scope = out->mutable_scope();
        scope->set_name(scopeMetrics.callMethod("getName"sv).getStringView());
        if (auto version = scopeMetrics.callMethod("getVersion"sv); version.isString()) {
            scope->set_version(version.getStringView());
        }
        auto attributes = scopeMetrics.callMethod("getAttributes"sv);
        AttributesConverter::convertAttributes(attributes, scope->mutable_attributes());
        scope->set_dropped_attributes_count(attributes.callMethod("getDroppedAttributesCount"sv).getLong());
        if (auto schemaUrl = scopeMetrics.callMethod("getSchemaUrl"sv); schemaUrl.isString()) {
            out->set_schema_url(schemaUrl.getStringView());
        }
    }

    void convertMetric(AutoZval const &metric, opentelemetry::proto::metrics::v1::Metric *out) {
        out->set_name(metric.readProperty("name").getStringView());
        out->set_description(metric.readProperty("description").getOptStringView().value_or(""sv));
        out->set_unit(metric.readProperty("unit").getOptStringView().value_or(""sv));

        convertMetricData(metric.readProperty("data"), out);
    }

void convertMetricData(AutoZval const &data, opentelemetry::proto::metrics::v1::Metric *out) {
        using namespace opentelemetry::proto::metrics::v1;

        if (data.instanceOf("OpenTelemetry\\SDK\\Metrics\\Data\\Gauge"sv)) {
            convertGauge(data, out->mutable_gauge());
        } else if (data.instanceOf("OpenTelemetry\\SDK\\Metrics\\Data\\Sum"sv)) {
            convertSum(data, out->mutable_sum());
        } else if (data.instanceOf("OpenTelemetry\\SDK\\Metrics\\Data\\Histogram"sv)) {
            convertHistogram(data, out->mutable_histogram());
        } else {
            //  ("Unsupported Metric data type");
        }
    }

    void convertGauge(AutoZval const &gauge, opentelemetry::proto::metrics::v1::Gauge *out) {
        for (auto const &point : gauge.readProperty("dataPoints")) {
            convertNumberDataPoint(point, out->add_data_points());
        }
    }

    void convertSum(AutoZval const &sum, opentelemetry::proto::metrics::v1::Sum *out) {
        for (auto const &point : sum.readProperty("dataPoints")) {
            convertNumberDataPoint(point, out->add_data_points());
        }
        out->set_is_monotonic(sum.readProperty("monotonic").getBoolean());
        out->set_aggregation_temporality(convertTemporality(sum.readProperty("temporality")));
    }

    void convertHistogram(AutoZval const &hist, opentelemetry::proto::metrics::v1::Histogram *out) {
        for (auto const &point : hist.readProperty("dataPoints")) {
            convertHistogramDataPoint(point, out->add_data_points());
        }
        out->set_aggregation_temporality(convertTemporality(hist.readProperty("temporality")));
    }

    void convertNumberDataPoint(AutoZval const &point, opentelemetry::proto::metrics::v1::NumberDataPoint *out) {
        AttributesConverter::convertAttributes(point.readProperty("attributes"), out->mutable_attributes());
        out->set_start_time_unix_nano(point.readProperty("startTimestamp").getLong());
        out->set_time_unix_nano(point.readProperty("timestamp").getLong());

        auto value = point.readProperty("value");
        if (value.isLong()) {
            out->set_as_int(value.getLong());
        } else if (value.isDouble()) {
            out->set_as_double(value.getDouble());
        }

        for (auto const &exemplar : point.readProperty("exemplars")) {
            convertExemplar(exemplar, out->add_exemplars());
        }
    }

    void convertHistogramDataPoint(AutoZval const &point, opentelemetry::proto::metrics::v1::HistogramDataPoint *out) {
        AttributesConverter::convertAttributes(point.readProperty("attributes"), out->mutable_attributes());
        out->set_start_time_unix_nano(point.readProperty("startTimestamp").getLong());
        out->set_time_unix_nano(point.readProperty("timestamp").getLong());
        out->set_count(point.readProperty("count").getLong());
        out->set_sum(point.readProperty("sum").getNumberAsDouble());

        for (auto const &val : point.readProperty("bucketCounts")) {
            out->add_bucket_counts(val.getLong());
        }
        for (auto const &val : point.readProperty("explicitBounds")) {
            out->add_explicit_bounds(val.getNumberAsDouble());
        }

        for (auto const &exemplar : point.readProperty("exemplars")) {
            convertExemplar(exemplar, out->add_exemplars());
        }
    }

    void convertExemplar(AutoZval const &ex, opentelemetry::proto::metrics::v1::Exemplar *out) {
        AttributesConverter::convertAttributes(ex.readProperty("attributes"), out->mutable_filtered_attributes());
        out->set_time_unix_nano(ex.readProperty("timestamp").getLong());

        if (auto spanId = ex.readProperty("spanId"); spanId.isString()) {
            out->set_span_id(hex2bin(spanId.getStringView()));
        }
        if (auto traceId = ex.readProperty("traceId"); traceId.isString()) {
            out->set_trace_id(hex2bin(traceId.getStringView()));
        }

        auto val = ex.readProperty("value");
        if (val.isLong()) {
            out->set_as_int(val.getLong());
        } else if (val.isDouble()) {
            out->set_as_double(val.getDouble());
        }
    }

    inline std::string hex2bin(std::string_view input) {
        if (input.length() % 2 != 0) {
            throw std::invalid_argument("hex2bin: input string must have even length");
        }
        std::string output;
        boost::algorithm::unhex(input.begin(), input.end(), std::back_inserter(output));
        return output;
    }

    opentelemetry::proto::metrics::v1::AggregationTemporality convertTemporality(AutoZval const &val) {
        using opentelemetry::proto::metrics::v1::AggregationTemporality;

        if (val.isLong()) {
            switch (val.getLong()) {
                case 1:
                    return AggregationTemporality::AGGREGATION_TEMPORALITY_DELTA;
                case 2:
                    return AggregationTemporality::AGGREGATION_TEMPORALITY_CUMULATIVE;
                case 0:
                default:
                    return AggregationTemporality::AGGREGATION_TEMPORALITY_UNSPECIFIED;
            }
        }

        using namespace elasticapm::utils::string_view_literals;

        if (val.isString()) {
            auto iStr = elasticapm::utils::traits_cast<elasticapm::utils::CiCharTraits>(val.getStringView());
            if (iStr == "Delta"_cisv) {
                return AggregationTemporality::AGGREGATION_TEMPORALITY_DELTA;
            } else if (iStr == "Cumulative"_cisv) {
                return AggregationTemporality::AGGREGATION_TEMPORALITY_CUMULATIVE;
            } else {
                return AggregationTemporality::AGGREGATION_TEMPORALITY_UNSPECIFIED;
            }
        }

        throw std::runtime_error("Invalid temporality value: expected int or string (DELTA, CUMULATIVE, UNSPECIFIED)");
    }
};

} // namespace elasticapm::php
