<?php declare(strict_types = 1);

namespace SimpleDowngrader\PhpDoc;

use PHPStan\PhpDocParser\Ast\AbstractNodeVisitor;
use PHPStan\PhpDocParser\Ast\Node;

class CallbackVisitor extends AbstractNodeVisitor
{

	/** @var callable(Node): (Node|Node[]|null) */
	private $callback;

	/** @param callable(Node): (Node|Node[]|null) $callback */
	public function __construct(callable $callback)
	{
		$this->callback = $callback;
	}

	public function enterNode(Node $node)
	{
		$callback = $this->callback;

		return $callback($node);
	}

}
