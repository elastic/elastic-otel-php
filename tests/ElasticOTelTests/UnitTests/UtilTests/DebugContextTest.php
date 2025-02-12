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
use ElasticOTelTests\Util\AssertEx;
use ElasticOTelTests\Util\BoolUtil;
use ElasticOTelTests\Util\DataProviderForTestBuilder;
use ElasticOTelTests\Util\DebugContext;
use ElasticOTelTests\Util\DebugContextConfig;
use ElasticOTelTests\Util\DebugContextScope;
use ElasticOTelTests\Util\FileUtil;
use ElasticOTelTests\Util\IterableUtil;
use ElasticOTelTests\Util\JsonUtil;
use ElasticOTelTests\Util\Log\LoggableToString;
use ElasticOTelTests\Util\MixedMap;
use ElasticOTelTests\Util\Pair;
use ElasticOTelTests\Util\RangeUtil;
use ElasticOTelTests\Util\TestCaseBase;
use ElasticOTelTests\VendorDir;
use Override;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\AssertionFailedError;
use RuntimeException;

use function dummyFuncForTestsWithoutNamespace;
use function ElasticOTelTests\dummyFuncForTestsWithNamespace;

use const ElasticOTelTests\DUMMY_FUNC_FOR_TESTS_WITH_NAMESPACE_CONTINUATION_CALL_LINE;
use const ElasticOTelTests\DUMMY_FUNC_FOR_TESTS_WITH_NAMESPACE_FILE;
use const ElasticOTelTests\DUMMY_FUNC_FOR_TESTS_WITH_NAMESPACE_FUNCTION;

/**
 * @phpstan-import-type ConfigOptionName from DebugContext
 * @phpstan-import-type Context from DebugContext
 * @phpstan-import-type ContextsStack from DebugContext
 * @phpstan-type ExpectedContextsStack list<Pair<string, array<string, mixed>>>
 */
class DebugContextTest extends TestCaseBase
{
    private const NON_VENDOR_CALLS_DEPTH_KEY = 'non_vendor_calls_depth';
    private const USE_FAIL_TO_TRIGGER_ASSERTION_KEY = 'use_fail_to_trigger_assertion';
    private const SHOULD_TOP_NON_VENDOR_CALL_ADD_CONTEXT_KEY = 'add_context_top_non_vendor_call';
    private const SHOULD_MIDDLE_NON_VENDOR_CALL_ADD_CONTEXT_KEY = 'add_context_middle_non_vendor_call';
    private const SHOULD_BOTTOM_NON_VENDOR_CALL_ADD_CONTEXT_KEY = 'add_context_bottom_non_vendor_call';

    #[Override]
    public function setUp(): void
    {
        parent::setUp();

        DebugContextConfig::addToAssertionMessage(false);
    }

    private static function shortcutTestIfDebugContextIsDisabledByDefault(): bool
    {
        // Use BoolUtil::toString to avoid error "boolean expression is always false/true" from static analysis
        if (BoolUtil::toString(DebugContextConfig::ENABLED_DEFAULT_VALUE) === 'false') {
            self::dummyAssert();
            return true;
        }

        return false;
    }

    /**
     * @param ?positive-int $line
     */
    private static function assertScopeDesc(string $actualScopeDesc, ?string $class = __CLASS__, ?string $function = null, ?string $file = __FILE__, ?int $line = null, string $message = ''): void
    {
        $classFuncPart = ($class ?? '') . ($class !== null && $function !== null ? '::' : '') . ($function ?? '');
        if ($classFuncPart !== '') {
            self::assertStringContainsString($classFuncPart, $actualScopeDesc, $message);
        }

        if ($file !== null) {
            self::assertStringContainsString($file . ($line === null ? '' : (':' . $line)), $actualScopeDesc, $message);
        }
    }

    /**
     * @param Context $actualCtx
     * @param Context $args
     * @param Context $addedCtx
     *
     * @noinspection PhpDocSignatureInspection
     */
    private static function assertScopeContext(array $actualCtx, ?object $thisObj, array $args, array $addedCtx): void
    {
        $index = 0;
        if (DebugContextConfig::autoCaptureThis() && ($thisObj !== null)) {
            self::assertTrue(IterableUtil::getNthKey($actualCtx, $index, /* out */ $actualCtxKey));
            self::assertSame(DebugContext::THIS_CONTEXT_KEY, $actualCtxKey);
            self::assertTrue(IterableUtil::getNthValue($actualCtx, $index, /* out */ $actualCtxValue));
            self::assertSame(LoggableToString::convert($thisObj), LoggableToString::convert($actualCtxValue));
            ++$index;
        } else {
            self::assertArrayNotHasKey(DebugContext::THIS_CONTEXT_KEY, $actualCtx);
        }

        if (DebugContextConfig::autoCaptureArgs()) {
            foreach ($args as $argName => $argValue) {
                self::assertTrue(IterableUtil::getNthKey($actualCtx, $index, /* out */ $actualCtxKey));
                self::assertSame($argName, $actualCtxKey);
                self::assertTrue(IterableUtil::getNthValue($actualCtx, $index, /* out */ $actualCtxValue));
                self::assertSame(LoggableToString::convert($argValue), LoggableToString::convert($actualCtxValue));
                ++$index;
            }
        } else {
            foreach (IterableUtil::keys($args) as $argName) {
                self::assertArrayNotHasKey($argName, $actualCtx);
            }
        }

        foreach ($addedCtx as $addedCtxKey => $addedCtxValue) {
            self::assertTrue(IterableUtil::getNthKey($actualCtx, $index, /* out */ $actualCtxKey));
            self::assertSame($addedCtxKey, $actualCtxKey);
            self::assertTrue(IterableUtil::getNthValue($actualCtx, $index, /* out */ $actualCtxValue));
            self::assertSame(LoggableToString::convert($addedCtxValue), LoggableToString::convert($actualCtxValue));
            ++$index;
        }

        self::assertCount($index, $actualCtx);
    }

    /**
     * @param ExpectedContextsStack $expected
     */
    private static function assertCurrentContextsStack(array $expected): void
    {
        $actual = DebugContext::getContextsStack();

        if (!DebugContextConfig::enabled()) {
            self::assertEmpty($actual);
            return;
        }

        /** @var array<string, mixed> $dbgCtx */
        $dbgCtx = [];
        $dbgCtx['count(expected)'] = count($expected);
        $dbgCtx['count(actual)'] = count($actual);
        ArrayUtilForTests::append(compact('expected', 'actual'), /* in,out */ $dbgCtx);
        self::assertSame(count($expected), count($actual), LoggableToString::convert($dbgCtx));
        foreach (IterableUtil::zip(IterableUtil::keys($expected), IterableUtil::keys($actual)) as [$expectedCtxIndex, $actualCtxDesc]) {
            /** @var int $expectedCtxIndex */
            /** @var string $actualCtxDesc */
            $dbgCtx = array_merge($dbgCtx, compact('expectedCtxIndex', 'actualCtxDesc'));
            /** @var array{string, array<string, mixed>} $expectedCtx */
            $expectedCtxFuncName = $expected[$expectedCtxIndex]->first;
            $expectedCtx = $expected[$expectedCtxIndex]->second;
            $actualCtx = $actual[$actualCtxDesc];
            $dbgCtx = array_merge($dbgCtx, compact('expectedCtxFuncName', 'expectedCtx', 'actualCtx'));
            $dbgCtxStr = LoggableToString::convert($dbgCtx);
            self::assertScopeDesc($actualCtxDesc, function: $expectedCtxFuncName, message: $dbgCtxStr);
            self::assertStringContainsString($expectedCtxFuncName, $actualCtxDesc, $dbgCtxStr);
            self::assertStringContainsString(basename(__FILE__) . ':', $actualCtxDesc, $dbgCtxStr);
            self::assertStringContainsString(__CLASS__, $actualCtxDesc, $dbgCtxStr);
            self::assertSame(count($expectedCtx), count($actualCtx), $dbgCtxStr);
            foreach (IterableUtil::zip(IterableUtil::keys($expectedCtx), IterableUtil::keys($actualCtx)) as [$expectedKey, $actualKey]) {
                $dbgCtx = array_merge($dbgCtx, compact('expectedKey', 'actualKey'));
                $dbgCtxStr = LoggableToString::convert($dbgCtx);
                self::assertSame($expectedKey, $actualKey, $dbgCtxStr);
                self::assertSame($expectedCtx[$expectedKey], $actualCtx[$actualKey], $dbgCtxStr);
            }
        }
    }

