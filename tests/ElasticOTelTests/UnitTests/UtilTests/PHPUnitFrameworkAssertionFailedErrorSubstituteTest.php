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
use ElasticOTelTests\Util\DisableDebugContextTestTrait;
use ElasticOTelTests\Util\FileUtil;
use ElasticOTelTests\Util\TestCaseBase;
use ElasticOTelTests\Util\TestsRootDir;
use ElasticOTelTests\Util\VendorDir;
use Override;
use PhpParser\NodeDumper as PhpParserNodeDumper;
use PhpParser\ParserFactory as PhpParserFactory;
use PHPUnit\Framework\AssertionFailedError;

/**
 * @phpstan-import-type PreProcessMessageCallback from AssertionFailedError
 */
final class PHPUnitFrameworkAssertionFailedErrorSubstituteTest extends TestCaseBase
{
    use DisableDebugContextTestTrait;

    /** @var ?PreProcessMessageCallback */
    private static mixed $preprocessMessageCallbackToRestore;

    #[Override]
    public function setUp(): void
    {
        parent::setUp();

        self::$preprocessMessageCallbackToRestore = AssertionFailedError::$preprocessMessage;
    }

    #[Override]
    public function tearDown(): void
    {
        AssertionFailedError::$preprocessMessage = self::$preprocessMessageCallbackToRestore;

        parent::tearDown();
    }

    /** @noinspection PhpUnusedParameterInspection */
    public static function testMessageIsPreprocessed(): void
    {
        $textToAdd = ' dummy text added by preprocessMessage';
        $exceptionMsg = null;

        AssertionFailedError::$preprocessMessage = function (AssertionFailedError $exceptionBeingConstructed, string $baseMessage, int $numberOfStackFramesToSkip) use ($textToAdd): string {
            return $baseMessage . $textToAdd;
        };
        try {
            self::fail();
        } catch (AssertionFailedError $ex) {
            $exceptionMsg = $ex->getMessage();
        }
        AssertionFailedError::$preprocessMessage = null;

        self::assertStringContainsString($textToAdd, $exceptionMsg);
    }

    /**
     * @return iterable<array{string, string}>
     */
    public static function dataProviderForTestOriginalMatchesVendor(): iterable
    {
        $pathToOriginalSubDir = TestsRootDir::adaptRelativeUnixStylePath('substitutes/PHPUnit_Framework_AssertionFailedError/original');
        $pathToVendorSubDir = VendorDir::adaptRelativeUnixStylePath('phpunit/phpunit/src');

        yield [
            FileUtil::listToPath([$pathToOriginalSubDir, FileUtil::adaptUnixDirectorySeparators('AssertionFailedError.php')]),
            FileUtil::listToPath([$pathToVendorSubDir, FileUtil::adaptUnixDirectorySeparators('Framework/Exception/AssertionFailedError.php')]),
        ];
    }

    /**
     * @dataProvider dataProviderForTestOriginalMatchesVendor
     */
    public static function testOriginalMatchesVendor(string $pathToOriginalFile, string $pathToVendorFile): void
    {
        DebugContext::getCurrentScope(/* out */ $dbgCtx);
        $phpParser = (new PhpParserFactory())->createForHostVersion();
        /**
         * @return array{'PHP': string, 'AST': string}
         */
        $parsePhpAndDumpAst = function (string $pathToPhpFile) use ($phpParser): array {
            $phpFileContent = file_get_contents($pathToPhpFile);
            self::assertNotFalse($phpFileContent);
            $ast = $phpParser->parse($phpFileContent);
            self::assertNotNull($ast);
            $dumper = new PhpParserNodeDumper(['dumpComments' => false, 'dumpPositions' => false]);
            return ['PHP' => $phpFileContent, 'AST' => $dumper->dump($ast)];
        };

        $originalPhpAst = $parsePhpAndDumpAst($pathToOriginalFile);
        $dbgCtx->add(compact('originalPhpAst'));
        $vendorPhpAst = $parsePhpAndDumpAst($pathToVendorFile);
        $dbgCtx->add(compact('vendorPhpAst'));
        self::assertSame($originalPhpAst['AST'], $vendorPhpAst['AST']);
    }
}
