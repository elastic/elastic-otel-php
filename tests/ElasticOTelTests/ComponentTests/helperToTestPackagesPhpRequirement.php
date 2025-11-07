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

namespace ElasticOTelTests\ComponentTests;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

// This script does NOT require classes from vendor on purpose
// because the script is going to load the same files from a different location

/** @var list<string> $argv */
global $argv;
if (count($argv) < 2) {
    echo 'Missing expected command line argument; ' . json_encode(compact('argv')) . PHP_EOL;
    exit(1);
}
$prodVendorDir = $argv[1];

foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($prodVendorDir)) as $fileInfo) {
    /** @var SplFileInfo $fileInfo */
    if ($fileInfo->isFile() && ($fileInfo->getExtension() === 'php')) {
        $filePath = $fileInfo->getRealPath();

        $pathParts = explode(DIRECTORY_SEPARATOR, $filePath);
        $containsHiddenDirInPath = false;
        foreach ($pathParts as $pathPart) {
            if (str_starts_with($pathPart, '.')) {
                $containsHiddenDirInPath = true;
                break;
            }
        }
        if ($containsHiddenDirInPath) {
            continue;
        }

        /** @noinspection PhpComposerExtensionStubsInspection */
        $retVal = opcache_compile_file($filePath);
        if (!$retVal) {
            echo 'opcache_compile_file() returned false for ' . $filePath . PHP_EOL;
            exit(1);
        }
    }
}
