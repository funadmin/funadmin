<?php
/* ============================================================================
 * Copyright 2020 Zindex Software
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 * ============================================================================ */

namespace Opis\JsonSchema\Parsers\Keywords;

use Opis\JsonSchema\Keyword;
use Opis\JsonSchema\Info\SchemaInfo;
use Opis\JsonSchema\Keywords\AdditionalPropertiesKeyword;
use Opis\JsonSchema\Parsers\{KeywordParser, SchemaParser};

class AdditionalPropertiesKeywordParser extends KeywordParser
{
    /**
     * @inheritDoc
     */
    public function type(): string
    {
        return self::TYPE_OBJECT;
    }

    /**
     * @inheritDoc
     */
    public function parse(SchemaInfo $info, SchemaParser $parser, object $shared): ?Keyword
    {
        $schema = $info->data();

        if (!$this->keywordExists($schema)) {
            return null;
        }

        $value = $this->keywordValue($schema);

        if (!is_bool($value) && !is_object($value)) {
            throw $this->keywordException("{keyword} must be a json schema (object or boolean)", $info);
        }

        return new AdditionalPropertiesKeyword($value);
    }
}