    /**
     * @param Context               $initialCtx
     * @param ExpectedContextsStack $expectedContextsStackFromCaller
     *
     * @return ExpectedContextsStack
     *
     * @noinspection PhpDocSignatureInspection
     */
    private static function newExpectedScope(string $funcName, array $initialCtx = [], array $expectedContextsStackFromCaller = []): array
    {
        $expectedContextsStack = $expectedContextsStackFromCaller;
        $newCount = array_unshift(/* ref */ $expectedContextsStack, new Pair($funcName, $initialCtx));
        self::assertSame(count($expectedContextsStackFromCaller) + 1, $newCount);
        self::assertCurrentContextsStack($expectedContextsStack);
        return $expectedContextsStack;
    }

    /**
     * @param ExpectedContextsStack $expectedContextsStack
     * @param Context               $ctx
     *
     * @noinspection PhpDocSignatureInspection
     */
    private static function addToTopExpectedScope(/* ref */ array $expectedContextsStack, array $ctx): void
    {
        self::assertNotEmpty($expectedContextsStack);
        DebugContextScope::appendContext(from: $ctx, to: $expectedContextsStack[0]->second);
        self::assertCurrentContextsStack($expectedContextsStack);
    }

    private static function assertActualTopScopeHasKeyWithSameValue(string|int $expectedKey, mixed $expectedValue): void
    {
        $actualContextsStack = DebugContext::getContextsStack();
        if (!DebugContextConfig::enabled()) {
            self::assertEmpty($actualContextsStack);
            return;
        }

        self::assertNotEmpty($actualContextsStack);
        $actualTopScope = ArrayUtilForTests::getFirstValue($actualContextsStack);
        AssertEx::arrayHasKeyWithSameValue($expectedKey, $expectedValue, $actualTopScope);
    }

    /** @noinspection PhpSameParameterValueInspection */
    private static function assertActualTopScopeNotHasKey(string|int $expectedKey): void
    {
        $actualContextsStack = DebugContext::getContextsStack();
        if (!DebugContextConfig::enabled()) {
            self::assertEmpty($actualContextsStack);
            return;
        }

        self::assertNotEmpty($actualContextsStack);
        $actualTopScope = ArrayUtilForTests::getFirstValue($actualContextsStack);
        self::assertArrayNotHasKey($expectedKey, $actualTopScope);
    }

    /**
     * @param array<ConfigOptionName> $optionsToVariate
     */
    private static function dataProviderBuilderForDebugContextConfig(array $optionsToVariate): DataProviderForTestBuilder
    {
        $builder = new DataProviderForTestBuilder();
        foreach ($optionsToVariate as $optionName) {
            $builder->addKeyedDimensionAllValuesCombinable($optionName, BoolUtil::allValuesStartingFrom(DebugContextConfig::DEFAULT_VALUES[$optionName]));
        }
        return $builder;
    }

    private static function setDebugContextConfigFromTestArgs(MixedMap $testArgs): void
    {
        $config = DebugContextConfig::getCopy();
        foreach ($testArgs as $testArgName => $testArgValue) {
            if (array_key_exists($testArgName, DebugContextConfig::DEFAULT_VALUES)) {
                self::assertIsBool($testArgValue);
                $config[$testArgName] = $testArgValue;
            }
        }
        DebugContextConfig::set($config);
    }

    private static function thisTestAssumesOnlyAddedContext(): void
    {
        // DebugContext by default should use all scopes not just the ones with added context
        self::assertFalse(DebugContextConfig::onlyAddedContext());
        // This test assumes that only scopes with added context are used
        DebugContextConfig::onlyAddedContext(true);
    }

    /**
     * @return iterable<string, array{MixedMap}>
     */
    public static function dataProviderForBasicDebugContextConfig(): iterable
    {
        return self::dataProviderBuilderForDebugContextConfig(
            [
                DebugContextConfig::ENABLED_OPTION_NAME,
                DebugContextConfig::USE_DESTRUCTORS_OPTION_NAME,
            ]
        )->buildAsMixedMaps();
    }

    /**
     * @dataProvider dataProviderForBasicDebugContextConfig
     */
    public function testOneFunctionOnlyAddedContext(MixedMap $testArgs): void
    {
        if (self::shortcutTestIfDebugContextIsDisabledByDefault()) {
            return;
        }

        self::setDebugContextConfigFromTestArgs($testArgs);
        self::thisTestAssumesOnlyAddedContext();

        DebugContext::getCurrentScope(/* out */ $dbgCtx, ['my_key' => 1]);
        $expectedContextsStack = self::newExpectedScope(__FUNCTION__, ['my_key' => 1]);
        self::assertActualTopScopeHasKeyWithSameValue('my_key', 1);
        $dbgCtx->add(['my_key' => '2']);
        self::addToTopExpectedScope(/* ref */ $expectedContextsStack, ['my_key' => '2']);
        self::assertActualTopScopeHasKeyWithSameValue('my_key', '2');
        $dbgCtx->add(['my_other_key' => 3.5]);
        self::addToTopExpectedScope(/* ref */ $expectedContextsStack, ['my_other_key' => 3.5]);
        self::assertActualTopScopeHasKeyWithSameValue('my_other_key', 3.5);
    }

    /**
     * @dataProvider dataProviderForBasicDebugContextConfig
     */
    public function testTwoFunctionsOnlyAddedContext(MixedMap $testArgs): void
    {
        if (self::shortcutTestIfDebugContextIsDisabledByDefault()) {
            return;
        }

        self::setDebugContextConfigFromTestArgs($testArgs);
        self::thisTestAssumesOnlyAddedContext();

        DebugContext::getCurrentScope(/* out */ $dbgCtx, ['my context' => 'before func']);
        $expectedContextsStack = self::newExpectedScope(__FUNCTION__, ['my context' => 'before func']);

        /**
         * @param ExpectedContextsStack $expectedContextsStackFromCaller
         */
        $secondFunc = static function (array $expectedContextsStackFromCaller): void {
            /** @var ExpectedContextsStack $expectedContextsStackFromCaller */
            DebugContext::getCurrentScope(/* out */ $dbgCtx, ['my context' => 'func entry']);
            $expectedContextsStack = self::newExpectedScope(__FUNCTION__, ['my context' => 'func entry'], $expectedContextsStackFromCaller);
            self::assertActualTopScopeHasKeyWithSameValue('my context', 'func entry');

            $dbgCtx->add(['some_other_key' => 'inside func']);
            self::addToTopExpectedScope(/* ref */ $expectedContextsStack, ['some_other_key' => 'inside func']);
            self::assertActualTopScopeHasKeyWithSameValue('some_other_key', 'inside func');
        };

        $secondFunc($expectedContextsStack);
        self::assertActualTopScopeHasKeyWithSameValue('my context', 'before func');
        self::assertActualTopScopeNotHasKey('some_other_key');

        $dbgCtx->add(['my context' => 'after func']);
        self::addToTopExpectedScope(/* ref */ $expectedContextsStack, ['my context' => 'after func']);
        self::assertActualTopScopeHasKeyWithSameValue('my context', 'after func');
    }

