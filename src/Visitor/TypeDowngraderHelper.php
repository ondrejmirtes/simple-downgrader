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
			$type = $node->type;
			$node->type = null;
			$this->phpDocEditor->edit($node, static function (\PHPStan\PhpDocParser\Ast\Node $node) use ($type, $callable) {
				if (!$node instanceof PhpDocNode) {
					return null;
				}

				if (count($node->getVarTagValues()) !== 0) {
					return null;
				}

				$resultType = $callable($type);
				if ($resultType === null) {
					return null;
				}

				$node->children[] = new PhpDocTagNode('@var', new VarTagValueNode(
					$resultType,
					'',
					''
				));

				return $node;
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

				$type = $param->type;
				$param->type = null;

				$this->phpDocEditor->edit($node, static function (\PHPStan\PhpDocParser\Ast\Node $node) use ($param, $type, $callable) {
					if (!$node instanceof PhpDocNode) {
						return null;
					}

					$paramTags = $node->getParamTagValues();
					foreach ($paramTags as $paramTag) {
						if ($paramTag->parameterName === '$' . $param->var->name) {
							return null;
						}
					}

					$resultType = $callable($type);
					if ($resultType === null) {
						return null;
					}

					$node->children[] = new PhpDocTagNode('@param', new ParamTagValueNode(
						$resultType,
						$param->variadic,
						'$' . $param->var->name,
						''
					));

					return $node;
				});
			}

			if ($node->returnType !== null) {
				$returnType = $node->returnType;
				$node->returnType = null;
				$this->phpDocEditor->edit($node, static function (\PHPStan\PhpDocParser\Ast\Node $node) use ($returnType, $callable) {
					if (!$node instanceof PhpDocNode) {
						return null;
					}

					if (count($node->getReturnTagValues()) !== 0) {
						return null;
					}

					$resultType = $callable($returnType);
					if ($resultType === null) {
						return null;
					}

					$node->children[] = new PhpDocTagNode('@return', new ReturnTagValueNode(
						$resultType,
						''
					));

					return $node;
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

		return null;
	}

}
