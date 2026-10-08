<?php

declare(strict_types=1);

namespace JMS\TranslationBundle\Tests\Translation\Extractor\File\Fixture;

use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatorInterface;

final class TranslatableTransCalls
{
    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    public function translate(TranslatableMessage $message, TranslatorInterface $translator, TranslatorInterface $t): array
    {
        return [
            $message->trans($translator),
            $message->trans($this->translator, 'fr'),
            $message->trans($this->getTranslator()),
            $this->translator->trans(...),
            $message->trans(),
            $this->translator->trans('text.still_extracted'),
            /** @Ignore */
            $message->trans($t),
        ];
    }

    private function getTranslator(): TranslatorInterface
    {
        return $this->translator;
    }
}
