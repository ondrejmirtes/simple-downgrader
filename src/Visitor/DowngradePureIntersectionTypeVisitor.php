<?php declare(strict_types = 1);

namespace SimpleDowngrader\Visitor;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\NodeVisitorAbstract;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IntersectionTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use function array_map;
use function preg_match;

class DowngradePureIntersectionTypeVisitor extends NodeVisitorAbstract
{

	private TypeDowngraderHelper $typeDowngraderHelper;

	public function __construct(TypeDowngraderHelper $typeDowngraderHelper)
	{
		$this->typeDowngraderHelper = $typeDowngraderHelper;
	}

	public function enterNode(Node $node)
	{
		return $this->typeDowngraderHelper->downgradeType(
			$node,
			function ($node): ?TypeNode {
				if ($node instanceof Node\IntersectionType) {
					return $this->createIntersectionTypeNode($node);
				}

				return null;
			},
			static function (TypeNode $resultType): ?Name {
				if (!$resultType instanceof IntersectionTypeNode) {
					return null;
				}

				// Scope intersected with interfaces it implements next to it, like NodeCallbackInvoker,
				// CollectedDataEmitter or DependencyEmitter - all from the PHPStan\Analyser namespace.
				$hasScope = false;
				foreach ($resultType->types as $type) {
					if (!$type instanceof IdentifierTypeNode || preg_match('~^\\\\PHPStan\\\\Analyser\\\\\w+$~', $type->name) !== 1) {
						return null;
					}
					if ($type->name !== '\PHPStan\Analyser\Scope') {
						continue;
					}

					$hasScope = true;
				}

				if (!$hasScope) {
					return null;
				}

				return new Name('\PHPStan\Analyser\Scope');
			},
		);
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
