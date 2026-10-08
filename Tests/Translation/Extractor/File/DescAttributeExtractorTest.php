<?php

declare(strict_types=1);

/*
 * Copyright 2011 Johannes M. Schmitt <schmittjoh@gmail.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace JMS\TranslationBundle\Tests\Translation\Extractor\File;

use JMS\TranslationBundle\Exception\RuntimeException;
use JMS\TranslationBundle\Model\Message;
use JMS\TranslationBundle\Model\MessageCatalogue;
use JMS\TranslationBundle\Translation\Extractor\File\DescAttributeExtractor;
use PHPUnit\Framework\Attributes\DataProvider;

class DescAttributeExtractorTest extends PhpFileExtractorTestCase
{
    public function testExtractsTheEnumCasesDescribedWithAttributes(): void
    {
        self::assertEquals(
            $this->createCatalogue('DescribedFailure.php', [
                ['failure.unreachable', 'failures', 'The service could not be reached.', null, 15],
                ['failure.unauthorized', 'failures', 'The service rejected the credentials.', 'Authentication', 18],
                ['failure.other', 'other', 'Something else failed.', null, 22],
            ]),
            $this->extract('DescribedFailure.php'),
        );
    }

    public function testExtractsTheClassConstantsDescribedWithAttributes(): void
    {
        self::assertEquals(
            $this->createCatalogue('DescribedMessages.php', [['status.saved', 'messages', 'Saved', null, 12]]),
            $this->extract('DescribedMessages.php'),
        );
    }

    #[DataProvider('provideInvalidDescriptions')]
    public function testInvalidDescriptionIsReported(string $fixture, string $expectedError): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($expectedError);

        $this->extract($fixture);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideInvalidDescriptions(): iterable
    {
        yield 'enum case whose value is not a string' => [
            'DescribedIntegerCase.php',
            'Can only extract the translation id from a scalar string, but got "PhpParser\Node\Scalar\Int_".',
        ];

        yield 'group of constants' => [
            'DescribedConstantGroup.php',
            'The Desc attribute can only describe a single constant, but describes 2',
        ];

        yield 'Desc with the argument of Domain' => [
            'DescribedWithDomainArgument.php',
            'The JMS\TranslationBundle\Annotation\Desc attribute needs a "text" argument',
        ];

        yield 'Domain with the argument of Desc' => [
            'DescribedInDomainWithTextArgument.php',
            'The JMS\TranslationBundle\Annotation\Domain attribute needs a "name" argument',
        ];
    }

    /**
     * @param list<array{string, string, string, string|null, int}> $messages id, domain, desc, meaning and line
     */
    private function createCatalogue(string $file, array $messages): MessageCatalogue
    {
        $fixture = new \SplFileInfo(__DIR__ . '/Fixture/' . $file);
        $catalogue = new MessageCatalogue();
        foreach ($messages as [$id, $domain, $desc, $meaning, $line]) {
            $message = new Message($id, $domain);
            $message->setDesc($desc);
            $message->setMeaning($meaning);
            $message->addSource($this->getFileSourceFactory()->create($fixture, $line));
            $catalogue->add($message);
        }

        return $catalogue;
    }

    protected function getDefaultExtractor(): DescAttributeExtractor
    {
        return new DescAttributeExtractor($this->getFileSourceFactory());
    }
}
