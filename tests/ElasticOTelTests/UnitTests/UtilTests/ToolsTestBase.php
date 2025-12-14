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

use ElasticOTelTests\Util\ClassNameUtil;
use ElasticOTelTests\Util\DebugContext;
use ElasticOTelTests\Util\FileUtil;
use ElasticOTelTests\Util\OsUtil;
use ElasticOTelTests\Util\RepoRootDir;
use ElasticOTelTests\Util\TestCaseBase;
use ElasticOTelTools\Build\BuildToolsUtil;
use SplFileInfo;

class ToolsTestBase extends TestCaseBase
{
    protected static function execShellCommand(string ...$cmdParts): void
    {
        // Append  1>&2 to redirect stdout to stderr because PHPUnit fails a test that writes anything to stdout
        BuildToolsUtil::execShellCommand(array_reduce($cmdParts, fn($carry, $part) => "$carry \"$part\"", '') . ' 1>&2');
    }

    protected static function execTool(string $toolPath, string ...$args): void
    {
        self::execShellCommand($toolPath, ...$args);
    }

    protected static function copyFromCurrentToTempRepoCopy(string $tempRepoCopyRootDir): void
    {
        self::assertFalse(OsUtil::isWindows());
        self::execShellCommand(RepoRootDir::adaptRelativeUnixStylePath('tools/copy_repo_exclude_generated.sh'), RepoRootDir::getFullPath(), $tempRepoCopyRootDir);
    }

    /**
     * @param class-string $fqTestClassName
     * @param callable(): void $code
     *
     * @noinspection PhpDocMissingThrowsInspection
     */
    protected static function runCodeOnTempRepoCopy(string $fqTestClassName, callable $code): void
    {
        $tempDir = BuildToolsUtil::createTempDirectoryGenerateUniqueName(ClassNameUtil::fqToShort($fqTestClassName) . '_');
        BuildToolsUtil::runCodeAndCleanUp(
            code: function () use ($code, $tempDir): void {
                self::copyFromCurrentToTempRepoCopy($tempDir);
                BuildToolsUtil::changeCurrentDirectoryRunCodeAndRestore($tempDir, $code);
            },
            cleanUp: fn() => BuildToolsUtil::deleteTempDirectory($tempDir),
        );
    }

    /**
     * @param callable(?SplFileInfo $lhsItemPath, ?SplFileInfo $rhsItemPath): void $compareItems
     */
    protected static function compareDirectoriesContents(string $lhsDir, string $rhsDir, callable $compareItems): void
    {
        DebugContext::getCurrentScope(/* out */ $dbgCtx);

        $dbgCtx->pushSubScope();
        foreach (FileUtil::iterateOverDirectoryContentsRecursively($lhsDir) as $lhsItem) {
            $lhsItemPath = $lhsItem->getRealPath();
            $dbgCtx->resetTopSubScope(compact('lhsItemPath'));
            $relPath = FileUtil::relativePathFromTo($lhsDir, $lhsItemPath);
            $dbgCtx->add(compact('relPath'));
            $rhsItemPath = FileUtil::partsToPath($rhsDir, $relPath);
            $dbgCtx->add(compact('rhsItemPath'));
            $compareItems($lhsItem, file_exists($rhsItemPath) ? new SplFileInfo($rhsItemPath) : null);
        }
        $dbgCtx->popSubScope();

        $dbgCtx->pushSubScope();
        foreach (FileUtil::iterateOverDirectoryContentsRecursively($rhsDir) as $rhsItem) {
            $rhsItemPath = $rhsItem->getRealPath();
            $dbgCtx->resetTopSubScope(compact('rhsItemPath'));
            $relPath = FileUtil::relativePathFromTo($rhsDir, $rhsItemPath);
            $dbgCtx->add(compact('relPath'));
            if (file_exists(FileUtil::partsToPath($lhsDir, $relPath))) {
                continue;
            }
            /** @noinspection PsalmAdvanceCallableParamsInspection */
            $compareItems(null, $rhsItem);
        }
        $dbgCtx->popSubScope();
    }
}
