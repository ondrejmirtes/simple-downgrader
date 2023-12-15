<?php declare(strict_types = 1);

namespace SimpleDowngrader\Visitor;

use PhpParser\NodeVisitor;
use PHPStan\PhpDocParser\Printer\Printer;
use SimpleDowngrader\PhpDoc\PhpDocEditor;

class DowngradeStaticReturnTypeVisitorTest extends AbstractVisitorTestCase
{

	protected function getVisitor(): NodeVisitor
	{
		return new DowngradeStaticReturnTypeVisitor(new TypeDowngraderHelper(new PhpDocEditor(new Printer())));
	}

	public function dataVisitor(): iterable
	{
		yield [
			<<<'PHP'
<?php

class SomeClass
{
    public function doFoo(): static
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
     * @return static
     */
    public function doFoo()
    {

    }
}
PHP
,
		];

		yield [
			<<<'PHP'
<?php

function (): static {
};
PHP
,
			<<<'PHP'
<?php

function () {
};
PHP
,
		];

		yield [
			<<<'PHP'
<?php

fn (): static => new \stdClass();
PHP
,
			<<<'PHP'
<?php

fn () => new \stdClass();
PHP
,
		];
	}

}
