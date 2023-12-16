<?php declare(strict_types = 1);

namespace SimpleDowngrader\Visitor;

use PhpParser\NodeVisitor;

class DowngradeArrowFunctionToAnonymousFunctionVisitorTest extends AbstractVisitorTestCase
{

	protected function getVisitor(): NodeVisitor
	{
		return new DowngradeArrowFunctionToAnonymousFunctionVisitor();
	}

	public function dataVisitor(): iterable
	{
		yield [
			<<<'PHP'
<?php

fn ($a) => $a + $b;
PHP
,
			<<<'PHP'
<?php

function ($a) use($b) {
    return $a + $b;
};
PHP
,
		];
	}

}
