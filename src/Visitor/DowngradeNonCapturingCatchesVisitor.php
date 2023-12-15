<?php declare(strict_types = 1);

namespace SimpleDowngrader\Visitor;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

class DowngradeNonCapturingCatchesVisitor extends NodeVisitorAbstract
{

	public function enterNode(Node $node)
	{
		if (!$node instanceof Node\Stmt\Catch_) {
			return null;
		}

		if ($node->var !== null) {
			return null;
		}

		$node->var = new Node\Expr\Variable('e');

		return $node;
	}

}
