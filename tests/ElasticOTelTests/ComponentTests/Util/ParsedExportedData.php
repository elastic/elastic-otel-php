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

namespace ElasticOTelTests\ComponentTests\Util;

use ElasticOTelTests\Util\ArrayUtilForTests;
use ElasticOTelTests\Util\IterableUtil;
use PHPUnit\Framework\Assert;

/**
 * @phpstan-import-type AttributeValue from SpanAttributes as SpanAttributeValue
 */
class ParsedExportedData
{
    /**
     * @param Span[] $spans
     */
    public function __construct(
        public array $spans,
    ) {
    }

    public function isEmpty(): bool
    {
        foreach ($this as $propValue) { // @phpstan-ignore foreach.nonIterable
            Assert::assertIsArray($propValue);
            if (!ArrayUtilForTests::isEmpty($propValue)) {
                return false;
            }
        }
        return true;
    }

    /**
     * @template EventType
     *
     * @param EventType[] $events
     *
     * @return EventType
     */
    private static function singleEvent(array $events)
    {
        return ArrayUtilForTests::getSingleValue($events);
    }

    /** @noinspection PhpUnused */
    public function singleSpan(): Span
    {
        return self::singleEvent($this->spans);
    }

    /**
     * @return Span[]
     */
    public function findSpansByName(string $name): array
    {
        $result = [];
        foreach ($this->spans as $span) {
            if ($span->name === $name) {
                $result[] = $span;
            }
        }
        return $result;
    }

    /**
     * @return Span
     *
     * @noinspection PhpUnused, PhpDocSignatureIsNotCompleteInspection
     */
    public function singleSpanByName(string $name): Span
    {
        $spans = $this->findSpansByName($name);
        Assert::assertCount(1, $spans);
        return $spans[0];
    }

    /**
     * @return iterable<Span>
     *
     * @noinspection PhpUnused
     */
    public function findChildSpans(string $parentId): iterable
    {
        foreach ($this->spans as $span) {
            if ($span->parentId === $parentId) {
                yield $span;
            }
        }
    }

    /**
     * @return iterable<Span>
     */
    public function findRootSpans(): iterable
    {
        foreach ($this->spans as $span) {
            if ($span->parentId === null) {
                yield $span;
            }
        }
    }

    public function singleRootSpan(): Span
    {
        return IterableUtil::singleValue($this->findRootSpans());
    }

    public function singleChildSpan(string $parentId): Span
    {
        return IterableUtil::singleValue($this->findChildSpans($parentId));
    }

    /**
     * @param non-empty-string   $attributeName
     * @param SpanAttributeValue $attributeValueToFind
     *
     * @return iterable<Span>
     */
    public function findSpansWithAttributeValue(string $attributeName, array|bool|float|int|null|string $attributeValueToFind): iterable
    {
        foreach ($this->spans as $span) {
            if ($span->attributes->tryToGetValue($attributeName, /* out */ $actualAttributeValue) && $actualAttributeValue === $attributeValueToFind) {
                yield $span;
            }
        }
    }
}
