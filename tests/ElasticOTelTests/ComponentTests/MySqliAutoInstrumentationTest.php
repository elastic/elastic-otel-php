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

use Elastic\OTel\Util\TextUtil;
use ElasticOTelTests\ComponentTests\Util\AppCodeHostParams;
use ElasticOTelTests\ComponentTests\Util\AppCodeRequestParams;
use ElasticOTelTests\ComponentTests\Util\AppCodeTarget;
use ElasticOTelTests\ComponentTests\Util\ComponentTestCaseBase;
use ElasticOTelTests\ComponentTests\Util\DbAutoInstrumentationUtilForTests;
use ElasticOTelTests\ComponentTests\Util\MySqli\MySqliApiFacade;
use ElasticOTelTests\ComponentTests\Util\MySqli\MySqliDbSpanDataExpectationsBuilder;
use ElasticOTelTests\ComponentTests\Util\MySqli\MySqliResultWrapped;
use ElasticOTelTests\ComponentTests\Util\MySqli\MySqliWrapped;
use ElasticOTelTests\ComponentTests\Util\SpanExpectations;
use ElasticOTelTests\ComponentTests\Util\SpanSequenceExpectations;
use ElasticOTelTests\ComponentTests\Util\WaitForEventCounts;
use ElasticOTelTests\Util\AmbientContextForTests;
use ElasticOTelTests\Util\AssertEx;
use ElasticOTelTests\Util\Config\OptionForProdName;
use ElasticOTelTests\Util\DataProviderForTestBuilder;
use ElasticOTelTests\Util\DebugContext;
use ElasticOTelTests\Util\Log\LoggableToString;
use ElasticOTelTests\Util\MixedMap;
use OpenTelemetry\Contrib\Instrumentation\MySqli\MySqliInstrumentation;
use OpenTelemetry\SemConv\TraceAttributes;
use PHPUnit\Framework\Attributes\Depends;

/**
 * @group smoke
 * @group requires_external_services
 * @group requires_mysql_external_service
 */
final class MySqliAutoInstrumentationTest extends ComponentTestCaseBase
{
    private const AUTO_INSTRUMENTATION_NAME = 'mysqli';
    private const IS_AUTO_INSTRUMENTATION_ENABLED_KEY = 'is_auto_instrumentation_enabled';

    private const IS_OOP_API_KEY = 'IS_OOP_API';

    public const CONNECT_DB_NAME_KEY = 'CONNECT_DB_NAME';
    public const WORK_DB_NAME_KEY = 'WORK_DB_NAME';

    private const QUERY_KIND_KEY = 'QUERY_KIND';
    private const QUERY_KIND_QUERY = 'query';
    private const QUERY_KIND_REAL_QUERY = 'real_query';
    private const QUERY_KIND_MULTI_QUERY = 'multi_query';
    private const QUERY_KIND_ALL_VALUES = [self::QUERY_KIND_QUERY, self::QUERY_KIND_REAL_QUERY, self::QUERY_KIND_MULTI_QUERY];

    private const MESSAGES
        = [
            'Just testing...'    => 1,
            'More testing...'    => 22,
            'SQLite3 is cool...' => 333,
        ];

    private const DROP_DATABASE_IF_EXISTS_SQL_PREFIX
        = /** @lang text */
        'DROP DATABASE IF EXISTS ';

    private const CREATE_DATABASE_SQL_PREFIX
        = /** @lang text */
        'CREATE DATABASE ';

    private const CREATE_DATABASE_IF_NOT_EXISTS_SQL_PREFIX
        = /** @lang text */
        'CREATE DATABASE IF NOT EXISTS ';

