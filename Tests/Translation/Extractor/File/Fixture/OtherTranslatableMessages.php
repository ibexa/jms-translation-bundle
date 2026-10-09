<?php

declare(strict_types=1);

namespace JMS\TranslationBundle\Tests\Translation\Extractor\File\Fixture;

/**
 * A class and a function of the same short names as Symfony's, but which are not Symfony's: "TranslatableMessage"
 * is this namespace's class, and "t()", not imported, this namespace's or the global function.
 */
final class OtherTranslatableMessages
{
    public function messages(): array
    {
        return [
            new TranslatableMessage('not.extracted.class'),
            t('not.extracted.function'),
        ];
    }
}
