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

class ChangeSet
{
    /**
     * @var array
     */
    private $addedMessages;

    /**
     * @var array
     */
    private $deletedMessages;

    /**
     * Messages that exist in both catalogues (same id+domain), but whose code-derived
     * content (desc/meaning) has drifted from what is currently on disk.
     *
     * @var Message[]
     */
    private $changedMessages;

    /**
     * @param array     $addedMessages
     * @param array     $deletedMessages
     * @param Message[] $changedMessages
     */
    public function __construct(array $addedMessages, array $deletedMessages, array $changedMessages = [])
    {
        $this->addedMessages = $addedMessages;
        $this->deletedMessages = $deletedMessages;
        $this->changedMessages = $changedMessages;
    }

    /**
     * @return array
     */
    public function getAddedMessages()
    {
        return $this->addedMessages;
    }

    /**
     * @return array
     */
    public function getDeletedMessages()
    {
        return $this->deletedMessages;
    }

    /**
     * @return Message[]
     */
    public function getChangedMessages()
    {
        return $this->changedMessages;
    }
}
