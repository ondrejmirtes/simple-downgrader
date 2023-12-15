<?php declare(strict_types = 1);

namespace SimpleDowngrader\Visitor;

use PhpParser\NodeVisitor;

class DowngradeReadonlyPromotedPropertyVisitorTest extends AbstractVisitorTestCase
{

	protected function getVisitor(): NodeVisitor
	{
		return new DowngradeReadonlyPromotedPropertyVisitor($this->createPhpDocEditor());
	}

	public function dataVisitor(): iterable
	{
		yield [
			<<<'PHP'
<?php

class SomeClass
{
    public function __construct(public readonly string $foo)
    {
    }
}
PHP
,
			<<<'PHP'
<?php

class SomeClass
{
    public function __construct(/**
     * @readonly
     */
    public string $foo)
    {
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
    	/**
         * @var non-empty-string
         */
        public readonly string $foo
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
    public function __construct(
    	/**
         * @var non-empty-string
         * @readonly
         */
        public string $foo
    )
    {

    }
}
PHP
,
		];
	}

}
