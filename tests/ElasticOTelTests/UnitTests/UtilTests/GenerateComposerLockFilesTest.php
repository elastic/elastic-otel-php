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

namespace ElasticOTelTests\UnitTests\UtilTests;

use ElasticOTelTests\Util\DebugContext;
use ElasticOTelTests\Util\FileUtil;
use ElasticOTelTests\Util\OsUtil;
use ElasticOTelTests\Util\RepoRootDir;
use ElasticOTelTools\Build\BuildToolsUtil;
use ElasticOTelTools\Build\ComposerUtil;
use ElasticOTelTools\Build\PhpDepsEnvKind;
use SplFileInfo;

class GenerateComposerLockFilesTest extends ToolsTestBase
{
    public function testCurrentFilesAreInSyncWithRootComposerJson(): void
    {
        self::assertFileEquals(
            RepoRootDir::adaptRelativeUnixStylePath(ComposerUtil::COMPOSER_JSON_FILE_NAME),
            ComposerUtil::buildToGeneratedFileFullPath(RepoRootDir::getFullPath(), ComposerUtil::buildGeneratedComposerJsonFileName(PhpDepsEnvKind::dev))
        );
    }

    public function testExecutionProducesFilesSimilarToCurrent(): void
    {
        if (OsUtil::isWindows()) {
            if (self::dummyAssert()) {
                return;
            }
        }

        self::runCodeOnTempRepoCopy(__CLASS__, self::implTestExecutionProducesFilesSimilarToCurrent(...));
    }

    private static function implTestExecutionProducesFilesSimilarToCurrent(): void
    {
        DebugContext::getCurrentScope(/* out */ $dbgCtx);

        $tempRepoCopyRootDir = BuildToolsUtil::getCurrentDirectory();
        $dbgCtx->add(compact('tempRepoCopyRootDir'));

        $newGeneratedFilesDir = FileUtil::partsToPath($tempRepoCopyRootDir, ComposerUtil::GENERATED_FILES_DIR_NAME);
        BuildToolsUtil::deleteDirectory($newGeneratedFilesDir);
        self::assertDirectoryDoesNotExist($newGeneratedFilesDir);

        self::execTool('tools/build/generate_composer_lock_files.sh');

        self::assertDirectoryExists($newGeneratedFilesDir);
        self::compareDirectoriesContents(
            RepoRootDir::adaptRelativeUnixStylePath(ComposerUtil::GENERATED_FILES_DIR_NAME),
            $newGeneratedFilesDir,
            function (?SplFileInfo $lhsItemPath, ?SplFileInfo $rhsItemPath): void {
                self::assertNotNull($lhsItemPath);
                self::assertNotNull($rhsItemPath);
                if ($lhsItemPath->isFile() && $lhsItemPath->getExtension() === 'json') {
                    self::assertFileEquals($lhsItemPath->getRealPath(), $rhsItemPath->getRealPath());
                }
            }
        );
    }
}
