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

$variantFn = fn ($acceptor) => new FunctionVariantWithPhpDocs(
    array_map(
        fn ($parameter) => new DummyParameterWithPhpDocs($parameter->getName()),
        $acceptor->getParameters(),
    )
);
PHP
,
			<<<'PHP'
<?php

$variantFn = function ($acceptor) {
    return new FunctionVariantWithPhpDocs(
        array_map(
            function ($parameter) {
                return new DummyParameterWithPhpDocs($parameter->getName());
            },
            $acceptor->getParameters(),
        )
    );
};
PHP
,
		];
	}

}
