<?php declare(strict_types = 1);

namespace SimpleDowngrader\Visitor;

use Exception;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\NodeVisitorAbstract;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionParameter;
use RuntimeException;
use function array_key_exists;
use function array_keys;
use function array_pop;
use function array_values;
use function count;
use function in_array;
use function is_array;
use function is_int;
use function is_null;
use function is_string;
use function ksort;
use function max;
use function sprintf;
use function var_export;

class DowngradeNamedArgumentsVisitor extends NodeVisitorAbstract
{

	public const ORIGINAL_ARG_ATTRIBUTE = 'originalArg';

	/** @var list<Node\Name|null> */
	private array $classLikeStack = [];

	public function enterNode(Node $node)
	{
		if ($node instanceof Node\Expr\ArrowFunction) {
			$node->attrGroups = $this->downgradeAttrGroups($node->attrGroups);

			return $node;
		}
		if ($node instanceof Node\Stmt\ClassConst) {
			$node->attrGroups = $this->downgradeAttrGroups($node->attrGroups);

			return $node;
		}
		if ($node instanceof Node\Expr\Closure) {
			$node->attrGroups = $this->downgradeAttrGroups($node->attrGroups);

			return $node;
		}
		if ($node instanceof Node\Stmt\ClassLike) {
			$this->classLikeStack[] = $node->namespacedName;
			$node->attrGroups = $this->downgradeAttrGroups($node->attrGroups);

			return $node;
		}
		if ($node instanceof Node\Stmt\ClassMethod) {
			$node->attrGroups = $this->downgradeAttrGroups($node->attrGroups);

			return $node;
		}
		if ($node instanceof Node\Stmt\EnumCase) {
			$node->attrGroups = $this->downgradeAttrGroups($node->attrGroups);

			return $node;
		}
		if ($node instanceof Node\Stmt\Function_) {
			$node->attrGroups = $this->downgradeAttrGroups($node->attrGroups);

			return $node;
		}
		if ($node instanceof Node\Param) {
			$node->attrGroups = $this->downgradeAttrGroups($node->attrGroups);

			return $node;
		}
		if ($node instanceof Node\Stmt\Property) {
			$node->attrGroups = $this->downgradeAttrGroups($node->attrGroups);

			return $node;
		}
		if ($node instanceof Node\PropertyHook) {
			$node->attrGroups = $this->downgradeAttrGroups($node->attrGroups);

			return $node;
		}
		if ($node instanceof Node\Expr\FuncCall && $node->name instanceof Node\Name && !$node->isFirstClassCallable()) {
			if (!$this->hasNamedArgs($node->getArgs())) {
				return null;
			}
			try {
				$function = new ReflectionFunction($node->name->toString());
			} catch (ReflectionException $e) {
				return null;
			}

			$newArgs = $this->downgradeArgs(array_values($node->getArgs()), $function->getParameters());
			if ($newArgs === null) {
				return null;
			}

			$node->args = $newArgs;

			return $node;
		}
		if ($node instanceof Node\Expr\New_ && $node->class instanceof Node\Name && !$node->isFirstClassCallable()) {
			if (!$this->hasNamedArgs($node->getArgs())) {
				return null;
			}

			$accessedClassName = $this->resolveName($node->class, $this->classLikeStack[count($this->classLikeStack) - 1] ?? null);

			try {
				$class = new ReflectionClass($accessedClassName); /** @phpstan-ignore argument.type */
			} catch (ReflectionException $e) {
				return null;
			}

			$constructor = $class->getConstructor();
			if ($constructor === null) {
				return null;
			}

			$newArgs = $this->downgradeArgs(array_values($node->getArgs()), $constructor->getParameters());
			if ($newArgs === null) {
				return null;
			}

			$node->args = $newArgs;

			return $node;
		}
		if (
			$node instanceof Node\Expr\StaticCall
			&& $node->class instanceof Node\Name
			&& $node->name instanceof Node\Identifier
			&& !$node->isFirstClassCallable()
		) {
			if (!$this->hasNamedArgs($node->getArgs())) {
				return null;
			}

			$accessedClassName = $this->resolveName($node->class, $this->classLikeStack[count($this->classLikeStack) - 1] ?? null);

			try {
				$class = new ReflectionClass($accessedClassName); /** @phpstan-ignore argument.type */
			} catch (ReflectionException $e) {
				return null;
			}

			if (!$class->hasMethod($node->name->toString())) {
				return null;
			}

			$method = $class->getMethod($node->name->toString());
			$newArgs = $this->downgradeArgs(array_values($node->getArgs()), $method->getParameters());
			if ($newArgs === null) {
				return null;
			}

			$node->args = $newArgs;

			return $node;
		}

		return null;
	}

	public function leaveNode(Node $node)
	{
		if (!($node instanceof Node\Stmt\ClassLike)) {
			return null;
		}

		array_pop($this->classLikeStack);

		return null;
	}

	private function resolveName(Node\Name $name, ?Node\Name $inClassName): string
	{
		$stringName = $name->toString();
		if ($inClassName === null) {
			return $stringName;
		}

		if (in_array($name->toLowerString(), ['self', 'static'], true)) {
			return $inClassName->toString();
		}

		if ($name->toLowerString() === 'parent') {
			try {
				$class = new ReflectionClass($inClassName->toString()); /** @phpstan-ignore argument.type */
				$parent = $class->getParentClass();
				if ($parent === false) {
					return $stringName;
				}
			} catch (ReflectionException $e) {
				return $stringName;
			}

			return $parent->getName();
		}

		return $stringName;
	}

