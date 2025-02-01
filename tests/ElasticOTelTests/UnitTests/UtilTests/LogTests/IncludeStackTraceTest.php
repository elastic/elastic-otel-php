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

/** @noinspection PhpUnusedPrivateFieldInspection, PhpPrivateFieldCanBeLocalVariableInspection */

declare(strict_types=1);

namespace ElasticOTelTests\UnitTests\UtilTests\LogTests;

use Elastic\OTel\Log\LogLevel;
use ElasticOTelTests\UnitTests\Util\MockLogPreformattedSink;
use ElasticOTelTests\Util\AssertEx;
use ElasticOTelTests\Util\ClassNameUtil;
use ElasticOTelTests\Util\JsonUtil;
use ElasticOTelTests\Util\Log\Backend as LogBackend;
use ElasticOTelTests\Util\Log\LogCategoryForTests;
use ElasticOTelTests\Util\Log\LoggableStackTrace;
use ElasticOTelTests\Util\Log\LoggableToString;
use ElasticOTelTests\Util\Log\Logger;
use ElasticOTelTests\Util\Log\LoggerFactory;
use ElasticOTelTests\Util\Log\LogLevelUtil;
use ElasticOTelTests\Util\Log\SinkInterface as LogSinkInterface;
use ElasticOTelTests\Util\StackTraceUtil;
use ElasticOTelTests\Util\TestCaseBase;

class IncludeStackTraceTest extends TestCaseBase
{
    private static function buildLogger(LogSinkInterface $logSink): Logger
    {
        $loggerFactory = new LoggerFactory(new LogBackend(LogLevelUtil::getHighest(), $logSink));
        return $loggerFactory->loggerForClass(LogCategoryForTests::TEST, __NAMESPACE__, __CLASS__, __FILE__);
    }

    /**
     * @param Logger $logger
     *
     * @return array<string, mixed>
     */
    private static function includeStackTraceHelperFunc(Logger $logger): array
    {
        ($lgrPxy = $logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__)) && $lgrPxy->includeStackTrace()->log('');
        $expectedSrcCodeLine = __LINE__ - 1;
        return [
            StackTraceUtil::FUNCTION_KEY => __FUNCTION__,
            StackTraceUtil::LINE_KEY     => $expectedSrcCodeLine,
        ];
    }

    /**
     * @param array<string, mixed> $expectedSrcCodeData
     * @param array<string, mixed> $actualFrame
     *
     * @return void
     */
    public static function verifyStackFrame(array $expectedSrcCodeData, array $actualFrame): void
    {
        $ctx = LoggableToString::convert(['$actualFrame' => $actualFrame]);
        self::assertCount(4, $actualFrame, $ctx);

        self::assertArrayHasKey(StackTraceUtil::FILE_KEY, $actualFrame, $ctx);
        $actualFilePath = $actualFrame[StackTraceUtil::FILE_KEY];
        self::assertIsString($actualFilePath, $ctx);
        self::assertSame(basename(__FILE__), basename($actualFilePath), $ctx);

        $expectedSrcCodeLine = $expectedSrcCodeData[StackTraceUtil::LINE_KEY];
        AssertEx::hasKeyWithSameValue(StackTraceUtil::LINE_KEY, $expectedSrcCodeLine, $actualFrame, $ctx);

        self::assertArrayHasKey(StackTraceUtil::CLASS_KEY, $actualFrame, $ctx);
        $thisClassShortName = ClassNameUtil::fqToShort(__CLASS__);
        $actualClass = $actualFrame[StackTraceUtil::CLASS_KEY];
        self::assertIsString($actualClass, $ctx);
        $actualClassShortName = ClassNameUtil::fqToShort($actualClass); // @phpstan-ignore-line
        self::assertSame($thisClassShortName, $actualClassShortName, $ctx);

        $expectedSrcCodeFunc = $expectedSrcCodeData[StackTraceUtil::FUNCTION_KEY];
        AssertEx::hasKeyWithSameValue(StackTraceUtil::FUNCTION_KEY, $expectedSrcCodeFunc, $actualFrame, $ctx);
    }

    public function testIncludeStackTrace(): void
    {
        $mockLogSink = new MockLogPreformattedSink();
        $logger = self::buildLogger($mockLogSink);

        $expectedSrcCodeLineForThisFrame = __LINE__ + 1;
        $expectedSrcCodeDataForTopFrame = self::includeStackTraceHelperFunc($logger);

        self::assertCount(1, $mockLogSink->consumed);
        $actualLogStatement = $mockLogSink->consumed[0];
        self::assertSame(LogLevel::trace, $actualLogStatement->statementLevel);
        self::assertSame(LogCategoryForTests::TEST, $actualLogStatement->category);
        self::assertSame(__FILE__, $actualLogStatement->srcCodeFile);
        self::assertSame($expectedSrcCodeDataForTopFrame[StackTraceUtil::LINE_KEY], $actualLogStatement->srcCodeLine);
        self::assertSame(
            $expectedSrcCodeDataForTopFrame[StackTraceUtil::FUNCTION_KEY],
            $actualLogStatement->srcCodeFunc
        );

        $actualCtx = JsonUtil::decode($actualLogStatement->messageWithContext, asAssocArray: true);
        self::assertIsArray($actualCtx);
        /** @var array<string, mixed> $actualCtx */
        AssertEx::hasKeyWithSameValue(LogBackend::NAMESPACE_KEY, __NAMESPACE__, $actualCtx);
        self::assertArrayHasKey(LogBackend::CLASS_KEY, $actualCtx);
        $thisClassShortName = ClassNameUtil::fqToShort(__CLASS__);
        $actualFqClassName = $actualCtx[LogBackend::CLASS_KEY];
        self::assertSame($thisClassShortName, ClassNameUtil::fqToShort($actualFqClassName)); // @phpstan-ignore argument.type

        self::assertArrayHasKey(LoggableStackTrace::STACK_TRACE_KEY, $actualCtx);
        $actualStackTrace = $actualCtx[LoggableStackTrace::STACK_TRACE_KEY];
        self::assertIsArray($actualStackTrace);
        /** @var array<string, mixed>[] $actualStackTrace */
        AssertEx::countAtLeast(2, $actualStackTrace);
        self::verifyStackFrame($expectedSrcCodeDataForTopFrame, $actualStackTrace[0]);
        $expectedSrcCodeDataForThisFrame = [
            StackTraceUtil::FUNCTION_KEY => __FUNCTION__,
            StackTraceUtil::LINE_KEY     => $expectedSrcCodeLineForThisFrame,
        ];
        self::verifyStackFrame($expectedSrcCodeDataForThisFrame, $actualStackTrace[1]);
    }
}
