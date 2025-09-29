<?php declare(strict_types = 1);

namespace Shredio\ObjectMapper\PhpStan;

use PHPStan\Type\CompoundType;
use PHPStan\Type\NeverType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

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
		if ($type instanceof NeverType) {
			return $type;
		}

		foreach ($this->converters as $converter) {
			if (!$type instanceof CompoundType) {
				if ($converter->acceptType->accepts($type, true)->yes()) {
					return $converter->returnType;
				}

				continue;
			}
			$newType = TypeCombinator::remove($type, $converter->acceptType);
			if ($newType instanceof NeverType) {
				return $converter->returnType;
			}
			if ($newType->equals($type)) {
				continue;
			}

			return TypeCombinator::union($newType, $converter->returnType);
		}

		return $type;
	}

}
