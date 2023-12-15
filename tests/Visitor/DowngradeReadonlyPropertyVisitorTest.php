<?php declare(strict_types = 1);

namespace SimpleDowngrader\Visitor;

use PhpParser\NodeVisitor;
use PHPStan\PhpDocParser\Printer\Printer;
use SimpleDowngrader\PhpDoc\PhpDocEditor;

class DowngradeReadonlyPropertyVisitorTest extends AbstractVisitorTestCase
{

	protected function getVisitor(): NodeVisitor
	{
		return new DowngradeReadonlyPropertyVisitor(new PhpDocEditor(new Printer()));
	}

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
PHP
,
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
PHP
,
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
PHP
,
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
PHP
,
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
PHP
,
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
PHP
,
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
PHP
,
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
PHP
,
		];
	}

}