    /**
     * @dataProvider dataProviderForBasicDebugContextConfig
     */
    public function testSubScopeSimple(MixedMap $testArgs): void
    {
        if (self::shortcutTestIfDebugContextIsDisabledByDefault()) {
            return;
        }

        self::setDebugContextConfigFromTestArgs($testArgs);
        self::thisTestAssumesOnlyAddedContext();

        DebugContext::getCurrentScope(/* out */ $dbgCtx);
        $expectedContextsStack = self::newExpectedScope(__FUNCTION__);
        $dbgCtx->add(['my context' => 'before sub-scope']);
        self::addToTopExpectedScope(/* ref */ $expectedContextsStack, ['my context' => 'before sub-scope']);

        {
            $dbgCtx->add(['my context' => 'inside sub-scope']);
            self::addToTopExpectedScope(/* ref */ $expectedContextsStack, ['my context' => 'inside sub-scope']);
            self::assertActualTopScopeHasKeyWithSameValue('my context', 'inside sub-scope');
        }
        self::assertCurrentContextsStack($expectedContextsStack);
        self::assertActualTopScopeHasKeyWithSameValue('my context', 'inside sub-scope');

        $dbgCtx->add(['my context' => 'after sub-scope']);
        self::addToTopExpectedScope(/* ref */ $expectedContextsStack, ['my context' => 'after sub-scope']);
        self::assertActualTopScopeHasKeyWithSameValue('my context', 'after sub-scope');
    }

    /**
     * @dataProvider dataProviderForBasicDebugContextConfig
     */
    public function testWithLoop(MixedMap $testArgs): void
    {
        if (self::shortcutTestIfDebugContextIsDisabledByDefault()) {
            return;
        }

        self::setDebugContextConfigFromTestArgs($testArgs);
        self::thisTestAssumesOnlyAddedContext();

        DebugContext::getCurrentScope(/* out */ $dbgCtx);
        $expectedContextsStack = self::newExpectedScope(__FUNCTION__);
        $dbgCtx->add(['my context' => 'before loop']);
        self::addToTopExpectedScope(/* ref */ $expectedContextsStack, ['my context' => 'before loop']);

        foreach (RangeUtil::generateUpTo(2) as $index) {
            $dbgCtx->add(compact('index'));
            self::addToTopExpectedScope(/* ref */ $expectedContextsStack, compact('index'));
            $dbgCtx->add(['key_with_index_' . $index => 'value_with_index_' . $index]);
            self::addToTopExpectedScope(/* ref */ $expectedContextsStack, ['key_with_index_' . $index => 'value_with_index_' . $index]);
        }

        $dbgCtx->add(['my context' => 'after loop']);
        self::addToTopExpectedScope(/* ref */ $expectedContextsStack, ['my context' => 'after loop']);
    }

    /**
     * @dataProvider dataProviderForBasicDebugContextConfig
     */
    public function testSubScopeForLoopWithContinue(MixedMap $testArgs): void
    {
        if (self::shortcutTestIfDebugContextIsDisabledByDefault()) {
            return;
        }

        self::setDebugContextConfigFromTestArgs($testArgs);
        self::thisTestAssumesOnlyAddedContext();

        DebugContext::getCurrentScope(/* out */ $dbgCtx);
        $expectedContextsStack = self::newExpectedScope(__FUNCTION__);
        $dbgCtx->add(['my context' => 'before 1st loop']);
        self::addToTopExpectedScope(/* ref */ $expectedContextsStack, ['my context' => 'before 1st loop']);

        foreach (RangeUtil::generateUpTo(3) as $index1stLoop) {
            $dbgCtx->add(compact('index1stLoop'));
            self::addToTopExpectedScope(/* ref */ $expectedContextsStack, compact('index1stLoop'));

            $dbgCtx->add(['my context' => 'before 1st loop']);
            self::addToTopExpectedScope(/* ref */ $expectedContextsStack, ['my context' => 'before 1st loop']);

            $dbgCtx->add(['1st loop key with index ' . $index1stLoop => '1st loop value with index ' . $index1stLoop]);
            self::addToTopExpectedScope(/* ref */ $expectedContextsStack, ['1st loop key with index ' . $index1stLoop => '1st loop value with index ' . $index1stLoop]);

            foreach (RangeUtil::generateUpTo(5) as $index2ndLoop) {
                $dbgCtx->add(compact('index2ndLoop'));
                self::addToTopExpectedScope(/* ref */ $expectedContextsStack, compact('index2ndLoop'));

                if ($index2ndLoop > 2) {
                    continue;
                }

                $dbgCtx->add(['2nd loop key with index ' . $index2ndLoop => '2nd loop value with index ' . $index2ndLoop]);
                self::addToTopExpectedScope(/* ref */ $expectedContextsStack, ['2nd loop key with index ' . $index2ndLoop => '2nd loop value with index ' . $index2ndLoop]);
            }

            if ($index1stLoop > 1) {
                continue;
            }

            $dbgCtx->add(['my context' => 'after 2nd loop']);
            self::addToTopExpectedScope(/* ref */ $expectedContextsStack, ['my context' => 'after 2nd loop']);
        }

        $dbgCtx->add(['my context' => 'after 1st loop']);
        self::addToTopExpectedScope(/* ref */ $expectedContextsStack, ['my context' => 'after 1st loop']);
    }

    /**
     * @param ExpectedContextsStack $expectedContextsStackFromCaller
     */
    private static function recursiveFunc(int $currentDepth, array $expectedContextsStackFromCaller): void
    {
        DebugContext::getCurrentScope(/* out */ $dbgCtx, ['my context' => 'inside recursive func before recursive call']);
        $expectedContextsStack = self::newExpectedScope(__FUNCTION__, ['my context' => 'inside recursive func before recursive call'], $expectedContextsStackFromCaller);

        $dbgCtx->add(['key for depth ' . $currentDepth => 'value for depth ' . $currentDepth]);
        self::addToTopExpectedScope(/* ref */ $expectedContextsStack, ['key for depth ' . $currentDepth => 'value for depth ' . $currentDepth]);

        if ($currentDepth < 3) {
            self::recursiveFunc($currentDepth + 1, $expectedContextsStack);
        }

        $assertMsgCtx = compact('currentDepth', 'expectedContextsStack');
        $depth = $currentDepth;
        foreach (DebugContext::getContextsStack() as $actualCtxDesc => $actualCtx) {
            $assertMsgCtx = array_merge($assertMsgCtx, compact('depth', 'actualCtxDesc', 'actualCtx'));
            self::assertStringContainsString(__FUNCTION__, $actualCtxDesc, LoggableToString::convert($assertMsgCtx));
            self::assertStringContainsString(basename(__FILE__), $actualCtxDesc, LoggableToString::convert($assertMsgCtx));
            self::assertStringContainsString(__CLASS__, $actualCtxDesc, LoggableToString::convert($assertMsgCtx));

            self::assertSame('inside recursive func before recursive call', $actualCtx['my context'], LoggableToString::convert($assertMsgCtx));
            self::assertSame('value for depth ' . $depth, $actualCtx['key for depth ' . $depth], LoggableToString::convert($assertMsgCtx));

            if ($depth === 1) {
                break;
            }
            --$depth;
        }

        $dbgCtx->add(['my context' => 'inside recursive func after recursive call']);
        self::addToTopExpectedScope(/* ref */ $expectedContextsStack, ['my context' => 'inside recursive func after recursive call']);
    }

