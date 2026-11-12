<?php declare(strict_types = 1);

namespace SimpleDowngrader\Visitor;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeVisitorAbstract;
use function count;
use function is_string;

/**
 * Downgrades `$a?->b->c()` to `$a !== null ? $a->b->c() : null`.
 *
 * Like the nullsafe operator, the condition short-circuits the rest of the chain.
 * Bases other than a plain variable are evaluated once into a temporary variable.
 * In isset(), empty() and on the left side of ??, missing values do not emit warnings.
 */
class DowngradeNullsafeOperatorVisitor extends NodeVisitorAbstract
{

	private const CHAIN_INNER_ATTRIBUTE = 'nullsafeChainInner';
	private const ISSET_MODE_ATTRIBUTE = 'nullsafeIssetMode';

	private int $variableCounter = 0;

	public function beforeTraverse(array $nodes)
	{
		$this->variableCounter = 0;

		return null;
	}

	public function enterNode(Node $node)
	{
		$chainVar = $this->getChainVar($node);
		if ($chainVar !== null) {
			$chainVar->setAttribute(self::CHAIN_INNER_ATTRIBUTE, true);
		}

		if ($node instanceof Expr\Isset_) {
			foreach ($node->vars as $var) {
				$var->setAttribute(self::ISSET_MODE_ATTRIBUTE, true);
			}
		} elseif ($node instanceof Expr\Empty_) {
			$node->expr->setAttribute(self::ISSET_MODE_ATTRIBUTE, true);
		} elseif ($node instanceof Expr\BinaryOp\Coalesce) {
			$node->left->setAttribute(self::ISSET_MODE_ATTRIBUTE, true);
		}

		return null;
	}

	public function leaveNode(Node $node)
	{
		if ($node instanceof Expr\Isset_) {
			$changed = false;
			$result = null;
			foreach ($node->vars as $var) {
				$downgraded = $this->downgradeIsset($var);
				$changed = $changed || !$downgraded instanceof Expr\Isset_;
				$result = $result === null ? $downgraded : new Expr\BinaryOp\BooleanAnd($result, $downgraded);
			}

			return $changed ? $result : null;
		}

		if ($node instanceof Expr\Empty_) {
			$downgraded = $this->downgradeEmpty($node->expr);

			return $downgraded instanceof Expr\Empty_ ? null : $downgraded;
		}

		if ($node instanceof Expr\BinaryOp\Coalesce) {
			if ($this->findInnermostNullsafe($node->left) === null) {
				return null;
			}

			$node->left = $this->downgradeCoalesceLeft($node->left);

			return $node;
		}

		if ($node instanceof Node\Scalar\InterpolatedString) {
			return $this->downgradeInterpolatedString($node);
		}

		if (
			!$node instanceof Expr
			|| $node->getAttribute(self::CHAIN_INNER_ATTRIBUTE) === true
			|| $node->getAttribute(self::ISSET_MODE_ATTRIBUTE) === true
		) {
			return null;
		}

		if ($this->findInnermostNullsafe($node) === null) {
			return null;
		}

		return $this->downgradeChain($node);
	}

	private function downgradeChain(Expr $chain): Expr
	{
		$split = $this->splitChain($chain, false, false);
		if ($split === null) {
			return $chain;
		}

		return new Expr\Ternary($split[0], $this->downgradeChain($split[1]), $this->createNull());
	}

	private function downgradeCoalesceLeft(Expr $chain): Expr
	{
		$split = $this->splitChain($chain, true, false);
		if ($split === null) {
			return new Expr\BinaryOp\Coalesce($chain, $this->createNull());
		}

		return new Expr\Ternary($split[0], $this->downgradeCoalesceLeft($split[1]), $this->createNull());
	}

	private function downgradeIsset(Expr $chain): Expr
	{
		$split = $this->splitChain($chain, true, false);
		if ($split === null) {
			return new Expr\Isset_([$chain]);
		}

		return new Expr\BinaryOp\BooleanAnd($split[0], $this->downgradeIsset($split[1]));
	}

	private function downgradeEmpty(Expr $chain): Expr
	{
		$split = $this->splitChain($chain, true, true);
		if ($split === null) {
			return new Expr\Empty_($chain);
		}

		return new Expr\BinaryOp\BooleanOr($split[0], $this->downgradeEmpty($split[1]));
	}

