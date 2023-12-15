<?php declare(strict_types = 1);

namespace SimpleDowngrader\Visitor;

use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use PHPStan\PhpDocParser\Ast\PhpDoc\ParamTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ReturnTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\VarTagValueNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IntersectionTypeNode;
use SimpleDowngrader\PhpDoc\PhpDocEditor;
use function array_map;
use function is_string;

class DowngradePureIntersectionTypeVisitor extends NodeVisitorAbstract
{

	/** @var PhpDocEditor */
	private $phpDocEditor;

	public function __construct(PhpDocEditor $phpDocEditor)
	{
		$this->phpDocEditor = $phpDocEditor;
	}

	public function enterNode(Node $node)
	{
		if ($node instanceof Node\Stmt\Property && $node->type instanceof Node\IntersectionType) {
			$intersectionType = $node->type;
			$node->type = null;
			$phpDoc = '/** */';
			if ($node->getDocComment() !== null) {
				$phpDoc = $node->getDocComment()->getText();
			}
			$node->setDocComment(new Doc(
				$this->phpDocEditor->edit($phpDoc, function (\PHPStan\PhpDocParser\Ast\Node $node) use ($intersectionType) {
					if (!$node instanceof PhpDocNode) {
						return null;
					}

					$node->children[] = new PhpDocTagNode('@var', new VarTagValueNode(
						$this->createIntersectionTypeNode($intersectionType),
						'',
						''
					));

					return $node;
				})
			));

			return $node;
		}

		if ($node instanceof Node\Stmt\ClassMethod || $node instanceof Node\Stmt\Function_) {
			foreach ($node->params as $param) {
				if (!$param->type instanceof Node\IntersectionType) {
					continue;
				}

				if (!$param->var instanceof Node\Expr\Variable || !is_string($param->var->name)) {
					continue;
				}

				$intersectionType = $param->type;
				$param->type = null;

				$phpDoc = '/** */';
				if ($node->getDocComment() !== null) {
					$phpDoc = $node->getDocComment()->getText();
				}
				$node->setDocComment(new Doc(
					$this->phpDocEditor->edit($phpDoc, function (\PHPStan\PhpDocParser\Ast\Node $node) use ($param, $intersectionType) {
						if (!$node instanceof PhpDocNode) {
							return null;
						}

						$node->children[] = new PhpDocTagNode('@param', new ParamTagValueNode(
							$this->createIntersectionTypeNode($intersectionType),
							$param->variadic,
							'$' . $param->var->name,
							''
						));

						return $node;
					})
				));
			}

			if ($node->returnType instanceof Node\IntersectionType) {
				$intersectionType = $node->returnType;
				$node->returnType = null;
				$phpDoc = '/** */';
				if ($node->getDocComment() !== null) {
					$phpDoc = $node->getDocComment()->getText();
				}
				$node->setDocComment(new Doc(
					$this->phpDocEditor->edit($phpDoc, function (\PHPStan\PhpDocParser\Ast\Node $node) use ($intersectionType) {
						if (!$node instanceof PhpDocNode) {
							return null;
						}

						$node->children[] = new PhpDocTagNode('@return', new ReturnTagValueNode(
							$this->createIntersectionTypeNode($intersectionType),
							''
						));

						return $node;
					})
				));
			}

			return $node;
		}

		return null;
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
