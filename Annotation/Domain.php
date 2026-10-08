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

/**
 * The translation domain of the message ids held by class constants or enum cases described with the Desc
 * attribute: on a constant or case, or on its class or enum for all of them. Without it, the domain is "messages".
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_CLASS_CONSTANT)]
final class Domain
{
    public function __construct(
        public readonly string $name,
    ) {
    }
}
