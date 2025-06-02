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
namespace azure
{

/**
 * Number of active client instances
 * <p>
 * updowncounter
 */
static constexpr const char *kMetricAzureCosmosdbClientActiveInstanceCount
 = "azure.cosmosdb.client.active_instance.count";
static constexpr const char *descrMetricAzureCosmosdbClientActiveInstanceCount
 = "Number of active client instances";
static constexpr const char *unitMetricAzureCosmosdbClientActiveInstanceCount
 = "{instance}";

static inline nostd::unique_ptr<metrics::UpDownCounter<int64_t>
>
CreateSyncInt64MetricAzureCosmosdbClientActiveInstanceCount
(metrics::Meter *meter)
{
  return meter->CreateInt64UpDownCounter
(
    kMetricAzureCosmosdbClientActiveInstanceCount
,
    descrMetricAzureCosmosdbClientActiveInstanceCount
,
    unitMetricAzureCosmosdbClientActiveInstanceCount
);
}

static inline nostd::unique_ptr<metrics::UpDownCounter<double>
>
CreateSyncDoubleMetricAzureCosmosdbClientActiveInstanceCount
(metrics::Meter *meter)
{
  return meter->CreateDoubleUpDownCounter
(
    kMetricAzureCosmosdbClientActiveInstanceCount
,
    descrMetricAzureCosmosdbClientActiveInstanceCount
,
    unitMetricAzureCosmosdbClientActiveInstanceCount
);
}

static inline nostd::shared_ptr<metrics::ObservableInstrument
>
CreateAsyncInt64MetricAzureCosmosdbClientActiveInstanceCount
(metrics::Meter *meter)
{
  return meter->CreateInt64ObservableUpDownCounter
(
    kMetricAzureCosmosdbClientActiveInstanceCount
,
    descrMetricAzureCosmosdbClientActiveInstanceCount
,
    unitMetricAzureCosmosdbClientActiveInstanceCount
);
}

static inline nostd::shared_ptr<metrics::ObservableInstrument
>
CreateAsyncDoubleMetricAzureCosmosdbClientActiveInstanceCount
(metrics::Meter *meter)
{
  return meter->CreateDoubleObservableUpDownCounter
(
    kMetricAzureCosmosdbClientActiveInstanceCount
,
    descrMetricAzureCosmosdbClientActiveInstanceCount
,
    unitMetricAzureCosmosdbClientActiveInstanceCount
);
}


/**
 * <a href="https://learn.microsoft.com/azure/cosmos-db/request-units">Request units</a> consumed by the operation
 * <p>
 * histogram
 */
static constexpr const char *kMetricAzureCosmosdbClientOperationRequestCharge
 = "azure.cosmosdb.client.operation.request_charge";
static constexpr const char *descrMetricAzureCosmosdbClientOperationRequestCharge
 = "[Request units](https://learn.microsoft.com/azure/cosmos-db/request-units) consumed by the operation";
static constexpr const char *unitMetricAzureCosmosdbClientOperationRequestCharge
 = "{request_unit}";

static inline nostd::unique_ptr<metrics::Histogram<uint64_t>
>
CreateSyncInt64MetricAzureCosmosdbClientOperationRequestCharge
(metrics::Meter *meter)
{
  return meter->CreateUInt64Histogram
(
    kMetricAzureCosmosdbClientOperationRequestCharge
,
    descrMetricAzureCosmosdbClientOperationRequestCharge
,
    unitMetricAzureCosmosdbClientOperationRequestCharge
);
}

static inline nostd::unique_ptr<metrics::Histogram<double>
>
CreateSyncDoubleMetricAzureCosmosdbClientOperationRequestCharge
(metrics::Meter *meter)
{
  return meter->CreateDoubleHistogram
(
    kMetricAzureCosmosdbClientOperationRequestCharge
,
    descrMetricAzureCosmosdbClientOperationRequestCharge
,
    unitMetricAzureCosmosdbClientOperationRequestCharge
);
}




}
}
}
