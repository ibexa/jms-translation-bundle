<?php

declare(strict_types=1);

namespace JMS\TranslationBundle\Tests\Translation\Extractor\File\Fixture;

use JMS\TranslationBundle\Annotation\Desc;

final class DescribedConstantGroup
{
    #[Desc('Which one?')]
    public const FIRST = 'first', SECOND = 'second'; // phpcs:ignore SlevomatCodingStandard.Classes.DisallowMultiConstantDefinition
}
