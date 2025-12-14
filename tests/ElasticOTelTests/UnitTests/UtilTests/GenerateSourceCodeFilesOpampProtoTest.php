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

use ElasticOTelTests\Util\ArrayUtilForTests;
use ElasticOTelTests\Util\DebugContext;
use ElasticOTelTests\Util\FileUtil;
use ElasticOTelTests\Util\OsUtil;
use ElasticOTelTests\Util\RepoRootDir;
use ElasticOTelTools\Build\BuildToolsUtil;
use SplFileInfo;

class GenerateSourceCodeFilesOpampProtoTest extends ToolsTestBase
{
    /**
     * @see elastic_otel_php_tests_generated_source_code_dir_rel_path in tool/shared.sh
     */
    private const GENERATED_SOURCE_CODE_DIR_REL_PATH = 'tests/GENERATED_source_code';

    /**
     * @see GENERATED_SOURCE_CODE_FILES_PHP_NAMESPACE in tools/test/component/generate_source_code_files_for_OpAMP_spec_protobuf.sh
     */
    private const GENERATED_SOURCE_CODE_FILES_PHP_NAMESPACE = "ElasticOTelTests\\Generated\\OpampProto";

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

        $namespaceParts = explode("\\", self::GENERATED_SOURCE_CODE_FILES_PHP_NAMESPACE);
        $lastNamespacePart = ArrayUtilForTests::getLastValue($namespaceParts);
        $generatedFilesDirRelPath = FileUtil::partsToPath(self::GENERATED_SOURCE_CODE_DIR_REL_PATH, $lastNamespacePart);

        $newGeneratedFilesDir = FileUtil::partsToPath($tempRepoCopyRootDir, $generatedFilesDirRelPath);
        BuildToolsUtil::deleteDirectory($newGeneratedFilesDir);
        self::assertDirectoryDoesNotExist($newGeneratedFilesDir);

        self::execTool('tools/test/component/generate_source_code_files_for_OpAMP_spec_protobuf.sh');

        self::assertDirectoryExists($newGeneratedFilesDir);
        self::compareDirectoriesContents(
            RepoRootDir::adaptRelativeUnixStylePath($generatedFilesDirRelPath),
            $newGeneratedFilesDir,
            function (?SplFileInfo $lhsItemPath, ?SplFileInfo $rhsItemPath): void {
                self::assertNotNull($lhsItemPath);
                self::assertNotNull($rhsItemPath);
                self::assertFileEquals($lhsItemPath->getRealPath(), $rhsItemPath->getRealPath());
            }
        );
    }
}
