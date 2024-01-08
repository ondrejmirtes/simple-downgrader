<?php declare(strict_types = 1);

namespace SimpleDowngrader\Visitor;

/**
 * @phpstan-type TokensArray array<string|array{int, string, int}>
 */
interface TokensAwareVisitor
{

	/**
	 * @param TokensArray $tokens
	 */
	public function setTokens(array $tokens): void;

}