    /**
     * @dataProvider dataProviderForBasicDebugContextConfig
     */
    public function testRecursiveFunc(MixedMap $testArgs): void
    {
        if (self::shortcutTestIfDebugContextIsDisabledByDefault()) {
            return;
        }

        self::setDebugContextConfigFromTestArgs($testArgs);
        self::thisTestAssumesOnlyAddedContext();

        DebugContext::getCurrentScope(/* out */ $dbgCtx, ['my context' => 'before recursive func']);
        $expectedContextsStack = self::newExpectedScope(__FUNCTION__, ['my context' => 'before recursive func']);

        self::recursiveFunc(1, $expectedContextsStack);
    }

    /**
     * @dataProvider dataProviderForBasicDebugContextConfig
     */
    public function testCaptureVarByRef(MixedMap $testArgs): void
    {
        if (self::shortcutTestIfDebugContextIsDisabledByDefault()) {
            return;
        }

        self::setDebugContextConfigFromTestArgs($testArgs);
        self::thisTestAssumesOnlyAddedContext();

        DebugContext::getCurrentScope(/* out */ $dbgCtx);

        $localVar = 1;
        // NOTE: $localVar is added to debug context by reference below
        $dbgCtx->add(['localVar' => &$localVar]);

        $localVar = 2;

        $capturedCtxStack = DebugContext::getContextsStack();
        if (DebugContextConfig::enabled()) {
            self::assertCount(1, $capturedCtxStack);
            $thisFuncCtx = ArrayUtilForTests::getFirstValue($capturedCtxStack);
            self::assertArrayHasKey('localVar', $thisFuncCtx);
            self::assertSame(2, $thisFuncCtx['localVar']);
        } else {
            self::assertEmpty($capturedCtxStack);
        }
    }

    /**
     * @return iterable<string, array{MixedMap}>
     */
    public static function dataProviderForTestFailedAssertionMessageContainsDebugContext(): iterable
    {
        return self::dataProviderBuilderForDebugContextConfig(
            [
                DebugContextConfig::ENABLED_OPTION_NAME,
                DebugContextConfig::USE_DESTRUCTORS_OPTION_NAME,
                DebugContextConfig::ADD_TO_ASSERTION_MESSAGE_OPTION_NAME,
            ]
        )->buildAsMixedMaps();
    }

    /**
     * @dataProvider dataProviderForTestFailedAssertionMessageContainsDebugContext
     */
    public function testFailedAssertionMessageContainsDebugContext(MixedMap $testArgs): void
    {
        if (self::shortcutTestIfDebugContextIsDisabledByDefault()) {
            return;
        }

        self::setDebugContextConfigFromTestArgs($testArgs);
        self::thisTestAssumesOnlyAddedContext();

        DebugContext::getCurrentScope(/* out */ $dbgCtx);

        $dbgCtx->add(['myLocalVar' => 'my localVar value']);

        $exceptionMsg = null;
        try {
            self::fail();
        } catch (AssertionFailedError $ex) {
            $exceptionMsg = $ex->getMessage();
        }

        $expectedMessageContains = DebugContextConfig::enabled() && DebugContextConfig::addToAssertionMessage();
        $helperCtxStr = LoggableToString::convert(compact('expectedMessageContains', 'exceptionMsg'));
        self::assertSame($expectedMessageContains, str_contains($exceptionMsg, 'myLocalVar'), $helperCtxStr);
        self::assertSame($expectedMessageContains, str_contains($exceptionMsg, 'my localVar value'), $helperCtxStr);
    }

    /**
     * @dataProvider dataProviderForBasicDebugContextConfig
     */
    public function testLineInScopeDescUpdated(MixedMap $testArgs): void
    {
        if (self::shortcutTestIfDebugContextIsDisabledByDefault()) {
            return;
        }

        self::setDebugContextConfigFromTestArgs($testArgs);

        DebugContext::getCurrentScope(/* out */ $dbgCtx);

        // Add dummy context entry to ensure this scope is included
        $dbgCtx->add(['dummy key' => 'dummy value']);

        /**
         * @param positive-int  $expectedLine
         * @param ContextsStack $contextsStack
         *
         * @noinspection PhpDocSignatureInspection
         */
        $assertTopScopeLine = function (int $expectedLine): void {
            $contextsStack = DebugContext::getContextsStack();
            if (DebugContextConfig::enabled()) {
                if (DebugContextConfig::onlyAddedContext()) {
                    $testFuncScopeDesc = array_key_first($contextsStack);
                } else {
                    self::assertTrue(IterableUtil::getNthKey($contextsStack, 1, /* out */ $testFuncScopeDesc));
                }
                self::assertIsString($testFuncScopeDesc);
                self::assertStringContainsString(__FILE__ . ':' . $expectedLine, $testFuncScopeDesc);
            } else {
                self::assertEmpty($contextsStack);
            }
        };

        $assertTopScopeLine(__LINE__);
        $assertTopScopeLine(__LINE__);
    }

    /**
     * @param int     $intParam
     * @param ?string $nullableStringParam
     *
     * @return ContextsStack
     *
     * @noinspection PhpUnusedParameterInspection
     */
    private static function helperFuncForTestAutoCaptureArgs(int $intParam, ?string $nullableStringParam): array
    {
        return DebugContext::getContextsStack();
    }

    /**
     * @return iterable<string, array{MixedMap}>
     */
    public static function dataProviderForTestAutoCaptureArgs(): iterable
    {
        $dummyArgsTuples = [
            ['intParam' => 1, 'nullableStringParam' => 'abc'],
            ['intParam' => 2, 'nullableStringParam' => null],
        ];

        /**
         * @param array<mixed> $resultSoFar
         *
         * @return iterable<array<mixed>>
         */
        $addDummyArgs = function (array $resultSoFar) use ($dummyArgsTuples): iterable {
            foreach ($dummyArgsTuples as $dummyArgsTuple) {
                yield array_merge($resultSoFar, $dummyArgsTuple);
            }
        };

        $optionsToVariate = [
            DebugContextConfig::ENABLED_OPTION_NAME,
            DebugContextConfig::USE_DESTRUCTORS_OPTION_NAME,
            DebugContextConfig::ONLY_ADDED_CONTEXT_OPTION_NAME,
            DebugContextConfig::AUTO_CAPTURE_THIS_OPTION_NAME,
            DebugContextConfig::AUTO_CAPTURE_ARGS_OPTION_NAME,
        ];
        return self::dataProviderBuilderForDebugContextConfig($optionsToVariate)
                   ->addGeneratorOnlyFirstValueCombinable($addDummyArgs)
                   ->buildAsMixedMaps();
    }

