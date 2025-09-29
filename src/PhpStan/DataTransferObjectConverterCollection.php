<?php declare(strict_types = 1);

namespace Shredio\ObjectMapper\PhpStan;

use PHPStan\Type\Type;

final readonly class DataTransferObjectConverterCollection
{

	/**
	 * @param list<DataTransferObjectConverter> $converters
	 */
	public function __construct(
		private array $converters = [],
	)
	{
	}

	public function getRealType(Type $type): Type
	{
		foreach ($this->converters as $converter) {
			if ($converter->acceptType->accepts($type, true)->yes()) {
				return $converter->returnType;
			}
		}

		return $type;
	}

}
