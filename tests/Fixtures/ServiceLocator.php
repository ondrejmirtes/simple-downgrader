<?php declare(strict_types = 1);

namespace SimpleDowngrader\Fixtures;

class ServiceLocator
{

	public StreamOutputFactory $streamOutputFactory;

	/** @var StreamOutputFactory */
	public $untypedStreamOutputFactory; // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint

}
