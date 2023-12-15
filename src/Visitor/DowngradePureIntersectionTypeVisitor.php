<?php declare(strict_types = 1);

namespace SimpleDowngrader\Visitor;

use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use SimpleDowngrader\PhpDoc\PhpDocEditor;

class DowngradePureIntersectionTypeVisitor extends NodeVisitorAbstract
{

	/** @var PhpDocEditor */
	private $phpDocEditor;

	public function __construct(PhpDocEditor $phpDocEditor)
	{
		$this->phpDocEditor = $phpDocEditor;
	}

	public function enterNode(Node $node)
	{
		if ($node instanceof Node\Stmt\Property && $node->type instanceof Node\IntersectionType) {
			$phpDoc = '/** */';
			if ($node->getDocComment() !== null) {
				$phpDoc = $node->getDocComment()->getText();
			}

			$node->type = null;
			$node->setDocComment(new Doc(
				$this->phpDocEditor->edit($phpDoc, function ($node) {

				})
			));
		}
	}

}
