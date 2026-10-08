<?php

declare(strict_types=1);

namespace JMS\TranslationBundle\Tests\Translation\Extractor\File\Fixture;

use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatorInterface;

final class TranslatableTransCallWithOtherTranslatorName
{
    public function translate(TranslatableMessage $message, TranslatorInterface $t): string
    {
        return $message->trans($t);
    }
}
