<?php

declare(strict_types=1);

namespace JMS\TranslationBundle\Tests\Translation\Extractor\File\Fixture;

use JMS\TranslationBundle\Annotation\Desc;

enum DescribedIntegerCase: int
{
    #[Desc('Not a message id')]
    case One = 1;
}
