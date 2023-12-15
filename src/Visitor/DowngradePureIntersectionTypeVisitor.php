<?php declare(strict_types = 1);

namespace SimpleDowngrader\Visitor;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IntersectionTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use function array_map;

class DowngradePureIntersectionTypeVisitor extends NodeVisitorAbstract
{

	/** @var TypeDowngraderHelper */
	private $typeDowngraderHelper;

	public function __construct(TypeDowngraderHelper $typeDowngraderHelper)
	{
		$this->typeDowngraderHelper = $typeDowngraderHelper;
	}

	public function enterNode(Node $node)
	{
		return $this->typeDowngraderHelper->downgradeType($node, function ($node): ?TypeNode {
			if ($node instanceof Node\IntersectionType) {
				return $this->createIntersectionTypeNode($node);
			}

			return null;
		});
	}

	private function createIntersectionTypeNode(Node\IntersectionType $intersectionType): IntersectionTypeNode
	{
		return new IntersectionTypeNode(array_map(static function ($typeNode) {
			if ($typeNode instanceof Node\Name) {
				return new IdentifierTypeNode($typeNode->toCodeString());
			}

			return new IdentifierTypeNode($typeNode->toString());
		}, $intersectionType->types));
	}

}