    /**
     * @dataProvider dataProviderForTestAutoCaptureArgs
     */
    public function testAutoCaptureArgs(MixedMap $testArgs): void
    {
        if (self::shortcutTestIfDebugContextIsDisabledByDefault()) {
            return;
        }

        self::setDebugContextConfigFromTestArgs($testArgs);

        DebugContext::getCurrentScope(/* out */ $dbgCtx);
        $myDummyLocalVar = 'my dummy local var value';
        $dbgCtx->add(compact('myDummyLocalVar'));

        $actualContextsStack = self::helperFuncForTestAutoCaptureArgs($testArgs->getInt('intParam'), $testArgs->getNullableString('nullableStringParam'));

        if (!DebugContextConfig::enabled()) {
            self::assertEmpty($actualContextsStack);
            return;
        }
        AssertEx::countAtLeast(DebugContextConfig::onlyAddedContext() ? 1 : 2, $actualContextsStack);

        if (!DebugContextConfig::onlyAddedContext()) {
            $helperFuncCtx = ArrayUtilForTests::getFirstValue($actualContextsStack);
            // helperFuncForTestAutoCaptureArgs is static so there is no $this
            self::assertArrayNotHasKey(DebugContext::THIS_CONTEXT_KEY, $helperFuncCtx);
            foreach (['intParam', 'nullableStringParam'] as $argName) {
                if (DebugContextConfig::autoCaptureArgs()) {
                    AssertEx::arrayHasKeyWithSameValue($argName, $testArgs->get($argName), $helperFuncCtx);
                } else {
                    self::assertArrayNotHasKey($argName, $helperFuncCtx);
                }
            }
        }

        self::assertTrue(IterableUtil::getNthValue($actualContextsStack, DebugContextConfig::onlyAddedContext() ? 0 : 1, /* out */ $thisFuncCtx));
        self::assertIsArray($thisFuncCtx);
        AssertEx::arrayHasKeyWithSameValue('myDummyLocalVar', $myDummyLocalVar, $thisFuncCtx);
        if (!DebugContextConfig::onlyAddedContext() && DebugContextConfig::autoCaptureThis()) {
            AssertEx::arrayHasKeyWithSameValue(DebugContext::THIS_CONTEXT_KEY, $this, $thisFuncCtx);
        } else {
            self::assertArrayNotHasKey(DebugContext::THIS_CONTEXT_KEY, $thisFuncCtx);
        }
        if (!DebugContextConfig::onlyAddedContext() && DebugContextConfig::autoCaptureArgs()) {
            AssertEx::arrayHasKeyWithSameValue('testArgs', $testArgs, $thisFuncCtx);
        } else {
            self::assertArrayNotHasKey('testArgs', $thisFuncCtx);
        }
    }

    /**
     * @dataProvider dataProviderForBasicDebugContextConfig
     */
    public function testTheSameClosureCalledTwiceOnTheSameLine(MixedMap $testArgs): void
    {
        if (self::shortcutTestIfDebugContextIsDisabledByDefault()) {
            return;
        }

        self::setDebugContextConfigFromTestArgs($testArgs);

        $capturedVar = 0;
        /** @var list<ContextsStack> $contextsStacks */
        $contextsStacks = [];
        $closure = function () use (&$capturedVar, &$contextsStacks): int {
            DebugContext::getCurrentScope(/* out */ $dbgCtx);
            $dbgCtx->add(['ctx_' . $capturedVar => 'value for ctx_' . $capturedVar]);
            $contextsStacks[] = DebugContext::getContextsStack();
            return $capturedVar++;
        };

        $thisFuncLine = __LINE__ + 1;
        self::assertSame(1, $closure() + $closure());

        self::assertCount(2, $contextsStacks);
        foreach ([0, 1] as $capturedVarVal) {
            $contextsStack = $contextsStacks[$capturedVarVal];

            if (!DebugContextConfig::enabled()) {
                self::assertEmpty($contextsStack);
                continue;
            }

            self::assertTrue(IterableUtil::getNthKey($contextsStack, 0, /* out */ $closureCtxDesc));
            /** @var string $closureCtxDesc */
            self::assertStringContainsString(__FILE__ . ':', $closureCtxDesc);
            $closureCtx = ArrayUtilForTests::getFirstValue($contextsStack);
            self::assertCount(2, $closureCtx);
            AssertEx::arrayHasKeyWithSameValue(DebugContext::THIS_CONTEXT_KEY, $this, $closureCtx);
            AssertEx::arrayHasKeyWithSameValue('ctx_' . $capturedVarVal, 'value for ctx_' . $capturedVarVal, $closureCtx);

            self::assertTrue(IterableUtil::getNthKey($contextsStack, 1, /* out */ $thisFuncCtxDesc));
            /** @var string $thisFuncCtxDesc */
            self::assertScopeDesc($thisFuncCtxDesc, function: __FUNCTION__, line: $thisFuncLine);
            self::assertTrue(IterableUtil::getNthValue($contextsStack, 1, /* out */ $thisFuncCtx));
            /** @var Context $thisFuncCtx */
            self::assertCount(2, $thisFuncCtx);
            AssertEx::arrayHasKeyWithSameValue(DebugContext::THIS_CONTEXT_KEY, $this, $thisFuncCtx);
            AssertEx::arrayHasKeyWithSameValue('testArgs', $testArgs, $thisFuncCtx);
        }
    }

    private static function extractTextAddedToAssertionMessage(string $exceptionMsg): string
    {
        $prefixPos = strpos($exceptionMsg, DebugContext::TEXT_ADDED_TO_ASSERTION_MESSAGE_PREFIX);
        self::assertNotFalse($prefixPos);
        $afterPrefixPos = $prefixPos + strlen(DebugContext::TEXT_ADDED_TO_ASSERTION_MESSAGE_PREFIX);
        $suffixPos = strpos($exceptionMsg, DebugContext::TEXT_ADDED_TO_ASSERTION_MESSAGE_SUFFIX, offset: $afterPrefixPos);
        self::assertNotFalse($suffixPos);
        return trim(substr($exceptionMsg, $afterPrefixPos, $suffixPos - $afterPrefixPos));
    }

    /**
     * @return iterable<string, array{MixedMap}>
     */
    public static function dataProviderForTestTrimVendorFrames(): iterable
    {
        $optionsToVariate = [
            DebugContextConfig::TRIM_VENDOR_FRAMES_OPTION_NAME,
        ];
        return self::dataProviderBuilderForDebugContextConfig($optionsToVariate)
                   ->addKeyedDimensionAllValuesCombinable(self::NON_VENDOR_CALLS_DEPTH_KEY, DataProviderForTestBuilder::rangeFromToIncluding(1, 6))
                   ->addBoolKeyedDimensionAllValuesCombinable(self::SHOULD_TOP_NON_VENDOR_CALL_ADD_CONTEXT_KEY)
                   ->addBoolKeyedDimensionOnlyFirstValueCombinable(self::SHOULD_MIDDLE_NON_VENDOR_CALL_ADD_CONTEXT_KEY)
                   ->addBoolKeyedDimensionOnlyFirstValueCombinable(self::SHOULD_BOTTOM_NON_VENDOR_CALL_ADD_CONTEXT_KEY)
                    // Use Assert::assertXYZ with all the combinations in other dimensions and Assert::fail only with the first tuple of the combinations in other dimensions
                   ->addKeyedDimensionOnlyFirstValueCombinable(self::USE_FAIL_TO_TRIGGER_ASSERTION_KEY, BoolUtil::allValuesStartingFrom(false))
                   ->buildAsMixedMaps();
    }

    private const HELPER_FUNC_FOR_TEST_TRIM_VENDOR_FRAMES_NAME = 'helperFuncForTestTrimVendorFrames';

