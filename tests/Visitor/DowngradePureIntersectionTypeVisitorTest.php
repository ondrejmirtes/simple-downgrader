<?php declare(strict_types = 1);

namespace SimpleDowngrader\Visitor;

use PhpParser\NodeVisitor;

class DowngradePureIntersectionTypeVisitorTest extends AbstractVisitorTestCase
{

	protected function getVisitor(): NodeVisitor
	{
		return new DowngradePureIntersectionTypeVisitor($this->createTypeDowngraderHelper());
	}

	public function dataVisitor(): iterable
	{
		yield [
			<<<'PHP'
<?php

class SomeClass
{
    public Foo&Bar $foo;

    public function doFoo(\Foo&\Bar $a): Foo&\Bar
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
     * @var \Foo&\Bar
     */
    public $foo;

    /**
     * @param \Foo&\Bar $a
     * @return \Foo&\Bar
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

function (Foo&Bar $fb): Foo&Bar {
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

fn (Foo&Bar $fb): Foo&Bar => new \stdClass();
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
     * @var Foo&Bar
     */
    public Foo&Bar $foo;

    /**
     * @param \Foo&\Bar $a
     * @return Foo&\Bar
     */
    public function doFoo(\Foo&\Bar $a): Foo&\Bar
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
     * @var Foo&Bar
     */
    public $foo;

    /**
     * @param \Foo&\Bar $a
     * @return Foo&\Bar
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

		yield [
			<<<'PHP'
<?php

use PHPStan\Analyser\Scope;
use PHPStan\Analyser\NodeCallbackInvoker;

class MyRule
{
    public function processNode(Node $node, Scope&NodeCallbackInvoker $scope): array
    {}
}
PHP
,
			<<<'PHP'
<?php

use PHPStan\Analyser\Scope;
use PHPStan\Analyser\NodeCallbackInvoker;

class MyRule
{
    /**
     * @param \PHPStan\Analyser\Scope&\PHPStan\Analyser\NodeCallbackInvoker $scope
     */
    public function processNode(Node $node, \PHPStan\Analyser\Scope $scope): array
    {}
}
PHP
,
		];

		yield [
			<<<'PHP'
<?php

use PHPStan\Analyser\Scope;
use PHPStan\Analyser\NodeCallbackInvoker;

class MyRule
{
    public function processNode(Node $node, NodeCallbackInvoker&Scope $scope): array
    {}
}
PHP
,
			<<<'PHP'
<?php

use PHPStan\Analyser\Scope;
use PHPStan\Analyser\NodeCallbackInvoker;

class MyRule
{
    /**
     * @param \PHPStan\Analyser\NodeCallbackInvoker&\PHPStan\Analyser\Scope $scope
     */
    public function processNode(Node $node, \PHPStan\Analyser\Scope $scope): array
    {}
}
PHP
,
		];
	}

}
