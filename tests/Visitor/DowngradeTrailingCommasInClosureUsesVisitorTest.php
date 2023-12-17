<?php declare(strict_types = 1);

namespace SimpleDowngrader\Visitor;

use PhpParser\NodeVisitor;
use SimpleDowngrader\Php\FollowedByCommaAnalyser;

class DowngradeTrailingCommasInClosureUsesVisitorTest extends AbstractVisitorTestCase
{

	protected function getVisitor(): NodeVisitor
	{
		return new DowngradeTrailingCommasInClosureUsesVisitor(new FollowedByCommaAnalyser());
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

function () use ($foo) {
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

function () use ($foo, $bar) {
};
PHP
,
		];

		yield [
			<<<'PHP'
<?php

function () use ($foo) {
    echo []; // @phpstan-ignore echo.nonString
};
PHP
,
			<<<'PHP'
<?php

function () use ($foo) {
    echo []; // @phpstan-ignore echo.nonString
};
PHP
,
		];
	}

}
