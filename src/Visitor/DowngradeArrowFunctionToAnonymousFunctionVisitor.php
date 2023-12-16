<?php declare(strict_types = 1);

namespace SimpleDowngrader\Visitor;

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\NodeVisitorAbstract;
use function array_key_exists;
use function is_string;

class DowngradeArrowFunctionToAnonymousFunctionVisitor extends NodeVisitorAbstract
{

	public function enterNode(Node $node)
	{
		if (!$node instanceof Node\Expr\ArrowFunction) {
			return null;
		}

		return new Node\Expr\Closure([
			'static' => $node->static,
			'byRef' => $node->byRef,
			'params' => $node->params,
			'uses' => $this->getUses($node->params, $node->expr),
			'returnType' => $node->returnType,
			'stmts' => [new Node\Stmt\Return_($node->expr)],
			'attrGroups' => $node->attrGroups,
		]);
	}

	/**
	 * @param Node\Param[] $params
	 * @return list<Node\Expr\ClosureUse>
	 */
	private function getUses(array $params, Node\Expr $expr): array
	{
		$paramNames = [];
		foreach ($params as $param) {
			if (!$param->var instanceof Node\Expr\Variable) {
				continue;
			}
			if (!is_string($param->var->name)) {
				continue;
			}

			$paramNames[$param->var->name] = true;
		}

		$nodeFinder = new NodeFinder();

		$uses = [];
		$alreadyUsed = [];

		/** @var Node\Expr\Variable[] $variables */
		$variables = $nodeFinder->findInstanceOf([$expr], Node\Expr\Variable::class);
		foreach ($variables as $variable) {
			if (!is_string($variable->name)) {
				continue;
			}

			if ($variable->name === 'this') {
				continue;
			}

			if (array_key_exists($variable->name, $paramNames)) {
				continue;
			}

			if (array_key_exists($variable->name, $alreadyUsed)) {
				continue;
			}

			$uses[] = new Node\Expr\ClosureUse($variable);
			$alreadyUsed[$variable->name] = true;
		}

		return $uses;
	}

}
