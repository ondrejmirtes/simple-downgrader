<?php declare(strict_types = 1);

namespace SimpleDowngrader\Visitor;

interface TokensAwareVisitor
{

	/**
	 * @param mixed[] $tokens
	 */
	public function setTokens(array $tokens): void;

}
