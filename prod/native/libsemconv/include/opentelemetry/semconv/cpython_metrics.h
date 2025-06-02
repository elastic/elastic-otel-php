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
namespace cpython
{

/**
 * The total number of objects collected inside a generation since interpreter start.
 * <p>
 * This metric reports data from <a href="https://docs.python.org/3/library/gc.html#gc.get_stats">@code gc.stats() @endcode</a>.
 * <p>
 * counter
 */
static constexpr const char *kMetricCpythonGcCollectedObjects
 = "cpython.gc.collected_objects";
static constexpr const char *descrMetricCpythonGcCollectedObjects
 = "The total number of objects collected inside a generation since interpreter start.";
static constexpr const char *unitMetricCpythonGcCollectedObjects
 = "{object}";

static inline nostd::unique_ptr<metrics::Counter<uint64_t>
>
CreateSyncInt64MetricCpythonGcCollectedObjects
(metrics::Meter *meter)
{
  return meter->CreateUInt64Counter
(
    kMetricCpythonGcCollectedObjects
,
    descrMetricCpythonGcCollectedObjects
,
    unitMetricCpythonGcCollectedObjects
);
}

static inline nostd::unique_ptr<metrics::Counter<double>
>
CreateSyncDoubleMetricCpythonGcCollectedObjects
(metrics::Meter *meter)
{
  return meter->CreateDoubleCounter
(
    kMetricCpythonGcCollectedObjects
,
    descrMetricCpythonGcCollectedObjects
,
    unitMetricCpythonGcCollectedObjects
);
}

static inline nostd::shared_ptr<metrics::ObservableInstrument
>
CreateAsyncInt64MetricCpythonGcCollectedObjects
(metrics::Meter *meter)
{
  return meter->CreateInt64ObservableCounter
(
    kMetricCpythonGcCollectedObjects
,
    descrMetricCpythonGcCollectedObjects
,
    unitMetricCpythonGcCollectedObjects
);
}

static inline nostd::shared_ptr<metrics::ObservableInstrument
>
CreateAsyncDoubleMetricCpythonGcCollectedObjects
(metrics::Meter *meter)
{
  return meter->CreateDoubleObservableCounter
(
    kMetricCpythonGcCollectedObjects
,
    descrMetricCpythonGcCollectedObjects
,
    unitMetricCpythonGcCollectedObjects
);
}


/**
 * The number of times a generation was collected since interpreter start.
 * <p>
 * This metric reports data from <a href="https://docs.python.org/3/library/gc.html#gc.get_stats">@code gc.stats() @endcode</a>.
 * <p>
 * counter
 */
static constexpr const char *kMetricCpythonGcCollections
 = "cpython.gc.collections";
static constexpr const char *descrMetricCpythonGcCollections
 = "The number of times a generation was collected since interpreter start.";
static constexpr const char *unitMetricCpythonGcCollections
 = "{collection}";

static inline nostd::unique_ptr<metrics::Counter<uint64_t>
>
CreateSyncInt64MetricCpythonGcCollections
(metrics::Meter *meter)
{
  return meter->CreateUInt64Counter
(
    kMetricCpythonGcCollections
,
    descrMetricCpythonGcCollections
,
    unitMetricCpythonGcCollections
);
}

static inline nostd::unique_ptr<metrics::Counter<double>
>
CreateSyncDoubleMetricCpythonGcCollections
(metrics::Meter *meter)
{
  return meter->CreateDoubleCounter
(
    kMetricCpythonGcCollections
,
    descrMetricCpythonGcCollections
,
    unitMetricCpythonGcCollections
);
}

static inline nostd::shared_ptr<metrics::ObservableInstrument
>
CreateAsyncInt64MetricCpythonGcCollections
(metrics::Meter *meter)
{
  return meter->CreateInt64ObservableCounter
(
    kMetricCpythonGcCollections
,
    descrMetricCpythonGcCollections
,
    unitMetricCpythonGcCollections
);
}

static inline nostd::shared_ptr<metrics::ObservableInstrument
>
CreateAsyncDoubleMetricCpythonGcCollections
(metrics::Meter *meter)
{
  return meter->CreateDoubleObservableCounter
(
    kMetricCpythonGcCollections
,
    descrMetricCpythonGcCollections
,
    unitMetricCpythonGcCollections
);
}


/**
 * The total number of objects which were found to be uncollectable inside a generation since interpreter start.
 * <p>
 * This metric reports data from <a href="https://docs.python.org/3/library/gc.html#gc.get_stats">@code gc.stats() @endcode</a>.
 * <p>
 * counter
 */
static constexpr const char *kMetricCpythonGcUncollectableObjects
 = "cpython.gc.uncollectable_objects";
static constexpr const char *descrMetricCpythonGcUncollectableObjects
 = "The total number of objects which were found to be uncollectable inside a generation since interpreter start.";
static constexpr const char *unitMetricCpythonGcUncollectableObjects
 = "{object}";

static inline nostd::unique_ptr<metrics::Counter<uint64_t>
>
CreateSyncInt64MetricCpythonGcUncollectableObjects
(metrics::Meter *meter)
{
  return meter->CreateUInt64Counter
(
    kMetricCpythonGcUncollectableObjects
,
    descrMetricCpythonGcUncollectableObjects
,
    unitMetricCpythonGcUncollectableObjects
);
}

static inline nostd::unique_ptr<metrics::Counter<double>
>
CreateSyncDoubleMetricCpythonGcUncollectableObjects
(metrics::Meter *meter)
{
  return meter->CreateDoubleCounter
(
    kMetricCpythonGcUncollectableObjects
,
    descrMetricCpythonGcUncollectableObjects
,
    unitMetricCpythonGcUncollectableObjects
);
}

static inline nostd::shared_ptr<metrics::ObservableInstrument
>
CreateAsyncInt64MetricCpythonGcUncollectableObjects
(metrics::Meter *meter)
{
  return meter->CreateInt64ObservableCounter
(
    kMetricCpythonGcUncollectableObjects
,
    descrMetricCpythonGcUncollectableObjects
,
    unitMetricCpythonGcUncollectableObjects
);
}

static inline nostd::shared_ptr<metrics::ObservableInstrument
>
CreateAsyncDoubleMetricCpythonGcUncollectableObjects
(metrics::Meter *meter)
{
  return meter->CreateDoubleObservableCounter
(
    kMetricCpythonGcUncollectableObjects
,
    descrMetricCpythonGcUncollectableObjects
,
    unitMetricCpythonGcUncollectableObjects
);
}



}
}
}
