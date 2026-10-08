<?php

declare(strict_types=1);

/*
 * Copyright 2011 Johannes M. Schmitt <schmittjoh@gmail.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace JMS\TranslationBundle\Annotation;

use JMS\TranslationBundle\Exception\RuntimeException;

/**
 * @Annotation
 *
 * The description of a translated message: an annotation in the doc comment of the message, or an attribute on the
 * class constant or enum case whose value is the message id.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_CLASS_CONSTANT)]
final class Desc
{
    /** @var string @Required */
    public $text;

    /**
     * @param array{value?: string, text?: string}|string $values the values of the annotation, or the text as an attribute
     * @param string|null $text   the text, as a named argument of the attribute
     */
    public function __construct(array|string $values = [], ?string $text = null)
    {
        if (0 === func_num_args()) {
            return;
        }

        if (is_string($values)) {
            $values = ['text' => $values];
        }

        if (null !== $text) {
            $values['text'] = $text;
        }

        if (isset($values['value'])) {
            $values['text'] = $values['value'];
        }

        if (!isset($values['text'])) {
            throw new RuntimeException(sprintf('The "text" attribute for annotation "@Desc" must be set.'));
        }

        $this->text = $values['text'];
    }
}
