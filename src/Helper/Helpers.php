<?php declare(strict_types = 1);

namespace Shredio\ObjectMapper\Helper;

final readonly class Helpers
{

	/**
	 * @param array<non-empty-string, mixed> $values
	 * @param list<non-empty-string>|null $omit
	 * @return array<non-empty-string, mixed>
	 */
	public static function omitProperties(array $values, ?array $omit): array
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
	public static function pickProperties(array $values, ?array $pick): array
	{
		if ($pick === null) {
			return $values;
		}

		return array_intersect_key($values, array_flip($pick));
	}

	/**
	 * @param array<non-empty-string, mixed> $values
	 * @param array<string, mixed> $options
	 *
	 * @return array<non-empty-string, mixed>
	 */
	public static function converters(array $values, array &$options, string $keyName): array
	{
		$converters = $options[$keyName] ?? [];
		if ($converters instanceof ConverterLookup) {
			$converterLookup = $converters;
		} else {
			$options[$keyName] = $converterLookup = new ConverterLookup($converters); // @phpstan-ignore argument.type
		}

		return $converterLookup->convertArray($values);
	}

}
