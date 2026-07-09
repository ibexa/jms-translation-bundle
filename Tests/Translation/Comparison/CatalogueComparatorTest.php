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

namespace JMS\TranslationBundle\Tests\Translation\Comparison;

use JMS\TranslationBundle\Model\Message;
use JMS\TranslationBundle\Model\MessageCatalogue;
use JMS\TranslationBundle\Translation\Comparison\CatalogueComparator;
use JMS\TranslationBundle\Translation\Comparison\ChangeSet;
use PHPUnit\Framework\TestCase;

class CatalogueComparatorTest extends TestCase
{
    public function testCompareWithMultipleDomains(): void
    {
        $current = new MessageCatalogue();
        $current->add(Message::create('foo')->setLocaleString('bar'));
        $current->add(Message::create('bar', 'routes')->setLocaleString('baz'));

        $new = new MessageCatalogue();
        $new->add(new Message('foo'));
        $new->add(new Message('bar'));

        $expected   = new ChangeSet(
            [new Message('bar')],
            [Message::create('bar', 'routes')->setLocaleString('baz')]
        );
        $comparator = new CatalogueComparator();

        $this->assertEquals($expected, $comparator->compare($current, $new));
    }

    public function testCompareDetectsChangedDesc(): void
    {
        $current = new MessageCatalogue();
        $current->add(Message::create('foo')->setDesc('old_desc')->setLocaleString('translated'));

        $new = new MessageCatalogue();
        $new->add(Message::create('foo')->setDesc('new_desc'));

        $comparator = new CatalogueComparator();
        $changeSet  = $comparator->compare($current, $new);

        $this->assertCount(0, $changeSet->getAddedMessages());
        $this->assertCount(0, $changeSet->getDeletedMessages());
        $this->assertCount(1, $changeSet->getChangedMessages());
        $this->assertSame('foo', $changeSet->getChangedMessages()[0]->getId());
    }

    public function testCompareDetectsChangedMeaning(): void
    {
        $current = new MessageCatalogue();
        $current->add(Message::create('foo')->setMeaning('old_meaning'));

        $new = new MessageCatalogue();
        $new->add(Message::create('foo')->setMeaning('new_meaning'));

        $comparator = new CatalogueComparator();
        $changeSet  = $comparator->compare($current, $new);

        $this->assertCount(1, $changeSet->getChangedMessages());
    }

    public function testCompareIgnoresLocaleStringDifferencesForChangedMessages(): void
    {
        $current = new MessageCatalogue();
        $current->add(Message::create('foo')->setDesc('desc')->setLocaleString('translated'));

        $new = new MessageCatalogue();
        $new->add(Message::create('foo')->setDesc('desc'));

        $comparator = new CatalogueComparator();
        $changeSet  = $comparator->compare($current, $new);

        $this->assertCount(0, $changeSet->getChangedMessages());
    }

    /**
     * A message with no code-derived desc (e.g. no @Desc annotation) always has a
     * non-null desc once loaded from XLIFF, since the loader falls back to <source>.
     * The freshly scanned message legitimately has no desc in that case, and that
     * absence must not be mistaken for drift.
     */
    public function testCompareDoesNotFlagMessageWithoutScannedDescAsChanged(): void
    {
        $current = new MessageCatalogue();
        $current->add(Message::create('foo')->setDesc('foo')->setLocaleString('foo'));

        $new = new MessageCatalogue();
        $new->add(Message::create('foo'));

        $comparator = new CatalogueComparator();
        $changeSet  = $comparator->compare($current, $new);

        $this->assertCount(0, $changeSet->getChangedMessages());
    }

    /**
     * Some extractors set meaning to '' rather than leaving it null when no
     * @Meaning annotation is present, while the loaded catalogue always has a
     * null meaning in that case. That must not be mistaken for drift either.
     */
    public function testCompareDoesNotFlagBlankScannedMeaningAsChanged(): void
    {
        $current = new MessageCatalogue();
        $current->add(Message::create('foo')->setDesc('desc'));

        $new = new MessageCatalogue();
        $new->add(Message::create('foo')->setDesc('desc')->setMeaning(''));

        $comparator = new CatalogueComparator();
        $changeSet  = $comparator->compare($current, $new);

        $this->assertCount(0, $changeSet->getChangedMessages());
    }

    /**
     * Some extractors write the sample text a translator sees into meaning instead
     * of desc, while the loaded catalogue never had a meaning for that message (no
     * <extradata> was ever written for it, e.g. because it predates that extractor).
     * A blank existing value is "never captured", not "captured as empty", so it
     * must not be mistaken for drift either.
     */
    public function testCompareDoesNotFlagBlankExistingMeaningAsChanged(): void
    {
        $current = new MessageCatalogue();
        $current->add(Message::create('foo')->setDesc('desc'));

        $new = new MessageCatalogue();
        $new->add(Message::create('foo')->setDesc('desc')->setMeaning('Location path'));

        $comparator = new CatalogueComparator();
        $changeSet  = $comparator->compare($current, $new);

        $this->assertCount(0, $changeSet->getChangedMessages());
    }
}
