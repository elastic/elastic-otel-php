<?php

/*
 * Licensed to Elasticsearch B.V. under one or more contributor
 * license agreements. See the NOTICE file distributed with
 * this work for additional information regarding copyright
 * ownership. Elasticsearch B.V. licenses this file to you under
 * the Apache License, Version 2.0 (the "License"); you may
 * not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
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

use ElasticOTelTests\Util\AssertEx;
use ElasticOTelTests\Util\DebugContext;
use ElasticOTelTests\Util\ElasticOTelProjectProperties;
use ElasticOTelTests\Util\IterableUtil;
use ElasticOTelTests\Util\Log\LoggableInterface;
use ElasticOTelTests\Util\Log\LoggableTrait;
use ElasticOTelTests\Util\OsUtil;
use ElasticOTelTests\Util\RepoRootDir;
use ElasticOTelTests\Util\TestCaseBase;

/**
 * @group smoke
 * @group does_not_require_external_services
 */
final class ComponentTestsGenerateUnpackMatrixTest extends TestCaseBase implements LoggableInterface
{
    use LoggableTrait;

    private const PACKAGE_TYPE_APK = 'apk';

    // TODO: Sergey Kleyman: UNCOMMENT
    // private const PACKAGE_TYPE = OptionForTestsName::ENV_VAR_NAME_PREFIX . 'PHP_VERSION';
    //
    // private const PHP_VERSION_ENV_VAR_NAME = OptionForTestsName::ENV_VAR_NAME_PREFIX . 'PHP_VERSION';
    // private const PACKAGE_TYPE_ENV_VAR_NAME = OptionForTestsName::ENV_VAR_NAME_PREFIX . 'PACKAGE_TYPE';
    //
    // private const APP_CODE_HOST_SHORT_TO_LONG_NAME
    //     = [
    //         'cli'  => 'CLI_script',
    //         'http' => 'Builtin_HTTP_server',
    //     ];
    //
    // private const TESTS_GROUP_SHORT_TO_LONG_NAME
    //     = [
    //         'no_ext_svc'   => 'does_not_require_external_services',
    //         'with_ext_svc' => 'requires_external_services',
    //     ];
    /**
     * @return string[]
     */
    private static function execCommand(string $command): array
    {
        DebugContext::getCurrentScope(/* out */ $dbgCtx);
        $outputLastLine = exec($command, /* out */ $outputLinesAsArray, /* out */ $exitCode);
        $dbgCtx->add(compact('exitCode', 'outputLinesAsArray', 'outputLastLine'));
        self::assertSame(0, $exitCode);
        self::assertIsString($outputLastLine);
        return $outputLinesAsArray;
    }

    // TODO: Sergey Kleyman: UNCOMMENT
    // private static function convertAppHostKindShortToLongName(string $shortName): string
    // {
    //     if (ArrayUtil::getValueIfKeyExists($shortName, self::APP_CODE_HOST_SHORT_TO_LONG_NAME, /* out */ $longName)) {
    //         return $longName;
    //     }
    //
    //     self::fail("Unknown test app code host kind short name: $shortName");
    // }
    //
    // private static function convertTestGroupShortToLongName(string $shortName): string
    // {
    //     if (ArrayUtil::getValueIfKeyExists($shortName, self::TESTS_GROUP_SHORT_TO_LONG_NAME, /* out */ $longName)) {
    //         return $longName;
    //     }
    //
    //     self::fail("Unknown test group short name: $shortName");
    // }

    // TODO: Sergey Kleyman: UNCOMMENT
    // /**
    //  * @param array<string, mixed> $expectedEnvVars
    //  */
    // private function execUnpackAndAssert(string $matrixRow, array $expectedEnvVars): void
    // {
    //     DebugContext::getCurrentScope(/* out */ $dbgCtx);
    //     $cmd = $this->unpackAndPrintEnvVarsScriptFullPath . ' ' . $matrixRow;
    //     $actualEnvVarNameValueLines = self::execCommand($cmd);
    //     self::assertNotEmpty($actualEnvVarNameValueLines);
    //     $actualEnvVars = [];
    //     foreach ($actualEnvVarNameValueLines as $actualEnvVarNameValueLine) {
    //         $actualEnvVarNameValue = explode('=', $actualEnvVarNameValueLine, limit: 2);
    //         $actualEnvVars[$actualEnvVarNameValue[0]] = $actualEnvVarNameValue[1];
    //     }
    //     $dbgCtx->add(compact('actualEnvVarNameValueLines', 'actualEnvVars'));
    //
    //     AssertEx::mapIsSubsetOf($expectedEnvVars, $actualEnvVars);
    // }

    /**
     * @param string $rowSoFar
     *
     * @return iterable<string>
     */
    private static function appendTestAppCodeHostKindAndGroup(string $rowSoFar): iterable
    {
        foreach (ElasticOTelProjectProperties::singletonInstance()->testAppCodeHostKindsShortNames as $testAppCodeHostKindShortName) {
            foreach (ElasticOTelProjectProperties::singletonInstance()->testGroupsShortNames as $testGroupShortName) {
                yield "$rowSoFar,$testAppCodeHostKindShortName,$testGroupShortName";
            }
        }
    }

