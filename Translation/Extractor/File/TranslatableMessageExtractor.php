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

use Doctrine\Common\Annotations\DocParser;
use JMS\TranslationBundle\Annotation\Desc;
use JMS\TranslationBundle\Annotation\Ignore;
use JMS\TranslationBundle\Annotation\Meaning;
use JMS\TranslationBundle\Model\Message;
use JMS\TranslationBundle\Translation\FileSourceFactory;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt;

/**
 * Extracts the messages of Symfony's translatable objects from PHP files: "new TranslatableMessage(...)" and the
 * "t(...)" function, which are translated later, e.g. by the "trans" Twig filter.
 *
 *     /** @Desc("Hello %name%!") *\/
 *     $greeting = new TranslatableMessage('greeting', ['%name%' => $name], 'app');
 *
 * As for "trans()" calls, the @Desc, @Meaning and @Ignore annotations can be put in a doc comment before the message
 * id or the expression, and also before the statement, assignment, array item or argument the message is part of.
 *
 * Names are resolved, so other classes or functions of the same short name are left alone.
 */
final class TranslatableMessageExtractor extends AbstractPhpFileExtractor
{
    private const TRANSLATABLE_MESSAGE_CLASS = 'Symfony\Component\Translation\TranslatableMessage';
    private const TRANSLATABLE_MESSAGE_FUNCTION = 'Symfony\Component\Translation\t';

    /** @var list<Node> the nodes being visited, the current one last */
    private array $stack = [];

    public function __construct(
        private readonly DocParser $docParser,
        private readonly FileSourceFactory $fileSourceFactory,
    ) {
        parent::__construct();
    }

    public function beforeTraverse(array $nodes)
    {
        $this->stack = [];

        return null;
    }

    public function enterNode(Node $node)
    {
        $this->stack[] = $node;

        if ($this->isTranslatableMessage($node)) {
            \assert($node instanceof New_ || $node instanceof FuncCall);
            $this->extract($node);
        }

        return null;
    }

    public function leaveNode(Node $node)
    {
        array_pop($this->stack);

        return null;
    }

    private function isTranslatableMessage(Node $node): bool
    {
        if ($node instanceof New_ && $node->class instanceof Name) {
            return self::TRANSLATABLE_MESSAGE_CLASS === self::getResolvedName($node->class);
        }

        if ($node instanceof FuncCall && $node->name instanceof Name) {
            return self::TRANSLATABLE_MESSAGE_FUNCTION === self::getResolvedName($node->name);
        }

        return false;
    }

    private function extract(New_|FuncCall $node): void
    {
        if ($node->isFirstClassCallable()) {
            return;
        }

        foreach ($node->getArgs() as $arg) {
            if ($arg->unpack) {
                // The arguments are only known at runtime
                return;
            }
        }

        $idArg = self::findArg($node, 0, 'message');
        if (null === $idArg) {
            return;
        }

        $ignore = false;
        $desc = $meaning = null;
        if (null !== $docComment = $this->findDocComment($idArg)) {
            foreach ($this->docParser->parse($docComment, 'file ' . $this->file . ' near line ' . $node->getLine()) as $annotation) {
                if ($annotation instanceof Ignore) {
                    $ignore = true;
                } elseif ($annotation instanceof Desc) {
                    $desc = $annotation->text;
                } elseif ($annotation instanceof Meaning) {
                    $meaning = $annotation->text;
                }
            }
        }

        if (!$idArg->value instanceof String_) {
            if (!$ignore) {
                $this->reportError(sprintf('Can only extract the translation id from a scalar string, but got "%s". Please refactor your code to make it extractable, or add the doc comment /** @Ignore */ to this code element (in %s on line %d).', get_class($idArg->value), $this->file, $idArg->getLine()));
            }

            return;
        }

        $domain = 'messages';
        $domainArg = self::findArg($node, 2, 'domain');
        if (null !== $domainArg && !($domainArg->value instanceof ConstFetch && 'null' === $domainArg->value->name->toLowerString())) {
            if (!$domainArg->value instanceof String_) {
                if (!$ignore) {
                    $this->reportError(sprintf('Can only extract the translation domain from a scalar string, but got "%s". Please refactor your code to make it extractable, or add the doc comment /** @Ignore */ to this code element (in %s on line %d).', get_class($domainArg->value), $this->file, $domainArg->getLine()));
                }

                return;
            }

            $domain = $domainArg->value->value;
        }

        $message = new Message($idArg->value->value, $domain);
        $message->setDesc($desc);
        $message->setMeaning($meaning);
        $message->addSource($this->fileSourceFactory->create($this->file, $node->getLine()));
        $this->catalogue->add($message);
    }

    /**
     * The argument at the position, unless it is given by name.
     */
    private static function findArg(New_|FuncCall $node, int $position, string $name): ?Arg
    {
        $args = $node->getArgs();
        if (isset($args[$position]) && null === $args[$position]->name) {
            return $args[$position];
        }

        foreach ($args as $arg) {
            if (null !== $arg->name && $name === $arg->name->toString()) {
                return $arg;
            }
        }

        return null;
    }

    /**
     * The doc comment of the message id, of the message, or of what the message is part of, up to its statement.
     */
    private function findDocComment(Arg $idArg): ?string
    {
        if (null !== $comment = $idArg->getDocComment()) {
            return $comment->getText();
        }

        // The current node is the message itself, the last one of the stack
        for ($i = count($this->stack) - 1; $i >= 0; --$i) {
            if (null !== $comment = $this->stack[$i]->getDocComment()) {
                return $comment->getText();
            }

            if ($this->stack[$i] instanceof Stmt) {
                break;
            }
        }

        return null;
    }
}
