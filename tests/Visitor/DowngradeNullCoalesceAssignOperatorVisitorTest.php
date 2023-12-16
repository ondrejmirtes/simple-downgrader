<?php declare(strict_types = 1);

namespace SimpleDowngrader\Visitor;

use PhpParser\NodeVisitor;

class DowngradeNullCoalesceAssignOperatorVisitorTest extends AbstractVisitorTestCase
{

	protected function getVisitor(): NodeVisitor
	{
		return new DowngradeNullCoalesceAssignOperatorVisitor();
	}

	public function dataVisitor(): iterable
	{
		yield [
			<<<'PHP'
<?php

$a ??= $b;
PHP
,
			<<<'PHP'
<?php

$a = $a ?? $b;
PHP
,
		];
	}

}
