<?php declare(strict_types = 1);

namespace SimpleDowngrader\Visitor;

use PhpParser\NodeVisitor;

class DowngradeNullsafeOperatorVisitorTest extends AbstractVisitorTestCase
{

	protected function getVisitor(): NodeVisitor
	{
		return new DowngradeNullsafeOperatorVisitor();
	}

	public function dataVisitor(): iterable
	{
		yield [
			<<<'PHP'
<?php

function (?Foo $foo) {
	$foo?->bar;
	$foo?->doBar(1);
	$a = $foo?->bar->baz?->doFoo()['x'] + 1;
	$b = $this->getFoo()?->bar;
	$c = isset($foo?->bar, $d);
	$e = empty($foo->bar()?->baz);
	$f = $foo?->bar ?? 'default';
	$g = "Hello {$foo?->name}!";
	$h = ($foo?->getCallback())();
	$i = $foo?->bar::CONST;
	$j = $foo->doBar($foo?->bar)?->baz;
	return $foo?->bar::$prop;
};
PHP
,
			<<<'PHP'
<?php

function (?Foo $foo) {
	$foo !== null ? $foo->bar : null;
	$foo !== null ? $foo->doBar(1) : null;
	$a = ($foo !== null ? ($nullsafeVariable1 = $foo->bar->baz) !== null ? $nullsafeVariable1->doFoo()['x'] : null : null) + 1;
	$b = ($nullsafeVariable2 = $this->getFoo()) !== null ? $nullsafeVariable2->bar : null;
	$c = isset($foo) && isset($foo->bar) && isset($d);
	$e = ($nullsafeVariable3 = $foo->bar() ?? null) === null || empty($nullsafeVariable3->baz);
	$f = (isset($foo) ? $foo->bar ?? null : null) ?? 'default';
	$g = 'Hello ' . ($foo !== null ? $foo->name : null) . '!';
	$h = ($foo !== null ? $foo->getCallback() : null)();
	$i = ($foo !== null ? $foo->bar : null)::CONST;
	$j = ($nullsafeVariable4 = $foo->doBar($foo !== null ? $foo->bar : null)) !== null ? $nullsafeVariable4->baz : null;
	return $foo !== null ? $foo->bar::$prop : null;
};
PHP
,
		];

		yield [
			<<<'PHP'
<?php

$foo->bar->baz();
PHP
,
			<<<'PHP'
<?php

$foo->bar->baz();
PHP
,
		];
	}

}
