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

use Elastic\OTel\Util\ArrayUtil;
use Elastic\OTel\Util\SingletonInstanceTrait;
use ElasticOTelTests\Util\Log\LogCategoryForTests;
use ElasticOTelTests\Util\Log\LoggableInterface;
use ElasticOTelTests\Util\Log\LoggableToString;
use ElasticOTelTests\Util\Log\LoggableTrait;
use ElasticOTelTests\Util\Log\Logger;
use ElasticOTelTests\Util\Log\LogStreamInterface;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\AssertionFailedError;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionParameter;
use stdClass;

/**
 * @phpstan-import-type CallStack from DebugContext
 * @phpstan-import-type ScopeContext from DebugContext
 * @phpstan-import-type ScopeNameToContext from DebugContext
 *
 * @phpstan-type ConfigOptionName DebugContextConfig::*_OPTION_NAME
 * @phpstan-type ConfigStore array<ConfigOptionName, bool>
 *
 * @phpstan-import-type PreProcessMessageCallback from AssertionFailedError
 */
final class DebugContextSingleton implements LoggableInterface
{
    use LoggableTrait;
    use SingletonInstanceTrait;

    private const TEXT_ADDED_TO_ASSERTION_MESSAGE_PREFIX = 'DebugContext begin';
    private const TEXT_ADDED_TO_ASSERTION_MESSAGE_SUFFIX = 'DebugContext end';

    /** @var ConfigStore */
    private array $config = DebugContextConfig::DEFAULT_VALUES;

    /** @var ?CallStack */
    private ?array $syncedWithCallStack = null;

    /** @var list<DebugContextScope> */
    private array $addedContextScopesStack = [];

    private StackTraceUtil $stackTraceUtil;

    private Logger $logger;

    private function __construct()
    {
        $this->stackTraceUtil = new StackTraceUtil(AmbientContextForTests::loggerFactory());

        $this->applyConfig();

        $this->logger = AmbientContextForTests::loggerFactory()->loggerForClass(LogCategoryForTests::TEST_INFRA, __NAMESPACE__, __CLASS__, __FILE__)->addAllContext(compact('this'));
        $this->assertValid();
    }

    public function assertValid(): void
    {
        Assert::assertSame($this->syncedWithCallStack === null, ArrayUtilForTests::isEmpty($this->addedContextScopesStack));

        if ($this->syncedWithCallStack !== null) {
            /** @var ?non-negative-int $prevCallStackFrameIndex */
            $prevCallStackFrameIndex = null;
            foreach ($this->addedContextScopesStack as $scope) {
                AssertEx::notNull($this->syncedWithCallStack);
                AssertEx::isValidIndexOf($scope->callStackFrameIndex, $this->syncedWithCallStack); // @phpstan-ignore staticMethod.alreadyNarrowedType
                if ($prevCallStackFrameIndex !== null) {
                    Assert::assertGreaterThan($prevCallStackFrameIndex, $scope->callStackFrameIndex);
                }
                $prevCallStackFrameIndex = $scope->callStackFrameIndex;
            }
        }
    }

    /**
     * @return ConfigStore
     */
    public function getConfigCopy(): array
    {
        return $this->config;
    }

    /**
     * @param ConfigStore $config
     */
    public function setConfig(array $config): void
    {
        DebugContextSingleton::singletonInstance()->config = $config;

        $this->applyConfig();
    }

    private function applyConfig(): void
    {
        $this->assertValid();

        AssertionFailedError::$preprocessMessage = $this->config[DebugContextConfig::ADD_TO_ASSERTION_MESSAGE_OPTION_NAME] ? $this->addToFailedAssertionMessage(...) : null;

        if (!$this->config[DebugContextConfig::ENABLED_OPTION_NAME]) {
            $this->resetImpl();
        }

        $this->assertValid();
    }

