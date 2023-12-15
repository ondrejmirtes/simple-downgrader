<?php declare(strict_types = 1);

namespace SimpleDowngrader\Visitor;

use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\VarTagValueNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Printer\Printer;
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

	/** @var Printer */
	private $printer;

	/** @var Node\Stmt\ClassLike|null */
	private $inClass;

	/** @var Node\Stmt[]|null */
	private $inClassStmts;

	public function __construct(
		Lexer $lexer,
		PhpDocParser $phpDocParser,
		Printer $printer
	)
	{
		$this->lexer = $lexer;
		$this->phpDocParser = $phpDocParser;
		$this->printer = $printer;
	}

	public function enterNode(Node $node)
	{
		if ($node instanceof Node\Stmt\ClassLike) {
			$this->inClass = $node;
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
		if ($this->inClass === null) {
			return null;
		}

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

		$classStmts = $this->inClass->stmts;
		$methodStmts = $node->stmts;
		foreach (array_reverse($promoted) as $p) {
			if (!$p->var instanceof Node\Expr\Variable || !is_string($p->var->name)) {
				continue;
			}
			$propertyNode = new Node\Stmt\Property(
				$p->flags,
				[
					new Node\Stmt\PropertyProperty($p->var->name),
				],
				[],
				$p->type,
				$p->attrGroups
			);
			if (array_key_exists($p->var->name, $phpDocParams)) {
				$propertyNode->setDocComment(new Doc(
					$this->printer->print(new PhpDocNode([
						new PhpDocTagNode('@var', new VarTagValueNode($phpDocParams[$p->var->name]->type, '', '')),
					]))
				));
			}
			array_unshift($classStmts, $propertyNode);
			array_unshift($methodStmts, new Node\Stmt\Expression(
				new Node\Expr\Assign(
					new Node\Expr\PropertyFetch(new Node\Expr\Variable('this'), $p->var->name),
					$p->var
				)
			));
			$p->flags = 0;
		}

		$this->inClassStmts = $classStmts;
		$node->stmts = $methodStmts;

		return $node;
	}

	public function leaveNode(Node $node)
	{
		if ($node instanceof Node\Stmt\ClassLike) {
			$this->inClass = null;
			if ($this->inClassStmts === null) {
				return null;
			}
			$stmts = $this->inClassStmts;
			$this->inClassStmts = null;
			if ($node->stmts === null) {
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