<?php declare(strict_types = 1);

namespace SimpleDowngrader\Visitor;

use PhpParser\NodeVisitor;

class DowngradeNewInAttributeArgumentsVisitorTest extends AbstractVisitorTestCase
{

	protected function getVisitor(): NodeVisitor
	{
		return new DowngradeNewInAttributeArgumentsVisitor();
	}

	public function dataVisitor(): iterable
	{
		yield [
			<<<'PHP'
<?php

namespace Foo;

#[Conditional(
	new Registered(4),
	'%featureToggles.foo%',
)]
#[Other(
	1,
	2,
)]
class Lorem
{

	public function doFoo(#[Wrapper([new Bar(new Baz('a'), level: 1), 'key' => 2])] $p)
	{
	}

}
PHP
,
			<<<'PHP'
<?php

namespace Foo;

#[Conditional(['olvlvl\ComposerAttributeCollector\NewExpression' => [Registered::class, [4]]], '%featureToggles.foo%')]
#[Other(
	1,
	2,
)]
class Lorem
{

	public function doFoo(#[Wrapper([['olvlvl\ComposerAttributeCollector\NewExpression' => [Bar::class, [['olvlvl\ComposerAttributeCollector\NewExpression' => [Baz::class, ['a']]], 'level' => 1]]], 'key' => 2])] $p)
	{
	}

}
PHP
,
		];

		yield [
			<<<'PHP'
<?php

#[Foo(1, 'a')]
class Lorem
{
}
PHP
,
			<<<'PHP'
<?php

#[Foo(1, 'a')]
class Lorem
{
}
PHP
,
		];
	}

}
