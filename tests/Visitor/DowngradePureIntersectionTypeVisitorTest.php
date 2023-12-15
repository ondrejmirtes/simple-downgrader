<?php declare(strict_types = 1);

namespace SimpleDowngrader\Visitor;

use PhpParser\NodeVisitor;
use PHPStan\PhpDocParser\Printer\Printer;
use SimpleDowngrader\PhpDoc\PhpDocEditor;

class DowngradePureIntersectionTypeVisitorTest extends AbstractVisitorTestCase
{

    protected function getVisitor(): NodeVisitor
    {
        return new DowngradePureIntersectionTypeVisitor(new PhpDocEditor(new Printer()));
    }

    public function dataVisitor(): iterable
    {
        yield [
            <<<'PHP'
<?php

class SomeClass
{
    public Foo&Bar $foo;

    public function doFoo(\Foo&\Bar $a): Foo&\Bar
    {
        $this->foo = 'foo';
    }
}
PHP
,
            <<<'PHP'
<?php

class SomeClass
{
    /** @var Foo&Bar */
    public $foo;

    /**
     * @param \Foo&\Bar
     * @return Foo&\Bar
     */
    public function doFoo($a)
    {
        $this->foo = 'foo';
    }
}
PHP
,
        ];
    }

}
