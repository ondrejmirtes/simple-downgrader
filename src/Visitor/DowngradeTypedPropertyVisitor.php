<?php declare(strict_types = 1);

namespace SimpleDowngrader\Visitor;

use Exception;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use function get_class;
use function sprintf;

class DowngradeTypedPropertyVisitor extends NodeVisitorAbstract
{

	/** @var TypeDowngraderHelper */
	private $typeDowngraderHelper;

	public function __construct(TypeDowngraderHelper $typeDowngraderHelper)
	{
		$this->typeDowngraderHelper = $typeDowngraderHelper;
	}

	public function enterNode(Node $node)
	{
		if (!$node instanceof Node\Stmt\Property) {
			return null;
		}

		return $this->typeDowngraderHelper->downgradeType($node, static function ($node): TypeNode {
			if ($node instanceof Node\Identifier) {
				return new IdentifierTypeNode($node->toString());
			}

			if ($node instanceof Node\Name) {
				return new IdentifierTypeNode($node->toCodeString());
			}

			throw new Exception(sprintf('%s should have already been downgraded', get_class($node)));
		});
	}

}
