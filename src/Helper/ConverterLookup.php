<?php declare(strict_types = 1);

namespace Shredio\ObjectMapper\Helper;

use InvalidArgumentException;
use Shredio\ObjectMapper\Trait\DataTransferObjectToArrayMethod;

/**
 * @phpstan-import-type ConverterType from DataTransferObjectToArrayMethod
 * @phpstan-import-type ConverterCallback from DataTransferObjectToArrayMethod
 */
final readonly class ConverterLookup
{

	/** @var array<class-string, ConverterCallback> */
	private array $lookup;

	/**
	 * @param array<ConverterType> $converters
	 */
	public function __construct(array $converters)
	{
		$lookup = [];
		foreach ($converters as [$class, $converter]) {
			if (isset($lookup[$class])) {
				throw new InvalidArgumentException(sprintf('Converter for class %s is already defined.', $class));
			}

			$lookup[$class] = $converter;
		}
		$this->lookup = $lookup;
	}

	public function tryToConvert(mixed $value): mixed
	{
		if ($this->lookup === []) {
			return $value;
		}
		if (!is_object($value)) {
			return $value;
		}

		foreach ($this->lookup as $class => $converter) {
			if ($value instanceof $class) {
				return $converter($value); // @phpstan-ignore callable.nonCallable
			}
		}
		return $value;
	}

	/**
	 * @param array<non-empty-string, mixed> $values
	 * @return array<non-empty-string, mixed>
	 */
	public function convertArray(array $values): array
	{
		if ($this->lookup === []) {
			return $values;
		}

		foreach ($values as $name => $value) {
			if (is_array($value)) {
				$values[$name] = array_map(fn (mixed $item): mixed => $this->tryToConvert($item), $value);
			} else {
				$values[$name] = $this->tryToConvert($value);
			}
		}
		return $values;
	}

}
