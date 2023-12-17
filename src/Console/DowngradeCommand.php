<?php declare(strict_types = 1);

namespace SimpleDowngrader\Console;

use Exception;
use Nette\Utils\Strings;
use PhpParser\Node\Stmt;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitor\CloningVisitor;
use PhpParser\Parser;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Printer\Printer;
use SimpleDowngrader\Php\FollowedByCommaAnalyser;
use SimpleDowngrader\Php\PhpPrinter;
use SimpleDowngrader\PhpDoc\PhpDocEditor;
use SimpleDowngrader\Visitor\DowngradeMixedTypeVisitor;
use SimpleDowngrader\Visitor\DowngradeNonCapturingCatchesVisitor;
use SimpleDowngrader\Visitor\DowngradePropertyPromotionVisitor;
use SimpleDowngrader\Visitor\DowngradePureIntersectionTypeVisitor;
use SimpleDowngrader\Visitor\DowngradeReadonlyPromotedPropertyVisitor;
use SimpleDowngrader\Visitor\DowngradeReadonlyPropertyVisitor;
use SimpleDowngrader\Visitor\DowngradeStaticReturnTypeVisitor;
use SimpleDowngrader\Visitor\DowngradeTrailingCommasInClosureUsesVisitor;
use SimpleDowngrader\Visitor\DowngradeTrailingCommasInParametersVisitor;
use SimpleDowngrader\Visitor\DowngradeUnionTypeVisitor;
use SimpleDowngrader\Visitor\TokensAwareVisitor;
use SimpleDowngrader\Visitor\TypeDowngraderHelper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use function array_map;
use function count;
use function explode;
use function file_get_contents;
use function file_put_contents;
use function fnmatch;
use function getcwd;
use function is_array;
use function is_dir;
use function is_file;
use function is_string;
use function preg_quote;
use function sprintf;
use function str_replace;

class DowngradeCommand extends Command
{

	private const STARTS_WITH_ASTERISK_REGEX = '#^\\*(.*?)[^*]$#';
	private const ENDS_WITH_ASTERISK_REGEX = '#^[^*](.*?)\\*$#';

	/** @var Parser */
	private $parser;

	/** @var PhpPrinter */
	private $printer;

	/** @var Lexer */
	private $phpDocLexer;

	/** @var PhpDocParser */
	private $phpDocParser;

	/** @var NodeTraverser */
	private $cloningTraverser;

	public function __construct(Parser $parser, PhpPrinter $printer, Lexer $phpDocLexer, PhpDocParser $phpDocParser)
	{
		parent::__construct();
		$this->parser = $parser;
		$this->printer = $printer;
		$this->phpDocLexer = $phpDocLexer;
		$this->phpDocParser = $phpDocParser;
		$this->cloningTraverser = new NodeTraverser();
		$this->cloningTraverser->addVisitor(new CloningVisitor());
	}

	protected function configure(): void
	{
		$this->setName('downgrade');
		$this->addOption('configuration', 'c', InputOption::VALUE_REQUIRED);
		$this->addArgument('php', InputArgument::REQUIRED);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$configuration = $input->getOption('configuration');
		if (!is_string($configuration)) {
			$output->writeln('Configuration not provided.');
			return 1;
		}

		$php = $input->getArgument('php');
		if (!is_string($php)) {
			$output->writeln('Wrong PHP version provided.');
			return 1;
		}

		$phpVersionId = $this->parsePhpVersion($php);
		$visitors = $this->createDowngradeVisitors($phpVersionId);

		$cwd = getcwd();
		if ($cwd === false) {
			$output->writeln('CWD not found.');
			return 1;
		}

		$configArray = require $cwd . '/' . $configuration;
		if (!is_array($configArray)) {
			$output->writeln('Invalid config.');
			return 1;
		}

		$paths = $configArray['paths'];
		$excludePaths = $configArray['excludePaths'];

		$files = $this->findFiles($paths, $excludePaths);
		foreach ($files as $file) {
			$this->processFile($file, $visitors);
		}

		return 0;
	}

