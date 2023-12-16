<?php declare (strict_types = 1);

namespace SimpleDowngrader\Php;

use Nette\Utils\Strings;
use PhpParser\Node;
use function in_array;
use function is_array;

/**
 * Taken from https://github.com/rectorphp/rector-downgrade-php/blob/917085c6a2a99412440cd3143288d1a17abb5c44/rules/DowngradePhp73/Tokenizer/FollowedByCommaAnalyzer.php
 * (c) 2017-present Tomáš Votruba (https://tomasvotruba.cz)
 */
class FollowedByCommaAnalyser
{

	/**
	 * @param mixed[] $tokens
	 */
	public function isFollowed(array $tokens, Node $node): bool
	{
		$nextTokenPosition = $node->getEndTokenPos() + 1;
		while (isset($tokens[$nextTokenPosition])) {
			$currentToken = $tokens[$nextTokenPosition];
			// only space
			if (is_array($currentToken) || Strings::match($currentToken, '#\\s+#')) {
				++$nextTokenPosition;
				continue;
			}
			// without comma
			if (in_array($currentToken, ['(', ')', ';'], true)) {
				return false;
			}
			break;
		}
		return true;
	}

}