    /**
     * @phpstan-param ConfigOptionName $optionName
     */
    public function readWriteConfigOption(string $optionName, ?bool $newValue = null): bool
    {
        if ($newValue === null) {
            return $this->config[$optionName];
        }

        $oldValue = $this->config[$optionName];
        $this->config[$optionName] = $newValue;
        self::applyConfig();
        return $oldValue;
    }

    /**
     * @param ?DebugContextScopeRef &$scopeVar
     * @param ScopeContext           $initialCtx
     * @param non-negative-int       $numberOfStackFramesToSkip
     *
     * @param-out DebugContextScopeRef $scopeVar
     */
    public function getCurrentScope(/* out */ ?DebugContextScopeRef &$scopeVar, array $initialCtx, int $numberOfStackFramesToSkip): void
    {
        Assert::assertNull($scopeVar);
        $this->assertValid();

        if (!$this->config[DebugContextConfig::ENABLED_OPTION_NAME]) {
            $scopeVar = self::getNoopRefSingleton();
            return;
        }

        $this->syncScopesStackWithCallStack($numberOfStackFramesToSkip + 1, newScopeAboutToBePushed: true);

        $newScope = new DebugContextScope($this, callStackFrameIndex: count($this->getSyncedWithCallStack()) - 1, initialCtx: $initialCtx);
        $this->addedContextScopesStack[] = $newScope;
        $scopeVar = new DebugContextScopeRef($newScope);

        $this->assertValid();
    }

    /**
     * @param non-negative-int $numberOfStackFramesToSkip
     *
     * @return ScopeNameToContext
     */
    public function getContextsStack(int $numberOfStackFramesToSkip): array
    {
        $this->assertValid();

        if (!$this->config[DebugContextConfig::ENABLED_OPTION_NAME]) {
            return [];
        }

        $onlyAddedContext = $this->config[DebugContextConfig::ONLY_ADDED_CONTEXT_OPTION_NAME];
        $this->syncScopesStackWithCallStack($numberOfStackFramesToSkip + 1);

        if ($onlyAddedContext) {
            /** @var list<Pair<string, ScopeContext>> $scopesNameContextStackTopLast */
            $scopesNameContextStackTopLast = array_map(
                fn($scope) => new Pair(self::buildScopeNameForCallStackFrame($this->getSyncedWithCallStack()[$scope->callStackFrameIndex]), $scope->getContext()),
                $this->addedContextScopesStack
            );
        } else {
            /** @var array<non-negative-int, DebugContextScope> $frameIndexToScope */
            $frameIndexToScope = [];
            /** @var DebugContextScope $scope */
            foreach (IterableUtil::zipOneWithIndex($this->addedContextScopesStack) as [$scopeIndex, $scope]) {
                $frameIndexToScope[$scope->callStackFrameIndex] = $this->addedContextScopesStack[$scopeIndex];
            }

            $trimVendorFrames = $this->config[DebugContextConfig::TRIM_VENDOR_FRAMES_OPTION_NAME];
            /** @var ?non-negative-int $lastNonVendorFrameIndex */
            $lastNonVendorFrameIndex = null;

            $autoCapturedArgs = $this->config[DebugContextConfig::AUTO_CAPTURE_ARGS_OPTION_NAME];
            $callStackTrace = $autoCapturedArgs ? $this->captureStackTraceTopFrameLast($numberOfStackFramesToSkip + 1, includeArgs: true) : $this->getSyncedWithCallStack();
            /** @var list<Pair<string, ScopeContext>> $scopesNameContextStackTopLast */
            $scopesNameContextStackTopLast = [];
            foreach (IterableUtil::zipOneWithIndex($callStackTrace) as [$callStackFrameIndex, $callStackFrame]) {
                if ($trimVendorFrames) {
                    $isSourceCodeFileFromVendor = ($callStackFrame->file !== null) && self::isSourceCodeFileFromVendor($callStackFrame->file);
                    if ($lastNonVendorFrameIndex === null) {
                        if ($isSourceCodeFileFromVendor) {
                            continue;
                        }
                    }
                    if (!$isSourceCodeFileFromVendor) {
                        $lastNonVendorFrameIndex = count($scopesNameContextStackTopLast);
                    }
                }
                $scopesNameContextStackTopLast[] = new Pair(
                    self::buildScopeNameForCallStackFrame($callStackFrame),
                    $this->buildScopeContextForCallStackFrame($callStackFrame, ArrayUtil::getValueIfKeyExistsElse($callStackFrameIndex, $frameIndexToScope, null))
                );
            }

            if ($trimVendorFrames && !ArrayUtilForTests::isEmpty($scopesNameContextStackTopLast)) {
                Assert::assertNotNull($lastNonVendorFrameIndex);
                AssertEx::countAtLeast($lastNonVendorFrameIndex + 1, $scopesNameContextStackTopLast);
            } else {
                Assert::assertNull($lastNonVendorFrameIndex);
            }

            // Keep one vendor frame above the last non-vendor frame - this vendor frame corresponds to Assert::assertXyz() or Assert::fail() call
            if ($lastNonVendorFrameIndex !== null && ArrayUtilForTests::isValidIndexOf($lastNonVendorFrameIndex + 2, $scopesNameContextStackTopLast)) {
                ArrayUtilForTests::popFromIndex($scopesNameContextStackTopLast, $lastNonVendorFrameIndex + 2);
            }
        }

        $totalCount = count($scopesNameContextStackTopLast);
        $result = [];
        /** @var non-negative-int $scopeIndex */
        /** @var Pair<string, ScopeContext> $scopeNameContext */
        foreach (IterableUtil::iterateListWithIndex(ArrayUtilForTests::iterateListInReverse($scopesNameContextStackTopLast)) as [$scopeIndex, $scopeNameContext]) {
            $name = 'Scope ' . ($scopeIndex + 1) . ' out of ' . $totalCount . ': ' . $scopeNameContext->first;
            $result[$name] = $scopeNameContext->second;
        }

        $this->assertValid();
        return $result;
    }

