<?php declare(strict_types = 1);

namespace SimpleDowngrader\Fixtures;

class StreamOutputFactory
{

	public function create(int $verbosity = StreamOutput::VERBOSITY_NORMAL, bool $decorated = false): StreamOutput
	{
		return new StreamOutput($verbosity, $decorated);
	}

}
