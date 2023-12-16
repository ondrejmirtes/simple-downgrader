<?php declare(strict_types = 1);

namespace SimpleDowngrader\Visitor;

use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use function array_key_exists;
use function array_pop;
use function count;
use function is_string;

class ImmediateScopeVariablesVisitor extends NodeVisitorAbstract
{

	/** @var list<Variable> */
	private $variables = [];

	/** @var list<array<string, true>> */
	private $parametersStack = [];

	/**
	 * @return list<Variable>
	 */
	public function getVariables(): array
	{
		return $this->variables;
	}

	public function enterNode(Node $node)
	{
		if ($node instanceof Node\Stmt\Class_) {
			return NodeTraverser::DONT_TRAVERSE_CHILDREN;
		}

		if ($node instanceof Node\Stmt\Function_) {
			return NodeTraverser::DONT_TRAVERSE_CHILDREN;
		}

		if ($node instanceof Node\Expr\ArrowFunction || $node instanceof Node\Expr\Closure) {
			$params = [];
			foreach ($node->params as $param) {
				if (!$param->var instanceof Variable) {
					continue;
				}
				if (!is_string($param->var->name)) {
					continue;
				}
				$params[$param->var->name] = true;
			}

			$this->parametersStack[] = $params;
		}

		if ($node instanceof Node\Expr\Closure) {
			foreach ($node->uses as $use) {
				$this->variables[] = $use->var;
			}
			return NodeTraverser::DONT_TRAVERSE_CHILDREN;
		}

		if ($node instanceof Node\Param) {
			return NodeTraverser::DONT_TRAVERSE_CHILDREN;
		}

		if ($node instanceof Variable) {
			if (count($this->parametersStack) > 0) {
				$params = $this->parametersStack[count($this->parametersStack) - 1];
				if (is_string($node->name) && !array_key_exists($node->name, $params)) {
					$this->variables[] = $node;
				}
			} else {
				$this->variables[] = $node;
			}
		}

		return null;
	}

	public function leaveNode(Node $node)
	{
		if ($node instanceof Node\Expr\ArrowFunction || $node instanceof Node\Expr\Closure) {
			array_pop($this->parametersStack);
		}

		return null;
	}

}
