<?php declare(strict_types = 1);

namespace SimpleDowngrader\Visitor;

use PhpParser\NodeVisitor;

class DowngradeNonCapturingCatchesVisitorTest extends AbstractVisitorTestCase
{

	protected function getVisitor(): NodeVisitor
	{
		return new DowngradeNonCapturingCatchesVisitor();
	}

	public function dataVisitor(): iterable
	{
		yield [
			<<<'PHP'
<?php

try {
} catch (\Exception) {
}
PHP
,
			<<<'PHP'
<?php

try {
} catch (\Exception $e) {
}
PHP
,
		];
	}

}
