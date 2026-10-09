<?php

declare(strict_types=1);

namespace JMS\TranslationBundle\Tests\Translation\Extractor\File\Fixture;

use JMS\TranslationBundle\Annotation\Desc;
use JMS\TranslationBundle\Annotation\Domain;

#[Domain(text: 'app')]
final class DescribedInDomainWithTextArgument
{
    #[Desc('Saved')]
    public const SAVED = 'status.saved';
}
