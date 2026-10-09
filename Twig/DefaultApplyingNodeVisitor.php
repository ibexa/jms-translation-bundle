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

namespace JMS\TranslationBundle\Twig;

use JMS\TranslationBundle\Exception\RuntimeException;
use Twig\Environment;
use Twig\Node\Expression\AbstractExpression;
use Twig\Node\Expression\ArrayExpression;
use Twig\Node\Expression\Binary\EqualBinary;
use Twig\Node\Expression\FilterExpression;
use Twig\Node\Expression\FunctionExpression;
use Twig\Node\Expression\Ternary\ConditionalTernary;
use Twig\Node\Node;
use Twig\Node\Nodes;
use Twig\NodeVisitor\NodeVisitorInterface;

/**
 * Applies the value of the "desc" filter if the "trans" filter has no
 * translations.
 *
 * This is only active in your development environment.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class DefaultApplyingNodeVisitor implements NodeVisitorInterface
{
    private bool $enabled = true;

    public function setEnabled($bool)
    {
        $this->enabled = (bool) $bool;
    }

    public function enterNode(Node $node, Environment $env): Node
    {
        if (!$this->enabled) {
            return $node;
        }

        if (
            $node instanceof FilterExpression
                && 'desc' === ($node->hasAttribute('name') ? $node->getAttribute('name') : $node->getNode('filter')->getAttribute('value'))
        ) {
            $transNode = $node->getNode('node');
            while (
                $transNode instanceof FilterExpression
                    && !in_array($transNode->hasAttribute('name') ? $transNode->getAttribute('name') : $transNode->getNode('filter')->getAttribute('value'), ['trans'], true)
            ) {
                $transNode = $transNode->getNode('node');
            }

            if (!$transNode instanceof FilterExpression) {
                if (self::isTranslatableMessage($transNode)) {
                    // It describes a TranslatableMessage translated later: there is no translation to default here
                    return $node;
                }

                throw new RuntimeException(sprintf('The "desc" filter in "%s" line %d must be applied after a "trans" filter.', $node->getTemplateName(), $node->getTemplateLine()));
            }

            $translatedNode = $transNode->getNode('node');
            if (self::isTranslatableMessage($translatedNode)) {
                \assert($translatedNode instanceof FunctionExpression);

                return $this->applyToTranslatableMessage($node, $transNode, $translatedNode);
            }

            $wrappingNode = $node->getNode('node');
            \assert($wrappingNode instanceof AbstractExpression);

            $testNode     = clone $wrappingNode;
            $arguments    = iterator_to_array($node->getNode('arguments'));
            $defaultNode  = $arguments[0];
            \assert($defaultNode instanceof AbstractExpression);

            $wrappingNodeArguments = iterator_to_array($wrappingNode->getNode('arguments'));

            // if the |trans filter has replacements parameters
            // (e.g. |trans({'%foo%': 'bar'}))
            if (isset($wrappingNodeArguments[0])) {
                $lineno =  $wrappingNode->getTemplateLine();

                // remove the replacements from the test node
                $testNodeArguments    = iterator_to_array($testNode->getNode('arguments'));
                $testNodeArguments[0] = new ArrayExpression([], $lineno);
                $testNode->setNode('arguments', new Nodes($testNodeArguments));

                // translate the default node as its id, so that the replacements apply to it as they
                // would to its translation: translatable ones are translated, and plurals selected
                $translatedDefaultNode = clone $wrappingNode;
                $translatedDefaultNode->setNode('node', $defaultNode);
                $defaultNode = $translatedDefaultNode;
            }

            $transNodeInner = $transNode->getNode('node');
            \assert($transNodeInner instanceof AbstractExpression);

            $condition = new ConditionalTernary(
                new EqualBinary($testNode, $transNodeInner, $wrappingNode->getTemplateLine()),
                $defaultNode,
                clone $wrappingNode,
                $wrappingNode->getTemplateLine()
            );
            $node->setNode('node', $condition);
        }

        return $node;
    }

    /**
     * Applies the "desc" filter to "t(...)|trans": the translation of the message without its parameters is compared
     * with the message id, rather than with the TranslatableMessage. The default value is translated as the message,
     * as its id, so that the parameters apply to it, translatable ones included.
     */
    private function applyToTranslatableMessage(FilterExpression $descNode, FilterExpression $transNode, FunctionExpression $message): Node
    {
        if ($descNode->getNode('node') !== $transNode) {
            // Other filters come between: the default value would not stand for the same text
            return $descNode;
        }

        $messageArguments = iterator_to_array($message->getNode('arguments'));
        $idKey = array_key_exists('message', $messageArguments) ? 'message' : 0;
        $idNode = $messageArguments[$idKey] ?? null;
        if (!$idNode instanceof AbstractExpression) {
            return $descNode;
        }

        $lineno      = $transNode->getTemplateLine();
        $defaultNode = iterator_to_array($descNode->getNode('arguments'))[0];
        \assert($defaultNode instanceof AbstractExpression);

        $testMessage = clone $message;
        $parametersKey = array_key_exists('parameters', $messageArguments) ? 'parameters' : (isset($messageArguments[1]) ? 1 : null);
        if (null !== $parametersKey) {
            $testMessageArguments                 = $messageArguments;
            $testMessageArguments[$parametersKey] = new ArrayExpression([], $lineno);
            $testMessage->setNode('arguments', new Nodes($testMessageArguments));

            $defaultMessageArguments         = $messageArguments;
            $defaultMessageArguments[$idKey] = $defaultNode;
            $defaultMessage                  = clone $message;
            $defaultMessage->setNode('arguments', new Nodes($defaultMessageArguments));

            $defaultNode = clone $transNode;
            $defaultNode->setNode('node', $defaultMessage);
        }

        $testNode = clone $transNode;
        $testNode->setNode('node', $testMessage);

        $descNode->setNode('node', new ConditionalTernary(
            new EqualBinary($testNode, $idNode, $lineno),
            $defaultNode,
            clone $transNode,
            $lineno
        ));

        return $descNode;
    }

    private static function isTranslatableMessage(Node $node): bool
    {
        return $node instanceof FunctionExpression && 't' === $node->getAttribute('name');
    }

    public function leaveNode(Node $node, Environment $env): Node
    {
        return $node;
    }

    public function getPriority(): int
    {
        return -2;
    }
}
