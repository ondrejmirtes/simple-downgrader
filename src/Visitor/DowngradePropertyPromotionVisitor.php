<?php declare(strict_types = 1);

namespace SimpleDowngrader\Visitor;

use Attribute;
use LogicException;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\VarTagValueNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use SimpleDowngrader\PhpDoc\PhpDocEditor;
use function array_key_exists;
use function array_reverse;
use function array_unshift;
use function array_values;
use function count;
use function is_string;
use function substr;

class DowngradePropertyPromotionVisitor extends NodeVisitorAbstract
{

	private Lexer $lexer;

	private PhpDocParser $phpDocParser;

	private PhpDocEditor $phpDocEditor;

	public function __construct(
		Lexer $lexer,
		PhpDocParser $phpDocParser,
		PhpDocEditor $phpDocEditor,
	)
	{
		$this->lexer = $lexer;
		$this->phpDocParser = $phpDocParser;
		$this->phpDocEditor = $phpDocEditor;
	}

	public function enterNode(Node $node)
	{
		if ($node instanceof Node\Stmt\ClassLike) {
			foreach ($node->stmts as $classStmt) {
				if (!$classStmt instanceof Node\Stmt\ClassMethod) {
					continue;
				}
				if ($classStmt->name->toLowerString() !== '__construct') {
					continue;
				}
				if ($classStmt->stmts === null) {
					continue;
				}

				$phpDocParams = [];
				if ($classStmt->getDocComment() !== null) {
					$phpDocNode = $this->parsePhpDoc($classStmt->getDocComment()->getText());
					foreach ($phpDocNode->getParamTagValues() as $paramTag) {
						$paramName = substr($paramTag->parameterName, 1);
						$phpDocParams[$paramName] = $paramTag;
					}
				}

				$classStmts = $node->stmts;
				$methodStmts = $classStmt->stmts;
				$newParameters = [];
				foreach (array_reverse($classStmt->params) as $p) {
					if ($p->flags === 0) {
						$newParameters[] = $p;
						continue;
					}
					if (!$p->var instanceof Node\Expr\Variable || !is_string($p->var->name)) {
						continue;
					}
					$propertyNode = new Node\Stmt\Property(
						$p->flags,
						[
							new Node\PropertyItem($p->var->name),
						],
						[
							'comments' => $p->getComments(),
						],
						$p->type,
						$this->filterAttrGroups($p->attrGroups, Attribute::TARGET_PROPERTY),
					);
					if (array_key_exists($p->var->name, $phpDocParams)) {
						$this->phpDocEditor->edit($propertyNode, static function (\PHPStan\PhpDocParser\Ast\Node $phpDocNode) use ($phpDocParams, $p) {
							if (!$phpDocNode instanceof PhpDocNode) {
								return null;
							}

							$phpDocNode->children[] = new PhpDocTagNode('@var', new VarTagValueNode($phpDocParams[$p->var->name]->type, '', ''));
						});
					}
					array_unshift($classStmts, $propertyNode);
					array_unshift($methodStmts, new Node\Stmt\Expression(
						new Node\Expr\Assign(
							new Node\Expr\PropertyFetch(new Node\Expr\Variable('this'), $p->var->name),
							$p->var,
						),
					));

					foreach ($p->attrGroups as $pAttrGroup) {
						$pAttrGroup->setAttributes([]);
						foreach ($pAttrGroup->attrs as $pAttr) {
							$pAttr->setAttributes([]);
						}
					}
					$newParameters[] = new Node\Param(
						$p->var,
						$p->default,
						$p->type,
						$p->byRef,
						$p->variadic,
						[],
						0,
						$this->filterAttrGroups($p->attrGroups, Attribute::TARGET_PARAMETER),
					);
					$p->setAttribute('comments', []);
				}

				$classStmt->params = array_reverse($newParameters);

				$classStmt->stmts = $methodStmts;
				$node->stmts = $classStmts;

				return $node;
			}

		}

		return null;
	}

	private function parsePhpDoc(string $phpDoc): PhpDocNode
	{
		$tokens = new TokenIterator($this->lexer->tokenize($phpDoc));

		return $this->phpDocParser->parse($tokens);
	}

	/**
	 * @param Node\AttributeGroup[] $attrGroups
	 * @return list<Node\AttributeGroup>
	 */
	private function filterAttrGroups(array $attrGroups, int $target): array
	{
		foreach ($attrGroups as $i => $attrGroup) {
			$attrGroup = clone $attrGroup;
			foreach ($attrGroup->attrs as $j => $attr) {
				try {
					$attributeReflection = new ReflectionClass($attr->name->toString()); /** @phpstan-ignore argument.type */
				} catch (ReflectionException $e) {
					continue;
				}

				$actualAttributes = $attributeReflection->getAttributes(Attribute::class);
				if (count($actualAttributes) !== 1) {
					continue;
				}
				if (!$actualAttributes[0] instanceof ReflectionAttribute) {
					throw new LogicException();
				}

				$arguments = $actualAttributes[0]->getArguments();
				/** @var int $flags */
				$flags = $arguments[0] ?? $arguments['flags'] ?? 0;
				if (($flags & $target) === $target) {
					continue;
				}

				unset($attrGroup->attrs[$j]);
			}

			if (count($attrGroup->attrs) !== 0) {
				continue;
			}

			unset($attrGroups[$i]);
		}

		return array_values($attrGroups);
	}

}
