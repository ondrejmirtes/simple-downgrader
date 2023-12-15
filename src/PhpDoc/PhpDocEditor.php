<?php declare(strict_types = 1);

namespace SimpleDowngrader\PhpDoc;

use PhpParser\Comment\Doc;
use PHPStan\PhpDocParser\Ast\Node;
use PHPStan\PhpDocParser\Ast\NodeTraverser;
use PHPStan\PhpDocParser\Ast\NodeVisitor\CloningVisitor;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\PhpDocParser\Printer\Printer;
use function count;

class PhpDocEditor
{

	/** @var Lexer */
	private $lexer;

	/** @var PhpDocParser */
	private $phpDocParser;

	/** @var Printer */
	private $printer;

	public function __construct(Printer $printer)
	{
		$this->lexer = new Lexer(true);

		$usedAttributes = ['lines' => true, 'indexes' => true];
		$constExprParser = new ConstExprParser(true, true, $usedAttributes);
		$typeParser = new TypeParser($constExprParser, true, $usedAttributes);
		$this->phpDocParser = new PhpDocParser($typeParser, $constExprParser, true, true, $usedAttributes, true, true);
		$this->printer = $printer;
	}

	/**
	 * @param callable(Node): mixed $callback
	 */
	public function edit(\PhpParser\Node $node, callable $callback): void
	{
		$doc = $node->getDocComment();
		if ($doc === null) {
			$phpDoc = '/** */';
		} else {
			$phpDoc = $doc->getText();
		}
		$tokens = new TokenIterator($this->lexer->tokenize($phpDoc));
		$phpDocNode = $this->phpDocParser->parse($tokens);

		$cloningTraverser = new NodeTraverser([new CloningVisitor()]);

		/** @var PhpDocNode $newPhpDocNode */
		[$newPhpDocNode] = $cloningTraverser->traverse([$phpDocNode]);

		$traverser = new NodeTraverser([new CallbackVisitor($callback)]);

		/** @var PhpDocNode $newPhpDocNode */
		[$newPhpDocNode] = $traverser->traverse([$newPhpDocNode]);

		if (count($newPhpDocNode->children) === 0) {
			$node->setAttribute('comments', []);
			return;
		}

		$doc = new Doc($this->printer->printFormatPreserving($newPhpDocNode, $phpDocNode, $tokens));
		$node->setDocComment($doc);
	}

}
