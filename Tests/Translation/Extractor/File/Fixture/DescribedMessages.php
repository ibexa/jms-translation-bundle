<?php

declare(strict_types=1);

namespace JMS\TranslationBundle\Tests\Translation\Extractor\File\Fixture;

use JMS\TranslationBundle\Annotation\Desc;

final class DescribedMessages
{
    #[Desc('Saved')]
    public const SAVED = 'status.saved';

    public const UNDESCRIBED = 'status.undescribed';

    #[\Attribute]
    public const NOT_DESCRIBED_WITH_DESC = 'status.attributed';
}
