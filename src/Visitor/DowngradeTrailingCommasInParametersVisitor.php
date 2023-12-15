<?php declare(strict_types = 1);

namespace SimpleDowngrader\Visitor;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use SimpleDowngrader\Php\PhpPrinter;
use function count;

class DowngradeTrailingCommasInParametersVisitor extends NodeVisitorAbstract
{

	public function enterNode(Node $node)
	{
		if (!$node instanceof Node\FunctionLike) {
			return null;
		}

		$params = $node->getParams();
		if (count($params) === 0) {
			return null;
		}

		$lastParam = $params[count($params) - 1];
		$lastParam->setAttribute(PhpPrinter::FUNC_ARGS_TRAILING_COMMA_ATTRIBUTE, false);
		$node->setAttribute('origNode', null);

		return $node;
	}

}
