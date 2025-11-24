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

namespace ElasticOTelTests\UnitTests;

use ElasticOTelTests\Util\FileUtil;
use ElasticOTelTests\Util\RepoRootDir;
use ElasticOTelTests\Util\TestCaseBase;
use ElasticOTelTools\build\ComposerUtil;
use ElasticOTelTools\build\GenerateComposerFiles;
use ElasticOTelTools\build\InstallPhpDeps;
use ElasticOTelTools\build\PhpDepsGroup;
use ElasticOTelTools\ToolsUtil;

final class PhpDepsUnitTest extends TestCaseBase
{
    public function testExtractPhpVersionPartFromLockFileName(): void
    {
        $test = function (string $fileName, PhpDepsGroup $depsGroup, ?string $expectedPhpVersionNoDot): void {
            $actualPhpVersionNoDot = GenerateComposerFiles::extractPhpVersionPartFromLockFileName($fileName, $depsGroup);
            self::assertSame($expectedPhpVersionNoDot, $actualPhpVersionNoDot);
        };

        $test('dev_81.lock', PhpDepsGroup::dev, '81');
        $test('prod_82.lock', PhpDepsGroup::prod, '82');
        $test('dev_for_prod_static_check_83.lock', PhpDepsGroup::dev_for_prod_static_check, '83');

        $test('dev_for_prod_static_check_83.lock', PhpDepsGroup::dev, null);
        $test('dev_83.lock', PhpDepsGroup::dev_for_prod_static_check, null);
        $test('prod_84.lock', PhpDepsGroup::dev, null);
        $test('base_81.lock', PhpDepsGroup::prod, null);
    }

    public function testVerifyRootJsonLock(): void
    {
        InstallPhpDeps::verifyBaseComposerJson(RepoRootDir::adaptRelativeUnixStylePath(ComposerUtil::JSON_FILE_NAME));

        // TODO: Sergey Kleyman: Implement: PhpDepsUnitTest::testVerifyGeneratedJsonLock
    }

    public function testVerifyGeneratedJsonLock(): void
    {
        $repoRootDir = ToolsUtil::getCurrentDirectory();
        $repoRootComposerJsonPath = ToolsUtil::partsToPath($repoRootDir, ComposerUtil::JSON_FILE_NAME);
        $generatedBaseComposerJsonPath = GenerateComposerFiles::buildFullPath($repoRootDir, GenerateComposerFiles::buildJsonFileName(GenerateComposerFiles::BASE_FILE_NAME_NO_EXT));
        self::assertFileEquals($repoRootComposerJsonPath, $generatedBaseComposerJsonPath);

        // TODO: Sergey Kleyman: Implement: PhpDepsUnitTest::testVerifyGeneratedJsonLock
    }

    public function testVerifyVendor(): void
    {
        // TODO: Sergey Kleyman: Implement: PhpDepsUnitTest::testVerifyVendor
    }

    public function testVerifyVendorProd(): void
    {
        // TODO: Sergey Kleyman: Implement: PhpDepsUnitTest::testVerifyVendorProd
    }
}
