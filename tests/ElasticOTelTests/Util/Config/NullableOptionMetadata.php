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

use Override;

/**
 * Code in this file is part of implementation internals, and thus it is not covered by the backward compatibility.
 *
 * @internal
 *
 * @template TParsedValue
 *
 * @extends OptionMetadata<TParsedValue>
 */
abstract class NullableOptionMetadata extends OptionMetadata
{
    /** @var OptionParser<TParsedValue> */
    private OptionParser $parser;

    /**
     * @param OptionParser<TParsedValue> $parser
     */
    public function __construct(OptionParser $parser)
    {
        $this->parser = $parser;
    }

    /** @inheritDoc */
    #[Override]
    public function parser(): OptionParser
    {
        return $this->parser;
    }

    /**
     * @inheritDoc
     *
     * @return null
     */
    #[Override]
    public function defaultValue(): mixed
    {
        return null;
    }
}
