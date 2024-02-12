<?php declare(strict_types = 1);

namespace SimpleDowngrader\Visitor;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\VarTagValueNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use SimpleDowngrader\PhpDoc\PhpDocEditor;
use function array_key_exists;
use function array_reverse;
use function array_unshift;
use function is_string;
use function substr;

class DowngradePropertyPromotionVisitor extends NodeVisitorAbstract
{

	/** @var Lexer */
	private $lexer;

	/** @var PhpDocParser */
	private $phpDocParser;

	/** @var PhpDocEditor */
	private $phpDocEditor;

	public function __construct(
		Lexer $lexer,
		PhpDocParser $phpDocParser,
		PhpDocEditor $phpDocEditor
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

				$promoted = [];
				foreach ($classStmt->params as $param) {
					if ($param->flags === 0) {
						continue;
					}

					$promoted[] = $param;
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
				foreach (array_reverse($promoted) as $p) {
					if (!$p->var instanceof Node\Expr\Variable || !is_string($p->var->name)) {
						continue;
					}
					$propertyNode = new Node\Stmt\Property(
						$p->flags,
						[
							new Node\Stmt\PropertyProperty($p->var->name),
						],
						[
							'comments' => $p->getComments(),
						],
						$p->type,
						$p->attrGroups
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
							$p->var
						)
					));
					$p->flags = 0;
					$p->setAttribute('comments', []);
				}

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

}
