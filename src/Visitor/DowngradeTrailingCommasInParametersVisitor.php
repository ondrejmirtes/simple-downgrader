<?php declare(strict_types = 1);

namespace SimpleDowngrader\Visitor;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Token;
use SimpleDowngrader\Php\FollowedByCommaAnalyser;
use SimpleDowngrader\Php\PhpPrinter;
use function count;

class DowngradeTrailingCommasInParametersVisitor extends NodeVisitorAbstract implements TokensAwareVisitor
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
		if (!$node instanceof Node\FunctionLike) {
			return null;
		}

		$params = $node->getParams();
		if (count($params) === 0) {
			return null;
		}

		$lastParam = $params[count($params) - 1];

		if (!$this->followedByCommaAnalyzer->isFollowed($this->tokens, $lastParam)) {
			return null;
		}

		$lastParam->setAttribute(PhpPrinter::FUNC_ARGS_TRAILING_COMMA_ATTRIBUTE, false);
		$node->setAttribute('origNode', null);

		return $node;
	}

}
