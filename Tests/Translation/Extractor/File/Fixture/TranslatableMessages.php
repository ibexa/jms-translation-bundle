<?php

declare(strict_types=1);

namespace JMS\TranslationBundle\Tests\Translation\Extractor\File\Fixture;

use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Translation\TranslatableMessage as Message;

use function Symfony\Component\Translation\t;

final class TranslatableMessages
{
    public function messages(string $name, string $dynamicId): array
    {
        /** @Desc("Hello %name%!") */
        $greeting = new TranslatableMessage('greeting', ['%name%' => $name], 'app');

        $farewell = /** @Desc("Goodbye!") @Meaning("Leaving the site") */ new Message('farewell');

        return [
            /** @Desc("Created") */
            'created' => t('status.created', domain: 'app'),
            'updated' => new TranslatableMessage(/** @Desc("Updated") */ 'status.updated', [], null),
            'shown' => $this->show(/** @Desc("Shown") */ t('status.shown', [], 'app')),
            'named' => new TranslatableMessage(domain: 'app', message: 'status.named'),
            'ignored' => /** @Ignore */ new TranslatableMessage($dynamicId),
            'greeting' => $greeting,
            'farewell' => $farewell,
        ];
    }

    private function show(TranslatableMessage $message): TranslatableMessage
    {
        return $message;
    }
}
