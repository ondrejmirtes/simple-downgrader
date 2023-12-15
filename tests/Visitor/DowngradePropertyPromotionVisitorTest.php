<?php declare(strict_types = 1);

namespace SimpleDowngrader\Visitor;

use PhpParser\NodeVisitor;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\PhpDocParser\Printer\Printer;

class DowngradePropertyPromotionVisitorTest extends AbstractVisitorTestCase
{

	protected function getVisitor(): NodeVisitor
	{
		$usedAttributes = ['lines' => true, 'indexes' => true];
		$constExprParser = new ConstExprParser(true, true, $usedAttributes);
		$typeParser = new TypeParser($constExprParser, true, $usedAttributes);
		$phpDocParser = new PhpDocParser($typeParser, $constExprParser, true, true, $usedAttributes, true, true);
		$phpDocLexer = new Lexer(true);

		return new DowngradePropertyPromotionVisitor(
			$phpDocLexer,
			$phpDocParser,
			new Printer()
		);
	}

	public function dataVisitor(): iterable
	{
		yield [
			<<<'PHP'
<?php

class SomeClass
{
    public function __construct(private Test $test)
    {

    }
}
PHP
,
			<<<'PHP'
<?php

class SomeClass
{
    private Test $test;
    public function __construct(Test $test)
    {
        $this->test = $test;
    }
}
PHP
,
		];

		yield [
			<<<'PHP'
<?php

class SomeClass
{
    public function __construct(private Test $test = null)
    {

    }
}
PHP
,
			<<<'PHP'
<?php

class SomeClass
{
    private Test $test;
    public function __construct(Test $test = null)
    {
        $this->test = $test;
    }
}
PHP
,
		];

		yield [
			<<<'PHP'
<?php

class SomeClass
{
    /** @param Test&Something $test */
    public function __construct(private Test $test)
    {

    }
}
PHP
,
			<<<'PHP'
<?php

class SomeClass
{
    /**
     * @var Test&Something
     */
    private Test $test;
    /** @param Test&Something $test */
    public function __construct(Test $test)
    {
        $this->test = $test;
    }
}
PHP
,
		];
	}

}
