<?php declare(strict_types = 1);

namespace Shredio\ObjectMapper\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class ToArraySkipProperties
{

	/**
	 * @param non-empty-list<literal-string> $properties
	 */
	public function __construct(
		public array $properties,
	)
	{
	}

}