    /**
     * @param positive-int &$actualNonVendorCallDepth
     * @param ?positive-int &$lineNumber
     *
     * @param-out positive-int $actualNonVendorCallDepth
     * @param-out positive-int $lineNumber
     *
     * @noinspection PhpDocSignatureInspection
     */
    private static function helperFuncForTestTrimVendorFrames(MixedMap $testArgs, /* out */ ?int &$lineNumber, /* in,out */ int &$actualNonVendorCallDepth): void
    {
        DebugContext::getCurrentScope(/* out */ $dbgCtx);

        self::assertNull($lineNumber);
        self::assertGreaterThanOrEqual(1, $actualNonVendorCallDepth);
        self::assertSame(self::HELPER_FUNC_FOR_TEST_TRIM_VENDOR_FRAMES_NAME, __FUNCTION__); // @phpstan-ignore staticMethod.alreadyNarrowedType

        ++$actualNonVendorCallDepth;

        $shouldTopNonVendorCallAddContext = $testArgs->getBool(self::SHOULD_TOP_NON_VENDOR_CALL_ADD_CONTEXT_KEY);
        $useFailToTriggerAssertion = $testArgs->getBool(self::USE_FAIL_TO_TRIGGER_ASSERTION_KEY);

        if ($shouldTopNonVendorCallAddContext) {
            $dbgCtx->add(['dummy ctx in top non-vendor call' => '[value for dummy ctx in top non-vendor call]']);
        }

        /** @noinspection PhpIfWithCommonPartsInspection */
        if ($useFailToTriggerAssertion) {
            $lineNumber = __LINE__ + 1;
            self::fail('Dummy message');
        } else {
            $lineNumber = __LINE__ + 1;
            self::assertSame(1, 2); // @phpstan-ignore staticMethod.impossibleType
        }
    }

