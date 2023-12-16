<?php declare(strict_types = 1);

namespace SimpleDowngrader\Visitor;

use PhpParser\NodeVisitor;

class DowngradeTypedPropertyVisitorTest extends AbstractVisitorTestCase
{

	protected function getVisitor(): NodeVisitor
	{
		return new DowngradeTypedPropertyVisitor($this->createTypeDowngraderHelper());
	}

	public function dataVisitor(): iterable
	{
		yield [
			<<<'PHP'
<?php

class SomeClass
{
    public mixed $foo;
}
PHP
,
			<<<'PHP'
<?php

class SomeClass
{
    /**
     * @var mixed
     */
    public $foo;
}
PHP
,
		];

		yield [
			<<<'PHP'
<?php

class SomeClass
{
    public \Foo $foo;
}
PHP
,
			<<<'PHP'
<?php

class SomeClass
{
    /**
     * @var \Foo
     */
    public $foo;
}
PHP
,
		];

		yield [
			<<<'PHP'
<?php

class SomeClass
{
    public Foo $foo;
}
PHP
,
			<<<'PHP'
<?php

class SomeClass
{
    /**
     * @var Foo
     */
    public $foo;
}
PHP
,
		];

		yield [
			<<<'PHP'
<?php

class SomeClass
{
    public ?Foo $foo;
}
PHP
,
			<<<'PHP'
<?php

class SomeClass
{
    /**
     * @var ?Foo
     */
    public $foo;
}
PHP
,
		];
	}

}
