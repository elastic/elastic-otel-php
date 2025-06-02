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
namespace cicd
{

/**
 * The number of pipeline runs currently active in the system by state.
 * <p>
 * updowncounter
 */
static constexpr const char *kMetricCicdPipelineRunActive
 = "cicd.pipeline.run.active";
static constexpr const char *descrMetricCicdPipelineRunActive
 = "The number of pipeline runs currently active in the system by state.";
static constexpr const char *unitMetricCicdPipelineRunActive
 = "{run}";

static inline nostd::unique_ptr<metrics::UpDownCounter<int64_t>
>
CreateSyncInt64MetricCicdPipelineRunActive
(metrics::Meter *meter)
{
  return meter->CreateInt64UpDownCounter
(
    kMetricCicdPipelineRunActive
,
    descrMetricCicdPipelineRunActive
,
    unitMetricCicdPipelineRunActive
);
}

static inline nostd::unique_ptr<metrics::UpDownCounter<double>
>
CreateSyncDoubleMetricCicdPipelineRunActive
(metrics::Meter *meter)
{
  return meter->CreateDoubleUpDownCounter
(
    kMetricCicdPipelineRunActive
,
    descrMetricCicdPipelineRunActive
,
    unitMetricCicdPipelineRunActive
);
}

static inline nostd::shared_ptr<metrics::ObservableInstrument
>
CreateAsyncInt64MetricCicdPipelineRunActive
(metrics::Meter *meter)
{
  return meter->CreateInt64ObservableUpDownCounter
(
    kMetricCicdPipelineRunActive
,
    descrMetricCicdPipelineRunActive
,
    unitMetricCicdPipelineRunActive
);
}

static inline nostd::shared_ptr<metrics::ObservableInstrument
>
CreateAsyncDoubleMetricCicdPipelineRunActive
(metrics::Meter *meter)
{
  return meter->CreateDoubleObservableUpDownCounter
(
    kMetricCicdPipelineRunActive
,
    descrMetricCicdPipelineRunActive
,
    unitMetricCicdPipelineRunActive
);
}


/**
 * Duration of a pipeline run grouped by pipeline, state and result.
 * <p>
 * histogram
 */
static constexpr const char *kMetricCicdPipelineRunDuration
 = "cicd.pipeline.run.duration";
static constexpr const char *descrMetricCicdPipelineRunDuration
 = "Duration of a pipeline run grouped by pipeline, state and result.";
static constexpr const char *unitMetricCicdPipelineRunDuration
 = "s";

static inline nostd::unique_ptr<metrics::Histogram<uint64_t>
>
CreateSyncInt64MetricCicdPipelineRunDuration
(metrics::Meter *meter)
{
  return meter->CreateUInt64Histogram
(
    kMetricCicdPipelineRunDuration
,
    descrMetricCicdPipelineRunDuration
,
    unitMetricCicdPipelineRunDuration
);
}

static inline nostd::unique_ptr<metrics::Histogram<double>
>
CreateSyncDoubleMetricCicdPipelineRunDuration
(metrics::Meter *meter)
{
  return meter->CreateDoubleHistogram
(
    kMetricCicdPipelineRunDuration
,
    descrMetricCicdPipelineRunDuration
,
    unitMetricCicdPipelineRunDuration
);
}



/**
 * The number of errors encountered in pipeline runs (eg. compile, test failures).
 * <p>
 * There might be errors in a pipeline run that are non fatal (eg. they are suppressed) or in a parallel stage multiple stages could have a fatal error.
 * This means that this error count might not be the same as the count of metric @code cicd.pipeline.run.duration @endcode with run result @code failure @endcode.
 * <p>
 * counter
 */
static constexpr const char *kMetricCicdPipelineRunErrors
 = "cicd.pipeline.run.errors";
static constexpr const char *descrMetricCicdPipelineRunErrors
 = "The number of errors encountered in pipeline runs (eg. compile, test failures).";
static constexpr const char *unitMetricCicdPipelineRunErrors
 = "{error}";

static inline nostd::unique_ptr<metrics::Counter<uint64_t>
>
CreateSyncInt64MetricCicdPipelineRunErrors
(metrics::Meter *meter)
{
  return meter->CreateUInt64Counter
(
    kMetricCicdPipelineRunErrors
,
    descrMetricCicdPipelineRunErrors
,
    unitMetricCicdPipelineRunErrors
);
}

static inline nostd::unique_ptr<metrics::Counter<double>
>
CreateSyncDoubleMetricCicdPipelineRunErrors
(metrics::Meter *meter)
{
  return meter->CreateDoubleCounter
(
    kMetricCicdPipelineRunErrors
,
    descrMetricCicdPipelineRunErrors
,
    unitMetricCicdPipelineRunErrors
);
}

static inline nostd::shared_ptr<metrics::ObservableInstrument
>
CreateAsyncInt64MetricCicdPipelineRunErrors
(metrics::Meter *meter)
{
  return meter->CreateInt64ObservableCounter
(
    kMetricCicdPipelineRunErrors
,
    descrMetricCicdPipelineRunErrors
,
    unitMetricCicdPipelineRunErrors
);
}

static inline nostd::shared_ptr<metrics::ObservableInstrument
>
CreateAsyncDoubleMetricCicdPipelineRunErrors
(metrics::Meter *meter)
{
  return meter->CreateDoubleObservableCounter
(
    kMetricCicdPipelineRunErrors
,
    descrMetricCicdPipelineRunErrors
,
    unitMetricCicdPipelineRunErrors
);
}


/**
 * The number of errors in a component of the CICD system (eg. controller, scheduler, agent).
 * <p>
 * Errors in pipeline run execution are explicitly excluded. Ie a test failure is not counted in this metric.
 * <p>
 * counter
 */
static constexpr const char *kMetricCicdSystemErrors
 = "cicd.system.errors";
static constexpr const char *descrMetricCicdSystemErrors
 = "The number of errors in a component of the CICD system (eg. controller, scheduler, agent).";
static constexpr const char *unitMetricCicdSystemErrors
 = "{error}";

static inline nostd::unique_ptr<metrics::Counter<uint64_t>
>
CreateSyncInt64MetricCicdSystemErrors
(metrics::Meter *meter)
{
  return meter->CreateUInt64Counter
(
    kMetricCicdSystemErrors
,
    descrMetricCicdSystemErrors
,
    unitMetricCicdSystemErrors
);
}

static inline nostd::unique_ptr<metrics::Counter<double>
>
CreateSyncDoubleMetricCicdSystemErrors
(metrics::Meter *meter)
{
  return meter->CreateDoubleCounter
(
    kMetricCicdSystemErrors
,
    descrMetricCicdSystemErrors
,
    unitMetricCicdSystemErrors
);
}

static inline nostd::shared_ptr<metrics::ObservableInstrument
>
CreateAsyncInt64MetricCicdSystemErrors
(metrics::Meter *meter)
{
  return meter->CreateInt64ObservableCounter
(
    kMetricCicdSystemErrors
,
    descrMetricCicdSystemErrors
,
    unitMetricCicdSystemErrors
);
}

static inline nostd::shared_ptr<metrics::ObservableInstrument
>
CreateAsyncDoubleMetricCicdSystemErrors
(metrics::Meter *meter)
{
  return meter->CreateDoubleObservableCounter
(
    kMetricCicdSystemErrors
,
    descrMetricCicdSystemErrors
,
    unitMetricCicdSystemErrors
);
}


/**
 * The number of workers on the CICD system by state.
 * <p>
 * updowncounter
 */
static constexpr const char *kMetricCicdWorkerCount
 = "cicd.worker.count";
static constexpr const char *descrMetricCicdWorkerCount
 = "The number of workers on the CICD system by state.";
static constexpr const char *unitMetricCicdWorkerCount
 = "{count}";

static inline nostd::unique_ptr<metrics::UpDownCounter<int64_t>
>
CreateSyncInt64MetricCicdWorkerCount
(metrics::Meter *meter)
{
  return meter->CreateInt64UpDownCounter
(
    kMetricCicdWorkerCount
,
    descrMetricCicdWorkerCount
,
    unitMetricCicdWorkerCount
);
}

static inline nostd::unique_ptr<metrics::UpDownCounter<double>
>
CreateSyncDoubleMetricCicdWorkerCount
(metrics::Meter *meter)
{
  return meter->CreateDoubleUpDownCounter
(
    kMetricCicdWorkerCount
,
    descrMetricCicdWorkerCount
,
    unitMetricCicdWorkerCount
);
}

static inline nostd::shared_ptr<metrics::ObservableInstrument
>
CreateAsyncInt64MetricCicdWorkerCount
(metrics::Meter *meter)
{
  return meter->CreateInt64ObservableUpDownCounter
(
    kMetricCicdWorkerCount
,
    descrMetricCicdWorkerCount
,
    unitMetricCicdWorkerCount
);
}

static inline nostd::shared_ptr<metrics::ObservableInstrument
>
CreateAsyncDoubleMetricCicdWorkerCount
(metrics::Meter *meter)
{
  return meter->CreateDoubleObservableUpDownCounter
(
    kMetricCicdWorkerCount
,
    descrMetricCicdWorkerCount
,
    unitMetricCicdWorkerCount
);
}



}
}
}
