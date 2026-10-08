<?php

declare(strict_types=1);

namespace JMS\TranslationBundle\Tests\Translation\Extractor\File\Fixture;

use Symfony\Component\Translation\TranslatableMessage;

final class TranslatableMessageWithDynamicId
{
    public function message(string $id): TranslatableMessage
    {
        return new TranslatableMessage($id);
    }
}