    /**
     * @dataProvider dataProviderForTestTrimVendorFrames
     */
    public function testTrimVendorFrames(MixedMap $testArgs): void
    {
        if (self::shortcutTestIfDebugContextIsDisabledByDefault()) {
            return;
        }

        DebugContext::getCurrentScope(/* out */ $dbgCtx);

        self::setDebugContextConfigFromTestArgs($testArgs);

        // This test extracts debug context from text added to assertion message
        DebugContextConfig::addToAssertionMessage(true);

        $nonVendorCallsDepth = $testArgs->getInt(self::NON_VENDOR_CALLS_DEPTH_KEY);
        AssertEx::inClosedRange(1, $nonVendorCallsDepth, 6);
        /** @var int<1, 6> $nonVendorCallsDepth */
        $shouldTopNonVendorCallAddContext = $testArgs->getBool(self::SHOULD_TOP_NON_VENDOR_CALL_ADD_CONTEXT_KEY);
        $shouldMiddleNonVendorCallAddContext = $testArgs->getBool(self::SHOULD_MIDDLE_NON_VENDOR_CALL_ADD_CONTEXT_KEY);
        $shouldBottomNonVendorCallAddContext = $testArgs->getBool(self::SHOULD_BOTTOM_NON_VENDOR_CALL_ADD_CONTEXT_KEY);
        $useFailToTriggerAssertion = $testArgs->getBool(self::USE_FAIL_TO_TRIGGER_ASSERTION_KEY);

        if ($shouldBottomNonVendorCallAddContext) {
            $dbgCtx->add(['dummy ctx in bottom non-vendor call' => '[value for dummy ctx in bottom non-vendor call]']);
        }

        $actualNonVendorCallDepth = 1;

        /** @var ?positive-int $helperFuncLine */
        $helperFuncLine = null;
        /** @var ?positive-int $closureCallingHelperFuncLine */
        $closureCallingHelperFuncLine = null;
        $closureCallingHelperFunc = static function () use ($testArgs, /* out */ &$helperFuncLine, /* out */ &$closureCallingHelperFuncLine, /* in,out */ &$actualNonVendorCallDepth): void {
            ++$actualNonVendorCallDepth;
            self::assertNull($closureCallingHelperFuncLine);
            $closureCallingHelperFuncLine = __LINE__ + 1;
            self::helperFuncForTestTrimVendorFrames($testArgs, /* out */ $helperFuncLine, /* in,out */ $actualNonVendorCallDepth);
        };

        /** @var ?positive-int $closureCallingDummyFuncWithoutNamespaceLine */
        $closureCallingDummyFuncWithoutNamespaceLine = null;
        $closureCallingDummyFuncWithoutNamespace = function () use (
            $closureCallingHelperFunc,
            $shouldMiddleNonVendorCallAddContext,
            &$closureCallingDummyFuncWithoutNamespaceLine,
            &$actualNonVendorCallDepth,
        ): void {
            DebugContext::getCurrentScope(/* out */ $dbgCtx);
            if ($shouldMiddleNonVendorCallAddContext) {
                $dbgCtx->add(['dummy ctx in middle non-vendor call' => '[value for dummy ctx in middle non-vendor call]']);
            }

            // Add 2 because it's this closure and dummyFuncForTestsWithoutNamespace
            $actualNonVendorCallDepth += 2;
            self::assertNull($closureCallingDummyFuncWithoutNamespaceLine);
            $closureCallingDummyFuncWithoutNamespaceLine = __LINE__ + 1;
            dummyFuncForTestsWithoutNamespace($closureCallingHelperFunc);
        };

        /** @var ?string $assertionMsg */
        $assertionMsg = null;
        /** @var ?positive-int $testFuncLine */
        $testFuncLine = null;
        try {
            switch ($nonVendorCallsDepth) {
                case 1:
                    // [testFunc]
                    /** @noinspection PhpIfWithCommonPartsInspection */
                    if ($useFailToTriggerAssertion) {
                        $testFuncLine = __LINE__ + 1;
                        self::fail('Dummy message');
                    } else {
                        $testFuncLine = __LINE__ + 1;
                        self::assertSame(1, 2); // @phpstan-ignore staticMethod.impossibleType
                    }
                    break;
                case 2:
                    // [testFunc, helperFunc]
                    $testFuncLine = __LINE__ + 1;
                    self::helperFuncForTestTrimVendorFrames($testArgs, /* out */ $helperFuncLine, /* in,out */ $actualNonVendorCallDepth);
                    break;
                case 3:
                    // [testFunc, closureCallingHelperFunc, helperFunc]
                    $testFuncLine = __LINE__ + 1;
                    $closureCallingHelperFunc();
                    break;
                case 4:
                    ++$actualNonVendorCallDepth; // to account for dummyFuncForTestsWithoutNamespace call
                    // [testFunc, dummyFuncForTestsWithoutNamespace, closureCallingHelperFunc, helperFunc]
                    $testFuncLine = __LINE__ + 1;
                    dummyFuncForTestsWithoutNamespace($closureCallingHelperFunc);
                    break;
                case 5:
                    // [testFunc, closureCallingDummyFuncWithoutNamespace, dummyFuncForTestsWithoutNamespace, closureCallingHelperFunc, helperFunc]
                    $testFuncLine = __LINE__ + 1;
                    $closureCallingDummyFuncWithoutNamespace();
                    break;
                case 6:
                    // [testFunc, dummyFuncForTestsWithNamespace, closureCallingDummyFuncWithoutNamespace, dummyFuncForTestsWithoutNamespace, closureCallingHelperFunc, helperFunc]
                    ++$actualNonVendorCallDepth; // to account for dummyFuncForTestsWithNamespace call
                    $testFuncLine = __LINE__ + 1;
                    dummyFuncForTestsWithNamespace($closureCallingDummyFuncWithoutNamespace);
                    break;
                default:
                    throw new RuntimeException('Unexpected nonVendorCallsDepth value: ' . $nonVendorCallsDepth);
            }
        } catch (AssertionFailedError $ex) {
            $assertionMsg = $ex->getMessage();
        }
        $dbgCtx->add(compact('assertionMsg'));
        self::assertNotNull($assertionMsg);
        self::assertNotNull($testFuncLine);
        self::assertSame($nonVendorCallsDepth, $actualNonVendorCallDepth);

        $addedText = self::extractTextAddedToAssertionMessage($assertionMsg);

        if (!DebugContextConfig::enabled()) {
            self::assertSame(DebugContext::TEXT_ADDED_TO_ASSERTION_MESSAGE_WHEN_DISABLED, $addedText);
            return;
        }

        $decodedContextsStack = JsonUtil::decode($addedText, asAssocArray: true);
        self::assertIsArray($decodedContextsStack);

        // Contexts in $decodedContextsStack is the order of the top call being at index 0
        if (DebugContextConfig::trimVendorFrames()) {
            self::assertCount($nonVendorCallsDepth + 1, $decodedContextsStack);
            $testFuncCtxIndex = $nonVendorCallsDepth;
        } else {
            // <PHPUnit's main> ... calls ... <this test func> ... calls ... Assert::xyz()
            AssertEx::countAtLeast($nonVendorCallsDepth + 2, $decodedContextsStack);
            $testFuncCtxIndex = null;
            /** @var non-negative-int $scopeIndex */
            /** @var string $scopeDesc */
            foreach (IterableUtil::zipWithIndex(IterableUtil::keys($decodedContextsStack)) as [$scopeIndex, $scopeDesc]) {
                if (str_contains(haystack: $scopeDesc, needle: __FUNCTION__)) {
                    $testFuncCtxIndex = $scopeIndex;
                    break;
                }
            }
            self::assertIsInt($testFuncCtxIndex);
        }
        self::assertGreaterThanOrEqual($nonVendorCallsDepth, $testFuncCtxIndex);

        // 1: [testFunc]
        // 2: [testFunc, helperFunc]
        // 3: [testFunc, closureCallingHelperFunc, helperFunc]
        // 4: [testFunc, dummyFuncForTestsWithoutNamespace, closureCallingHelperFunc, helperFunc]
        // 5: [testFunc, closureCallingDummyFuncWithoutNamespace, dummyFuncForTestsWithoutNamespace, closureCallingHelperFunc, helperFunc]
        // 6: [testFunc, dummyFuncForTestsWithNamespace, closureCallingDummyFuncWithoutNamespace, dummyFuncForTestsWithoutNamespace, closureCallingHelperFunc, helperFunc]

        $scopeIndex = $testFuncCtxIndex;

        self::assertTrue(IterableUtil::getNthKey($decodedContextsStack, $scopeIndex, /* out */ $testFuncScopeDesc));
        self::assertIsString($testFuncScopeDesc);
        self::assertScopeDesc($testFuncScopeDesc, function: __FUNCTION__, line: $testFuncLine);
        self::assertTrue(IterableUtil::getNthValue($decodedContextsStack, $scopeIndex, /* out */ $testFuncScopeCtx));
        self::assertIsArray($testFuncScopeCtx);
        /** @var Context $testFuncScopeCtx */
        $addedCtx = $shouldBottomNonVendorCallAddContext ? ['dummy ctx in bottom non-vendor call' => '[value for dummy ctx in bottom non-vendor call]'] : [];
        self::assertScopeContext($testFuncScopeCtx, thisObj: $this, args: compact('testArgs'), addedCtx: $addedCtx);
        --$scopeIndex;

        if ($nonVendorCallsDepth >= 6) {
            self::assertTrue(IterableUtil::getNthKey($decodedContextsStack, $scopeIndex, /* out */ $dummyFuncForTestsWithNamespaceScopeDesc));
            self::assertIsString($dummyFuncForTestsWithNamespaceScopeDesc);
            self::assertScopeDesc(
                actualScopeDesc: $dummyFuncForTestsWithNamespaceScopeDesc,
                class:           null,
                function:        DUMMY_FUNC_FOR_TESTS_WITH_NAMESPACE_FUNCTION,
                file:            DUMMY_FUNC_FOR_TESTS_WITH_NAMESPACE_FILE,
                line:            DUMMY_FUNC_FOR_TESTS_WITH_NAMESPACE_CONTINUATION_CALL_LINE,
            );

            self::assertTrue(IterableUtil::getNthValue($decodedContextsStack, $scopeIndex, /* out */ $dummyFuncForTestsWithNamespaceScopeCtx));
            self::assertIsArray($dummyFuncForTestsWithNamespaceScopeCtx);
            /** @var Context $dummyFuncForTestsWithNamespaceScopeCtx */
            // dummyFuncForTestsWithNamespace is freestanding function (i.e., not a function that is a method in a class)
            self::assertScopeContext($dummyFuncForTestsWithNamespaceScopeCtx, thisObj: null, args: ['continuation' => $closureCallingDummyFuncWithoutNamespace], addedCtx: []);
            --$scopeIndex;
        }

        if ($nonVendorCallsDepth >= 5) {
            self::assertTrue(IterableUtil::getNthKey($decodedContextsStack, $scopeIndex, /* out */ $closureCallingDummyFuncWithoutNamespaceScopeDesc));
            self::assertIsString($closureCallingDummyFuncWithoutNamespaceScopeDesc);
            self::assertNotNull($closureCallingDummyFuncWithoutNamespaceLine);
            self::assertScopeDesc($closureCallingDummyFuncWithoutNamespaceScopeDesc, line: $closureCallingDummyFuncWithoutNamespaceLine);

            self::assertTrue(IterableUtil::getNthValue($decodedContextsStack, $scopeIndex, /* out */ $closureCallingDummyFuncWithoutNamespaceScopeCtx));
            self::assertIsArray($closureCallingDummyFuncWithoutNamespaceScopeCtx);
            /** @var Context $closureCallingDummyFuncWithoutNamespaceScopeCtx */
            $addedCtx = $shouldMiddleNonVendorCallAddContext ? ['dummy ctx in middle non-vendor call' => '[value for dummy ctx in middle non-vendor call]'] : [];
            self::assertScopeContext($closureCallingDummyFuncWithoutNamespaceScopeCtx, thisObj: $this, args: [], addedCtx: $addedCtx);
            --$scopeIndex;
        }

        if ($nonVendorCallsDepth >= 4) {
            self::assertTrue(IterableUtil::getNthKey($decodedContextsStack, $scopeIndex, /* out */ $dummyFuncForTestsWithoutNamespaceScopeDesc));
            self::assertIsString($dummyFuncForTestsWithoutNamespaceScopeDesc);
            self::assertScopeDesc(
                actualScopeDesc: $dummyFuncForTestsWithoutNamespaceScopeDesc,
                class:           null,
                function:        ELASTIC_OTEL_TESTS_DUMMY_FUNC_FOR_TESTS_WITHOUT_NAMESPACE_FUNCTION,
                file:            ELASTIC_OTEL_TESTS_DUMMY_FUNC_FOR_TESTS_WITHOUT_NAMESPACE_FILE,
                line:            ELASTIC_OTEL_TESTS_DUMMY_FUNC_FOR_TESTS_WITHOUT_NAMESPACE_CONTINUATION_CALL_LINE,
            );

            self::assertTrue(IterableUtil::getNthValue($decodedContextsStack, $scopeIndex, /* out */ $dummyFuncForTestsWithoutNamespaceScopeCtx));
            self::assertIsArray($dummyFuncForTestsWithoutNamespaceScopeCtx);
            /** @var Context $dummyFuncForTestsWithoutNamespaceScopeCtx */
            // dummyFuncForTestsWithoutNamespace is freestanding function (i.e., not a function that is a method in a class)
            self::assertScopeContext($dummyFuncForTestsWithoutNamespaceScopeCtx, thisObj: null, args: ['continuation' => $closureCallingHelperFunc], addedCtx: []);
            --$scopeIndex;
        }

        if ($nonVendorCallsDepth >= 3) {
            self::assertTrue(IterableUtil::getNthKey($decodedContextsStack, $scopeIndex, /* out */ $closureCallingHelperFuncScopeDesc));
            self::assertIsString($closureCallingHelperFuncScopeDesc);
            self::assertNotNull($closureCallingHelperFuncLine);
            self::assertScopeDesc($closureCallingHelperFuncScopeDesc, line: $closureCallingHelperFuncLine);

            self::assertTrue(IterableUtil::getNthValue($decodedContextsStack, $scopeIndex, /* out */ $closureCallingHelperFuncScopeCtx));
            self::assertIsArray($closureCallingHelperFuncScopeCtx);
            /** @var Context $closureCallingHelperFuncScopeCtx */
            // $closureCallingHelperFunc is static so there is no $this
            self::assertScopeContext($closureCallingHelperFuncScopeCtx, thisObj: null, args: [], addedCtx: []);
            --$scopeIndex;
        }

        if ($nonVendorCallsDepth >= 2) {
            self::assertTrue(IterableUtil::getNthKey($decodedContextsStack, $scopeIndex, /* out */ $helperFuncScopeDesc));
            self::assertIsString($helperFuncScopeDesc);
            self::assertNotNull($helperFuncLine);
            self::assertScopeDesc($helperFuncScopeDesc, function: self::HELPER_FUNC_FOR_TEST_TRIM_VENDOR_FRAMES_NAME, line: $helperFuncLine);

            self::assertTrue(IterableUtil::getNthValue($decodedContextsStack, $scopeIndex, /* out */ $helperFuncScopeCtx));
            self::assertIsArray($helperFuncScopeCtx);
            /** @var Context $helperFuncScopeCtx */
            $helperFuncArgs = compact('testArgs');
            $helperFuncArgs['lineNumber'] = $helperFuncLine;
            $helperFuncArgs['actualNonVendorCallDepth'] = $nonVendorCallsDepth;
            $addedCtx = $shouldTopNonVendorCallAddContext ? ['dummy ctx in top non-vendor call' => '[value for dummy ctx in top non-vendor call]'] : [];
            // helperFuncForTestTrimVendorFrames is static so there is no $this
            self::assertScopeContext($helperFuncScopeCtx, thisObj:null, args: $helperFuncArgs, addedCtx: $addedCtx);
            --$scopeIndex;
        }

        self::assertSame($nonVendorCallsDepth, $testFuncCtxIndex - $scopeIndex);

        self::assertTrue(IterableUtil::getNthKey($decodedContextsStack, $scopeIndex, /* out */ $assertionFuncScopeDesc));
        self::assertIsString($assertionFuncScopeDesc);
        self::assertScopeDesc($assertionFuncScopeDesc, class: Assert::class, function: $useFailToTriggerAssertion ? 'fail' : 'assertSame', file: null);

        self::assertTrue(IterableUtil::getNthValue($decodedContextsStack, $scopeIndex, /* out */ $assertionFuncScopeCtx));
        self::assertIsArray($assertionFuncScopeCtx);
        /** @var Context $assertionFuncScopeCtx */
        $assertionFuncArgs = $useFailToTriggerAssertion ? ['message' => 'Dummy message'] : ['expected' => 1, 'actual' => 2];
        // Assert::assertXyz()/fail() is static method so there is no $this
        self::assertScopeContext($assertionFuncScopeCtx, thisObj: null, args: $assertionFuncArgs, addedCtx: []);
    }

