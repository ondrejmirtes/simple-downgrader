<?php declare(strict_types = 1);

namespace SimpleDowngrader\Visitor;

use PhpParser\NodeVisitor;

class DowngradePhpunitAttributesVisitorTest extends AbstractVisitorTestCase
{

	protected function getVisitor(): NodeVisitor
	{
		return new DowngradePhpunitAttributesVisitor($this->createPhpDocEditor());
	}

	public function dataVisitor(): iterable
	{
		yield [
			<<<'PHP'
<?php

#[\PHPUnit\Framework\Attributes\Group(name: 'foo')]
class Foo
{
}
PHP
,
			<<<'PHP'
<?php

/**
 * @group foo
 */
class Foo
{
}
PHP
,
		];

		yield [
			<<<'PHP'
<?php

#[\PHPUnit\Framework\Attributes\Group('foo')]
class Foo
{
}
PHP
,
			<<<'PHP'
<?php

/**
 * @group foo
 */
class Foo
{
}
PHP
,
		];

		yield [
			<<<'PHP'
<?php

#[\PHPUnit\Framework\Attributes\RequiresPhp('>= 8.3')]
class Foo
{
}
PHP
,
			<<<'PHP'
<?php

/**
 * @requires PHP >= 8.3
 */
class Foo
{
}
PHP
,
		];

		yield [
			<<<'PHP'
<?php

#[\PHPUnit\Framework\Attributes\CoversNothing]
class Foo
{
}
PHP
,
			<<<'PHP'
<?php

/**
 * @coversNothing
 */
class Foo
{
}
PHP
,
		];
	}

}
