<?php declare(strict_types = 1);

namespace Shredio\ObjectMapper;

use Shredio\ObjectMapper\Trait\DataTransferObjectToArrayMethod;

/**
 * @phpstan-import-type OptionsType from DataTransferObjectToArrayMethod
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
