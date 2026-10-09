<?php

declare(strict_types=1);

namespace JMS\TranslationBundle\Tests\Translation\Extractor\File\Fixture;

use JMS\TranslationBundle\Annotation\Desc;

final class DescribedWithDomainArgument
{
    #[Desc(name: 'Saved')]
    public const SAVED = 'status.saved';
}
