<?php declare(strict_types = 1);

namespace SimpleDowngrader\Visitor;

use PhpParser\NodeVisitor;

class DowngradeTrailingCommasInParametersVisitorTest extends AbstractVisitorTestCase
{

	protected function getVisitor(): NodeVisitor
	{
		return new DowngradeTrailingCommasInParametersVisitor();
	}

	public function dataVisitor(): iterable
	{
		yield [
			<<<'PHP'
<?php

class SomeClass
{
    public function doFoo($a, $b,): void
    {

    }
}
PHP
,
			<<<'PHP'
<?php

class SomeClass
{
    public function doFoo($a, $b) : void
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
    public function doFoo(
        $a,
        $b,
    ): void
    {
    }
}
PHP
,
			<<<'PHP'
<?php

class SomeClass
{
    public function doFoo($a, $b) : void
    {
    }
}
PHP
,
		];
	}

}
