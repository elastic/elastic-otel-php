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

/** @noinspection PhpComposerExtensionStubsInspection */

declare(strict_types=1);

namespace ElasticOTelTests\ComponentTests;

use Composer\Semver\Semver;
use Elastic\OTel\PhpPartFacade;
use ElasticOTelTests\ComponentTests\Util\AppCodeHostParams;
use ElasticOTelTests\ComponentTests\Util\AppCodeTarget;
use ElasticOTelTests\ComponentTests\Util\ComponentTestCaseBase;
use ElasticOTelTests\ComponentTests\Util\OTelUtil;
use ElasticOTelTests\ComponentTests\Util\WaitForOTelSignalCounts;
use ElasticOTelTests\Util\AssertEx;
use ElasticOTelTests\Util\DebugContext;
use ElasticOTelTests\Util\FileUtil;
use ElasticOTelTests\Util\JsonUtil;
use PhpParser\ErrorHandler\Throwing as ThrowingPhpParserErrorHandler;
use PhpParser\ParserFactory;
use Throwable;

use function opcache_compile_file;

/**
 * @group does_not_require_external_services
 */
final class PackagesPhpRequirementTest extends ComponentTestCaseBase
{
    private const PROD_VENDOR_DIR_KEY = 'prod_vendor_dir';

    private const PACKAGES_EXPECTED_NOT_SUPPORT_PHP_81 = [
        'open-telemetry/opentelemetry-auto-curl',
        'open-telemetry/opentelemetry-auto-mysqli',
        'open-telemetry/opentelemetry-auto-pdo',
        'open-telemetry/opentelemetry-auto-postgresql',
    ];

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

    private static function isCurrentPhpVersion81(): bool
    {
        // If the current PHP version is 8.1.*
        // @phpstan-ignore-next-line
        return (80100 <= PHP_VERSION_ID) && (PHP_VERSION_ID < 80200);
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
        $packageComposerJsonFilePath = FileUtil::listToPath([$packageDir, 'composer.json']);
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

    private static function verifyPackagesPhpVersion(string $prodVendorDir): void
    {
        DebugContext::getCurrentScope(/* out */ $dbgCtx);
        $currentPhpVersion = self::getCurrentPhpVersion();
        $dbgCtx->add(compact('currentPhpVersion'));

        $numberOfPackagesNotSupportCurrentPhpVersion = 0;
        self::callForEachPackage(
            $prodVendorDir,
            function (string $packageVendor, string $packageName) use ($prodVendorDir, $dbgCtx, $currentPhpVersion, &$numberOfPackagesNotSupportCurrentPhpVersion) {
                $packageDir = FileUtil::listToPath([$prodVendorDir, $packageVendor, $packageName]);
                if (($phpVersionConstraints = self::getPackagePhpVersionConstraints($packageDir)) === null) {
                    return;
                }

                $dbgCtx->add(compact('phpVersionConstraints'));
                if (Semver::satisfies($currentPhpVersion, $phpVersionConstraints)) {
                    return;
                }

                self::assertTrue(self::isCurrentPhpVersion81());
                self::assertTrue(in_array("$packageVendor/$packageName", self::PACKAGES_EXPECTED_NOT_SUPPORT_PHP_81));
                ++$numberOfPackagesNotSupportCurrentPhpVersion;
            }
        );
        self::assertSame(self::isCurrentPhpVersion81() ? count(self::PACKAGES_EXPECTED_NOT_SUPPORT_PHP_81) : 0, $numberOfPackagesNotSupportCurrentPhpVersion);
    }

    private static function verifyPhpFilesValidForCurrentPhpVersion(string $prodVendorDir): void
    {
        $parser = (new ParserFactory())->createForHostVersion();
        $throwingErrorHandler = new ThrowingPhpParserErrorHandler();
        foreach (FileUtil::iterateOverFilesInDirectoryRecursively($prodVendorDir) as $fileInfo) {
            if ($fileInfo->getExtension() === 'php') {
                self::assertNotNull($parser->parse(FileUtil::getFileContents($fileInfo->getRealPath()), $throwingErrorHandler));
                /** @noinspection PhpComposerExtensionStubsInspection */
                self::assertTrue(opcache_compile_file($fileInfo->getRealPath()));
            }
        }
    }

    public static function appCodeForTestPackagesHaveCorrectPhpVersion(): void
    {
        OTelUtil::addActiveSpanAttributes([self::PROD_VENDOR_DIR_KEY => PhpPartFacade::getVendorDirPath()]);
    }

    public function testPackagesHaveCorrectPhpVersion(): void
    {
        if (PhpPartFacade::isInDevMode()) {
            self::dummyAssert();
            return;
        }

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
        $prodVendorDir = $agentBackendComms->singleSpan()->attributes->getString(self::PROD_VENDOR_DIR_KEY);

        self::verifyPackagesPhpVersion($prodVendorDir);
        self::verifyPhpFilesValidForCurrentPhpVersion($prodVendorDir);
    }
}
