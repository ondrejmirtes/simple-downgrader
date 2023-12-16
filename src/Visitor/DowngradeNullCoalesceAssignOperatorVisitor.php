<?php declare(strict_types = 1);

namespace SimpleDowngrader\Visitor;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

class DowngradeNullCoalesceAssignOperatorVisitor extends NodeVisitorAbstract
{

	public function enterNode(Node $node)
	{
		if (!$node instanceof Node\Expr\AssignOp\Coalesce) {
			return null;
		}

		return new Node\Expr\Assign(
			$node->var,
			new Node\Expr\BinaryOp\Coalesce($node->var, $node->expr)
		);
	}

}
