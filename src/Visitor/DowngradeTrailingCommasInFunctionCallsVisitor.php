<?php declare(strict_types = 1);

namespace SimpleDowngrader\Visitor;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Token;
use SimpleDowngrader\Php\FollowedByCommaAnalyser;
use SimpleDowngrader\Php\PhpPrinter;
use function count;

class DowngradeTrailingCommasInFunctionCallsVisitor extends NodeVisitorAbstract implements TokensAwareVisitor
{

	private FollowedByCommaAnalyser $followedByCommaAnalyzer;

	/** @var Token[] */
	private array $tokens;

	public function __construct(FollowedByCommaAnalyser $followedByCommaAnalyser)
	{
		$this->followedByCommaAnalyzer = $followedByCommaAnalyser;
	}

	public function setTokens(array $tokens): void
	{
		$this->tokens = $tokens;
	}

	public function enterNode(Node $node)
	{
		if (!$node instanceof Node\Expr\CallLike) {
			return null;
		}

		if ($node->isFirstClassCallable()) {
			return null;
		}

		$args = $node->getArgs();
		if (count($args) === 0) {
			return null;
		}

		$lastArg = $args[count($args) - 1];

		if (!$this->followedByCommaAnalyzer->isFollowed($this->tokens, $lastArg)) {
			return null;
		}

		$lastArg->setAttribute(PhpPrinter::FUNC_ARGS_TRAILING_COMMA_ATTRIBUTE, false);
		$node->setAttribute('origNode', null);

		return $node;
	}

}
