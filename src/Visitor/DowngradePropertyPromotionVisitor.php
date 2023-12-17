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
use function array_pop;
use function array_reverse;
use function array_unshift;
use function count;
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

	/** @var list<Node\Stmt\ClassLike> */
	private $inClassStack = [];

	/** @var list<Node\Stmt[]> */
	private $inClassStmtsStack = [];

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
			$this->inClassStack[] = $node;
			return null;
		}

		if (!$node instanceof Node\Stmt\ClassMethod) {
			return null;
		}
		if ($node->name->toLowerString() !== '__construct') {
			return null;
		}
		if ($node->stmts === null) {
			return null;
		}

		if (count($this->inClassStack) === 0) {
			return null;
		}

		$inClass = $this->inClassStack[count($this->inClassStack) - 1];

		$promoted = [];
		foreach ($node->params as $param) {
			if ($param->flags === 0) {
				continue;
			}

			$promoted[] = $param;
		}

		$phpDocParams = [];
		if ($node->getDocComment() !== null) {
			$phpDocNode = $this->parsePhpDoc($node->getDocComment()->getText());
			foreach ($phpDocNode->getParamTagValues() as $paramTag) {
				$paramName = substr($paramTag->parameterName, 1);
				$phpDocParams[$paramName] = $paramTag;
			}
		}

		$classStmts = $inClass->stmts;
		$methodStmts = $node->stmts;
		foreach (array_reverse($promoted) as $p) {
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

		$this->inClassStmtsStack[] = $classStmts;
		$node->stmts = $methodStmts;

		return $node;
	}

	public function leaveNode(Node $node)
	{
		if ($node instanceof Node\Stmt\ClassLike) {
			array_pop($this->inClassStack);
			$stmts = array_pop($this->inClassStmtsStack);
			if ($stmts === null) {
				return null;
			}

			$node->stmts = $stmts;

			return $node;
		}

		return null;
	}

	private function parsePhpDoc(string $phpDoc): PhpDocNode
	{
		$tokens = new TokenIterator($this->lexer->tokenize($phpDoc));

		return $this->phpDocParser->parse($tokens);
	}

}
