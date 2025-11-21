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

declare(strict_types=1);

namespace ElasticOTelTests\Util\Config;

use ElasticOTelTests\Util\Duration;
use ElasticOTelTests\Util\DurationUnit;

/**
 * Code in this file is part of implementation internals, and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class OptionsForProdMetadata
{
    use OptionsMetadataTrait;

    /**
     * Constructor is hidden
     */
    private function __construct()
    {
        $inferredSpansSamplingInterval = new DurationOptionMetadata(
            minValidValue: new Duration(1, DurationUnit::ms),
            maxValidValue: null,
            defaultUnit:   DurationUnit::ms,
            defaultValue:  new Duration(50, DurationUnit::ms),
        );
        $inferredSpansMinDuration = new DurationOptionMetadata(
            minValidValue: new Duration(0, DurationUnit::ms),
            maxValidValue: null,
            defaultUnit:   DurationUnit::ms,
            defaultValue:  new Duration(0, DurationUnit::ms),
        );

        /** @var array{OptionForProdName, OptionMetadata<mixed>}[] $optNameMetaPairs */
        $optNameMetaPairs = [
            [OptionForProdName::autoload_enabled, new BoolOptionMetadata(false)],
            [OptionForProdName::bootstrap_php_part_file, new NullableStringOptionMetadata()],
            [OptionForProdName::disabled_instrumentations, new NullableWildcardListOptionMetadata()],
            [OptionForProdName::enabled, new BoolOptionMetadata(true)],
            [OptionForProdName::exporter_otlp_endpoint, new NullableStringOptionMetadata()],
            [OptionForProdName::inferred_spans_enabled, new BoolOptionMetadata(false)],
            [OptionForProdName::inferred_spans_min_duration, $inferredSpansMinDuration],
            [OptionForProdName::inferred_spans_reduction_enabled, new BoolOptionMetadata(true)],
            [OptionForProdName::inferred_spans_sampling_interval, $inferredSpansSamplingInterval],
            [OptionForProdName::inferred_spans_stacktrace_enabled, new BoolOptionMetadata(true)],
            [OptionForProdName::log_file, new NullableStringOptionMetadata()],
            [OptionForProdName::log_level_file, new LogLevelOptionMetadata(OptionsForProdDefaultValues::LOG_LEVEL_FILE)],
            [OptionForProdName::log_level_stderr, new LogLevelOptionMetadata(OptionsForProdDefaultValues::LOG_LEVEL_STDERR)],
            [OptionForProdName::log_level_syslog, new LogLevelOptionMetadata(OptionsForProdDefaultValues::LOG_LEVEL_SYSLOG)],
            [OptionForProdName::resource_attributes, new NullableStringOptionMetadata()],
            [OptionForProdName::transaction_span_enabled, new BoolOptionMetadata(OptionsForProdDefaultValues::TRANSACTION_SPAN_ENABLED)],
            [OptionForProdName::transaction_span_enabled_cli, new BoolOptionMetadata(OptionsForProdDefaultValues::TRANSACTION_SPAN_ENABLED_CLI)],
        ];
        $this->optionsNameValueMap = self::convertPairsToMap($optNameMetaPairs, OptionForProdName::cases());
    }
}
