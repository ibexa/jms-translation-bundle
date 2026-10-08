<?php

declare(strict_types=1);

namespace JMS\TranslationBundle\Tests\Translation\Extractor\File\Fixture;

use JMS\TranslationBundle\Annotation\Desc;
use JMS\TranslationBundle\Annotation\Domain;
use JMS\TranslationBundle\Annotation\Meaning;

#[Domain('failures')]
enum DescribedFailure: string
{
    #[Desc('The service could not be reached.')]
    case Unreachable = 'failure.unreachable';

    #[Desc(text: 'The service rejected the credentials.'), Meaning('Authentication')]
    case Unauthorized = 'failure.unauthorized';

    #[Desc('Something else failed.')]
    #[Domain('other')]
    case Other = 'failure.other';

    case Undescribed = 'failure.undescribed';
}
