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

namespace JMS\TranslationBundle\Translation\Comparison;

use JMS\TranslationBundle\Model\Message;
use JMS\TranslationBundle\Model\MessageCatalogue;

/**
 * Compares two message catalogues.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class CatalogueComparator
{
    private $domains = [];
    private $ignoredDomains = [];

    public function setDomains(array $domains)
    {
        $this->domains = $domains;
    }

    /**
     * @param array $domains
     */
    public function setIgnoredDomains(array $domains)
    {
        $this->ignoredDomains = $domains;
    }

    /**
     * Compares two message catalogues.
     *
     * @param MessageCatalogue $current
     * @param MessageCatalogue $new
     *
     * @return ChangeSet
     */
    public function compare(MessageCatalogue $current, MessageCatalogue $new)
    {
        $newMessages = [];
        $changedMessages = [];

        foreach ($new->getDomains() as $name => $domain) {
            if ($this->domains && !isset($this->domains[$name])) {
                continue;
            }

            if (isset($this->ignoredDomains[$name])) {
                continue;
            }

            foreach ($domain->all() as $message) {
                if ($current->has($message)) {
                    $existingMessage = $current->get($message->getId(), $message->getDomain());
                    if ($this->hasContentChanged($existingMessage, $message)) {
                        $changedMessages[] = $message;
                    }

                    continue;
                }

                $newMessages[] = $message;
            }
        }

        $deletedMessages = [];
        foreach ($current->getDomains() as $name => $domain) {
            if ($this->domains && !isset($this->domains[$name])) {
                continue;
            }

            if (isset($this->ignoredDomains[$name])) {
                continue;
            }

            foreach ($domain->all() as $message) {
                if ($new->has($message)) {
                    continue;
                }

                $deletedMessages[] = $message;
            }
        }

        return new ChangeSet($newMessages, $deletedMessages, $changedMessages);
    }

    /**
     * Compares the code-derived content of a message (desc/meaning) between the version
     * currently on disk and the freshly scanned one. Deliberately ignores localeString,
     * since that is translator-provided content and is expected to differ until a human
     * (re)translates it.
     */
    private function hasContentChanged(Message $existing, Message $scanned): bool
    {
        return $this->valueHasChanged($existing->getDesc(), $scanned->getDesc())
            || $this->valueHasChanged($existing->getMeaning(), $scanned->getMeaning());
    }

    /**
     * Compares a single code-derived value (desc or meaning).
     *
     * Only flags a change if both sides actually have a value. If either side is blank,
     * that's usually just a field that was never filled in (e.g. no @Desc annotation), not
     * something that got deleted, so we don't want to flag it as changed.
     */
    private function valueHasChanged(?string $existing, ?string $scanned): bool
    {
        $scanned = null !== $scanned ? trim($scanned) : '';
        $existing = null !== $existing ? trim($existing) : '';

        if ('' === $scanned || '' === $existing) {
            return false;
        }

        return $existing !== $scanned;
    }
}