    private const HELPER_FUNC_FOR_TEST_ADDED_TEXT_FORMAT = 'helperFuncForTestAddedTextFormat';

    /**
     * @param list<mixed>          $listArg
     * @param array<string, mixed> $mapArg
     * @param ?positive-int       &$lineNumber
     *
     * @param-out positive-int     $lineNumber
     *
     * @noinspection PhpUnusedParameterInspection
     */
    private static function helperFuncForTestAddedTextFormat(array $listArg, array $mapArg, /* out */ ?int &$lineNumber): void
    {
        self::assertNull($lineNumber);
        self::assertSame(self::HELPER_FUNC_FOR_TEST_ADDED_TEXT_FORMAT, __FUNCTION__); // @phpstan-ignore staticMethod.alreadyNarrowedType

        $lineNumber = __LINE__ + 1;
        self::fail('Dummy message');
    }

    public static function testAddedTextFormat(): void
    {
        if (self::shortcutTestIfDebugContextIsDisabledByDefault()) {
            return;
        }

        DebugContext::getCurrentScope(/* out */ $dbgCtx);

        // This test extracts debug context from text added to assertion message
        DebugContextConfig::addToAssertionMessage(true);

        $listArg = ['a', 1, null, 4.5];
        $mapArg = ['list key' => $listArg, 'int key' => 2, 'null key' => null];
        $helperFuncLine = null;
        $assertionMsg = null;
        try {
            $thisFuncLine = __LINE__ + 1;
            self::helperFuncForTestAddedTextFormat(listArg: $listArg, mapArg: $mapArg, /* out */ lineNumber: $helperFuncLine);
        } catch (AssertionFailedError $ex) {
            $assertionMsg = $ex->getMessage();
        }
        $dbgCtx->add(compact('assertionMsg'));
        self::assertNotNull($assertionMsg);
        self::assertNotNull($helperFuncLine); // @phpstan-ignore staticMethod.impossibleType
        $actualAddedText = self::extractTextAddedToAssertionMessage($assertionMsg);

        $phpUnitFrameworkAssertPhpFileFullPath = FileUtil::normalizePath(VendorDir::get() . FileUtil::adaptUnixDirectorySeparators('/phpunit/phpunit/src/Framework/Assert.php'));

        // Extract line number in Framework/Assert.php
        $strBeforeAssertPhpLine = JsonUtil::adaptStringToSearchInJson($phpUnitFrameworkAssertPhpFileFullPath . ':');
        self::assertNotFalse($strBeforeAssertPhpLinePos = strpos($actualAddedText, $strBeforeAssertPhpLine));
        $assertPhpLineStrPos = $strBeforeAssertPhpLinePos + strlen($strBeforeAssertPhpLine);
        self::assertGreaterThan(0, $assertPhpLineNumberStrLen = strspn($actualAddedText, '0123456789', $assertPhpLineStrPos));
        $phpUnitFrameworkAssertPhpLine = substr($actualAddedText, $assertPhpLineStrPos, $assertPhpLineNumberStrLen);

        $thisFileFullPath = __FILE__;
        $thisClass = __CLASS__;
        $thisFuncName = __FUNCTION__;
        $helperFuncName = self::HELPER_FUNC_FOR_TEST_ADDED_TEXT_FORMAT;
        $expectedAddedText = <<<string_delimiter_begin_19f62de5_93a7_4e30_b762_39225db3c4fd_end
            {
                "Scope 1 out of 3: PHPUnit\\Framework\\Assert::fail [$phpUnitFrameworkAssertPhpFileFullPath:$phpUnitFrameworkAssertPhpLine]": {
                    "message": "Dummy message"
                },
                "Scope 2 out of 3: $thisClass::$helperFuncName [$thisFileFullPath:$helperFuncLine]": {
                    "listArg": ["a",1,null,4.5],
                    "mapArg": {"list key":["a",1,null,4.5],"int key":2,"null key":null},
                    "lineNumber": $helperFuncLine
                },
                "Scope 3 out of 3: $thisClass::$thisFuncName [$thisFileFullPath:$thisFuncLine]": {}
            }
            string_delimiter_begin_19f62de5_93a7_4e30_b762_39225db3c4fd_end;

        self::assertSame(JsonUtil::adaptStringToSearchInJson($expectedAddedText), $actualAddedText);
    }
}