	/**
	 * @param Node\AttributeGroup[] $attrGroups
	 * @return Node\AttributeGroup[]
	 */
	private function downgradeAttrGroups(array $attrGroups): array
	{
		foreach ($attrGroups as $attrGroup) {
			foreach ($attrGroup->attrs as $attr) {
				if (!$this->hasNamedArgs($attr->args)) {
					continue;
				}
				try {
					$class = new ReflectionClass($attr->name->toString()); /** @phpstan-ignore argument.type */
				} catch (ReflectionException $e) {
					continue;
				}

				$constructor = $class->getConstructor();
				if ($constructor === null) {
					continue;
				}

				$newArgs = $this->downgradeArgs($attr->args, $constructor->getParameters());
				if ($newArgs === null) {
					continue;
				}

				$attr->args = $newArgs;
			}
		}
		return $attrGroups;
	}

	/**
	 * @param Arg[] $args
	 */
	private function hasNamedArgs(array $args): bool
	{
		foreach ($args as $arg) {
			if ($arg->name === null) {
				continue;
			}

			return true;
		}

		return false;
	}

	/**
	 * @param list<Arg> $args
	 * @param list<ReflectionParameter> $parameters
	 * @return list<Arg>|null
	 */
	private function downgradeArgs(array $args, array $parameters): ?array
	{
		if (count($args) === 0) {
			return [];
		}

		$hasNamedArgs = false;
		foreach ($args as $arg) {
			if ($arg->name !== null) {
				$hasNamedArgs = true;
				break;
			}
		}
		if (!$hasNamedArgs) {
			return $args;
		}

		$hasVariadic = false;
		$argumentPositions = [];
		foreach ($parameters as $i => $parameter) {
			if ($hasVariadic) {
				// variadic parameter must be last
				return null;
			}

			$hasVariadic = $parameter->isVariadic();
			$argumentPositions[$parameter->getName()] = $i;
		}

		$reorderedArgs = [];
		$additionalNamedArgs = [];
		$appendArgs = [];
		foreach ($args as $i => $arg) {
			if ($arg->name === null) {
				// add regular args as is
				$reorderedArgs[$i] = $arg;
			} elseif (array_key_exists($arg->name->toString(), $argumentPositions)) {
				$argName = $arg->name->toString();
				// order named args into the position the signature expects them
				$attributes = $arg->getAttributes();
				$attributes[self::ORIGINAL_ARG_ATTRIBUTE] = $arg;
				$reorderedArgs[$argumentPositions[$argName]] = new Arg(
					$arg->value,
					$arg->byRef,
					$arg->unpack,
					$attributes,
					null,
				);
			} else {
				if (!$hasVariadic) {
					$attributes = $arg->getAttributes();
					$attributes[self::ORIGINAL_ARG_ATTRIBUTE] = $arg;
					$appendArgs[] = new Arg(
						$arg->value,
						$arg->byRef,
						$arg->unpack,
						$attributes,
						null,
					);
					continue;
				}

				$attributes = $arg->getAttributes();
				$attributes[self::ORIGINAL_ARG_ATTRIBUTE] = $arg;
				$additionalNamedArgs[] = new Arg(
					$arg->value,
					$arg->byRef,
					$arg->unpack,
					$attributes,
					null,
				);
			}
		}

		// replace variadic parameter with additional named args, except if it is already set
		$additionalNamedArgsOffset = count($argumentPositions) - 1;
		if (array_key_exists($additionalNamedArgsOffset, $reorderedArgs)) {
			$additionalNamedArgsOffset++;
		}

		foreach ($additionalNamedArgs as $i => $additionalNamedArg) {
			$reorderedArgs[$additionalNamedArgsOffset + $i] = $additionalNamedArg;
		}

		if (count($reorderedArgs) === 0) {
			foreach ($appendArgs as $arg) {
				$reorderedArgs[] = $arg;
			}
			return $reorderedArgs;
		}

		// fill up all holes with default values until the last given argument
		for ($j = 0; $j < max(array_keys($reorderedArgs)); $j++) {
			if (array_key_exists($j, $reorderedArgs)) {
				continue;
			}
			if (!array_key_exists($j, $parameters)) {
				throw new Exception('Parameter signatures cannot have holes');
			}

			$parameter = $parameters[$j];

			// we can only fill up optional parameters with default values
			if (!$parameter->isOptional()) {
				return null;
			}

			if (!$parameter->isDefaultValueAvailable()) {
				if (!$parameter->isVariadic()) {
					throw new Exception(sprintf('An optional parameter $%s must have a default value', $parameter->getName()));
				}

				$defaultValue = new Node\Expr\Array_();

			} else {
				$defaultValue = $this->constantToExpr($parameter->getDefaultValue());
			}

			$reorderedArgs[$j] = new Arg($defaultValue);
		}

		ksort($reorderedArgs);

		foreach ($appendArgs as $arg) {
			$reorderedArgs[] = $arg;
		}

		return array_values($reorderedArgs);
	}

	/**
	 * @param mixed $value
	 */
	private function constantToExpr($value): Node\Expr
	{
		if (is_string($value)) {
			return new Node\Scalar\String_($value);
		} elseif (is_int($value)) {
			return new Node\Scalar\Int_($value);
		} elseif ($value === true) {
			return new Node\Expr\ConstFetch(new Node\Name\FullyQualified('true'));
		} elseif ($value === false) {
			return new Node\Expr\ConstFetch(new Node\Name\FullyQualified('false'));
		} elseif (is_array($value)) {
			$items = [];
			foreach ($value as $key => $val) {
				$items[] = new Node\ArrayItem($this->constantToExpr($val), $this->constantToExpr($key));
			}
			return new Node\Expr\Array_($items);
		} elseif (is_null($value)) {
			return new Node\Expr\ConstFetch(new Node\Name\FullyQualified('null'));
		}

		throw new RuntimeException(sprintf('Unexpected value %s', var_export($value, true)));
	}

}
