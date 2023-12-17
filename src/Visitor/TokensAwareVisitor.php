<?php declare(strict_types = 1);

namespace SimpleDowngrader\Visitor;

use PhpParser\Token;

interface TokensAwareVisitor
{

	/**
	 * @param Token[] $tokens
	 */
	public function setTokens(array $tokens): void;

}
