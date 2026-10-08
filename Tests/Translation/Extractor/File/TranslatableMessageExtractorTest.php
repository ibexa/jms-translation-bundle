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
use JMS\TranslationBundle\Translation\Extractor\File\TranslatableMessageExtractor;
use Psr\Log\AbstractLogger;

class TranslatableMessageExtractorTest extends PhpFileExtractorTestCase
{
    public function testExtractsTranslatableMessagesAndTheirFunction(): void
    {
        $fileSourceFactory = $this->getFileSourceFactory();
        $fixture = new \SplFileInfo(__DIR__ . '/Fixture/TranslatableMessages.php');

        $expected = new MessageCatalogue();
        foreach (
            [
                ['greeting', 'app', 'Hello %name%!', null, 17],
                ['farewell', 'messages', 'Goodbye!', 'Leaving the site', 19],
                ['status.created', 'app', 'Created', null, 23],
                ['status.updated', 'messages', 'Updated', null, 24],
                ['status.shown', 'app', 'Shown', null, 25],
                ['status.named', 'app', null, null, 26],
            ] as [$id, $domain, $desc, $meaning, $line]
        ) {
            $message = new Message($id, $domain);
            $message->setDesc($desc);
            $message->setMeaning($meaning);
            $message->addSource($fileSourceFactory->create($fixture, $line));
            $expected->add($message);
        }

        self::assertEquals($expected, $this->extract('TranslatableMessages.php'));
    }

    public function testOtherClassesAndFunctionsOfTheSameNameAreLeftAlone(): void
    {
        self::assertEquals(new MessageCatalogue(), $this->extract('OtherTranslatableMessages.php'));
    }

    public function testMessageIdWhichIsNotAStringIsReported(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Can only extract the translation id from a scalar string, but got "PhpParser\Node\Expr\Variable".');

        $this->extract('TranslatableMessageWithDynamicId.php');
    }

    public function testMessageIdWhichIsNotAStringIsLoggedWithALogger(): void
    {
        $logger = new class () extends AbstractLogger {
            /** @var list<string> */
            public array $errors = [];

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->errors[] = (string) $message;
            }
        };
        $extractor = $this->getDefaultExtractor();
        $extractor->setLogger($logger);

        self::assertEquals(new MessageCatalogue(), $this->extract('TranslatableMessageWithDynamicId.php', $extractor));
        self::assertCount(1, $logger->errors);
    }

    protected function getDefaultExtractor(): TranslatableMessageExtractor
    {
        return new TranslatableMessageExtractor($this->getDocParser(), $this->getFileSourceFactory());
    }
}
