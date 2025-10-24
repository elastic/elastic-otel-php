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

use ElasticOTelTests\Util\EnumUtilForTestsTrait;

/**
 * Code in this file is part of implementation internals, and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
enum OptionForTestsName
{
    use EnumUtilForTestsTrait;

    case app_code_bootstrap_php_part_file;
    case app_code_ext_binary;
    case app_code_host_kind;
    case app_code_php_exe;

    case data_per_process;
    case data_per_request;

    case env_vars_to_pass_through;

    case escalated_reruns_prod_code_log_level_option_name;
    case escalated_reruns_max_count;

    case group;

    case log_level;
    case logs_directory;

    case mysql_host;
    case mysql_port;
    case mysql_user;
    case mysql_password;
    case mysql_db;

    public const ENV_VAR_NAME_PREFIX = 'ELASTIC_OTEL_PHP_TESTS_';

    public function toEnvVarName(): string
    {
        return EnvVarsRawSnapshotSource::optionNameToEnvVarName(self::ENV_VAR_NAME_PREFIX, $this->name);
    }
}