    private static function buildScopeNameForCallStackFrame(ClassicFormatStackTraceFrame $callStackFrame): string
    {
        $classMethodPart = '';
        if ($callStackFrame->class !== null) {
            $classMethodPart .= $callStackFrame->class;
        }
        if ($callStackFrame->function !== null) {
            if ($classMethodPart !== '') {
                $classMethodPart .= '::';
            }
            $classMethodPart .= $callStackFrame->function;
        }

        $fileLinePart = '';
        if ($callStackFrame->file !== null) {
            $fileLinePart .= $callStackFrame->file;
            if ($callStackFrame->line !== null) {
                $fileLinePart .= ':' . $callStackFrame->line;
            }
        }

        if ($classMethodPart === '') {
            return $fileLinePart;
        }

        return $classMethodPart . ' [' . $fileLinePart . ']';
    }

    /**
     * @return ScopeContext
     */
    private function buildScopeContextForCallStackFrame(ClassicFormatStackTraceFrame $callStackFrame, ?DebugContextScope $scopeWithAddedContext): array
    {
        $ctx = [];

        // $this should be the first since $this is a hidden argument before the explicit arguments
        if ($this->config[DebugContextConfig::AUTO_CAPTURE_THIS_OPTION_NAME] && $callStackFrame->thisObj !== null) {
            $ctx[DebugContext::THIS_CONTEXT_KEY] = $callStackFrame->thisObj;
        }

        // then the explicit arguments
        if ($this->config[DebugContextConfig::AUTO_CAPTURE_ARGS_OPTION_NAME]) {
            ArrayUtilForTests::append(from: self::buildFuncArgsNamesToValues($callStackFrame), to: $ctx);
        }

        // and the added context is the last
        if ($scopeWithAddedContext !== null) {
            ArrayUtilForTests::append(from: $scopeWithAddedContext->getContext(), to: $ctx);
        }

        return $ctx;
    }

    public function reset(): void
    {
        $this->assertValid();
        $this->resetImpl();
        $this->assertValid();
    }

    private function resetImpl(): void
    {
        $this->addedContextScopesStack = [];
        $this->syncedWithCallStack = null;
    }

