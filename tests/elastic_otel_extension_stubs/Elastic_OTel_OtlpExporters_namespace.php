<?php

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

/** @noinspection PhpUnusedParameterInspection */

declare(strict_types=1);

namespace Elastic\OTel\OtlpExporters;

use OpenTelemetry\SDK\Logs\ReadableLogRecord;
use OpenTelemetry\SDK\Trace\SpanDataInterface;
use OpenTelemetry\SDK\Metrics\Data\Metric;

/**
 * This function is implemented by the extension
 *
 * @param iterable<SpanDataInterface> $batch
 *
 * @see \OpenTelemetry\SDK\Trace\SpanExporterInterface::export
 */
function convert_spans(iterable $batch): string
{
    return "";
}

/**
 * This function is implemented by the extension
 *
 * @param iterable<ReadableLogRecord> $batch
 *
 * @see \OpenTelemetry\SDK\Logs\LogRecordExporterInterface::export
 */
function convert_logs(iterable $batch): string
{
    return "";
}

/**
 * This function is implemented by the extension
 *
 * @param iterable<int, Metric> $batch
 *
 * @see \OpenTelemetry\SDK\Metrics\MetricExporterInterface::export
 */
function convert_metrics(iterable $batch): string
{
    return "";
}
