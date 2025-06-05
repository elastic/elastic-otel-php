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
 * buildscripts/semantic-convention/templates/registry/semantic_metrics-h.j2
 */





















#pragma once

namespace opentelemetry {
namespace semconv
{
namespace dns
{

/**
 * Measures the time taken to perform a DNS lookup.
 * <p>
 * histogram
 */
static constexpr const char *kMetricDnsLookupDuration
 = "dns.lookup.duration";
static constexpr const char *descrMetricDnsLookupDuration
 = "Measures the time taken to perform a DNS lookup.";
static constexpr const char *unitMetricDnsLookupDuration
 = "s";

static inline nostd::unique_ptr<metrics::Histogram<uint64_t>
>
CreateSyncInt64MetricDnsLookupDuration
(metrics::Meter *meter)
{
  return meter->CreateUInt64Histogram
(
    kMetricDnsLookupDuration
,
    descrMetricDnsLookupDuration
,
    unitMetricDnsLookupDuration
);
}

static inline nostd::unique_ptr<metrics::Histogram<double>
>
CreateSyncDoubleMetricDnsLookupDuration
(metrics::Meter *meter)
{
  return meter->CreateDoubleHistogram
(
    kMetricDnsLookupDuration
,
    descrMetricDnsLookupDuration
,
    unitMetricDnsLookupDuration
);
}




}
}
}
