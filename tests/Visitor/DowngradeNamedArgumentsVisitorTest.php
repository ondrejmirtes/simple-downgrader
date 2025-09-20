<?php declare(strict_types = 1);

namespace SimpleDowngrader\Visitor;

use PhpParser\NodeVisitor;
use const PHP_VERSION_ID;

class DowngradeNamedArgumentsVisitorTest extends AbstractVisitorTestCase
{

	protected function getVisitor(): NodeVisitor
	{
		return new DowngradeNamedArgumentsVisitor();
	}

	public function dataVisitor(): iterable
	{
		yield [
			<<<'PHP'
<?php

#[\SimpleDowngrader\Fixtures\MyDeprecated(since: 'foo', message: 'bar')]
class Foo
{
}
PHP
,
			<<<'PHP'
<?php

#[\SimpleDowngrader\Fixtures\MyDeprecated('bar', 'foo')]
class Foo
{
}
PHP
,
		];

		yield [
			<<<'PHP'
<?php

use SimpleDowngrader\Fixtures\MyDeprecated;

#[MyDeprecated(since: 'foo', message: 'bar')]
class Foo
{
}
PHP
,
			<<<'PHP'
<?php

use SimpleDowngrader\Fixtures\MyDeprecated;

#[MyDeprecated('bar', 'foo')]
class Foo
{
}
PHP
,
		];

		yield [
			<<<'PHP'
<?php

#[\SimpleDowngrader\Fixtures\MyDeprecated(since: 'foo')]
class Foo
{
}
PHP
,
			<<<'PHP'
<?php

#[\SimpleDowngrader\Fixtures\MyDeprecated(\null, 'foo')]
class Foo
{
}
PHP
,
		];

		yield [
			<<<'PHP'
<?php

strpos(needle: $n, haystack: $h);
PHP
,
			<<<'PHP'
<?php

strpos($h, $n);
PHP
,
		];

		yield [
			<<<'PHP'
<?php

new Exception('msg', previous: $e);
PHP
,
			<<<'PHP'
<?php

new Exception('msg', 0, $e);
PHP
,
		];

		if (PHP_VERSION_ID >= 80400) {
			yield [
				<<<'PHP'
<?php

Dom\XMLDocument::createEmpty(encoding: 'ISO-8859-2');
PHP
,
				<<<'PHP'
<?php

Dom\XMLDocument::createEmpty("1.0", 'ISO-8859-2');
PHP
,
			];

			yield [
				<<<'PHP'
<?php

namespace Dom;

class XMLDocument
{
	public function doFoo()
	{
		self::createEmpty(encoding: 'ISO-8859-2');
	}
}
PHP
,
				<<<'PHP'
<?php

namespace Dom;

class XMLDocument
{
	public function doFoo()
	{
		self::createEmpty("1.0", 'ISO-8859-2');
	}
}
PHP,
			];
		}

		yield [
			<<<'PHP'
<?php

class OutOfRangeException extends Exception
{
	public function doFoo()
	{
		new parent('msg', previous: $e);
	}
}
PHP
,
			<<<'PHP'
<?php

class OutOfRangeException extends Exception
{
	public function doFoo()
	{
		new parent('msg', 0, $e);
	}
}
PHP,
		];

		yield [
			<<<'PHP'
<?php

array_slice(
	$this->resolvedPhpDocBlockCache,
	1,
	preserve_keys: true,
);
PHP
,
			<<<'PHP'
<?php

array_slice(
	$this->resolvedPhpDocBlockCache,
	1,
    \null,
	true,
);
PHP,
		];

		yield [
			<<<'PHP'
<?php

// 777 octal is 511 in decimal
@mkdir(dirname($symbolsFile), recursive: true);
PHP
,
			<<<'PHP'
<?php

// 777 octal is 511 in decimal
@mkdir(dirname($symbolsFile), 511, true);
PHP,
		];

		yield [
			<<<'PHP'
<?php

use SimpleDowngrader\Fixtures\StreamOutput;

new StreamOutput(decorated: true);
PHP
,
			<<<'PHP'
<?php

use SimpleDowngrader\Fixtures\StreamOutput;

new StreamOutput(1, true);
PHP,
		];

		yield [
			<<<'PHP'
<?php

class Foo
{
	public function __construct(
		#[\SimpleDowngrader\Fixtures\MyDeprecated(since: 'foo')]
		public int $foo,
	)
	{
	}
}
PHP
,
			<<<'PHP'
<?php

class Foo
{
	public function __construct(
		#[\SimpleDowngrader\Fixtures\MyDeprecated(\null, 'foo')]
		public int $foo,
	)
	{
	}
}
PHP
,
		];
	}

}
