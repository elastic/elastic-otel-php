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
use ElasticOTelTests\Util\Log\LoggableInterface;
use ElasticOTelTests\Util\Log\LoggableToString;
use ElasticOTelTests\Util\Log\LoggableTrait;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\AssertionFailedError;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionParameter;
use stdClass;

/**
 * @phpstan-import-type Context from DebugContext
 * @phpstan-import-type ContextsStack from DebugContext
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

    /** @var DebugContextScope[] */
    private array $addedContextScopesStack = [];

    private StackTraceUtil $stackTraceUtil;

    private function __construct()
    {
        $this->stackTraceUtil = new StackTraceUtil(AmbientContextForTests::loggerFactory());

        $this->applyConfig();
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
        AssertionFailedError::$preprocessMessage = $this->config[DebugContextConfig::ADD_TO_ASSERTION_MESSAGE_OPTION_NAME] ? $this->addToFailedAssertionMessage(...) : null;

        if (!$this->config[DebugContextConfig::ENABLED_OPTION_NAME]) {
            $this->addedContextScopesStack = [];
        }
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
     * @param ?DebugContextScopeRef   &$scopeVar
     * @param Context                  $initialCtx
     * @param non-negative-int         $numberOfStackFramesToSkip
     *
     * @param-out DebugContextScopeRef $scopeVar
     */
    public function getCurrentScope(/* out */ ?DebugContextScopeRef &$scopeVar, array $initialCtx, int $numberOfStackFramesToSkip): void
    {
        Assert::assertNull($scopeVar);

        if (!$this->config[DebugContextConfig::ENABLED_OPTION_NAME]) {
            $scopeVar = self::getNoopRefSingleton();
            return;
        }

        $stackTrace = $this->captureStackTraceTopFrameLast($numberOfStackFramesToSkip + 1);

        // Do not use the top frame for sync since the top frame should be used for the new (current) scope
        $remainingStackTraceTopStartIndex = $this->syncScopesStackWithCallStack((new ListSlice($stackTrace))->withoutSuffix(1));
        // Construct a new stack trace top segment that does include the top frame
        $remainingStackTraceTop = new ListSlice($stackTrace, $remainingStackTraceTopStartIndex);

        $newScope = new DebugContextScope($remainingStackTraceTop, $initialCtx);
        $this->addedContextScopesStack[] = $newScope;
        $scopeVar = new DebugContextScopeRef($this, $newScope);
    }

    /**
     * @param non-negative-int $numberOfStackFramesToSkip
     *
     * @return ContextsStack
     */
    public function getContextsStack(int $numberOfStackFramesToSkip): array
    {
        if (!$this->config[DebugContextConfig::ENABLED_OPTION_NAME]) {
            return [];
        }

        $onlyAddedContext = $this->config[DebugContextConfig::ONLY_ADDED_CONTEXT_OPTION_NAME];
        $stackTrace = $this->captureStackTraceTopFrameLast($numberOfStackFramesToSkip + 1, includeArgs: !$onlyAddedContext && $this->config[DebugContextConfig::AUTO_CAPTURE_ARGS_OPTION_NAME]);
        $syncRetVal = $this->syncScopesStackWithCallStack(new ListSlice($stackTrace), returnFrameIndexesForScopes: !$onlyAddedContext);

        /** @var ?non-negative-int $lastNonVendorFrameIndex */
        $lastNonVendorFrameIndex = null;
        if ($onlyAddedContext) {
            $scopesStack = $this->addedContextScopesStack;
        } else {
            $frameIndexToScope = [];
            /** @var non-empty-list<non-negative-int> $frameIndexesForScopes */
            $frameIndexesForScopes = $syncRetVal;
            foreach (IterableUtil::zipOneWithIndex($frameIndexesForScopes) as [$scopeIndex, $frameIndex]) {
                $frameIndexToScope[$frameIndex] = $this->addedContextScopesStack[$scopeIndex];
            }

            /** @var DebugContextScope[] $scopesStack */
            $scopesStack = [];
            $trimVendorFrames = $this->config[DebugContextConfig::TRIM_VENDOR_FRAMES_OPTION_NAME];
            foreach (RangeUtil::generateUpTo(count($stackTrace)) as $stackTraceFrameIndex) {
                $stackTraceFrame = $stackTrace[$stackTraceFrameIndex];
                if ($trimVendorFrames) {
                    $isSourceCodeFileFromVendor = ($stackTraceFrame->file !== null) && self::isSourceCodeFileFromVendor($stackTraceFrame->file);
                    if ($lastNonVendorFrameIndex === null) {
                        if ($isSourceCodeFileFromVendor) {
                            continue;
                        }
                    }
                    if (!$isSourceCodeFileFromVendor) {
                        $lastNonVendorFrameIndex = count($scopesStack);
                    }
                }
                $scopesStack[] = $this->buildScopeForStackTraceFrame($stackTraceFrame, ArrayUtil::getValueIfKeyExistsElse($stackTraceFrameIndex, $frameIndexToScope, null));
            }
            if ($trimVendorFrames && !ArrayUtilForTests::isEmpty($scopesStack)) {
                Assert::assertNotNull($lastNonVendorFrameIndex);
                AssertEx::countAtLeast($lastNonVendorFrameIndex + 1, $scopesStack);
            } else {
                Assert::assertNull($lastNonVendorFrameIndex);
            }

            // Keep one vendor frame above the last non-vendor frame - this vendor frame corresponds to Assert::assertXyz() or Assert::fail() call
            if ($lastNonVendorFrameIndex !== null && ArrayUtilForTests::isValidIndexOf($lastNonVendorFrameIndex + 2, $scopesStack)) {
                ArrayUtilForTests::popFromIndex($scopesStack, $lastNonVendorFrameIndex + 2);
            }
        }

        $totalCount = count($scopesStack);
        $result = [];
        /** @var non-negative-int $scopeIndex */
        /** @var DebugContextScope $scope */
        foreach (IterableUtil::iterateListWithIndex(ArrayUtilForTests::iterateListInReverse($scopesStack)) as [$scopeIndex, $scope]) {
            $name = 'Scope ' . ($scopeIndex + 1) . ' out of ' . $totalCount . ': ' . $scope->getName();
            $result[$name] = $scope->getContext();
        }
        return $result;
    }

    public function reset(): void
    {
        $this->addedContextScopesStack = [];
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
     * @return ClassicFormatStackTraceFrame[]
     */
    private function captureStackTraceTopFrameLast(int $numberOfStackFramesToSkip, bool $includeArgs = false): array
    {
        // Always capture $this since it's used to determine if two stack trace frames represent the same call
        return array_reverse($this->stackTraceUtil->captureInClassicFormat(offset: $numberOfStackFramesToSkip + 1, includeThisObj: true, includeArgs: $includeArgs));
    }

    /**
     * @param ListSlice<ClassicFormatStackTraceFrame> $stackTrace
     *
     * @return ($returnFrameIndexesForScopes is true ? list<non-negative-int> : non-negative-int)
     */
    private function syncScopesStackWithCallStack(ListSlice $stackTrace, bool $returnFrameIndexesForScopes = false): array|int
    {
        if ($returnFrameIndexesForScopes) {
            $frameIndexesForScopes = [];
        }
        $popScopesFromIndex = null;
        $remainingStackTraceTop = $stackTrace->clone();
        /** @var non-negative-int $scopeIndex */
        /** @var DebugContextScope $scope */
        foreach (IterableUtil::iterateListWithIndex($this->addedContextScopesStack) as [$scopeIndex, $scope]) {
            if (!$scope->syncWithCallStack($remainingStackTraceTop, /* out */ $matchingFrameIndex, /* out */ $matchingFrameHasSameLine)) {
                $popScopesFromIndex = $scopeIndex;
                break;
            }
            // If source code line is different that means that all the scopes up to top of the scopes stack
            // are for calls different from the ones on the current calls stack trace
            // so we should pop those scopes as stale
            if (!$matchingFrameHasSameLine && ($scopeIndex !== count($this->addedContextScopesStack) - 1)) {
                $popScopesFromIndex = $scopeIndex + 1;
                break;
            }
            $remainingStackTraceTop = $remainingStackTraceTop->withoutPrefix($matchingFrameIndex + 1);
            if ($returnFrameIndexesForScopes) {
                $frameIndexesForScopes[] = $remainingStackTraceTop->offset - 1;
            }
        }

        if ($popScopesFromIndex !== null) {
            Assert::assertLessThan(count($this->addedContextScopesStack), $popScopesFromIndex);
            ArrayUtilForTests::popFromIndex(/* in,out */ $this->addedContextScopesStack, $popScopesFromIndex);
        }

        /** @var list<non-negative-int> $frameIndexesForScopes */
        return $returnFrameIndexesForScopes ? $frameIndexesForScopes : $remainingStackTraceTop->offset; // @phpstan-ignore variable.undefined
    }

    private function getNoopRefSingleton(): DebugContextScopeRef
    {
        /** @var ?DebugContextScopeRef $result */
        static $result = null;

        if ($result === null) {
            $result = new DebugContextScopeRef($this, null);
        }

        return $result;
    }

    /**
     * @param non-negative-int $numberOfStackFramesToSkip
     */
    public function popTopScope(DebugContextScope $scopeToPop, int $numberOfStackFramesToSkip): void
    {
        // Relevant call stack trace starts from this function caller
        $stackTrace = $this->captureStackTraceTopFrameLast($numberOfStackFramesToSkip + 1);

        // Do not use the top frame for sync since the top frame is for the scope that should be pop-ed
        $this->syncScopesStackWithCallStack((new ListSlice($stackTrace)));

        /** @var ?non-positive-int $scopeToPopIndex */
        $scopeToPopIndex = null;
        foreach (IterableUtil::iterateListWithIndex($this->addedContextScopesStack) as [$currentScopeIndex, $currentScope]) {
            if ($currentScope === $scopeToPop) {
                $scopeToPopIndex = $currentScopeIndex;
                break;
            }
        }
        if ($scopeToPopIndex !== null) {
            AssertEx::isValidIndexOf($scopeToPopIndex, $this->addedContextScopesStack);
            ArrayUtilForTests::popFromIndex($this->addedContextScopesStack, $scopeToPopIndex);
        }
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
     * @return Context
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

    private function buildScopeForStackTraceFrame(ClassicFormatStackTraceFrame $stackTraceFrame, ?DebugContextScope $scopeWithAddedContext): DebugContextScope
    {
        $ctx = [];

        $onlyAddedContext = $this->config[DebugContextConfig::ONLY_ADDED_CONTEXT_OPTION_NAME];
        if (!$onlyAddedContext && $this->config[DebugContextConfig::AUTO_CAPTURE_THIS_OPTION_NAME] && ($stackTraceFrame->thisObj !== null)) {
            $ctx[DebugContext::THIS_CONTEXT_KEY] = $stackTraceFrame->thisObj;
        }

        if (!$onlyAddedContext && $this->config[DebugContextConfig::AUTO_CAPTURE_ARGS_OPTION_NAME]) {
            ArrayUtilForTests::append(from: self::buildFuncArgsNamesToValues($stackTraceFrame), to: $ctx);
        }

        if ($scopeWithAddedContext !== null) {
            ArrayUtilForTests::append(from: $scopeWithAddedContext->getContext(), to: $ctx);
        }

        return new DebugContextScope(new ListSlice([$stackTraceFrame]), $ctx);
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
     * @return ?ContextsStack
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

        $decodedContextsStack = JsonUtil::decode($addedText, asAssocArray: true);
        return AssertEx::isArray($decodedContextsStack); // @phpstan-ignore return.type
    }
}
