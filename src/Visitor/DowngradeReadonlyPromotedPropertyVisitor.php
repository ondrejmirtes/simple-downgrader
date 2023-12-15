<?php declare(strict_types = 1);

namespace SimpleDowngrader\Visitor;

use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeVisitorAbstract;
use PHPStan\PhpDocParser\Ast\PhpDoc\GenericTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use SimpleDowngrader\PhpDoc\PhpDocEditor;

class DowngradeReadonlyPromotedPropertyVisitor extends NodeVisitorAbstract
{

	/** @var PhpDocEditor */
	private $phpDocEditor;

	public function __construct(PhpDocEditor $phpDocEditor)
	{
		$this->phpDocEditor = $phpDocEditor;
	}

	public function enterNode(Node $node)
	{
		if (!$node instanceof Node\Param) {
			return null;
		}

		if ($node->flags === 0) {
			return null;
		}

		$isReadonly = (bool) ($node->flags & Class_::MODIFIER_READONLY);
		if (!$isReadonly) {
			return null;
		}

		$node->flags &= ~Node\Stmt\Class_::MODIFIER_READONLY;
		$docComment = $node->getDocComment();
		$phpDoc = '/** */';
		if ($docComment !== null) {
			$phpDoc = $docComment->getText();
		}

		$node->setDocComment(new Doc(
			$this->phpDocEditor->edit($phpDoc, static function (\PHPStan\PhpDocParser\Ast\Node $node) {
				if (!$node instanceof PhpDocNode) {
					return null;
				}

				$node->children[] = new PhpDocTagNode('@readonly', new GenericTagValueNode(''));

				return $node;
			})
		));

		return $node;
	}

}