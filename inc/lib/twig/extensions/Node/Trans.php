<?php

/*
 * This file is part of Twig.
 *
 * (c) 2010 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Twig\Compiler;
use Twig\Node\Expression\AbstractExpression;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\FilterExpression;
use Twig\Node\Expression\NameExpression;
use Twig\Node\Expression\TempNameExpression;
use Twig\Node\Node;
use Twig\Node\PrintNode;
use Twig\Node\SetNode;

/**
 * Represents a trans node.
 *
 * @package    twig
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class Twig_Extensions_Node_Trans extends Node
{
    public function __construct(Node $body, ?Node $plural = null, ?AbstractExpression $count = null, ?Node $notes = null, $lineno, $tag = null)
    {
        $nodes = array('body' => $body);
        if (null !== $count) {
            $nodes['count'] = $count;
        }
        if (null !== $plural) {
            $nodes['plural'] = $plural;
        }
        if (null !== $notes) {
            $nodes['notes'] = $notes;
        }
        
        parent::__construct($nodes, [], $lineno, $tag);
    }
    
    /**
     * Compiles the node to PHP.
     *
     * @param Compiler $compiler A Twig_Compiler instance
     */
    public function compile(Compiler $compiler): void
    {
        $compiler->addDebugInfo($this);
        
        [$msg, $vars] = $this->compileString($this->getNode('body'));
        
        if (null !== $this->getNode('plural')) {
            [$msg1, $vars1] = $this->compileString($this->getNode('plural'));
            
            $vars = array_merge($vars, $vars1);
        }
        
        $function = null === $this->getNode('plural') ? 'gettext' : 'ngettext';
        
        if ($vars) {
            $compiler
                ->write('echo strtr(' . $function . '(')
                ->subcompile($msg);
            
            if (null !== $this->getNode('plural')) {
                $compiler
                    ->raw(', ')
                    ->subcompile($msg1)
                    ->raw(', abs(')
                    ->subcompile($this->getNode('count'))
                    ->raw(')');
            }
            
            $compiler->raw('), array(');
            
            foreach ($vars as $var) {
                if ('count' === $var->getAttribute('name')) {
                    $compiler
                        ->string('%count%')
                        ->raw(' => abs(')
                        ->subcompile($this->getNode('count'))
                        ->raw('), ');
                } else {
                    $compiler
                        ->string('%' . $var->getAttribute('name') . '%')
                        ->raw(' => ')
                        ->subcompile($var)
                        ->raw(', ');
                }
            }
            
            $compiler->raw("));\n");
        } else {
            $compiler
                ->write('echo ' . $function . '(')
                ->subcompile($msg);
            
            if (null !== $this->getNode('plural')) {
                $compiler
                    ->raw(', ')
                    ->subcompile($msg1)
                    ->raw(', abs(')
                    ->subcompile($this->getNode('count'))
                    ->raw(')');
            }
            
            $compiler->raw(");\n");
        }
    }
    
    public function getNode($name)
    {
        if (!isset($this->nodes[$name])) {
            return null;
        }
    
        return $this->nodes[$name];
    }
    
    
    protected function compileString(Node $body): array
    {
        if ($body instanceof NameExpression || $body instanceof ConstantExpression || $body instanceof TempNameExpression) {
            return array($body, array());
        }
        
        $vars = array();
        if (count($body)) {
            $msg = '';
            
            foreach ($body as $node) {
                if (get_class($node) === Node::class && $node->getNode(0) instanceof SetNode) {
                    $node = $node->getNode(1);
                }
                
                if ($node instanceof PrintNode) {
                    $n = $node->getNode('expr');
                    while ($n instanceof FilterExpression) {
                        $n = $n->getNode('node');
                    }
                    $msg .= sprintf('%%%s%%', $n->getAttribute('name'));
                    $vars[] = new NameExpression($n->getAttribute('name'), $n->getTemplateLine());
                } else {
                    $msg .= $node->getAttribute('data');
                }
            }
        } else {
            if ($body->hasAttribute('data')) {
                $msg = $body->getAttribute('data');
            }
        }
        
        return [
            new Twig\Node\Node([new ConstantExpression(trim($msg), $body->getTemplateLine())]),
            $vars
        ];
    }
}
