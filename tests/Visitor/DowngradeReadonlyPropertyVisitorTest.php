<?php declare(strict_types = 1);

namespace SimpleDowngrader\Visitor;

use PhpParser\Lexer\Emulative;
use PhpParser\Node\Stmt;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitor\CloningVisitor;
use PhpParser\Parser\Php7;
use PHPStan\PhpDocParser\Printer\Printer;
use PHPUnit\Framework\TestCase;
use SimpleDowngrader\Php\PhpPrinter;
use SimpleDowngrader\PhpDoc\PhpDocEditor;

class DowngradeReadonlyPropertyVisitorTest extends TestCase
{

	protected function getVisitor(): NodeVisitor
	{
		return new DowngradeReadonlyPropertyVisitor(new PhpDocEditor(new Printer()));
	}

	/** @return iterable<array{string, string}> */
	public function dataVisitor(): iterable
	{
		yield [
			<<<'PHP'
<?php

class SomeClass
{
    public readonly string $foo;

    public function __construct()
    {
        $this->foo = 'foo';
    }
}
PHP,
			<<<'PHP'
<?php

class SomeClass
{
    /**
     * @readonly
     */
    public string $foo;

    public function __construct()
    {
        $this->foo = 'foo';
    }
}
PHP,
		];

		yield [
			<<<'PHP'
<?php

class SomeClass
{
    /**
     * @var non-empty-string
     */
    public readonly string $foo;

    public function __construct()
    {
        $this->foo = 'foo';
    }
}
PHP,
			<<<'PHP'
<?php

class SomeClass
{
    /**
     * @var non-empty-string
     * @readonly
     */
    public string $foo;

    public function __construct()
    {
        $this->foo = 'foo';
    }
}
PHP,
		];

		yield [
			<<<'PHP'
<?php

class SomeClass
{
	public readonly string $foo;

	public function __construct()
	{
		$this->foo = 'foo';
	}
}
PHP,
			<<<'PHP'
<?php

class SomeClass
{
	/**
	 * @readonly
	 */
	public string $foo;

	public function __construct()
	{
		$this->foo = 'foo';
	}
}
PHP,
		];

		yield [
			<<<'PHP'
<?php

class SomeClass
{
	/**
	 * @var non-empty-string
	 */
	public readonly string $foo;

	public function __construct()
	{
		$this->foo = 'foo';
	}
}
PHP,
			<<<'PHP'
<?php

class SomeClass
{
	/**
	 * @var non-empty-string
	 * @readonly
	 */
	public string $foo;

	public function __construct()
	{
		$this->foo = 'foo';
	}
}
PHP,
		];
	}

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