    /**
     * @param non-negative-int $numberOfStackFramesToSkip
     */
    private function addToFailedAssertionMessage(AssertionFailedError $exceptionBeingConstructed, string $baseMessage, int $numberOfStackFramesToSkip): string
    {
        $formattedContextsStack = $this->config[DebugContextConfig::ENABLED_OPTION_NAME]
            ? $this->getFormattedContextsStack($exceptionBeingConstructed, $numberOfStackFramesToSkip + 1)
            : DebugContext::TEXT_ADDED_TO_ASSERTION_MESSAGE_WHEN_DISABLED;
        return $baseMessage . PHP_EOL .
               self::TEXT_ADDED_TO_ASSERTION_MESSAGE_PREFIX . PHP_EOL .
               $formattedContextsStack . PHP_EOL .
               self::TEXT_ADDED_TO_ASSERTION_MESSAGE_SUFFIX;
    }

    /**
     * @param non-negative-int $numberOfStackFramesToSkip
     *
     * @return CallStack
     */
    private function captureStackTraceTopFrameLast(int $numberOfStackFramesToSkip, bool $includeArgs = false): array
    {
        // Always capture $this since it's used to determine if two stack trace frames represent the same call
        $callStackTraceTopFrameFirst = $this->stackTraceUtil->captureInClassicFormat(offset: $numberOfStackFramesToSkip + 1, includeThisObj: true, includeArgs: $includeArgs);
        return AssertEx::arrayIsNotEmptyList(array_reverse($callStackTraceTopFrameFirst));
    }

    /**
     * @param non-negative-int $numberOfStackFramesToSkip
     */
    private function syncScopesStackWithCallStack(int $numberOfStackFramesToSkip, bool $newScopeAboutToBePushed = false): void
    {
        ($loggerProxy = $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__)) && $loggerProxy->includeStackTrace()->log('Entered');

        $newCallStack = $this->captureStackTraceTopFrameLast($numberOfStackFramesToSkip + 1);
        $newCallStackFromFrameIndex = 0;

        /** @var ?non-negative-int $popScopesFromIndex */
        $popScopesFromIndex = null;

        /** @var non-negative-int $scopeIndex */
        /** @var DebugContextScope $scope */
        foreach (IterableUtil::iterateListWithIndex($this->addedContextScopesStack) as [$scopeIndex, $scope]) {
            // If a new scope is about to be pushed, then the top call stack frame should be used the new scope,
            //  so the top call stack frame should not be used for the sync
            if (!$scope->syncWithCallStack($newCallStack, $newCallStackFromFrameIndex) || ($newScopeAboutToBePushed && $scope->callStackFrameIndex === array_key_last($newCallStack))) {
                $popScopesFromIndex = $scopeIndex;
                break;
            }

            if ($scopeIndex === (count($this->addedContextScopesStack) - 1)) {
                break;
            }

            // If source code line is different that means that all the scopes up to the top of the scope stack
            // are for calls different from the ones on the current calls stack, so we should pop those scopes as stale.
            if (
                $this->getSyncedWithCallStack()[$scope->callStackFrameIndex]->line !== $newCallStack[$scope->callStackFrameIndex]->line
                || !ArrayUtilForTests::isValidIndexOf($scope->callStackFrameIndex + 1, $newCallStack)
            ) {
                $popScopesFromIndex = $scopeIndex + 1;
                break;
            }

            $newCallStackFromFrameIndex = $scope->callStackFrameIndex + 1;
        }

        if ($popScopesFromIndex !== null) {
            if ($popScopesFromIndex === 0) {
                $this->resetImpl();
            } else {
                AssertEx::isValidIndexOf($popScopesFromIndex, $this->addedContextScopesStack); // @phpstan-ignore staticMethod.alreadyNarrowedType
                ArrayUtilForTests::popFromIndex(/* in,out */ $this->addedContextScopesStack, $popScopesFromIndex); // @phpstan-ignore assign.propertyType
                AssertEx::arrayIsNotEmptyList($this->addedContextScopesStack);
            }
        }

