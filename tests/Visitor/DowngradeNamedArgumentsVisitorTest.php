<?php declare(strict_types = 1);

namespace SimpleDowngrader\Visitor;

use PhpParser\NodeVisitor;
use PHPStan\BetterReflection\BetterReflection;
use PHPStan\BetterReflection\Reflector\DefaultReflector;
use PHPStan\BetterReflection\SourceLocator\Type\AggregateSourceLocator;
use PHPStan\BetterReflection\SourceLocator\Type\DirectoriesSourceLocator;
use PHPStan\BetterReflection\SourceLocator\Type\PhpInternalSourceLocator;
use PHPStan\BetterReflection\SourceLocator\Type\StringSourceLocator;
use const PHP_VERSION_ID;

class DowngradeNamedArgumentsVisitorTest extends AbstractVisitorTestCase
{

	protected function getVisitor(): NodeVisitor
	{
		$betterReflection = new BetterReflection();
		$astLocator = $betterReflection->astLocator();
		$sourceStubber = $betterReflection->sourceStubber();

		return new DowngradeNamedArgumentsVisitor(new DefaultReflector(new AggregateSourceLocator([
			new StringSourceLocator(
				<<<'PHP'
<?php

namespace MyNamespace;

class StreamOutput
{

	public const VERBOSITY_NORMAL = 1;

	public function __construct(int $verbosity = self::VERBOSITY_NORMAL, bool $decorated = false)
	{
	}

}
PHP,
				$astLocator,
			),
			new DirectoriesSourceLocator([__DIR__ . '/../../vendor/jetbrains/phpstorm-stubs/meta/attributes'], $astLocator),
			new PhpInternalSourceLocator($astLocator, $sourceStubber),
		])));
	}

	public function dataVisitor(): iterable
	{
		yield [
			<<<'PHP'
<?php

#[\JetBrains\PhpStorm\Deprecated(replacement: 'foo', reason: 'bar')]
class Foo
{
}
PHP
,
			<<<'PHP'
<?php

#[\JetBrains\PhpStorm\Deprecated('bar', 'foo')]
class Foo
{
}
PHP
,
		];

		yield [
			<<<'PHP'
<?php

use JetBrains\PhpStorm\Deprecated;

#[Deprecated(replacement: 'foo', reason: 'bar')]
class Foo
{
}
PHP
,
			<<<'PHP'
<?php

use JetBrains\PhpStorm\Deprecated;

#[Deprecated('bar', 'foo')]
class Foo
{
}
PHP
,
		];

		yield [
			<<<'PHP'
<?php

#[\JetBrains\PhpStorm\Deprecated(replacement: 'foo')]
class Foo
{
}
PHP
,
			<<<'PHP'
<?php

#[\JetBrains\PhpStorm\Deprecated("", 'foo')]
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
    null,
	true,
);
PHP,
		];

		yield [
			<<<'PHP'
<?php

@mkdir(dirname($symbolsFile), recursive: true);
PHP
,
			<<<'PHP'
<?php

@mkdir(dirname($symbolsFile), 0777, true);
PHP,
		];

		yield [
			<<<'PHP'
<?php

use MyNamespace\StreamOutput;

new StreamOutput(decorated: true);
PHP
,
			<<<'PHP'
<?php

use MyNamespace\StreamOutput;

new StreamOutput(\MyNamespace\StreamOutput::VERBOSITY_NORMAL, true);
PHP,
		];

		yield [
			<<<'PHP'
<?php

class Foo
{
	public function __construct(
		#[\JetBrains\PhpStorm\Deprecated(replacement: 'foo')]
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
		#[\JetBrains\PhpStorm\Deprecated("", 'foo')]
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
