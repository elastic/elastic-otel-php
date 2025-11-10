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

use Composer\Semver\Semver;
use Elastic\OTel\PhpPartFacade;
use ElasticOTelTests\ComponentTests\Util\AppCodeHostParams;
use ElasticOTelTests\ComponentTests\Util\AppCodeTarget;
use ElasticOTelTests\ComponentTests\Util\ComponentTestCaseBase;
use ElasticOTelTests\ComponentTests\Util\EnvVarUtilForTests;
use ElasticOTelTests\ComponentTests\Util\OTelUtil;
use ElasticOTelTests\ComponentTests\Util\ProcessUtil;
use ElasticOTelTests\ComponentTests\Util\WaitForOTelSignalCounts;
use ElasticOTelTests\Util\AssertEx;
use ElasticOTelTests\Util\DebugContext;
use ElasticOTelTests\Util\FileUtil;
use ElasticOTelTests\Util\JsonUtil;
use ElasticOTelTests\Util\TimeUtil;
use PhpParser\Error as PhpParserError;
use PhpParser\ErrorHandler\Throwing as ThrowingPhpParserErrorHandler;
use PhpParser\ParserFactory;
use SplFileInfo;
use Throwable;

/**
 * @group does_not_require_external_services
 */
final class PackagesPhpRequirementTest extends ComponentTestCaseBase
{
    private const PROD_VENDOR_DIR_KEY = 'prod_vendor_dir';

    public function testSemverConstraint(): void
    {
        $assertSatisfies = function (string $version, string $constraint): void {
            self::assertTrue(Semver::satisfies($version, $constraint));
        };

        $assertNotSatisfies = function (string $version, string $constraint): void {
            self::assertNotTrue(Semver::satisfies($version, $constraint));
        };

        $assertThrows = function (string $version, string $constraint): void {
            $thrown = null;
            try {
                Semver::satisfies($version, $constraint);
            } catch (Throwable $throwable) {
                $thrown = $throwable;
            }
            self::assertNotNull($thrown);
        };

        $assertSatisfies('8.1', '^8.0');
        $assertSatisfies('8.1', '^8.1');
        $assertNotSatisfies('8.1', '^8.2');
        $assertNotSatisfies('8.1', '^8.3');
        $assertNotSatisfies('8.1', '^9.1');

        $assertSatisfies('8.1', '>=7.0');
        $assertSatisfies('8.1', '>=8.0');
        $assertSatisfies('8.1', '>=8.1');
        $assertNotSatisfies('8.1', '>=8.2');
        $assertNotSatisfies('8.1', '>=9.1');

        $assertSatisfies('8.1', '^5.3 || ^7.0 || ^8.0');
        $assertNotSatisfies('4.1', '^5.3 || ^7.0 || ^8.0');

        $assertSatisfies('8.1.2.3', '^8.1');
        $assertNotSatisfies('8.1.2.3', '^8.2');

        $assertThrows('8.1.2.3-extra', '^8.1');
    }

    private static function getCurrentPhpVersion(): string
    {
        // PHP_VERSION: 5.3.6-13ubuntu3.2
        // PHP_EXTRA_VERSION: -13ubuntu3.2

        if (PHP_EXTRA_VERSION === '') {
            return PHP_VERSION;
        }

        self::assertStringEndsWith(PHP_EXTRA_VERSION, PHP_VERSION);
        return substr(PHP_VERSION, offset: 0, length: strlen(PHP_VERSION) - strlen(PHP_EXTRA_VERSION));
    }

    private static function getPackagePhpVersionConstraints(string $packageDir): ?string
    {
        $packageComposerJsonFilePath = FileUtil::partsToPath($packageDir, 'composer.json');
        if (!file_exists($packageComposerJsonFilePath)) {
            return null;
        }
        $jsonEncoded = FileUtil::getFileContents($packageComposerJsonFilePath);
        $jsonDecoded = AssertEx::isArray(JsonUtil::decode($jsonEncoded, asAssocArray: true));
        $requireMap = AssertEx::isArray(AssertEx::arrayHasKey('require', $jsonDecoded));
        return AssertEx::isString(AssertEx::arrayHasKey('php', $requireMap));
    }

    /**
     * @param callable(string $packageVendor, string $packageName): void $code
     */
    private static function callForEachPackage(string $prodVendorDir, callable $code): void
    {
        DebugContext::getCurrentScope(/* out */ $dbgCtx);

        $dbgCtx->pushSubScope();
        foreach (FileUtil::iterateDirectory($prodVendorDir) as $vendorDirChildEntry) {
            if (!$vendorDirChildEntry->isDir()) {
                continue;
            }
            $dbgCtx->resetTopSubScope(compact('vendorDirChildEntry'));

            $dbgCtx->pushSubScope();
            foreach (FileUtil::iterateDirectory($vendorDirChildEntry->getRealPath()) as $vendorDirGrandChildEntry) {
                if (!$vendorDirGrandChildEntry->isDir()) {
                    continue;
                }
                $dbgCtx->resetTopSubScope(compact('vendorDirGrandChildEntry'));

                $code($vendorDirChildEntry->getBasename(), $vendorDirGrandChildEntry->getBasename());
            }
            $dbgCtx->popSubScope();
        }
        $dbgCtx->popSubScope();
    }

    private static function assertOpcacheEnabled(): void
    {
        DebugContext::getCurrentScope(/* out */ $dbgCtx);
        /** @noinspection PhpComposerExtensionStubsInspection */
        $opcacheStatus = AssertEx::isArray(opcache_get_status());
        $dbgCtx->add(compact('opcacheStatus'));
        $opcacheEnabled = AssertEx::isBool(AssertEx::arrayHasKey('opcache_enabled', $opcacheStatus));
        self::assertTrue($opcacheEnabled);
    }

