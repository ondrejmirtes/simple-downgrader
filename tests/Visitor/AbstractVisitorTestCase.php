<?php declare(strict_types = 1);

namespace SimpleDowngrader\Visitor;

use PhpParser\Lexer\Emulative;
use PhpParser\Node\Stmt;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitor\CloningVisitor;
use PhpParser\Parser\Php7;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\PhpDocParser\Printer\Printer;
use PHPUnit\Framework\TestCase;
use SimpleDowngrader\Php\PhpPrinter;
use SimpleDowngrader\PhpDoc\PhpDocEditor;

abstract class AbstractVisitorTestCase extends TestCase
{

	abstract protected function getVisitor(): NodeVisitor;

	/** @return iterable<array{string, string}> */
	abstract public function dataVisitor(): iterable;

	/**
	 * @dataProvider dataVisitor
	 */
	public function testVisitor(string $codeBefore, string $codeAfter): void
	{
		$lexer = new Emulative([
			'usedAttributes' => [
				'comments',
				'startLine', 'endLine',
				'startTokenPos', 'endTokenPos',
			],
		]);
		$parser = new Php7($lexer);

		/** @var Stmt[] $oldStmts */
		$oldStmts = $parser->parse($codeBefore);
		$oldTokens = $lexer->getTokens();

		$cloningTraverser = new NodeTraverser();
		$cloningTraverser->addVisitor(new CloningVisitor());

		/** @var Stmt[] $newStmts */
		$newStmts = $cloningTraverser->traverse($oldStmts);

		$traverser = new NodeTraverser();
		$visitor = $this->getVisitor();
		if ($visitor instanceof TokensAwareVisitor) {
			$visitor->setTokens($oldTokens);
		}
		$traverser->addVisitor($visitor);

		/** @var Stmt[] $newStmts */
		$newStmts = $traverser->traverse($newStmts);

		$printer = new PhpPrinter();
		$newCode = $printer->printFormatPreserving($newStmts, $oldStmts, $oldTokens);

		$this->assertSame($codeAfter, $newCode);
	}

	public function createPhpDocParser(): PhpDocParser
	{
		$usedAttributes = ['lines' => true, 'indexes' => true];
		$constExprParser = new ConstExprParser(true, true, $usedAttributes);
		$typeParser = new TypeParser($constExprParser, true, $usedAttributes);

		return new PhpDocParser($typeParser, $constExprParser, true, true, $usedAttributes, true, true);
	}

	public function createPhpDocEditor(): PhpDocEditor
	{
		return new PhpDocEditor(
			new Printer(),
			new Lexer(true),
			$this->createPhpDocParser()
		);
	}

	public function createTypeDowngraderHelper(): TypeDowngraderHelper
	{
		return new TypeDowngraderHelper($this->createPhpDocEditor());
	}

}
