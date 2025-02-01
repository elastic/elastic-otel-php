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

namespace ElasticOTelTests\UnitTests\UtilTests\LogTests;

use Elastic\OTel\Log\LogLevel;
use ElasticOTelTests\UnitTests\Util\MockLogPreformattedSink;
use ElasticOTelTests\Util\ArrayUtilForTests;
use ElasticOTelTests\Util\ClassNameUtil;
use ElasticOTelTests\Util\DebugContextForTests;
use ElasticOTelTests\Util\IterableUtil;
use ElasticOTelTests\Util\JsonUtil;
use ElasticOTelTests\Util\Log\Backend as LogBackend;
use ElasticOTelTests\Util\Log\LogCategoryForTests;
use ElasticOTelTests\Util\Log\Logger;
use ElasticOTelTests\Util\Log\LoggerFactory;
use ElasticOTelTests\Util\Log\LogLevelUtil;
use ElasticOTelTests\Util\Log\SinkInterface as LogSinkInterface;
use ElasticOTelTests\Util\TestCaseBase;

class LogContextMapTest extends TestCaseBase
{
    private static function buildLogger(LogSinkInterface $logSink): Logger
    {
        $loggerFactory = new LoggerFactory(new LogBackend(LogLevelUtil::getHighest(), $logSink));
        return $loggerFactory->loggerForClass(LogCategoryForTests::TEST, __NAMESPACE__, __CLASS__, __FILE__);
    }

    public function testMergingContexts(): void
    {
        DebugContextForTests::newScope(/* out */ $dbgCtx, DebugContextForTests::funcArgs());
        try {
            $mockLogSink = new MockLogPreformattedSink();
            $dbgCtx->add(compact('mockLogSink'));
            $level1Ctx = ['level_1_key_1' => 'level_1_key_1 value', 'level_1_key_2' => 'level_1_key_2 value', 'some_key' => 'some_key level_1 value'];
            $loggerA = self::buildLogger($mockLogSink)->addAllContext($level1Ctx);
            $level2Ctx = ['level_2_key_1' => 'level_2_key_1 value', 'level_2_key_2' => 'level_2_key_2 value'];
            $loggerB = $loggerA->inherit()->addAllContext($level2Ctx);

            $loggerProxyDebug = $loggerB->ifDebugLevelEnabledNoLine(__FUNCTION__);

            $level3Ctx = ['level_3_key_1' => 'level_3_key_1 value', 'level_3_key_2' => 'level_3_key_2 value', 'some_key' => 'some_key level_3 value'];
            $loggerB->addAllContext($level3Ctx);

            $stmtMsg = 'Some message';
            $stmtCtx = ['stmt_key_1' => 'stmt_key_1 value', 'stmt_key_2' => 'stmt_key_2 value'];
            $stmtLine = __LINE__ + 1;
            $loggerProxyDebug && $loggerProxyDebug->log(__LINE__, $stmtMsg, $stmtCtx);

            $actualStmt = ArrayUtilForTests::getSingleValue($mockLogSink->consumed);

            self::assertSame(LogLevel::debug, $actualStmt->statementLevel);
            self::assertSame(LogCategoryForTests::TEST, $actualStmt->category);
            self::assertSame(__FILE__, $actualStmt->srcCodeFile);
            self::assertSame($stmtLine, $actualStmt->srcCodeLine);
            self::assertSame(__FUNCTION__, $actualStmt->srcCodeFunc);

            self::assertStringStartsWith($stmtMsg, $actualStmt->messageWithContext);
            $actualCtxEncodedAsJson = trim(substr($actualStmt->messageWithContext, strlen($stmtMsg)));
            $dbgCtx->add(compact('actualCtxEncodedAsJson'));

            $actualCtx = JsonUtil::decode($actualCtxEncodedAsJson, asAssocArray: true);
            self::assertIsArray($actualCtx);
            $expectedCtx = [
                'stmt_key_1' => 'stmt_key_1 value', 'stmt_key_2' => 'stmt_key_2 value',
                'level_3_key_1' => 'level_3_key_1 value', 'level_3_key_2' => 'level_3_key_2 value', 'some_key' => 'some_key level_3 value',
                'level_2_key_1' => 'level_2_key_1 value', 'level_2_key_2' => 'level_2_key_2 value',
                'level_1_key_1' => 'level_1_key_1 value', 'level_1_key_2' => 'level_1_key_2 value',
                LogBackend::NAMESPACE_KEY => __NAMESPACE__,
                LogBackend::CLASS_KEY => ClassNameUtil::fqToShort(__CLASS__),
            ];
            self::assertCount(count($expectedCtx), $actualCtx);
            $dbgCtx->pushSubScope();
            foreach (IterableUtil::zip(IterableUtil::keys($expectedCtx), IterableUtil::keys($actualCtx)) as [$expectedKey, $actualKey]) {
                $dbgCtx->clearCurrentSubScope(compact('expectedKey', 'actualKey'));
                self::assertSame($expectedKey, $actualKey);
                self::assertSame($expectedCtx[$expectedKey], $actualCtx[$actualKey]);
            }
            $dbgCtx->popSubScope();

            $expectedCtxEncodedAsJson = JsonUtil::encode($expectedCtx);
            $dbgCtx->add(compact('expectedCtxEncodedAsJson'));
            self::assertSame($expectedCtxEncodedAsJson, $actualCtxEncodedAsJson);
        } finally {
            $dbgCtx->pop();
        }
    }
}
