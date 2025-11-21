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

use ArrayAccess;
use ElasticOTelTests\ComponentTests\Util\AppCodeHostParams;
use ElasticOTelTests\ComponentTests\Util\AppCodeRequestParams;
use ElasticOTelTests\ComponentTests\Util\AppCodeTarget;
use ElasticOTelTests\ComponentTests\Util\ComponentTestCaseBase;
use ElasticOTelTests\ComponentTests\Util\DbAutoInstrumentationUtilForTests;
use ElasticOTelTests\ComponentTests\Util\PDOSpanExpectationsBuilder;
use ElasticOTelTests\ComponentTests\Util\SpanExpectations;
use ElasticOTelTests\ComponentTests\Util\SpanSequenceExpectations;
use ElasticOTelTests\ComponentTests\Util\WaitForOTelSignalCounts;
use ElasticOTelTests\Util\AssertEx;
use ElasticOTelTests\Util\ClassNameUtil;
use ElasticOTelTests\Util\Config\OptionForProdName;
use ElasticOTelTests\Util\DataProviderForTestBuilder;
use ElasticOTelTests\Util\DebugContext;
use ElasticOTelTests\Util\Log\LoggableToString;
use ElasticOTelTests\Util\MixedMap;
use OpenTelemetry\Contrib\Instrumentation\PDO\PDOInstrumentation;
use OpenTelemetry\SemConv\TraceAttributes;
use PDO;

/**
 * @group smoke
 * @group does_not_require_external_services
 */
final class PDOAutoInstrumentationTest extends ComponentTestCaseBase
{
    private const AUTO_INSTRUMENTATION_NAME = 'pdo';
    private const IS_AUTO_INSTRUMENTATION_ENABLED_KEY = 'is_auto_instrumentation_enabled';

    private const CONNECTION_STRING_PREFIX = 'sqlite:';

    /** @noinspection RequiredAttributes */
    public const TEMP_DB_NAME = '<temporary database>';
    public const MEMORY_DB_NAME = 'memory';
    /** @noinspection RequiredAttributes */
    public const FILE_DB_NAME = '<file DB>';

    public const MESSAGES
        = [
            'Just testing...'    => 1,
            'More testing...'    => 22,
            'SQLite3 is cool...' => 333,
        ];

