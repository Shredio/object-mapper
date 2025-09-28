<?php declare(strict_types = 1);

namespace Shredio\ObjectMapper;

use Shredio\ObjectMapper\Trait\DataTransferObjectMethods;

/**
 * @phpstan-import-type OptionsType from DataTransferObjectMethods
 * @internal
 */
interface ConvertableToArray
{

	/**
	 * @param OptionsType $options
	 * @return array<non-empty-string, mixed>
	 */
	public function toArray(array $options = []): array;

}
