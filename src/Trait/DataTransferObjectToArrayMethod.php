<?php declare(strict_types = 1);

namespace Shredio\ObjectMapper\Trait;

use Shredio\ObjectMapper\ConvertableToArray;
use Shredio\ObjectMapper\Helper\ConverterLookup;

/**
 * @phpstan-type ConverterCallback callable(object $object): mixed
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
		$values = $this->omitProperties($values, $options['omit'] ?? null);
		$values = $this->pickProperties($values, $options['pick'] ?? null);

		// 2. Remove static values from conversion
		//    (they will be added back later, after conversion)
		$staticValues = $options['values'] ?? [];
		foreach ($staticValues as $name => $_) {
			unset($values[$name]);
		}

		$converters = $options['converters'] ?? [];
		if ($converters instanceof ConverterLookup) { // @phpstan-ignore instanceof.alwaysFalse (cache hack)
			$converterLookup = $converters;
		} else {
			$converterLookup = new ConverterLookup($converters);
		}

		// 3. Convert values
		$values = $converterLookup->convertArray($values);

		// 4. Deep conversion
		if (($options['deep'] ?? false) === true) {
			/** @var list<ConverterType> $cachedConverterLookup */
			$cachedConverterLookup = $converterLookup; // @phpstan-ignore varTag.nativeType (cache hack)
			$newOptions = [
				'deep' => $options['deep'],
				'converters' => $cachedConverterLookup,
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

	/**
	 * @param list<ConverterType> $converters
	 * @return array<class-string, callable(object $object): mixed>
	 */
	private function createLookupConverters(array $converters): array
	{
		$lookup = [];
		foreach ($converters as [$class, $converter]) {
			foreach (class_implements($class) as $interface) {
				$lookup[$interface] = $converter;
			}
			foreach (class_parents($class) as $parent) {
				$lookup[$parent] = $converter;
			}
			$lookup[$class] = $converter;
		}
		return $lookup;
	}

	/**
	 * @param array<non-empty-string, mixed> $values
	 * @param list<non-empty-string>|null $omit
	 * @return array<non-empty-string, mixed>
	 */
	private function omitProperties(array $values, ?array $omit): array
	{
		if ($omit === null) {
			return $values;
		}

		foreach ($omit as $name) {
			if (array_key_exists($name, $values)) {
				unset($values[$name]);
			}
		}

		return $values;
	}

	/**
	 * @param array<non-empty-string, mixed> $values
	 * @param list<non-empty-string>|null $pick
	 * @return array<non-empty-string, mixed>
	 */
	private function pickProperties(array $values, ?array $pick): array
	{
		if ($pick === null) {
			return $values;
		}

		return array_intersect_key($values, array_flip($pick));
	}

}
