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

namespace JMS\TranslationBundle\Translation\Extractor\File;

use JMS\TranslationBundle\Exception\RuntimeException;
use JMS\TranslationBundle\Logger\LoggerAwareInterface;
use JMS\TranslationBundle\Model\MessageCatalogue;
use JMS\TranslationBundle\Translation\Extractor\FileVisitorInterface;
use PhpParser\Node\Name;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitorAbstract;
use Psr\Log\LoggerInterface;
use Twig\Node\Node as TwigNode;

/**
 * Extracts messages from PHP files, whose names are resolved: see getResolvedName(). Subclasses visit the nodes, and
 * reset what they keep between files in beforeTraverse().
 *
 * @internal
 */
abstract class AbstractPhpFileExtractor extends NodeVisitorAbstract implements LoggerAwareInterface, FileVisitorInterface
{
    protected \SplFileInfo $file;

    protected MessageCatalogue $catalogue;

    private ?LoggerInterface $logger = null;

    private readonly NodeTraverser $traverser;

    public function __construct()
    {
        // Names are only annotated, not replaced: other visitors get the same AST.
        $this->traverser = new NodeTraverser(new NameResolver(null, ['replaceNodes' => false]), $this);
    }

    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function visitPhpFile(\SplFileInfo $file, MessageCatalogue $catalogue, array $ast)
    {
        $this->file = $file;
        $this->catalogue = $catalogue;
        $this->traverser->traverse($ast);
    }

    public function visitFile(\SplFileInfo $file, MessageCatalogue $catalogue)
    {
    }

    public function visitTwigFile(\SplFileInfo $file, MessageCatalogue $catalogue, TwigNode $ast)
    {
    }

    /**
     * The fully qualified name, e.g. of a class imported under another name.
     */
    final protected static function getResolvedName(Name $name): ?string
    {
        $resolvedName = $name->getAttribute('resolvedName');

        return $resolvedName instanceof Name ? $resolvedName->toString() : null;
    }

    /**
     * Logs the error, or throws it without a logger.
     */
    final protected function reportError(string $message): void
    {
        if (null === $this->logger) {
            throw new RuntimeException($message);
        }

        $this->logger->error($message);
    }
}
