<?php declare(strict_types = 1);

namespace SimpleDowngrader\Visitor;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use RuntimeException;
use function sprintf;

/**
 * `new` in attribute arguments requires PHP 8.1. It is replaced with
 * `['olvlvl\ComposerAttributeCollector\NewExpression' => [Foo::class, [arguments]]]`,
 * which ondrejmirtes/composer-attribute-collector turns back into `new \Foo(...arguments)`.
 *
 * The changed attribute groups are printed on a single line so that PHP 7.4
 * still reads them as comments.
 */
class DowngradeNewInAttributeArgumentsVisitor extends NodeVisitorAbstract
{

	public const NEW_EXPRESSION_MARKER = 'olvlvl\\ComposerAttributeCollector\\NewExpression';

	public function enterNode(Node $node)
	{
		if (
			!$node instanceof Node\Stmt\ClassLike
			&& !$node instanceof Node\Stmt\ClassMethod
			&& !$node instanceof Node\Stmt\Function_
			&& !$node instanceof Node\Stmt\Property
			&& !$node instanceof Node\Stmt\ClassConst
			&& !$node instanceof Node\Stmt\EnumCase
			&& !$node instanceof Node\Param
			&& !$node instanceof Node\PropertyHook
			&& !$node instanceof Node\Expr\Closure
			&& !$node instanceof Node\Expr\ArrowFunction
		) {
			return null;
		}

		$attrGroups = $node->attrGroups;
		$changed = false;
		foreach ($attrGroups as $i => $attrGroup) {
			$newAttrs = [];
			$groupChanged = false;
			foreach ($attrGroup->attrs as $attr) {
				$newArgs = [];
				foreach ($attr->args as $arg) {
					$value = $this->replaceNewExpressions($arg->value, $groupChanged);
					$newArgs[] = new Node\Arg($value, $arg->byRef, $arg->unpack, [], $arg->name);
				}
				$newAttrs[] = new Node\Attribute($attr->name, $newArgs);
			}

			if (!$groupChanged) {
				continue;
			}

			// a new node without the original formatting is printed on a single line
			$attrGroups[$i] = new Node\AttributeGroup($newAttrs);
			$changed = true;
		}

		if (!$changed) {
			return null;
		}

		$node->attrGroups = $attrGroups;

		return $node;
	}

	private function replaceNewExpressions(Node\Expr $expr, bool &$replaced): Node\Expr
	{
		$traverser = new NodeTraverser();
		$visitor = new class () extends NodeVisitorAbstract {

			public bool $replaced = false;

			/**
			 * @return Node\Expr\Array_|null
			 */
			public function leaveNode(Node $node)
			{
				if (!$node instanceof Node\Expr\New_) {
					return null;
				}

				if (!$node->class instanceof Node\Name) {
					throw new RuntimeException('Anonymous class in attribute arguments cannot be downgraded.');
				}

				$arguments = [];
				foreach ($node->getArgs() as $arg) {
					if ($arg->unpack || $arg->byRef) {
						throw new RuntimeException(sprintf('Argument of new %s in attribute arguments cannot be downgraded.', $node->class->toString()));
					}

					$arguments[] = new Node\ArrayItem(
						$arg->value,
						$arg->name !== null ? new Node\Scalar\String_($arg->name->toString()) : null,
					);
				}

				$this->replaced = true;

				return new Node\Expr\Array_([
					new Node\ArrayItem(
						new Node\Expr\Array_([
							new Node\ArrayItem(new Node\Expr\ClassConstFetch($node->class, 'class')),
							new Node\ArrayItem(new Node\Expr\Array_($arguments, ['kind' => Node\Expr\Array_::KIND_SHORT])),
						], ['kind' => Node\Expr\Array_::KIND_SHORT]),
						new Node\Scalar\String_(DowngradeNewInAttributeArgumentsVisitor::NEW_EXPRESSION_MARKER),
					),
				], ['kind' => Node\Expr\Array_::KIND_SHORT]);
			}

		};
		$traverser->addVisitor($visitor);

		/** @var array{Node\Expr} $result */
		$result = $traverser->traverse([$expr]);
		$replaced = $replaced || $visitor->replaced;

		return $result[0];
	}

}
