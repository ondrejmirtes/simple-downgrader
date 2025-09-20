<?php declare(strict_types = 1);

namespace SimpleDowngrader\Fixtures;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_CLASS)]
class MyImmutable
{

}
