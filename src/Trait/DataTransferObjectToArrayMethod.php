<?php declare(strict_types = 1);

namespace Shredio\ObjectMapper\Trait;

use Shredio\ObjectMapper\ConvertableToArray;
use Shredio\ObjectMapper\Helper\Helpers;

/**
 * @phpstan-type ConverterCallback callable
 * @phpstan-type ConverterType array{ class-string, ConverterCallback }
 * @phpstan-type OptionsType array{ values?: array<non-empty-string, mixed>, omit?: list<non-empty-string>, pick?: list<non-empty-string>, deep?: bool, converters?: list<ConverterType> }
 */
trait DataTransferObjectToArrayMethod
{

	/**
	 * @param OptionsType $options
	 * @return array<non-empty-string, mixed>
	 */
	public function toArray(array $options = []): array
	{
		/** @var array<non-empty-string, mixed> $values */
		$values = get_object_vars($this);
		// 1. Omit and pick properties
		$values = Helpers::omitProperties($values, $options['omit'] ?? null);
		$values = Helpers::pickProperties($values, $options['pick'] ?? null);

		// 2. Remove static values from conversion
		//    (they will be added back later, after conversion)
		$staticValues = $options['values'] ?? [];
		foreach ($staticValues as $name => $_) {
			unset($values[$name]);
		}

		// 3. Convert values
		$values = Helpers::converters($values, $options, 'converters');
		/** @var OptionsType $options */ // Because of the phpstan type-hinting issue

		// 4. Deep conversion
		if (($options['deep'] ?? false) === true) {
			$newOptions = [
				'deep' => $options['deep'],
				'converters' => $options['converters'] ?? [],
			];
			foreach ($values as $name => $value) {
				if ($value instanceof ConvertableToArray) {
					$values[$name] = $value->toArray($newOptions);
				} elseif (is_array($value)) {
					$values[$name] = array_map(
						fn (mixed $item): mixed => $item instanceof ConvertableToArray ? $item->toArray($newOptions) : $item,
						$value,
					);
				}
			}
		}

		// 5. Add static values back
		foreach ($staticValues as $name => $value) {
			$values[$name] = $value;
		}

		return $values;
	}

	/**
	 * @param OptionsType $options
	 * @return array<non-empty-string, mixed>
	 */
	public function toArrayNoStrict(array $options = []): array
	{
		return $this->toArray($options);
	}

}
