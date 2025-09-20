<?php declare(strict_types = 1);

namespace SimpleDowngrader\Fixtures;

class StreamOutput
{

	public const VERBOSITY_NORMAL = 1;

	private int $verbosity;

	private bool $decorated;

	public function __construct(int $verbosity = self::VERBOSITY_NORMAL, bool $decorated = false)
	{
		$this->decorated = $decorated;
		$this->verbosity = $verbosity;
	}

	public function isDecorated(): bool
	{
		return $this->decorated;
	}

	public function getVerbosity(): int
	{
		return $this->verbosity;
	}

}
