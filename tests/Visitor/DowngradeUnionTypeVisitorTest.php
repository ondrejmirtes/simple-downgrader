<?php declare(strict_types = 1);

namespace SimpleDowngrader\Visitor;

use PhpParser\NodeVisitor;
use PHPStan\PhpDocParser\Printer\Printer;
use SimpleDowngrader\PhpDoc\PhpDocEditor;

class DowngradeUnionTypeVisitorTest extends AbstractVisitorTestCase
{

	protected function getVisitor(): NodeVisitor
	{
		return new DowngradeUnionTypeVisitor(new TypeDowngraderHelper(new PhpDocEditor(new Printer())));
	}

	public function dataVisitor(): iterable
	{
		yield [
			<<<'PHP'
<?php

class SomeClass
{
    public Foo|Bar $foo;

    public function doFoo(\Foo|\Bar $a): Foo|\Bar
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
     * @var Foo|Bar
     */
    public $foo;

    /**
     * @param \Foo|\Bar $a
     * @return Foo|\Bar
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

function (Foo|Bar $fb): Foo|Bar {
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

fn (Foo|Bar $fb): Foo|Bar => new \stdClass();
PHP
,
			<<<'PHP'
<?php

fn ($fb) => new \stdClass();
PHP
,
		];

		yield [
			<<<'PHP'
<?php

class SomeClass
{
    /**
     * @var Foo|Bar
     */
    public Foo|Bar $foo;

    /**
     * @param \Foo|\Bar $a
     * @return Foo|\Bar
     */
    public function doFoo(\Foo|\Bar $a): Foo|\Bar
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
     * @var Foo|Bar
     */
    public $foo;

    /**
     * @param \Foo|\Bar $a
     * @return Foo|\Bar
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
    public Foo $foo;
}
PHP
,
		];
	}

}
