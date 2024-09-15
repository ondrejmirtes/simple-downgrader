<?php declare(strict_types = 1);

namespace SimpleDowngrader\Visitor;

use PhpParser\Node;
use PhpParser\Node\ComplexType;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\PhpDocParser\Ast\PhpDoc\ParamTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ReturnTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\VarTagValueNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use SimpleDowngrader\PhpDoc\PhpDocEditor;
use function count;
use function is_string;

class TypeDowngraderHelper
{

	/** @var PhpDocEditor */
	private $phpDocEditor;

	public function __construct(PhpDocEditor $phpDocEditor)
	{
		$this->phpDocEditor = $phpDocEditor;
	}

	/**
	 * @param callable(Identifier|Name|ComplexType): ?TypeNode $callable
	 */
	public function downgradeType(Node $node, callable $callable): ?Node
	{
		if ($node instanceof Node\Stmt\Property && $node->type !== null) {
			$this->phpDocEditor->edit($node, static function (\PHPStan\PhpDocParser\Ast\Node $phpDocNode) use ($node, $callable) {
				if (!$phpDocNode instanceof PhpDocNode) {
					return null;
				}

				$resultType = $callable($node->type);
				if ($resultType === null) {
					return null;
				}

				$node->type = null;

				if (count($phpDocNode->getVarTagValues()) !== 0) {
					return null;
				}
				$phpDocNode->children[] = new PhpDocTagNode('@var', new VarTagValueNode(
					$resultType,
					'',
					''
				));

				return $phpDocNode;
			});

			return $node;
		}

		if ($node instanceof Node\Stmt\ClassMethod || $node instanceof Node\Stmt\Function_) {
			foreach ($node->params as $param) {
				if ($param->type === null) {
					continue;
				}

				if (!$param->var instanceof Node\Expr\Variable || !is_string($param->var->name)) {
					continue;
				}

				$this->phpDocEditor->edit($node, static function (\PHPStan\PhpDocParser\Ast\Node $phpDocNode) use ($param, $callable) {
					if (!$phpDocNode instanceof PhpDocNode) {
						return null;
					}

					$resultType = $callable($param->type);
					if ($resultType === null) {
						return null;
					}

					$param->type = null;

					$paramTags = $phpDocNode->getParamTagValues();
					foreach ($paramTags as $paramTag) {
						if ($paramTag->parameterName === '$' . $param->var->name) {
							return null;
						}
					}
					$phpDocNode->children[] = new PhpDocTagNode('@param', new ParamTagValueNode(
						$resultType,
						$param->variadic,
						'$' . $param->var->name,
						'',
						false,
					));

					return $phpDocNode;
				});
			}

			if ($node->returnType !== null) {
				$this->phpDocEditor->edit($node, static function (\PHPStan\PhpDocParser\Ast\Node $phpDocNode) use ($node, $callable) {
					if (!$phpDocNode instanceof PhpDocNode) {
						return null;
					}

					if ($node->returnType === null) {
						return null;
					}

					$resultType = $callable($node->returnType);
					if ($resultType === null) {
						return null;
					}

					$node->returnType = null;

					if (count($phpDocNode->getReturnTagValues()) !== 0) {
						return null;
					}
					$phpDocNode->children[] = new PhpDocTagNode('@return', new ReturnTagValueNode(
						$resultType,
						''
					));

					return $phpDocNode;
				});
			}

			return $node;
		}

		if ($node instanceof Node\Expr\Closure || $node instanceof Node\Expr\ArrowFunction) {
			if ($node->returnType !== null) {
				$returnResultType = $callable($node->returnType);
				if ($returnResultType !== null) {
					$node->returnType = null;
				}
			}
			foreach ($node->params as $param) {
				if ($param->type === null) {
					continue;
				}
				$resultType = $callable($param->type);
				if ($resultType === null) {
					continue;
				}

				$param->type = null;
			}

			return $node;
		}

		return $node;
	}

}