	/**
	 * Replaces the nullsafe operator closest to the start of the chain with a regular one.
	 *
	 * @return array{Expr, Expr}|null the condition that the object is not null (or is null when negated), and the rest of the chain
	 */
	private function splitChain(Expr $chain, bool $issetMode, bool $negated): ?array
	{
		$found = $this->findInnermostNullsafe($chain);
		if ($found === null) {
			return null;
		}

		[$parent, $nullsafe] = $found;
		$var = $nullsafe->var;
		if ($var instanceof Expr\Variable && is_string($var->name)) {
			$object = new Expr\Variable($var->name);
			if ($issetMode) {
				$condition = new Expr\Isset_([$var]);
				if ($negated) {
					$condition = new Expr\BooleanNot($condition);
				}
			} else {
				$condition = $this->createNullComparison($var, $negated);
			}
		} else {
			$this->variableCounter++;
			$object = new Expr\Variable('nullsafeVariable' . $this->variableCounter);
			$condition = $this->createNullComparison(
				new Expr\Assign($object, $issetMode ? new Expr\BinaryOp\Coalesce($var, $this->createNull()) : $var),
				$negated,
			);
		}

		if ($nullsafe instanceof Expr\NullsafePropertyFetch) {
			$regular = new Expr\PropertyFetch($object, $nullsafe->name);
		} else {
			$regular = new Expr\MethodCall($object, $nullsafe->name, $nullsafe->args);
		}

		if ($parent === null) {
			return [$condition, $regular];
		}

		if ($parent instanceof Expr\StaticPropertyFetch || $parent instanceof Expr\StaticCall) {
			$parent->class = $regular;
		} elseif (
			$parent instanceof Expr\PropertyFetch
			|| $parent instanceof Expr\NullsafePropertyFetch
			|| $parent instanceof Expr\MethodCall
			|| $parent instanceof Expr\NullsafeMethodCall
			|| $parent instanceof Expr\ArrayDimFetch
		) {
			$parent->var = $regular;
		}

		return [$condition, $chain];
	}

	/**
	 * @return array{Expr|null, Expr\NullsafePropertyFetch|Expr\NullsafeMethodCall}|null
	 */
	private function findInnermostNullsafe(Expr $chain): ?array
	{
		$found = null;
		$parent = null;
		$current = $chain;
		while (($var = $this->getChainVar($current)) !== null) {
			if ($current instanceof Expr\NullsafePropertyFetch || $current instanceof Expr\NullsafeMethodCall) {
				$found = [$parent, $current];
			}
			$parent = $current;
			$current = $var;
		}

		return $found;
	}

	/**
	 * Returns the expression that the nullsafe operator short-circuits through, like PHP does.
	 */
	private function getChainVar(Node $node): ?Expr
	{
		if (
			$node instanceof Expr\PropertyFetch
			|| $node instanceof Expr\NullsafePropertyFetch
			|| $node instanceof Expr\MethodCall
			|| $node instanceof Expr\NullsafeMethodCall
			|| $node instanceof Expr\ArrayDimFetch
		) {
			return $node->var;
		}

		if (
			($node instanceof Expr\StaticPropertyFetch || $node instanceof Expr\StaticCall)
			&& $node->class instanceof Expr
		) {
			return $node->class;
		}

		return null;
	}

	/**
	 * Expressions are not allowed in "{...}" interpolation, so the string becomes a concatenation.
	 */
	private function downgradeInterpolatedString(Node\Scalar\InterpolatedString $node): ?Expr
	{
		$changed = false;
		$parts = [];
		foreach ($node->parts as $part) {
			if ($part instanceof Node\InterpolatedStringPart) {
				$parts[] = new Node\Scalar\String_($part->value);
				continue;
			}

			$changed = $changed || $part instanceof Expr\Ternary;
			$parts[] = $part;
		}

		if (!$changed) {
			return null;
		}

		if (count($parts) === 1) {
			return new Expr\Cast\String_($parts[0]);
		}

		$result = $parts[0];
		for ($i = 1; $i < count($parts); $i++) {
			$result = new Expr\BinaryOp\Concat($result, $parts[$i]);
		}

		return $result;
	}

	private function createNullComparison(Expr $expr, bool $negated): Expr
	{
		if ($negated) {
			return new Expr\BinaryOp\Identical($expr, $this->createNull());
		}

		return new Expr\BinaryOp\NotIdentical($expr, $this->createNull());
	}

	private function createNull(): Expr\ConstFetch
	{
		return new Expr\ConstFetch(new Node\Name('null'));
	}

}
