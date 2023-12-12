<?php declare(strict_types = 1);

namespace SimpleDowngrader\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DowngradeCommand extends Command
{

	protected function configure(): void
	{
		$this->setName('downgrade');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		return 0;
	}

}
