<?php declare(strict_types = 1);

namespace SimpleDowngrader\Visitor;

use Exception;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Ast\Type\UnionTypeNode;
use function array_map;

class DowngradeUnionTypeVisitor extends NodeVisitorAbstract
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
			if ($node instanceof Node\UnionType) {
				return $this->createUnionTypeNode($node);
			}

			return null;
		});
	}

	private function createUnionTypeNode(Node\UnionType $unionType): UnionTypeNode
	{
		return new UnionTypeNode(array_map(static function ($typeNode) {
			if ($typeNode instanceof Node\IntersectionType) {
				throw new Exception('DNF not supported');
			}
			if ($typeNode instanceof Node\Name) {
				return new IdentifierTypeNode($typeNode->toCodeString());
			}

			return new IdentifierTypeNode($typeNode->toString());
		}, $unionType->types));
	}

}