    private static function verifyPackagesPhpVersion(string $prodVendorDir): void
    {
        DebugContext::getCurrentScope(/* out */ $dbgCtx);
        $currentPhpVersion = self::getCurrentPhpVersion();
        $dbgCtx->add(compact('currentPhpVersion'));

        self::callForEachPackage(
            $prodVendorDir,
            function (string $packageVendor, string $packageName) use ($prodVendorDir, $dbgCtx, $currentPhpVersion) {
                $packageDir = FileUtil::partsToPath($prodVendorDir, $packageVendor, $packageName);
                if (($phpVersionConstraints = self::getPackagePhpVersionConstraints($packageDir)) === null) {
                    return;
                }

                $packageFqName = "$packageVendor/$packageName";
                $dbgCtx->add(compact('packageFqName'));
                $dbgCtx->add(compact('phpVersionConstraints'));
                if (Semver::satisfies($currentPhpVersion, $phpVersionConstraints)) {
                    self::fail(
                        'Encountered a package with PHP constraints that are not satisfied by the current PHP version; '
                        . "package: $packageFqName, PHP constraints: $phpVersionConstraints, current PHP version: $currentPhpVersion"
                    );
                }
            }
        );
    }

    private static function containsHiddenDirInPath(string $filePath): bool
    {
        $pathParts = explode(DIRECTORY_SEPARATOR, $filePath);
        foreach ($pathParts as $pathPart) {
            if (str_starts_with($pathPart, '.')) {
                return true;
            }
        }
        return false;
    }

    private static function validatePhpFilesUseParser(string $prodVendorDir): void
    {
        DebugContext::getCurrentScope(/* out */ $dbgCtx);

        $loggerProxy = self::getLoggerStatic(__NAMESPACE__, __CLASS__, __FILE__)->ifDebugLevelEnabledNoLine(__FUNCTION__);
        $loggerProxy?->log(__LINE__, 'Entered', compact('prodVendorDir'));

        $parser = (new ParserFactory())->createForHostVersion();
        $throwingErrorHandler = new ThrowingPhpParserErrorHandler();
        $dbgCtx->pushSubScope();
        foreach (FileUtil::iterateOverFilesInDirectoryRecursively($prodVendorDir) as $fileInfo) {
            $filePath = $fileInfo->getRealPath();
            if ($fileInfo->getExtension() !== 'php' || self::containsHiddenDirInPath($filePath)) {
                continue;
            }

            $dbgCtx->resetTopSubScope(compact('filePath'));
            $loggerProxy?->log(__LINE__, '', compact('filePath'));

            try {
                $tokens = $parser->parse(FileUtil::getFileContents($filePath), $throwingErrorHandler);
                self::assertNotNull($tokens);
            } catch (PhpParserError $parserError) {
                $dbgCtx->add(compact('parserError'));
                self::fail("PHP parser failed on $filePath: {$parserError->getMessage()}");
            }
        }
        $dbgCtx->popSubScope();
    }

    private static function validatePhpFilesUseOpCache(string $prodVendorDir): void
    {
        DebugContext::getCurrentScope(/* out */ $dbgCtx);

        $loggerProxy = self::getLoggerStatic(__NAMESPACE__, __CLASS__, __FILE__)->ifDebugLevelEnabledNoLine(__FUNCTION__);
        $loggerProxy?->log(__LINE__, 'Entered', compact('prodVendorDir'));

        $helperScript = __DIR__ . DIRECTORY_SEPARATOR . 'helperToTestPackagesPhpRequirement.php';
        $helperScriptFileInfo = new SplFileInfo($helperScript);
        $procInfo = ProcessUtil::startProcessAndWaitForItToExit(
            dbgProcessName: $helperScriptFileInfo->getBasename($helperScriptFileInfo->getExtension()),
            command: "php \"$helperScript\" \"$prodVendorDir\"",
            envVars: EnvVarUtilForTests::getAll(),
            maxWaitTimeInMicroseconds: intval(TimeUtil::secondsToMicroseconds(60)) // 1 minute
        );
        $dbgCtx->add(compact('procInfo'));
        self::assertSame(0, $procInfo['exitCode']);
    }

    public static function appCodeForTestPackagesHaveCorrectPhpVersion(): void
    {
        OTelUtil::addActiveSpanAttributes([self::PROD_VENDOR_DIR_KEY => PhpPartFacade::getVendorDirPath()]);
    }

    public function testPackagesHaveCorrectPhpVersion(): void
    {
        self::assertOpcacheEnabled();

        DebugContext::getCurrentScope(/* out */ $dbgCtx);

        $testCaseHandle = $this->getTestCaseHandle();

        $appCodeHost = $testCaseHandle->ensureMainAppCodeHost(
            function (AppCodeHostParams $appCodeParams): void {
                self::ensureTransactionSpanEnabled($appCodeParams);
            }
        );
        $appCodeHost->execAppCode(AppCodeTarget::asRouted([__CLASS__, 'appCodeForTestPackagesHaveCorrectPhpVersion']));

        $agentBackendComms = $testCaseHandle->waitForEnoughAgentBackendComms(WaitForOTelSignalCounts::spans(1)); // exactly 1 span (the root span) is expected
        $dbgCtx->add(compact('agentBackendComms'));
        $prodVendorDir = FileUtil::normalizePath($agentBackendComms->singleSpan()->attributes->getString(self::PROD_VENDOR_DIR_KEY));

        self::verifyPackagesPhpVersion($prodVendorDir);
        self::validatePhpFilesUseParser($prodVendorDir);
        self::validatePhpFilesUseOpCache($prodVendorDir);
    }
}
