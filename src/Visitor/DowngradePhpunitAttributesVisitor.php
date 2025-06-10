<?php declare(strict_types = 1);

namespace SimpleDowngrader\Visitor;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use PHPStan\PhpDocParser\Ast\PhpDoc\GenericTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use SimpleDowngrader\PhpDoc\PhpDocEditor;
use function array_key_exists;
use function array_values;
use function count;
use function property_exists;
use function sprintf;

class DowngradePhpunitAttributesVisitor extends NodeVisitorAbstract
{

	private PhpDocEditor $phpDocEditor;

	public function __construct(PhpDocEditor $phpDocEditor)
	{
		$this->phpDocEditor = $phpDocEditor;
	}

	public function enterNode(Node $node)
	{
		if (!property_exists($node, 'attrGroups')) {
			return null;
		}

		/** @var Node\AttributeGroup[] $attrGroups */
		$attrGroups = $node->attrGroups;
		if (count($attrGroups) === 0) {
			return null;
		}

		$map = [
			'PHPUnit\Framework\Attributes\Group' => '@group',
			'PHPUnit\Framework\Attributes\DataProvider' => '@dataProvider',
			'PHPUnit\Framework\Attributes\RequiresPhp' => '@requires',
		];

		foreach ($attrGroups as $i => $attrGroup) {
			foreach ($attrGroup->attrs as $j => $attr) {
				$attrName = $attr->name->toString();
				if (!array_key_exists($attrName, $map)) {
					continue;
				}
				if (
					count($attr->args) === 0
					|| !($attr->args[0]->value instanceof Node\Scalar\String_)
				) {
					continue;
				}

				$mappedAnnotation = $map[$attrName];

				unset($attrGroup->attrs[$j]);
				$this->phpDocEditor->edit($node, static function (\PHPStan\PhpDocParser\Ast\Node $node) use ($mappedAnnotation, $attrName, $attr) {
					if (!$node instanceof PhpDocNode) {
						return null;
					}

					$annotationValue = $attr->args[0]->value->value;
					if ($attrName === 'PHPUnit\Framework\Attributes\RequiresPhp') {
						$annotationValue = sprintf('PHP %s', $annotationValue);
					}

					$node->children[] = new PhpDocTagNode($mappedAnnotation, new GenericTagValueNode($annotationValue));
				});
			}

			if (count($attrGroup->attrs) > 0) {
				continue;
			}

			unset($attrGroups[$i]);
		}

		$node->attrGroups = array_values($attrGroups);

		return $node;
	}

}
