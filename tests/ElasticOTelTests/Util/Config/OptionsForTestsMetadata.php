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

use Elastic\OTel\Log\LogLevel;
use ElasticOTelTests\ComponentTests\Util\PhpSerializationUtil;
use ElasticOTelTests\ComponentTests\Util\TestInfraDataPerProcess;
use ElasticOTelTests\ComponentTests\Util\TestInfraDataPerRequest;

/**
 * Code in this file is part of implementation internals, and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class OptionsForTestsMetadata
{
    use OptionsMetadataTrait;

    /**
     * Constructor is hidden
     */
    private function __construct()
    {
        $parseTestInfraDataPerProcess = function (string $rawValue): TestInfraDataPerProcess {
            return PhpSerializationUtil::unserializeFromStringAssertType($rawValue, TestInfraDataPerProcess::class);
        };
        $parseTestInfraDataPerRequest = function (string $rawValue): TestInfraDataPerRequest {
            return PhpSerializationUtil::unserializeFromStringAssertType($rawValue, TestInfraDataPerRequest::class);
        };

        /** @var array{OptionForTestsName, OptionMetadata<mixed>}[] $optNameMetaPairs */
        $optNameMetaPairs = [
            [OptionForTestsName::app_code_host_kind, new NullableAppCodeHostKindOptionMetadata()],
            [OptionForTestsName::app_code_php_exe, new NullableStringOptionMetadata()],
            [OptionForTestsName::app_code_bootstrap_php_part_file, new NullableStringOptionMetadata()],
            [OptionForTestsName::app_code_ext_binary, new NullableStringOptionMetadata()],

            [OptionForTestsName::data_per_process, new NullableCustomOptionMetadata($parseTestInfraDataPerProcess)],
            [OptionForTestsName::data_per_request, new NullableCustomOptionMetadata($parseTestInfraDataPerRequest)],

            [OptionForTestsName::env_vars_to_pass_through, new NullableWildcardListOptionMetadata()],

            [OptionForTestsName::escalated_reruns_max_count, new IntOptionMetadata(minValidValue: 0, maxValidValue: null, defaultValue: 10)],
            [OptionForTestsName::escalated_reruns_prod_code_log_level_option_name, new NullableStringOptionMetadata()],

            [OptionForTestsName::group, new NullableTestGroupNameOptionMetadata()],

            [OptionForTestsName::log_level, new LogLevelOptionMetadata(LogLevel::info)],
            [OptionForTestsName::logs_directory, new NullableStringOptionMetadata()],

            [OptionForTestsName::mysql_host, new NullableStringOptionMetadata()],
            [OptionForTestsName::mysql_port, new NullableIntOptionMetadata(1, 65535)],
            [OptionForTestsName::mysql_user, new NullableStringOptionMetadata()],
            [OptionForTestsName::mysql_password, new NullableStringOptionMetadata()],
            [OptionForTestsName::mysql_db, new NullableStringOptionMetadata()],
        ];
        $this->optionsNameValueMap = self::convertPairsToMap($optNameMetaPairs, OptionForTestsName::cases());
    }
}
