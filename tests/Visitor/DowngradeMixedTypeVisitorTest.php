<?php declare(strict_types = 1);

namespace SimpleDowngrader\Visitor;

use PhpParser\NodeVisitor;
use PHPStan\PhpDocParser\Printer\Printer;
use SimpleDowngrader\PhpDoc\PhpDocEditor;

class DowngradeMixedTypeVisitorTest extends AbstractVisitorTestCase
{

	protected function getVisitor(): NodeVisitor
	{
		return new DowngradeMixedTypeVisitor(new TypeDowngraderHelper(new PhpDocEditor(new Printer())));
	}

	public function dataVisitor(): iterable
	{
		yield [
			<<<'PHP'
<?php

class SomeClass
{
    public mixed $foo;

    public function doFoo(mixed $a): mixed
    {
        $this->foo = 'foo';
    }
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

    /**
     * @param mixed $a
     * @return mixed
     */
    public function doFoo($a)
    {
        $this->foo = 'foo';
    }
}
PHP
,
		];

		yield [
			<<<'PHP'
<?php

function (mixed $fb): mixed {
};
PHP
,
			<<<'PHP'
<?php

function ($fb) {
};
PHP
,
		];

		yield [
			<<<'PHP'
<?php

fn (mixed $fb): mixed => new \stdClass();
PHP
,
			<<<'PHP'
<?php

fn ($fb) => new \stdClass();
PHP
,
		];
	}

}
