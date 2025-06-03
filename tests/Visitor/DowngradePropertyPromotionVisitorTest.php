<?php declare(strict_types = 1);

namespace SimpleDowngrader\Visitor;

use PhpParser\NodeVisitor;
use PHPStan\BetterReflection\BetterReflection;
use PHPStan\BetterReflection\Reflector\DefaultReflector;
use PHPStan\BetterReflection\SourceLocator\Type\AggregateSourceLocator;
use PHPStan\BetterReflection\SourceLocator\Type\DirectoriesSourceLocator;
use PHPStan\BetterReflection\SourceLocator\Type\PhpInternalSourceLocator;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\ParserConfig;
use const PHP_VERSION_ID;

class DowngradePropertyPromotionVisitorTest extends AbstractVisitorTestCase
{

	protected function getVisitor(): NodeVisitor
	{
		$betterReflection = new BetterReflection();
		$astLocator = $betterReflection->astLocator();
		$sourceStubber = $betterReflection->sourceStubber();
		$reflector = new DefaultReflector(new AggregateSourceLocator([
			new DirectoriesSourceLocator([__DIR__ . '/../../vendor/jetbrains/phpstorm-stubs/meta/attributes'], $astLocator),
			new PhpInternalSourceLocator($astLocator, $sourceStubber),
		]));

		return new DowngradePropertyPromotionVisitor(
			new Lexer(new ParserConfig([])),
			$this->createPhpDocParser(),
			$this->createPhpDocEditor(),
			$reflector,
		);
	}

	public function dataVisitor(): iterable
	{
		yield [
			<<<'PHP'
<?php

class SomeClass
{
    public function __construct(private Test $test)
    {

    }
}
PHP
,
			<<<'PHP'
<?php

class SomeClass
{
    private Test $test;
    public function __construct(Test $test)
    {
        $this->test = $test;
    }
}
PHP
,
		];

		yield [
			<<<'PHP'
<?php

class SomeClass
{
    public function __construct(private Test $test = null)
    {

    }
}
PHP
,
			<<<'PHP'
<?php

class SomeClass
{
    private Test $test;
    public function __construct(Test $test = null)
    {
        $this->test = $test;
    }
}
PHP
,
		];

		yield [
			<<<'PHP'
<?php

class SomeClass
{
    /** @param Test&Something $test */
    public function __construct(private Test $test)
    {

    }
}
PHP
,
			<<<'PHP'
<?php

class SomeClass
{
    /**
     * @var Test&Something
     */
    private Test $test;
    /** @param Test&Something $test */
    public function __construct(Test $test)
    {
        $this->test = $test;
    }
}
PHP
,
		];

		yield [
			<<<'PHP'
<?php

class SomeClass
{
    /** @param Test&Foo $test */
    public function __construct(
        /**
         * @readonly
         */
        private Test $test
    )
    {

    }
}
PHP
,
			<<<'PHP'
<?php

class SomeClass
{
    /**
     * @readonly
     * @var Test&Foo
     */
    private Test $test;
    /** @param Test&Foo $test */
    public function __construct(Test $test)
    {
        $this->test = $test;
    }
}
PHP
,
		];

		yield [
			<<<'PHP'
<?php

class SomeClass
{
    public function __construct(private Test $test)
    {

    }

    public function doFoo()
    {
    	$traverser->addVisitor(new class () extends NodeVisitorAbstract {
			/**
			 * @return ExistingArrayDimFetch|null
			 */
			public function leaveNode(Node $node)
			{
				if (!$node instanceof ArrayDimFetch || $node->dim === null) {
					return null;
				}

				return new ExistingArrayDimFetch($node->var, $node->dim);
			}

		});
    }
}
PHP
,
			<<<'PHP'
<?php

class SomeClass
{
    private Test $test;
    public function __construct(Test $test)
    {
        $this->test = $test;
    }

    public function doFoo()
    {
    	$traverser->addVisitor(new class () extends NodeVisitorAbstract {
			/**
			 * @return ExistingArrayDimFetch|null
			 */
			public function leaveNode(Node $node)
			{
				if (!$node instanceof ArrayDimFetch || $node->dim === null) {
					return null;
				}

				return new ExistingArrayDimFetch($node->var, $node->dim);
			}

		});
    }
}
PHP
,
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
			PHP_VERSION_ID < 80000 ? <<<'PHP'
<?php

class Foo
{
	#[\JetBrains\PhpStorm\Deprecated(replacement: 'foo')]
	public int $foo;
	public function __construct(
		#[\JetBrains\PhpStorm\Deprecated(replacement: 'foo')]
		int $foo
	)
	{
		$this->foo = $foo;
	}
}
PHP : <<<'PHP'
<?php

class Foo
{
	#[\JetBrains\PhpStorm\Deprecated(replacement: 'foo')]
	public int $foo;
	public function __construct(#[\JetBrains\PhpStorm\Deprecated(replacement: 'foo')] int $foo)
	{
		$this->foo = $foo;
	}
}
PHP
,
		];

		if (PHP_VERSION_ID >= 80000) {
			yield [
				<<<'PHP'
<?php

class Foo
{
	public function __construct(
		#[\JetBrains\PhpStorm\Immutable]
		public int $foo,
	) {}
}
PHP,
				<<<'PHP'
<?php

class Foo
{
	#[\JetBrains\PhpStorm\Immutable]
	public int $foo;
	public function __construct(int $foo)
	{
		$this->foo = $foo;
	}
}
PHP,
			];
		}

		if (PHP_VERSION_ID >= 80000) {
			yield [
				<<<'PHP'
<?php

class Foo
{
	public function __construct(
		#[\JetBrains\PhpStorm\Language]
		public int $foo,
	) {}
}
PHP,
				<<<'PHP'
<?php

class Foo
{
	public int $foo;
	public function __construct(#[\JetBrains\PhpStorm\Language] int $foo)
	{
		$this->foo = $foo;
	}
}
PHP,
			];
		}

		yield [
			<<<'PHP'
<?php

class SomeClass
{
    public function __construct(
        /** @readonly */
        private Test $test
    )
    {

    }
}
PHP
,
			<<<'PHP'
<?php

class SomeClass
{
    /** @readonly */
    private Test $test;
    public function __construct(Test $test)
    {
        $this->test = $test;
    }
}
PHP
,
		];
	}

}
