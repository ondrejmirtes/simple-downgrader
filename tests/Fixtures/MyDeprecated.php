<?php declare(strict_types = 1);

namespace SimpleDowngrader\Fixtures;

use Attribute;

#[Attribute(Attribute::TARGET_FUNCTION | Attribute::TARGET_METHOD | Attribute::TARGET_CLASS | Attribute::TARGET_CLASS_CONSTANT | Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
class MyDeprecated
{

	public ?string $message;

	public ?string $since;

	public function __construct(?string $message = null, ?string $since = null)
	{
		$this->message = $message;
		$this->since = $since;
	}

}