    public const CREATE_TABLE_SQL
        = /** @lang text */
        'CREATE TABLE messages (
            id INTEGER PRIMARY KEY,
            text TEXT,
            time INTEGER)';

    public const INSERT_SQL
        = /** @lang text */
        'INSERT INTO messages (text, time) VALUES (:text, :time)';

    public const SELECT_SQL
        = /** @lang text */
        'SELECT * FROM messages';

    private static function buildConnectionString(string $dbName): string
    {
        // https://www.php.net/manual/en/ref.pdo-sqlite.connection.php

        return match ($dbName) {
            self::TEMP_DB_NAME => self::CONNECTION_STRING_PREFIX,
            self::MEMORY_DB_NAME => self::CONNECTION_STRING_PREFIX . ':' . self::MEMORY_DB_NAME . ':',
            default => self::CONNECTION_STRING_PREFIX . $dbName,
        };
    }

    /**
     * @return iterable<array{string}>
     */
    public static function dataProviderForTestBuildConnectionString(): iterable
    {
        // https://www.php.net/manual/en/ref.pdo-sqlite.connection.php

        return self::adaptToSmoke(
            [
                // To create a database in memory, :memory: has to be appended to the DSN prefix
                yield [self::MEMORY_DB_NAME, 'sqlite::memory:'],

                // To create a database in memory, :memory: has to be appended to the DSN prefix
                yield ['/opt/databases/my_db.sqlite', 'sqlite:/opt/databases/my_db.sqlite'],

                // If the DSN consists of the DSN prefix only, a temporary database is used,
                // which is deleted when the connection is closed
                yield [self::TEMP_DB_NAME, 'sqlite:'],
            ]
        );
    }

    /**
     * @dataProvider dataProviderForTestBuildConnectionString
     *
     * @param string $dbName
     * @param string $expectedDbConnectionString
     */
    public function testBuildConnectionString(string $dbName, string $expectedDbConnectionString): void
    {
        $dbgCtx = LoggableToString::convert(compact('dbName'));
        $actualDbConnectionString = self::buildConnectionString($dbName);
        self::assertSame($expectedDbConnectionString, $actualDbConnectionString, $dbgCtx);
    }

    public function testPrerequisitesSatisfied(): void
    {
        $extensionName = 'pdo_sqlite';
        self::assertTrue(extension_loaded($extensionName), 'Required extension ' . $extensionName . ' is not loaded');
    }

    /**
     * @return iterable<string, array{MixedMap}>
     */
    public static function dataProviderForTestAutoInstrumentation(): iterable
    {
        return self::adaptDataProviderForTestBuilderToSmokeToDescToMixedMap(
            (new DataProviderForTestBuilder())
                ->addBoolKeyedDimensionAllValuesCombinable(self::IS_AUTO_INSTRUMENTATION_ENABLED_KEY)
                ->addKeyedDimensionOnlyFirstValueCombinable(DbAutoInstrumentationUtilForTests::DB_NAME_KEY, [self::MEMORY_DB_NAME, self::TEMP_DB_NAME, self::FILE_DB_NAME])
                ->addGeneratorOnlyFirstValueCombinable(DbAutoInstrumentationUtilForTests::wrapTxRelatedArgsDataProviderGenerator())
        );
    }

    public static function appCodeForTestAutoInstrumentation(MixedMap $appCodeArgs): void
    {
        DebugContext::getCurrentScope(/* out */ $dbgCtx);

        self::assertTrue(extension_loaded('pdo'));

        $isAutoInstrumentationEnabled = $appCodeArgs->getBool(self::IS_AUTO_INSTRUMENTATION_ENABLED_KEY);
        if ($isAutoInstrumentationEnabled) {
            self::assertTrue(class_exists(PDOInstrumentation::class, autoload: false));
            AssertEx::sameConstValues(PDOInstrumentation::NAME, self::AUTO_INSTRUMENTATION_NAME);
        }

        $dbName = $appCodeArgs->getString(DbAutoInstrumentationUtilForTests::DB_NAME_KEY);
        $wrapInTx = $appCodeArgs->getBool(DbAutoInstrumentationUtilForTests::WRAP_IN_TX_KEY);
        $rollback = $appCodeArgs->getBool(DbAutoInstrumentationUtilForTests::SHOULD_ROLLBACK_KEY);

        $pdo = new PDO(self::buildConnectionString($dbName));
        self::assertTrue($pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION));
        if ($wrapInTx) {
            self::assertTrue($pdo->beginTransaction());
        }

        self::assertNotFalse($pdo->exec(self::CREATE_TABLE_SQL));

        self::assertNotFalse($stmt = $pdo->prepare(self::INSERT_SQL));
        $boundMsgText = '';
        $boundMsgTime = 0;
        self::assertTrue($stmt->bindParam(':text', /* ref */ $boundMsgText));
        self::assertTrue($stmt->bindParam(':time', /* ref */ $boundMsgTime));
        foreach (self::MESSAGES as $msgText => $msgTime) {
            $boundMsgText = $msgText;
            $boundMsgTime = $msgTime;
            self::assertTrue($stmt->execute());
        }

        self::assertNotFalse($queryResult = $pdo->query(self::SELECT_SQL));
        foreach ($queryResult as $row) {
            $dbgCtx = LoggableToString::convert(['$row' => $row, '$queryResult' => $queryResult]);
            /** @var ArrayAccess<string, mixed> $row */
            $msgText = $row['text'];
            self::assertIsString($msgText);
            self::assertArrayHasKey($msgText, self::MESSAGES, $dbgCtx);
            self::assertEquals(self::MESSAGES[$msgText], $row['time'], $dbgCtx);
        }

        if ($wrapInTx) {
            self::assertTrue($rollback ? $pdo->rollback() : $pdo->commit());
        }
    }

    private function implTestAutoInstrumentation(MixedMap $testArgs): void
    {
        DebugContext::getCurrentScope(/* out */ $dbgCtx);

        $isAutoInstrumentationEnabled = $testArgs->getBool(self::IS_AUTO_INSTRUMENTATION_ENABLED_KEY);
        $dbNameArg = $testArgs->getString(DbAutoInstrumentationUtilForTests::DB_NAME_KEY);
        $wrapInTx = $testArgs->getBool(DbAutoInstrumentationUtilForTests::WRAP_IN_TX_KEY);
        $rollback = $testArgs->getBool(DbAutoInstrumentationUtilForTests::SHOULD_ROLLBACK_KEY);

        $testCaseHandle = $this->getTestCaseHandle();

        $appCodeArgs = $testArgs->clone();

        $dbName = $dbNameArg;
        if ($dbNameArg === self::FILE_DB_NAME) {
            $resourcesClient = $testCaseHandle->getResourcesClient();
            $dbFileFullPath = $resourcesClient->createTempFile('temp DB for ' . ClassNameUtil::fqToShort(__CLASS__));
            $dbName = $dbFileFullPath;
            $appCodeArgs[DbAutoInstrumentationUtilForTests::DB_NAME_KEY] = $dbName;
        }

        $expectationsBuilder = (new PDOSpanExpectationsBuilder())->dbSystemName('sqlite')->dbNamespace($dbName === self::TEMP_DB_NAME ? '' : $dbName);
        /** @var SpanExpectations[] $expectedDbSpans */
        $expectedDbSpans = [];
        if ($isAutoInstrumentationEnabled) {
            $expectedDbSpans[] = $expectationsBuilder->buildForPDOClassMethod('__construct');

            if ($wrapInTx) {
                $expectedDbSpans[] = $expectationsBuilder->buildForPDOClassMethod('beginTransaction');
            }

            $expectedDbSpans[] = $expectationsBuilder->buildForPDOClassMethod('exec', dbQueryText: self::CREATE_TABLE_SQL);
            $expectedDbSpans[] = $expectationsBuilder->buildForPDOClassMethod('prepare', dbQueryText: self::INSERT_SQL);
            foreach (self::MESSAGES as $ignored) {
                $expectedDbSpans[] = $expectationsBuilder->buildForPDOStatementClassMethod('execute');
            }
            $expectedDbSpans[] = $expectationsBuilder->buildForPDOClassMethod('query', dbQueryText: self::SELECT_SQL);

            if ($wrapInTx) {
                $expectedDbSpans[] = $expectationsBuilder->buildForPDOClassMethod($rollback ? 'rollBack' : 'commit');
            }
        }

        $appCodeHost = $testCaseHandle->ensureMainAppCodeHost(
            function (AppCodeHostParams $appCodeParams) use ($isAutoInstrumentationEnabled): void {
                if (!$isAutoInstrumentationEnabled) {
                    $appCodeParams->setProdOptionIfNotNull(OptionForProdName::disabled_instrumentations, self::AUTO_INSTRUMENTATION_NAME);
                }
                self::disableTimingDependentFeatures($appCodeParams);
            }
        );
        $appCodeHost->execAppCode(
            AppCodeTarget::asRouted([__CLASS__, 'appCodeForTestAutoInstrumentation']),
            function (AppCodeRequestParams $appCodeRequestParams) use ($appCodeArgs): void {
                $appCodeRequestParams->setAppCodeArgs($appCodeArgs);
            }
        );

        // +1 for automatic local root span
        $agentBackendComms = $testCaseHandle->waitForEnoughAgentBackendComms(WaitForOTelSignalCounts::spans(1 + count($expectedDbSpans)));
        $dbgCtx->add(compact('agentBackendComms'));

        $actualDbSpans = [];
        foreach ($agentBackendComms->spans() as $span) {
            if ($span->attributes->keyExists(TraceAttributes::DB_SYSTEM_NAME)) {
                $actualDbSpans[] = $span;
            }
        }
        (new SpanSequenceExpectations($expectedDbSpans))->assertMatches($actualDbSpans);
    }

    /**
     * @dataProvider dataProviderForTestAutoInstrumentation
     */
    public function testAutoInstrumentation(MixedMap $testArgs): void
    {
        self::runAndEscalateLogLevelOnFailure(
            self::buildDbgDescForTestWithArgs(__CLASS__, __FUNCTION__, $testArgs),
            function () use ($testArgs): void {
                $this->implTestAutoInstrumentation($testArgs);
            }
        );
    }
}
