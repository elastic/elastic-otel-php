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

namespace ElasticOTelTools\build;

use ElasticOTelTools\ToolsUtil;
use ElasticOTelTools\ToolsLog;

require __DIR__ . '/../bootstrap_tools.php';

if (ComposerUtil::shouldAllowDirectCommand()) {
    exit();
}

/** @var list<string> $argv */
global $argv;

/**
 * @param array<string, mixed> $context
 */
$logError = function (int $line, string $msg, array $context = []): void {
    /** @var array<string, mixed> $context */
    ToolsLog::error(__FILE__, $line, /* fqMethod */ '', $msg, $context);
};

if (count($argv) !== 2) {
    if (count($argv) < 2) {
        $logError(__LINE__, 'Missing command line argument: <composer command>', compact('argv'));
        exit(ToolsUtil::FAILURE_EXIT_CODE);
    }
    $logError(__LINE__, 'Provided more command line arguments than expected', ['expected number of arguments' => 1] + compact('argv'));
    exit(ToolsUtil::FAILURE_EXIT_CODE);
}

$usedComposerCommand = $argv[1];
const COMMAND_TO_USE_INSTEAD_OF_COMPOSER_INSTALL = './tools/build/install_PHP_deps_in_dev_env.sh';
$cmdToUseInstead = match ($usedComposerCommand) {
    'install' => COMMAND_TO_USE_INSTEAD_OF_COMPOSER_INSTALL,
    'update' => './tools/build/generate_composer_lock_files.sh && ' . COMMAND_TO_USE_INSTEAD_OF_COMPOSER_INSTALL,
    default => null,
};

if ($cmdToUseInstead === null) {
    $logError(__LINE__, "Unexpected composer command: $usedComposerCommand");
} else {
    ToolsLog::writeLineRaw("Direct `composer $usedComposerCommand' is not allowed");
    ToolsLog::writeLineRaw('Instead use');
    ToolsLog::writeLineRaw("\t" . $cmdToUseInstead);
}

exit(ToolsUtil::FAILURE_EXIT_CODE);