	/**
	 * @param list<NodeVisitor> $visitors
	 */
	private function processFile(string $file, array $visitors): void
	{
		$contents = file_get_contents($file);
		if ($contents === false) {
			throw new Exception(sprintf('%s could not be read', $file));
		}

		/** @var Stmt[] $oldStmts */
		$oldStmts = $this->parser->parse($contents);
		$oldTokens = $this->parser->getTokens();

		/** @var Stmt[] $newStmts */
		$newStmts = $this->cloningTraverser->traverse($oldStmts);

		foreach ($visitors as $visitor) {
			$traverser = new NodeTraverser();
			$traverser->addVisitor($visitor);

			if ($visitor instanceof TokensAwareVisitor) {
				$visitor->setTokens($oldTokens);
			}

			/** @var Stmt[] $newStmts */
			$newStmts = $traverser->traverse($newStmts);
		}

		$newCode = $this->printer->printFormatPreserving($newStmts, $oldStmts, $oldTokens);
		$result = file_put_contents($file, $newCode);
		if ($result === false) {
			throw new Exception(sprintf('%s could not be written', $file));
		}
	}

	/**
	 * @return list<NodeVisitor>
	 */
	private function createDowngradeVisitors(int $phpVersionId): array
	{
		$phpDocPrinter = new Printer();
		$phpDocEditor = new PhpDocEditor($phpDocPrinter, $this->phpDocLexer, $this->phpDocParser);
		$typeDowngraderHelper = new TypeDowngraderHelper($phpDocEditor);
		$followedByCommaAnalyser = new FollowedByCommaAnalyser();
		$visitors = [];
		if ($phpVersionId < 80100) {
			$visitors[] = new DowngradeReadonlyPropertyVisitor($phpDocEditor);
			$visitors[] = new DowngradeReadonlyPromotedPropertyVisitor($phpDocEditor);
			$visitors[] = new DowngradePureIntersectionTypeVisitor($typeDowngraderHelper);
		}

		if ($phpVersionId < 80000) {
			$visitors[] = new DowngradeTrailingCommasInParametersVisitor($followedByCommaAnalyser);
			$visitors[] = new DowngradeTrailingCommasInClosureUsesVisitor($followedByCommaAnalyser);
			$visitors[] = new DowngradeNonCapturingCatchesVisitor();
			$visitors[] = new DowngradeUnionTypeVisitor($typeDowngraderHelper);
			$visitors[] = new DowngradePropertyPromotionVisitor(
				$this->phpDocLexer,
				$this->phpDocParser,
				$phpDocEditor
			);
			$visitors[] = new DowngradeMixedTypeVisitor($typeDowngraderHelper);
			$visitors[] = new DowngradeStaticReturnTypeVisitor($typeDowngraderHelper);
		}

		return $visitors;
	}

	/**
	 * @param list<string> $paths
	 * @param list<string> $skipPaths
	 * @return list<string>
	 */
	private function findFiles(array $paths, array $skipPaths): array
	{
		$finder = new Finder();
		$finder->followLinks();
		$finder->filter(function (SplFileInfo $splFileInfo) use ($skipPaths): bool {
			$realPath = $splFileInfo->getRealPath();
			if ($realPath === '') {
				// dead symlink
				return \false;
			}
			// make the path work accross different OSes
			$realPath = str_replace('\\', '/', $realPath);
			// return false to remove file
			foreach ($skipPaths as $excludePath) {
				// make the path work accross different OSes
				$excludePath = str_replace('\\', '/', $excludePath);
				if (Strings::match($realPath, '#' . preg_quote($excludePath, '#') . '#') !== null) {
					return \false;
				}
				$excludePath = $this->normalizeForFnmatch($excludePath);
				if (fnmatch($excludePath, $realPath)) {
					return \false;
				}
			}
			return \true;
		});
		$files = [];
		$directories = [];
		foreach ($paths as $path) {
			if (is_dir($path)) {
				$directories[] = $path;
			} elseif (is_file($path)) {
				$files[] = $path;
			} else {
				throw new Exception(sprintf('Path %s does not exist', $path));
			}
		}

		if (count($directories) > 0) {
			foreach ($finder->files()->name('*.php')->in($directories) as $fileInfo) {
				$files[] = $fileInfo->getRealPath();
			}
		}

		return $files;
	}

	private function normalizeForFnmatch(string $path): string
	{
		// ends with *
		if (Strings::match($path, self::ENDS_WITH_ASTERISK_REGEX) !== null) {
			return '*' . $path;
		}
		// starts with *
		if (Strings::match($path, self::STARTS_WITH_ASTERISK_REGEX) !== null) {
			return $path . '*';
		}
		return $path;
	}

	private function parsePhpVersion(string $version): int
	{
		$parts = array_map('intval', explode('.', $version));

		return $parts[0] * 10000 + $parts[1] * 100 + ($parts[2] ?? 0);
	}

}