    /**
     * @return iterable<string>
     */
    private static function generateRowsToTestIncreasedLogLevel(): iterable
    {
        AssertEx::countAtLeast(2, ElasticOTelProjectProperties::singletonInstance()->testAppCodeHostKindsShortNames);
        AssertEx::countAtLeast(2, ElasticOTelProjectProperties::singletonInstance()->testGroupsShortNames);

        $phpVersion = ElasticOTelProjectProperties::singletonInstance()->getLowestSupportedPhpVersion();
        $packageType = ElasticOTelProjectProperties::singletonInstance()->testAllPhpVersionsWithPackageType;
        $testAppCodeHostKindShortName = ElasticOTelProjectProperties::singletonInstance()->testAppCodeHostKindsShortNames[0];
        $testGroupShortName = ElasticOTelProjectProperties::singletonInstance()->testGroupsShortNames[0];
        yield "{$phpVersion->asDotSeparated()},$packageType,$testAppCodeHostKindShortName,$testGroupShortName,prod_log_level_syslog=TRACE";

        $phpVersion = ElasticOTelProjectProperties::singletonInstance()->getHighestSupportedPhpVersion();
        $packageType = self::PACKAGE_TYPE_APK;
        $testAppCodeHostKindShortName = ElasticOTelProjectProperties::singletonInstance()->testAppCodeHostKindsShortNames[1];
        $testGroupShortName = ElasticOTelProjectProperties::singletonInstance()->testGroupsShortNames[1];
        yield "{$phpVersion->asDotSeparated()},$packageType,$testAppCodeHostKindShortName,$testGroupShortName,prod_log_level_syslog=DEBUG";
    }

    /**
     * @return iterable<string>
     */
    private static function generateRowsToTestHighestSupportedPhpVersionWithOtherPackageTypes(): iterable
    {
        $packageTypeToExclude = ElasticOTelProjectProperties::singletonInstance()->testAllPhpVersionsWithPackageType;
        $phpVersion = ElasticOTelProjectProperties::singletonInstance()->getHighestSupportedPhpVersion();

        foreach (ElasticOTelProjectProperties::singletonInstance()->supportedPackageTypes as $packageType) {
            if ($packageType === $packageTypeToExclude) {
                continue;
            }
            yield from self::appendTestAppCodeHostKindAndGroup("{$phpVersion->asDotSeparated()},$packageType");
        }
    }

    /**
     * @return iterable<string>
     */
    private static function generateRowsToTestAllPhpVersionsWithOnePackageType(): iterable
    {
        $packageType = ElasticOTelProjectProperties::singletonInstance()->testAllPhpVersionsWithPackageType;

        foreach (ElasticOTelProjectProperties::singletonInstance()->supportedPhpVersions as $phpVersion) {
            yield from self::appendTestAppCodeHostKindAndGroup("{$phpVersion->asDotSeparated()},$packageType");
        }
    }

    /**
     * @return iterable<string>
     */
    private static function generateExpectedMatrix(): iterable
    {
        yield from self::generateRowsToTestAllPhpVersionsWithOnePackageType();
        yield from self::generateRowsToTestHighestSupportedPhpVersionWithOtherPackageTypes();

        yield from self::generateRowsToTestIncreasedLogLevel();
    }

    /**
     * @return string[]
     */
    private static function generateMatrix(): array
    {
        $generateMatrixScriptFullPath = RepoRootDir::adaptRelativeUnixStylePath('tools/test/generate_component_tests_matrix.sh');
        self::assertFileExists($generateMatrixScriptFullPath);

        return self::execCommand($generateMatrixScriptFullPath);
    }

    public function testGenerateMatrixAsExpected(): void
    {
        if (OsUtil::isWindows()) {
            self::dummyAssert();
            return;
        }

        $expectedMatrixRows = IterableUtil::toList(self::generateExpectedMatrix());
        $actualMatrixRows = self::generateMatrix();
        self::assertSame($expectedMatrixRows, $actualMatrixRows);
    }
    // TODO: Sergey Kleyman: UNCOMMENT
    // public function testGenerateAndUnpackAreInSync(): void
    // {
    //     if (OsUtil::isWindows()) {
    //         self::dummyAssert();
    //         return;
    //     }
    //
    //     $actualMatrixRows = self::generateMatrix();
    //
    //     $this->unpackAndPrintEnvVarsScriptFullPath = FileUtil::normalizePath(__DIR__ . DIRECTORY_SEPARATOR . 'unpack_component_tests_matrix_row_and_print_env_vars.sh');
    //     self::assertFileExists($this->unpackAndPrintEnvVarsScriptFullPath);
    //
    //     $this->matrixRowToExpectedEnvVars = self::unpackToEnvVars($actualMatrixRows);
    //
    //     foreach ($this->matrixRowToExpectedEnvVars as $matrixRow => $expectedEnvVars) {
    //         $this->execUnpackAndAssert($matrixRow, $expectedEnvVars);
    //     }
    // }
}
