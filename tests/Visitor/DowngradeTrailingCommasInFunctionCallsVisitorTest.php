<?php declare(strict_types = 1);

namespace SimpleDowngrader\Visitor;

use PhpParser\NodeVisitor;
use SimpleDowngrader\Php\FollowedByCommaAnalyser;

class DowngradeTrailingCommasInFunctionCallsVisitorTest extends AbstractVisitorTestCase
{

	protected function getVisitor(): NodeVisitor
	{
		return new DowngradeTrailingCommasInFunctionCallsVisitor(new FollowedByCommaAnalyser());
	}

	public function dataVisitor(): iterable
	{
		yield [
			<<<'PHP'
<?php

foo($a, $b, );
PHP
,
			<<<'PHP'
<?php

foo($a, $b);
PHP
,
		];
	}

}
