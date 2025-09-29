<?php declare(strict_types = 1);

namespace Shredio\ObjectMapper\PhpStan;

use PHPStan\Type\CompoundType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\TypeUtils;
use PHPStan\Type\UnionType;

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
			if (!$type instanceof CompoundType) {
				if ($converter->acceptType->accepts($type, true)->yes()) {
					return $converter->returnType;
				}

				continue;
			}
			$newType = $type->tryRemove($converter->acceptType);
			if ($newType === null) {
				continue;
			}

			return TypeCombinator::union($newType, $converter->returnType);
		}

		return $type;
	}

}