    private const CREATE_TABLE_SQL
        = /** @lang text */
        'CREATE TABLE messages (
            id INT AUTO_INCREMENT,
            text TEXT,
            time INTEGER,
            PRIMARY KEY(id)
        )';

    private const INSERT_SQL
        = /** @lang text */
        'INSERT INTO messages (text, time) VALUES (?, ?)';

    private const SELECT_SQL
        = /** @lang text */
        'SELECT * FROM messages';

    public function testPrerequisitesSatisfied(): void
    {
        DebugContext::getCurrentScope(/* out */ $dbgCtx);

        $extensionName = 'mysqli';
        self::assertTrue(extension_loaded($extensionName), 'Required extension ' . $extensionName . ' is not loaded');

        $dbgCtx->pushSubScope();
        foreach (get_object_vars(AmbientContextForTests::testConfig()) as $cfgPropName => $cfgPropValue) {
            if (TextUtil::isPrefixOf('mysql', $cfgPropName)) {
                $dbgCtx->resetTopSubScope(compact('cfgPropName', 'cfgPropValue'));
                self::assertNotNull($cfgPropValue);
            }
        }
        $dbgCtx->popSubScope();

        $mySQLiApiFacade = new MySqliApiFacade(/* isOOPApi */ true);
        $mySQLi = $mySQLiApiFacade->connect(
            AssertEx::notNull(AmbientContextForTests::testConfig()->mysqlHost),
            AssertEx::notNull(AmbientContextForTests::testConfig()->mysqlPort),
            AssertEx::notNull(AmbientContextForTests::testConfig()->mysqlUser),
            AssertEx::notNull(AmbientContextForTests::testConfig()->mysqlPassword),
            AssertEx::notNull(AmbientContextForTests::testConfig()->mysqlDb)
        );
        self::assertNotNull($mySQLi);
        // Method mysqli::ping() is deprecated since PHP 8.4
        if (PHP_VERSION_ID < 80400) {
            self::assertTrue($mySQLi->ping());
        }
    }

    /**
     * @param MySqliWrapped $mySQLi
     * @param string[]      $queries
     * @param string        $kind
     *
     * @return void
     */
    private static function runQueriesUsingKind(MySqliWrapped $mySQLi, array $queries, string $kind): void
    {
        switch ($kind) {
            case self::QUERY_KIND_MULTI_QUERY:
                $multiQuery = '';
                foreach ($queries as $query) {
                    if (!TextUtil::isEmptyString($multiQuery)) {
                        $multiQuery .= ';';
                    }
                    $multiQuery .= $query;
                }
                self::assertTrue($mySQLi->multiQuery($multiQuery));
                while (true) {
                    $result = $mySQLi->storeResult();
                    if ($result === false) {
                        self::assertEmpty($mySQLi->error());
                    } else {
                        $result->close();
                    }
                    if (!$mySQLi->moreResults()) {
                        break;
                    }
                    self::assertTrue($mySQLi->nextResult());
                }
                break;
            case self::QUERY_KIND_REAL_QUERY:
                foreach ($queries as $query) {
                    self::assertTrue($mySQLi->realQuery($query));
                }
                break;
            case self::QUERY_KIND_QUERY:
                foreach ($queries as $query) {
                    self::assertTrue($mySQLi->query($query));
                }
                break;
            default:
                self::fail();
        }
    }

    /**
     * @param MySqliDbSpanDataExpectationsBuilder $expectationsBuilder
     * @param string[]                            $queries
     * @param string                              $kind
     * @param SpanExpectations[]                 &$expectedSpans
     */
    private static function addExpectationsForQueriesUsingKind(
        MySqliDbSpanDataExpectationsBuilder $expectationsBuilder,
        array $queries,
        string $kind,
        array &$expectedSpans
    ): void {
        switch ($kind) {
            case self::QUERY_KIND_MULTI_QUERY:
                $multiQuery = '';
                foreach ($queries as $query) {
                    if (!TextUtil::isEmptyString($multiQuery)) {
                        $multiQuery .= ';';
                    }
                    $multiQuery .= $query;
                }
                $expectedSpans[] = $expectationsBuilder->dbQueryText($multiQuery)->build();
                break;
            case self::QUERY_KIND_QUERY:
            case self::QUERY_KIND_REAL_QUERY:
                foreach ($queries as $query) {
                    $expectedSpans[] = $expectationsBuilder->dbQueryText($query)->build();
                }
                break;
            default:
                self::fail();
        }
    }

    /**
     * @return string[]
     */
    private static function allDbNames(): array
    {
        $defaultDbName = AmbientContextForTests::testConfig()->mysqlDb;
        self::assertNotNull($defaultDbName);
        return [$defaultDbName, $defaultDbName . '_ALT'];
    }

    /**
     * @return string[]
     */
    private static function queriesToResetDbState(): array
    {
        $queries = [];
        foreach (self::allDbNames() as $dbName) {
            $queries[] = self::DROP_DATABASE_IF_EXISTS_SQL_PREFIX . $dbName;
        }
        $queries[] = self::CREATE_DATABASE_SQL_PREFIX . AmbientContextForTests::testConfig()->mysqlDb;
        return $queries;
    }

    private static function resetDbState(MySqliWrapped $mySQLi, string $queryKind): void
    {
        $queries = self::queriesToResetDbState();
        self::runQueriesUsingKind($mySQLi, $queries, $queryKind);
    }

    /**
     * @param MySqliDbSpanDataExpectationsBuilder $expectationsBuilder
     * @param string                              $queryKind
     * @param SpanExpectations[]             &$expectedSpans
     */
    private static function addExpectationsForResetDbState(
        MySqliDbSpanDataExpectationsBuilder $expectationsBuilder,
        string $queryKind,
        /* out */ array &$expectedSpans
    ): void {
        $queries = self::queriesToResetDbState();
        self::addExpectationsForQueriesUsingKind($expectationsBuilder, $queries, $queryKind, /* out */ $expectedSpans);
    }

    /**
     * @return iterable<string, array{MixedMap}>
     */
    public static function dataProviderForTestAutoInstrumentation(): iterable
    {
        /** @var array<?string> $connectDbNameVariants */
        $connectDbNameVariants = [AmbientContextForTests::testConfig()->mysqlDb, null];

        return self::adaptDataProviderForTestBuilderToSmokeToDescToMixedMap(
            (new DataProviderForTestBuilder())
                ->addBoolKeyedDimensionAllValuesCombinable(self::IS_AUTO_INSTRUMENTATION_ENABLED_KEY)
                ->addBoolKeyedDimensionAllValuesCombinable(self::IS_OOP_API_KEY)
                ->addCartesianProductOnlyFirstValueCombinable([self::CONNECT_DB_NAME_KEY => $connectDbNameVariants, self::WORK_DB_NAME_KEY => self::allDbNames()])
                ->addKeyedDimensionOnlyFirstValueCombinable(self::QUERY_KIND_KEY, self::QUERY_KIND_ALL_VALUES)
                ->addGeneratorOnlyFirstValueCombinable(DbAutoInstrumentationUtilForTests::wrapTxRelatedArgsDataProviderGenerator())
        );
    }

    public static function appCodeForTestAutoInstrumentation(MixedMap $appCodeArgs): void
    {
        DebugContext::getCurrentScope(/* out */ $dbgCtx);

        self::assertTrue(extension_loaded('mysqli'));

        $isAutoInstrumentationEnabled = $appCodeArgs->getBool(self::IS_AUTO_INSTRUMENTATION_ENABLED_KEY);
        if ($isAutoInstrumentationEnabled) {
            self::assertTrue(class_exists(MySqliInstrumentation::class, autoload: false));
            self::assertSame(MySqliInstrumentation::NAME, self::AUTO_INSTRUMENTATION_NAME); // @phpstan-ignore staticMethod.alreadyNarrowedType
        }

        $isOOPApi = $appCodeArgs->getBool(self::IS_OOP_API_KEY);
        $connectDbName = $appCodeArgs->getNullableString(self::CONNECT_DB_NAME_KEY);
        $workDbName = $appCodeArgs->getString(self::WORK_DB_NAME_KEY);
        $queryKind = $appCodeArgs->getString(self::QUERY_KIND_KEY);
        $wrapInTx = $appCodeArgs->getBool(DbAutoInstrumentationUtilForTests::WRAP_IN_TX_KEY);
        $rollback = $appCodeArgs->getBool(DbAutoInstrumentationUtilForTests::ROLLBACK_KEY);

        $host = $appCodeArgs->getString(DbAutoInstrumentationUtilForTests::HOST_KEY);
        $port = $appCodeArgs->getInt(DbAutoInstrumentationUtilForTests::PORT_KEY);
        $user = $appCodeArgs->getString(DbAutoInstrumentationUtilForTests::USER_KEY);
        $password = $appCodeArgs->getString(DbAutoInstrumentationUtilForTests::PASSWORD_KEY);

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        $mySQLiApiFacade = new MySqliApiFacade($isOOPApi);
        $mySQLi = $mySQLiApiFacade->connect($host, $port, $user, $password, $connectDbName);
        self::assertNotNull($mySQLi);

        // Method mysqli::ping() is deprecated since PHP 8.4
        if (PHP_VERSION_ID < 80400) {
            self::assertTrue($mySQLi->ping());
        }

        if ($connectDbName !== $workDbName) {
            self::assertTrue($mySQLi->query(self::CREATE_DATABASE_IF_NOT_EXISTS_SQL_PREFIX . $workDbName));
            self::assertTrue($mySQLi->selectDb($workDbName));
        }

        self::assertTrue($mySQLi->query(self::CREATE_TABLE_SQL));

        if ($wrapInTx) {
            self::assertTrue($mySQLi->beginTransaction());
        }

        self::assertNotFalse($stmt = $mySQLi->prepare(self::INSERT_SQL));
        foreach (self::MESSAGES as $msgText => $msgTime) {
            self::assertTrue($stmt->bindParam('si', $msgText, $msgTime));
            self::assertTrue($stmt->execute());
        }
        self::assertTrue($stmt->close());

        self::assertInstanceOf(MySqliResultWrapped::class, $queryResult = $mySQLi->query(self::SELECT_SQL));
        self::assertSame(count(self::MESSAGES), $queryResult->numRows());
        $rowCount = 0;
        while (true) {
            $row = $queryResult->fetchAssoc();
            if (!is_array($row)) {
                self::assertNull($row);
                self::assertSame(count(self::MESSAGES), $rowCount);
                break;
            }
            ++$rowCount;
            $dbgCtx = LoggableToString::convert(['$row' => $row, '$queryResult' => $queryResult]);
            $msgText = $row['text'];
            self::assertIsString($msgText);
            self::assertArrayHasKey($msgText, self::MESSAGES, $dbgCtx);
            self::assertEquals(self::MESSAGES[$msgText], $row['time'], $dbgCtx);
        }
        $queryResult->close();

        if ($wrapInTx) {
            self::assertTrue($rollback ? $mySQLi->rollback() : $mySQLi->commit());
        }

        self::resetDbState($mySQLi, $queryKind);
        self::assertTrue($mySQLi->close());
    }

    private function implTestAutoInstrumentation(MixedMap $testArgs): void
    {
        DebugContext::getCurrentScope(/* out */ $dbgCtx);

        self::assertNotEmpty(self::MESSAGES); // @phpstan-ignore staticMethod.alreadyNarrowedType

        $logger = self::getLoggerStatic(__NAMESPACE__, __CLASS__, __FILE__);
        ($loggerProxy = $logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Entered', ['$testArgs' => $testArgs]);

        $isAutoInstrumentationEnabled = $testArgs->getBool(self::IS_AUTO_INSTRUMENTATION_ENABLED_KEY);

        $isOOPApi = $testArgs->getBool(self::IS_OOP_API_KEY);
        $connectDbName = $testArgs->getNullableString(self::CONNECT_DB_NAME_KEY);
        $workDbName = $testArgs->getString(self::WORK_DB_NAME_KEY);
        $queryKind = $testArgs->getString(self::QUERY_KIND_KEY);
        $wrapInTx = $testArgs->getBool(DbAutoInstrumentationUtilForTests::WRAP_IN_TX_KEY);
        $rollback = $testArgs->getBool(DbAutoInstrumentationUtilForTests::ROLLBACK_KEY);

        $testCaseHandle = $this->getTestCaseHandle();

        $appCodeArgs = $testArgs->clone();

        $appCodeArgs[DbAutoInstrumentationUtilForTests::HOST_KEY] = AmbientContextForTests::testConfig()->mysqlHost;
        $appCodeArgs[DbAutoInstrumentationUtilForTests::PORT_KEY] = AmbientContextForTests::testConfig()->mysqlPort;
        $appCodeArgs[DbAutoInstrumentationUtilForTests::USER_KEY] = AmbientContextForTests::testConfig()->mysqlUser;
        $appCodeArgs[DbAutoInstrumentationUtilForTests::PASSWORD_KEY] = AmbientContextForTests::testConfig()->mysqlPassword;

        $expectationsBuilder = new MySqliDbSpanDataExpectationsBuilder($isOOPApi);
        /** @var SpanExpectations[] $expectedDbSpans */
        $expectedDbSpans = [];
        if ($isAutoInstrumentationEnabled) {
            $expectedDbSpans[] = $expectationsBuilder->setNameUsingApiNames('mysqli', '__construct', 'mysqli_connect')->build();

            // Method mysqli::ping() is deprecated since PHP 8.4
            if (PHP_VERSION_ID < 80400) {
                $expectedDbSpans[] = $expectationsBuilder->setNameUsingApiNames('mysqli', 'ping')->build();
            }

            if ($connectDbName !== $workDbName) {
                $expectedDbSpans[] = $expectationsBuilder->dbQueryText(self::CREATE_DATABASE_IF_NOT_EXISTS_SQL_PREFIX . $workDbName)->build();
                $expectationsBuilder = new MySqliDbSpanDataExpectationsBuilder($isOOPApi);
                $expectedDbSpans[] = $expectationsBuilder->setNameUsingApiNames('mysqli', 'select_db')->build();
            }

            $expectedDbSpans[] = $expectationsBuilder->dbQueryText(self::CREATE_TABLE_SQL)->build();

            if ($wrapInTx) {
                $expectedDbSpans[] = $expectationsBuilder->setNameUsingApiNames('mysqli', 'begin_transaction')->build();
            }

            foreach (self::MESSAGES as $ignored) {
                $expectedDbSpans[] = $expectationsBuilder->dbQueryText(self::INSERT_SQL)->build();
            }

            $expectedDbSpans[] = $expectationsBuilder->dbQueryText(self::SELECT_SQL)->build();

            if ($wrapInTx) {
                $expectedDbSpans[] = $expectationsBuilder->setNameUsingApiNames('mysqli', $rollback ? 'rollback' : 'commit')->build();
            }

            self::addExpectationsForResetDbState($expectationsBuilder, $queryKind, /* out */ $expectedDbSpans);
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
        $exportedData = $testCaseHandle->waitForEnoughExportedData(WaitForEventCounts::spans(1 + count($expectedDbSpans)));
        $dbgCtx->add(compact('exportedData'));

        $actualDbSpans = [];
        foreach ($exportedData->spans as $span) {
            if ($span->attributes->keyExists(TraceAttributes::DB_SYSTEM_NAME)) {
                $actualDbSpans[] = $span;
            }
        }
        (new SpanSequenceExpectations($expectedDbSpans))->assertMatches($actualDbSpans);
    }

    /**
     * @dataProvider dataProviderForTestAutoInstrumentation
     */
    #[Depends('testPrerequisitesSatisfied')]
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
