<?php declare(strict_types = 1);

namespace SimpleDowngrader\Visitor;

use PhpParser\Lexer\Emulative;
use PhpParser\Node\Stmt;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitor\CloningVisitor;
use PhpParser\Parser\Php7;
use PHPUnit\Framework\TestCase;
use SimpleDowngrader\Php\PhpPrinter;

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
		$traverser->addVisitor($this->getVisitor());

		/** @var Stmt[] $newStmts */
		$newStmts = $traverser->traverse($newStmts);

		$printer = new PhpPrinter();
		$newCode = $printer->printFormatPreserving($newStmts, $oldStmts, $oldTokens);

		$this->assertSame($codeAfter, $newCode);
	}

}
