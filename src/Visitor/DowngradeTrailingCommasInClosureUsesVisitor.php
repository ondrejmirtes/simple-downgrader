<?php declare(strict_types = 1);

namespace SimpleDowngrader\Visitor;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Token;
use SimpleDowngrader\Php\FollowedByCommaAnalyser;
use SimpleDowngrader\Php\PhpPrinter;
use function count;

class DowngradeTrailingCommasInClosureUsesVisitor extends NodeVisitorAbstract implements TokensAwareVisitor
{

	private FollowedByCommaAnalyser $followedByCommaAnalyzer;

	/** @var Token[] */
	private array $tokens;

	public function __construct(FollowedByCommaAnalyser $followedByCommaAnalyzer)
	{
		$this->followedByCommaAnalyzer = $followedByCommaAnalyzer;
	}

	public function setTokens(array $tokens): void
	{
		$this->tokens = $tokens;
	}

	public function enterNode(Node $node)
	{
		if (!$node instanceof Node\Expr\Closure) {
			return null;
		}

		$uses = $node->uses;
		if (count($uses) === 0) {
			return null;
		}

		$lastUse = $uses[count($uses) - 1];
		if (!$this->followedByCommaAnalyzer->isFollowed($this->tokens, $lastUse)) {
			return null;
		}

		$lastUse->setAttribute(PhpPrinter::FUNC_ARGS_TRAILING_COMMA_ATTRIBUTE, false);
		$node->setAttribute('origNode', null);

		return $node;
	}

}
