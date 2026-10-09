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

use JMS\TranslationBundle\Annotation\Desc;
use JMS\TranslationBundle\Annotation\Domain;
use JMS\TranslationBundle\Annotation\Meaning;
use JMS\TranslationBundle\Model\Message;
use JMS\TranslationBundle\Translation\FileSourceFactory;
use PhpParser\Node;
use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\EnumCase;

/**
 * Extracts the message ids held by class constants and enum cases described with the Desc attribute, e.g. an enum
 * whose cases are translated messages:
 *
 *     #[Domain('app')]
 *     enum Failure: string implements TranslatableInterface
 *     {
 *         #[Desc('The service could not be reached.')]
 *         case Unreachable = 'failure.unreachable';
 *     }
 *
 * PHP attributes cannot describe an expression, such as "new TranslatableMessage(...)": they only apply to
 * declarations, hence the constants and enum cases. Meaning gives the meaning of the message, and Domain its
 * translation domain, on the constant or case, or on its class or enum; it is "messages" without it.
 */
final class DescAttributeExtractor extends AbstractPhpFileExtractor
{
    /** @var list<string> the domain given by each class or enum being visited, the innermost last */
    private array $classDomains = [];

    public function __construct(
        private readonly FileSourceFactory $fileSourceFactory,
    ) {
        parent::__construct();
    }

    public function beforeTraverse(array $nodes)
    {
        $this->classDomains = [];

        return null;
    }

    public function enterNode(Node $node)
    {
        if ($node instanceof ClassLike) {
            $this->classDomains[] = $this->findText($node->attrGroups, Domain::class, 'name') ?? end($this->classDomains) ?: 'messages';
        } elseif ($node instanceof ClassConst) {
            $this->extractConstants($node);
        } elseif ($node instanceof EnumCase) {
            $this->extract($node, $node->expr, $node->attrGroups);
        }

        return null;
    }

    public function leaveNode(Node $node)
    {
        if ($node instanceof ClassLike) {
            array_pop($this->classDomains);
        }

        return null;
    }

    private function extractConstants(ClassConst $node): void
    {
        if (null === $this->findAttribute($node->attrGroups, Desc::class)) {
            return;
        }

        if (1 !== count($node->consts)) {
            $this->reportError(sprintf('The Desc attribute can only describe a single constant, but describes %d (in %s on line %d).', count($node->consts), $this->file, $node->getLine()));

            return;
        }

        $this->extract($node, $node->consts[0]->value, $node->attrGroups);
    }

    /**
     * @param AttributeGroup[] $attrGroups
     */
    private function extract(Node $node, ?Expr $value, array $attrGroups): void
    {
        $desc = $this->findText($attrGroups, Desc::class, 'text');
        if (null === $desc) {
            return;
        }

        if (!$value instanceof String_) {
            $this->reportError(sprintf('Can only extract the translation id from a scalar string, but got "%s". The Desc attribute can only describe a constant or enum case whose value is a string (in %s on line %d).', null === $value ? 'nothing' : $value::class, $this->file, $node->getLine()));

            return;
        }

        $message = new Message($value->value, $this->findText($attrGroups, Domain::class, 'name') ?? end($this->classDomains) ?: 'messages');
        $message->setDesc($desc);
        $message->setMeaning($this->findText($attrGroups, Meaning::class, 'text'));
        // The line of the id, after the attributes the declaration starts with
        $message->addSource($this->fileSourceFactory->create($this->file, $value->getLine()));
        $this->catalogue->add($message);
    }

    /**
     * The text of the attribute: its first argument, or the argument of that name, as its constructor names it, e.g.
     * "text" for Desc and Meaning, and "name" for Domain.
     *
     * @param AttributeGroup[] $attrGroups
     * @param class-string $class
     */
    private function findText(array $attrGroups, string $class, string $argumentName): ?string
    {
        $attribute = $this->findAttribute($attrGroups, $class);
        if (null === $attribute) {
            return null;
        }

        foreach ($attribute->args as $position => $arg) {
            if (null === $arg->name ? 0 === $position : $argumentName === $arg->name->toString()) {
                if ($arg->value instanceof String_) {
                    return $arg->value->value;
                }

                $this->reportError(sprintf('The "%s" argument of the %s attribute must be a scalar string, but got "%s" (in %s on line %d).', $argumentName, $class, get_class($arg->value), $this->file, $attribute->getLine()));

                return null;
            }
        }

        $this->reportError(sprintf('The %s attribute needs a "%s" argument (in %s on line %d).', $class, $argumentName, $this->file, $attribute->getLine()));

        return null;
    }

    /**
     * @param AttributeGroup[] $attrGroups
     * @param class-string $class
     */
    private function findAttribute(array $attrGroups, string $class): ?Attribute
    {
        foreach ($attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attribute) {
                if ($class === self::getResolvedName($attribute->name)) {
                    return $attribute;
                }
            }
        }

        return null;
    }
}
