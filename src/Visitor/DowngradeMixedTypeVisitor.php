<?php declare(strict_types = 1);

namespace SimpleDowngrader\Visitor;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;

class DowngradeMixedTypeVisitor extends NodeVisitorAbstract
{

	/** @var TypeDowngraderHelper */
	private $typeDowngraderHelper;

	public function __construct(TypeDowngraderHelper $typeDowngraderHelper)
	{
		$this->typeDowngraderHelper = $typeDowngraderHelper;
	}

	public function enterNode(Node $node)
	{
		return $this->typeDowngraderHelper->downgradeType($node, static function ($node): ?TypeNode {
			if ($node instanceof Node\Identifier && $node->toLowerString() === 'mixed') {
				return new IdentifierTypeNode('mixed');
			}

			return null;
		});
	}

}
