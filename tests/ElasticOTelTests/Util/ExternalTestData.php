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

namespace ElasticOTelTests\Util;

use Elastic\OTel\Util\StaticClassTrait;
use Elastic\OTel\Util\TextUtil;
use ElasticOTelTests\TestsRootDir;

final class ExternalTestData
{
    use StaticClassTrait;

    /**
     * @param string[] $relativePathToSubDir
     * @param string   $relativePathToFile
     *
     * @return string
     */
    private static function fullPathForFileInSubDir(array $relativePathToSubDir, string $relativePathToFile): string
    {
        $fullPathAsList = [TestsRootDir::getFullPath()];
        ArrayUtilForTests::append($relativePathToSubDir, /* in,out */ $fullPathAsList);
        ArrayUtilForTests::append([$relativePathToFile], /* in,out */ $fullPathAsList);
        return FileUtil::normalizePath(FileUtil::listToPath($fullPathAsList));
    }

    public static function readJsonSpecsFile(string $relativePathToFile): mixed
    {
        $filePath = self::fullPathForFileInSubDir(['external_test_data', 'APM_Agents_shared', 'json-specs'], $relativePathToFile);

        $fileContent = '';
        FileUtil::readLines(
            $filePath,
            function (string $line) use (&$fileContent): void {
                if (TextUtil::isPrefixOf('//', trim($line))) {
                    return;
                }
                $fileContent .= $line;
            }
        );

        return JsonUtil::decode($fileContent, asAssocArray: true);
    }
}
