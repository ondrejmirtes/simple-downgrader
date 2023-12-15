<?php declare(strict_types = 1);

namespace SimpleDowngrader\Visitor;

use PhpParser\NodeVisitor;

class DowngradeTrailingCommasInClosureUsesVisitorTest extends AbstractVisitorTestCase
{

	protected function getVisitor(): NodeVisitor
	{
		return new DowngradeTrailingCommasInClosureUsesVisitor();
	}

	public function dataVisitor(): iterable
	{
		yield [
			<<<'PHP'
<?php

function () use ($foo,) {
};
PHP
,
			<<<'PHP'
<?php

function () use($foo) {
};
PHP
,
		];

		yield [
			<<<'PHP'
<?php

function () use (
    $foo,
    $bar,
) {
};
PHP
,
			<<<'PHP'
<?php

function () use($foo, $bar) {
};
PHP
,
		];
	}

}
