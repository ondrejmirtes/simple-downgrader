<?php

namespace SimpleDowngrader\Fixtures;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
class MyLanguage
{
	/**
	 * @param string $languageName Language name like "PHP", "SQL", "RegExp", etc...
	 */
	public function __construct(string $languageName) {}
}