        if ($newScopeAboutToBePushed || !ArrayUtilForTests::isEmpty($this->addedContextScopesStack)) {
            $this->syncedWithCallStack = AssertEx::arrayIsNotEmptyList($newCallStack);
        }
    }

    private function getNoopRefSingleton(): DebugContextScopeRef
    {
        /** @var ?DebugContextScopeRef $result */
        static $result = null;

        if ($result === null) {
            $result = new DebugContextScopeRef(null);
        }

        return $result;
    }

    /**
     * @return null|ReflectionParameter[]
     */
    private static function getReflectionParametersForStackFrame(ClassicFormatStackTraceFrame $frame): ?array
    {
        if ($frame->function === null) {
            return null;
        }

        try {
            if ($frame->class === null) {
                $reflFuc = new ReflectionFunction($frame->function);
                return $reflFuc->getParameters();
            }
            /** @var class-string $className */
            $className = $frame->class;
            $reflClass = new ReflectionClass($className);
            $reflMethod = $reflClass->getMethod($frame->function);
            return $reflMethod->getParameters();
        } catch (ReflectionException) {
            return null;
        }
    }

    /**
     * @return ScopeContext
     */
    private static function buildFuncArgsNamesToValues(ClassicFormatStackTraceFrame $stackTraceFrame): array
    {
        if ($stackTraceFrame->args === null || ArrayUtilForTests::isEmpty($stackTraceFrame->args)) {
            return [];
        }

        $result = [];
        $reflParams = self::getReflectionParametersForStackFrame($stackTraceFrame);
        foreach (RangeUtil::generateUpTo(count($stackTraceFrame->args)) as $argIndex) {
            $argName = $reflParams === null || count($reflParams) <= $argIndex ? ('arg #' . ($argIndex + 1)) : $reflParams[$argIndex]->getName();
            $result[$argName] = $stackTraceFrame->args[$argIndex];
        }
        return $result;
    }

    /**
     * @param non-negative-int $numberOfStackFramesToSkip
     *
     * @noinspection PhpDocMissingThrowsInspection
     */
    private function getFormattedContextsStack(AssertionFailedError $exceptionBeingConstructed, int $numberOfStackFramesToSkip): string
    {
        /*
         * We would like to get the following format:
         *
         *    {
         *        "Scope 1 of 2: MyClass::func (MyFile.php:133)": {
         *            "localVar_1": 1,
         *            "localVar_2": "localVar_2 value",
         *            "localVar_3": {...},
         *        },
         *        "Scope 2 of 2: MyClass::func (MyFile.php:123)": {
         *            "arg_1": 1,
         *            "arg_2": "arg_2 value",
         *            "arg_3": {...},
         *            "localVar_1": 1,
         *            "localVar_2": "localVar_2 value",
         *            "localVar_3": {...},
         *        }
         *    }
         *
         * namely the whole stack is JSON encoded and pretty printed but each arg/var is printed on one line (i.e., not pretty printed)
         * In order to achieve that we will create an auxiliary structure with arg/var values replaced by unique strings then JSON encode and pretty print it:
         *
         *    {
         *        "Scope 1 of 2: MyClass::func (MyFile.php:133)": {
         *            "localVar_1": "<unique string 1>",
         *            "localVar_2": "<unique string 2>",
         *            "localVar_3": "<unique string 3>",
         *        },
         *        "Scope 2 of 2: MyClass::func (MyFile.php:123)": {
         *            "arg_1": "<unique string 4>",
         *            "arg_2": "<unique string 5>",
         *            "arg_3": "<unique string 6>",
         *            "localVar_1": "<unique string 7>",
         *            "localVar_2": "<unique string 8>",
         *            "localVar_3": "<unique string 9>",
         *        }
         *    }
         *
         * then we JSON encode each arg/var (not pretty printed) and replace the corresponding "<unique string N>" with with JSON encoded arg/var.
         */
        $uniqueStrSuffix = ' value ' . uniqid();
        $uniqueStrIndex = 1;
        $genUniqueStr = function (string $ctxKey) use ($uniqueStrSuffix, &$uniqueStrIndex): string {
            return $ctxKey . $uniqueStrSuffix . ($uniqueStrIndex++);
        };

        /** @var ?stdClass $emptyStdClassInstance */
        static $emptyStdClassInstance = null;
        if ($emptyStdClassInstance === null) {
            $emptyStdClassInstance = new stdClass();
        }

        $contextsStack = self::getContextsStack($numberOfStackFramesToSkip + 1);
        $outerStruct = [];
        $uniqueStrToCtxValue = [];
        foreach ($contextsStack as $desc => $context) {
            $ctxWithValuesReplaced = [];
            foreach ($context as $ctxKey => $ctxValue) {
                $uniqueStr = $genUniqueStr($ctxKey);
                $uniqueStrToCtxValue[$uniqueStr] = $ctxValue;
                $ctxWithValuesReplaced[$ctxKey] = $uniqueStr;
            }
            $outerStruct[$desc] = ArrayUtilForTests::isEmpty($ctxWithValuesReplaced) ? $emptyStdClassInstance : $ctxWithValuesReplaced;
        }

        $exceptionBeingConstructedAsJson = JsonUtil::encode(['exception being constructed class' => get_class($exceptionBeingConstructed)]);
        $result = JsonUtil::encode($outerStruct, prettyPrint: true);
        foreach ($uniqueStrToCtxValue as $uniqueStr => $ctxValue) {
            $ctxValueAsJson = $exceptionBeingConstructed === $ctxValue ? $exceptionBeingConstructedAsJson : LoggableToString::convert($ctxValue);
            $result = str_replace(JsonUtil::encode($uniqueStr), $ctxValueAsJson, $result);
        }

        return $result;
    }

    private static function isSourceCodeFileFromVendor(string $filePath): bool
    {
        /** @var ?string $vendorDirPathPrefix */
        static $vendorDirPathPrefix = null;
        if ($vendorDirPathPrefix === null) {
            $vendorDirPathPrefix = VendorDir::getFullPath() . DIRECTORY_SEPARATOR;
        }

        return str_starts_with($filePath, $vendorDirPathPrefix);
    }

    public function extractAddedTextFromMessage(string $message): ?string
    {
        $prefixPos = strpos($message, self::TEXT_ADDED_TO_ASSERTION_MESSAGE_PREFIX);
        if ($prefixPos === false) {
            return null;
        }

        $afterPrefixPos = $prefixPos + strlen(self::TEXT_ADDED_TO_ASSERTION_MESSAGE_PREFIX);
        $suffixPos = strpos($message, self::TEXT_ADDED_TO_ASSERTION_MESSAGE_SUFFIX, offset: $afterPrefixPos);
        if ($suffixPos === false) {
            return null;
        }

        return trim(substr($message, $afterPrefixPos, $suffixPos - $afterPrefixPos));
    }

    /**
     * @return ?ScopeNameToContext
     *
     * @noinspection PhpDocMissingThrowsInspection
     */
    public function extractContextsStackFromMessage(string $message): ?array
    {
        if (!$this->config[DebugContextConfig::ENABLED_OPTION_NAME]) {
            return null;
        }

        $addedText = self::extractAddedTextFromMessage($message);
        if ($addedText === null) {
            return null;
        }

        $decodedContextsStack = JsonUtil::decode($addedText);
        return AssertEx::isArray($decodedContextsStack); // @phpstan-ignore return.type
    }

    /**
     * @return CallStack
     */
    public function getSyncedWithCallStack(): array
    {
        AssertEx::notNull($this->syncedWithCallStack);
        return $this->syncedWithCallStack;
    }

    public function toLog(LogStreamInterface $stream): void
    {
        $stream->toLogAs(
            [
                'scopes count' => count($this->addedContextScopesStack),
                'scopes' => $this->addedContextScopesStack,
            ],
        );
    }
}
