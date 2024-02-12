<?php declare(strict_types = 1);

namespace SimpleDowngrader\Visitor;

use PhpParser\NodeVisitor;
use PHPStan\PhpDocParser\Lexer\Lexer;

class DowngradePropertyPromotionVisitorTest extends AbstractVisitorTestCase
{

	protected function getVisitor(): NodeVisitor
	{
		return new DowngradePropertyPromotionVisitor(
			new Lexer(true),
			$this->createPhpDocParser(),
			$this->createPhpDocEditor()
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

		yield [
			<<<'PHP'
<?php

class SomeClass
{
    public function __construct(
        /** @readonly */
        private Test $test
    )
    {

    }
}
PHP
,
			<<<'PHP'
<?php

class SomeClass
{
    /** @readonly */
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
    /** @param Test&Foo $test */
    public function __construct(
        /**
         * @readonly
         */
        private Test $test
    )
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
     * @readonly
     * @var Test&Foo
     */
    private Test $test;
    /** @param Test&Foo $test */
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
    public function __construct(private Test $test)
    {

    }

    public function doFoo()
    {
    	$traverser->addVisitor(new class () extends NodeVisitorAbstract {
			/**
			 * @return ExistingArrayDimFetch|null
			 */
			public function leaveNode(Node $node)
			{
				if (!$node instanceof ArrayDimFetch || $node->dim === null) {
					return null;
				}

				return new ExistingArrayDimFetch($node->var, $node->dim);
			}

		});
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

    public function doFoo()
    {
    	$traverser->addVisitor(new class () extends NodeVisitorAbstract {
			/**
			 * @return ExistingArrayDimFetch|null
			 */
			public function leaveNode(Node $node)
			{
				if (!$node instanceof ArrayDimFetch || $node->dim === null) {
					return null;
				}

				return new ExistingArrayDimFetch($node->var, $node->dim);
			}

		});
    }
}
PHP
,
		];
	}

}
