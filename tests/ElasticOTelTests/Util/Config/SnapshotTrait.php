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

namespace ElasticOTelTests\Util\Config;

use Elastic\OTel\Util\ArrayUtil;
use Elastic\OTel\Util\TextUtil;
use ElasticOTelTests\Util\ArrayUtilForTests;
use ElasticOTelTests\Util\Log\LoggableTrait;
use ElasticOTelTests\Util\TestCaseBase;

/**
 * Code in this file is part of implementation internals, and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
trait SnapshotTrait
{
    use LoggableTrait;

    /** @var ?array<string, mixed> */
    private ?array $optNameToParsedValue = null;

    /**
     * @param array<string, mixed> $optNameToParsedValue
     */
    protected function setPropertiesToValuesFrom(array $optNameToParsedValue): void
    {
        TestCaseBase::assertNull($this->optNameToParsedValue);

        $actualClass = get_called_class();
        foreach ($optNameToParsedValue as $optName => $parsedValue) {
            $propertyName = TextUtil::snakeToCamelCase($optName);
            if (!property_exists($actualClass, $propertyName)) {
                throw new ConfigException("Property `$propertyName' doesn't exist in class " . $actualClass);
            }
            $this->$propertyName = $parsedValue;
        }

        $this->optNameToParsedValue = $optNameToParsedValue;
    }

    /**
     * @return string[]
     */
    protected static function snapshotTraitPropNamesNotForOptions(): array
    {
        return ['optNameToParsedValue'];
    }

    /**
     * @return string[]
     */
    protected static function additionalPropNamesNotForOptions(): array
    {
        return [];
    }

    /**
     * @return string[]
     */
    public static function propertyNamesForOptions(): array
    {
        /** @var array<string> $propNames */
        $propNames = array_keys(get_class_vars(get_called_class()));
        $propNamesNotForOptions = array_merge(self::snapshotTraitPropNamesNotForOptions(), self::additionalPropNamesNotForOptions());
        TestCaseBase::assertSame(count($propNamesNotForOptions), ArrayUtilForTests::removeAllValues(/* in,out */ $propNames, $propNamesNotForOptions));
        return $propNames;
    }

    public function getOptionValueByName(OptionForTestsName $optName): mixed
    {
        TestCaseBase::assertNotNull($this->optNameToParsedValue);
        return ArrayUtil::getValueIfKeyExistsElse($optName->name, $this->optNameToParsedValue, null);
    }
}
