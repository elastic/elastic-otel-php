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

use Elastic\OTel\Util\TextUtil;
use ElasticOTelTests\Util\Log\LogCategoryForTests;
use ElasticOTelTests\Util\Log\Logger;
use ElasticOTelTests\Util\Log\LoggerFactory;
use RuntimeException;

/**
 * Code in this file is part of implementation internals, and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class StackTraceUtil
{
    public const FILE_KEY = 'file';
    public const LINE_KEY = 'line';
    public const FUNCTION_KEY = 'function';
    public const CLASS_KEY = 'class';
    public const TYPE_KEY = 'type';
    public const FUNCTION_IS_STATIC_METHOD_TYPE_VALUE = '::';
    public const FUNCTION_IS_METHOD_TYPE_VALUE = '->';
    public const THIS_OBJECT_KEY = 'object';
    public const ARGS_KEY = 'args';

    /** @noinspection PhpUnused */
    public const FILE_NAME_NOT_AVAILABLE_SUBSTITUTE = 'FILE NAME N/A';
    /** @noinspection PhpUnused */
    public const LINE_NUMBER_NOT_AVAILABLE_SUBSTITUTE = 0;

    private const ELASTIC_OTEL_FQ_NAME_PREFIX = 'Elastic\\OTel\\';
    private const ELASTIC_OTEL_INTERNAL_FUNCTION_NAME_PREFIX = 'elastic_otel_';

    private LoggerFactory $loggerFactory;
    private readonly Logger $logger;
    private string $namePrefixForFramesToHide;
    private string $namePrefixForInternalFramesToHide;

    public function __construct(
        LoggerFactory $loggerFactory,
        string $namePrefixForFramesToHide = self::ELASTIC_OTEL_FQ_NAME_PREFIX,
        string $namePrefixForInternalFramesToHide = self::ELASTIC_OTEL_INTERNAL_FUNCTION_NAME_PREFIX
    ) {
        $this->loggerFactory = $loggerFactory;
        $this->logger = $this->loggerFactory->loggerForClass(LogCategoryForTests::TEST_INFRA, __NAMESPACE__, __CLASS__, __FILE__);
        $this->namePrefixForFramesToHide = $namePrefixForFramesToHide;
        $this->namePrefixForInternalFramesToHide = $namePrefixForInternalFramesToHide;
    }

    /**
     * @param int           $offset
     * @param ?positive-int $maxNumberOfFrames
     * @param bool          $keepElasticOTelFrames
     * @param bool          $includeArgs
     * @param bool          $includeThisObj
     *
     * @return ClassicFormatStackTraceFrame[]
     *
     * @phpstan-param 0|positive-int $offset
     */
    public function captureInClassicFormat(int $offset = 0, ?int $maxNumberOfFrames = null, bool $keepElasticOTelFrames = true, bool $includeArgs = false, bool $includeThisObj = false): array
    {
        $options = ($includeArgs ? 0 : DEBUG_BACKTRACE_IGNORE_ARGS) | ($includeThisObj ? DEBUG_BACKTRACE_PROVIDE_OBJECT : 0);
        return $this->convertCaptureToClassicFormat(
            // If there is non-null $maxNumberOfFrames we need to capture one more frame in PHP format
            debug_backtrace($options, limit: $maxNumberOfFrames === null ? 0 : ($offset + $maxNumberOfFrames + 1)),
            // $offset + 1 to exclude the frame for the current method (captureInClassicFormat) call
            $offset + 1,
            $maxNumberOfFrames,
            $keepElasticOTelFrames,
            $includeArgs,
            $includeThisObj
        );
    }

    /**
     * @param array<array<string, mixed>> $phpFormatFrames
     * @param int                         $offset
     * @param ?positive-int               $maxNumberOfFrames
     * @param bool                        $keepElasticOTelFrames
     * @param bool                        $includeArgs
     * @param bool                        $includeThisObj
     *
     * @return ClassicFormatStackTraceFrame[]
     *
     * @phpstan-param 0|positive-int $offset
     */
    public function convertCaptureToClassicFormat(array $phpFormatFrames, int $offset, ?int $maxNumberOfFrames, bool $keepElasticOTelFrames, bool $includeArgs, bool $includeThisObj): array
    {
        if ($offset >= count($phpFormatFrames)) {
            return [];
        }

        return $this->convertPhpToClassicFormat(
            $offset === 0 ? null : $phpFormatFrames[$offset - 1] /* <- prevPhpFormatFrame */,
            $offset === 0 ? $phpFormatFrames : IterableUtil::arraySuffix($phpFormatFrames, $offset),
            $maxNumberOfFrames,
            $keepElasticOTelFrames,
            $includeArgs,
            $includeThisObj
        );
    }

    /**
     * @param ?array<string, mixed>          $prevPhpFormatFrame
     * @param iterable<array<string, mixed>> $phpFormatFrames
     * @param ?positive-int                  $maxNumberOfFrames
     * @param bool                           $keepElasticOTelFrames
     * @param bool                           $includeArgs
     * @param bool                           $includeThisObj
     *
     * @return ClassicFormatStackTraceFrame[]
     */
    public function convertPhpToClassicFormat(
        ?array $prevPhpFormatFrame,
        iterable $phpFormatFrames,
        ?int $maxNumberOfFrames,
        bool $keepElasticOTelFrames,
        bool $includeArgs,
        bool $includeThisObj
    ): array {
        $allClassicFormatFrames = [];
        $prevInFrame = $prevPhpFormatFrame;
        foreach ($phpFormatFrames as $currentInFrame) {
            $outFrame = new ClassicFormatStackTraceFrame();
            $isOutFrameEmpty = true;
            if ($prevInFrame !== null && $this->hasLocationPropertiesInPhpFormat($prevInFrame)) {
                $this->copyLocationPropertiesFromPhpToClassicFormat($prevInFrame, $outFrame);
                $isOutFrameEmpty = false;
            }
            if ($this->hasNonLocationPropertiesInPhpFormat($currentInFrame)) {
                $this->copyNonLocationPropertiesFromPhpToClassicFormat($currentInFrame, $includeArgs, $includeThisObj, $outFrame);
                $isOutFrameEmpty = false;
            }
            if (!$isOutFrameEmpty) {
                $allClassicFormatFrames[] = $outFrame;
            }
            $prevInFrame = $currentInFrame;
        }

        if ($prevInFrame !== null && $this->hasLocationPropertiesInPhpFormat($prevInFrame)) {
            $outFrame = new ClassicFormatStackTraceFrame();
            $this->copyLocationPropertiesFromPhpToClassicFormat($prevInFrame, $outFrame);
            $allClassicFormatFrames[] = $outFrame;
        }

        return $keepElasticOTelFrames
            ? ($maxNumberOfFrames === null ? $allClassicFormatFrames : array_slice($allClassicFormatFrames, /* offset */ 0, $maxNumberOfFrames))
            : $this->excludeCodeToHide($allClassicFormatFrames, $maxNumberOfFrames);
    }


    /**
     * @param ClassicFormatStackTraceFrame[] $inFrames
     * @param ?positive-int                  $maxNumberOfFrames
     *
     * @return ClassicFormatStackTraceFrame[]
     */
    private function excludeCodeToHide(array $inFrames, ?int $maxNumberOfFrames): array
    {
        $outFrames = [];
        /** @var ?int $bufferedFromIndex */
        $bufferedFromIndex = null;
        foreach (RangeUtil::generateUpTo(count($inFrames)) as $currentInFrameIndex) {
            $currentInFrame = $inFrames[$currentInFrameIndex];
            if (self::isTrampolineCall($currentInFrame)) {
                if ($bufferedFromIndex === null) {
                    $bufferedFromIndex = $currentInFrameIndex;
                }
                continue;
            }

            if ($this->isCallToCodeToHide($currentInFrame)) {
                $bufferedFromIndex = null;
                continue;
            }

            for ($index = $bufferedFromIndex ?? $currentInFrameIndex; $index <= $currentInFrameIndex; ++$index) {
                $hasSpace = self::addToOutputFrames($inFrames[$index], $maxNumberOfFrames, /* ref */ $outFrames);
                if (!$hasSpace) {
                    throw new RuntimeException('Unexpectedly number of frames reached max' . '; number of frames: ' . count($outFrames) . '; max: ' . $maxNumberOfFrames);
                }
            }
            $bufferedFromIndex = null;
        }

        return $outFrames;
    }

    private static function isTrampolineCall(ClassicFormatStackTraceFrame $frame): bool
    {
        return $frame->class === null && $frame->isStaticMethod === null && ($frame->function === 'call_user_func' || $frame->function === 'call_user_func_array');
    }

    private function isCallToCodeToHide(ClassicFormatStackTraceFrame $frame): bool
    {
        return ($frame->class !== null && TextUtil::isPrefixOf($this->namePrefixForFramesToHide, $frame->class))
               || ($frame->function !== null && TextUtil::isPrefixOf($this->namePrefixForFramesToHide, $frame->function))
               || ($frame->function !== null && $frame->file === null && TextUtil::isPrefixOf($this->namePrefixForInternalFramesToHide, $frame->function));
    }

    /**
     * @param array<string, mixed> $frame
     *
     * @return ?bool
     */
    private function isStaticMethodInPhpFormat(array $frame): ?bool
    {
        if (($funcType = self::getNullableStringValue(self::TYPE_KEY, $frame)) === null) {
            return null;
        }

        switch ($funcType) {
            case self::FUNCTION_IS_STATIC_METHOD_TYPE_VALUE:
                return true;
            case self::FUNCTION_IS_METHOD_TYPE_VALUE:
                return false;
            default:
                ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
                && $loggerProxy->log('Unexpected `' . self::TYPE_KEY . '\' value', ['type' => $funcType]);
                return null;
        }
    }

    /**
     * @param string               $key
     * @param array<string, mixed> $phpFormatFormatFrame
     *
     * @return ?string
     */
    private function getNullableStringValue(string $key, array $phpFormatFormatFrame): ?string
    {
        /** @var ?string $value */
        $value = $this->getNullableValue($key, 'is_string', 'string', $phpFormatFormatFrame);
        return $value;
    }

    /**
     * @param string               $key
     * @param array<string, mixed> $phpFormatFormatFrame
     *
     * @return ?int
     *
     * @noinspection PhpSameParameterValueInspection
     */
    private function getNullableIntValue(string $key, array $phpFormatFormatFrame): ?int
    {
        /** @var ?int $value */
        $value = $this->getNullableValue($key, 'is_int', 'int', $phpFormatFormatFrame);
        return $value;
    }

    /**
     * @param string               $key
     * @param array<string, mixed> $phpFormatFormatFrame
     *
     * @return ?object
     *
     * @noinspection PhpSameParameterValueInspection
     */
    private function getNullableObjectValue(string $key, array $phpFormatFormatFrame): ?object
    {
        /** @var ?object $value */
        $value = $this->getNullableValue($key, 'is_object', 'object', $phpFormatFormatFrame);
        return $value;
    }

    /**
     * @param string               $key
     * @param array<string, mixed> $phpFormatFormatFrame
     *
     * @return null|mixed[]
     *
     * @noinspection PhpSameParameterValueInspection
     */
    private function getNullableArrayValue(string $key, array $phpFormatFormatFrame): ?array
    {
        /** @var ?array<mixed> $value */
        $value = $this->getNullableValue($key, 'is_array', 'array', $phpFormatFormatFrame);
        return $value;
    }

    /**
     * @param callable(mixed): bool $isValueTypeFunc
     * @param array<string, mixed>  $phpFormatFormatFrame
     */
    private function getNullableValue(string $key, callable $isValueTypeFunc, string $dbgExpectedType, array $phpFormatFormatFrame): mixed
    {
        if (!array_key_exists($key, $phpFormatFormatFrame)) {
            return null;
        }

        $value = $phpFormatFormatFrame[$key];
        if ($value === null) {
            return null;
        }

        if (!$isValueTypeFunc($value)) {
            ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Unexpected type for value under key (expected ' . $dbgExpectedType . ')',
                ['$key' => $key, 'value type' => DbgUtil::getType($value), 'value' => $value]
            );
            return null;
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $frame
     */
    private function hasNonLocationPropertiesInPhpFormat(array $frame): bool
    {
        return $this->getNullableStringValue(self::FUNCTION_KEY, $frame) !== null;
    }

    /**
     * @param array<string, mixed> $frame
     */
    private function hasLocationPropertiesInPhpFormat(array $frame): bool
    {
        return $this->getNullableStringValue(self::FILE_KEY, $frame) !== null;
    }

    /**
     * @param array<string, mixed>         $srcFrame
     * @param ClassicFormatStackTraceFrame $dstFrame
     */
    private function copyLocationPropertiesFromPhpToClassicFormat(array $srcFrame, ClassicFormatStackTraceFrame $dstFrame): void
    {
        $dstFrame->file = $this->getNullableStringValue(self::FILE_KEY, $srcFrame);
        $dstFrame->line = $this->getNullableIntValue(self::LINE_KEY, $srcFrame);
    }

    /**
     * @param array<string, mixed>         $srcFrame
     * @param bool                         $includeArgs
     * @param bool                         $includeThisObj
     * @param ClassicFormatStackTraceFrame $dstFrame
     */
    private function copyNonLocationPropertiesFromPhpToClassicFormat(array $srcFrame, bool $includeArgs, bool $includeThisObj, ClassicFormatStackTraceFrame $dstFrame): void
    {
        $dstFrame->class = $this->getNullableStringValue(self::CLASS_KEY, $srcFrame);
        $dstFrame->function = $this->getNullableStringValue(self::FUNCTION_KEY, $srcFrame);
        $dstFrame->isStaticMethod = $this->isStaticMethodInPhpFormat($srcFrame);
        if ($includeThisObj) {
            $dstFrame->thisObj = $this->getNullableObjectValue(self::THIS_OBJECT_KEY, $srcFrame);
        }
        if ($includeArgs) {
            $dstFrame->args = $this->getNullableArrayValue(self::ARGS_KEY, $srcFrame);
        }
    }

    /**
     * @template TOutputFrame
     *
     * @param TOutputFrame    $frameToAdd
     * @param ?int            $maxNumberOfFrames
     * @param TOutputFrame[] &$outputFrames
     *
     * @return bool
     *
     * @phpstan-param null|positive-int $maxNumberOfFrames
     */
    private static function addToOutputFrames($frameToAdd, ?int $maxNumberOfFrames, /* ref */ array &$outputFrames): bool
    {
        $outputFrames[] = $frameToAdd;
        return (count($outputFrames) !== $maxNumberOfFrames);
    }
}
