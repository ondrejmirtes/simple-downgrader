<?php declare(strict_types = 1);

namespace SimpleDowngrader\Visitor;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use SimpleDowngrader\Php\PhpPrinter;
use function count;

class DowngradeTrailingCommasInClosureUsesVisitor extends NodeVisitorAbstract
{

	public function enterNode(Node $node)
	{
		if (!$node instanceof Node\Expr\Closure) {
			return null;
		}

		$uses = $node->uses;
		if (count($uses) === 0) {
			return null;
		}

		$lastUse = $uses[count($uses) - 1];
		$lastUse->setAttribute(PhpPrinter::FUNC_ARGS_TRAILING_COMMA_ATTRIBUTE, false);
		$node->setAttribute('origNode', null);

		return $node;
	}

}
