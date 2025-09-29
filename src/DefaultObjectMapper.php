<?php declare(strict_types = 1);

namespace Shredio\ObjectMapper;

use ReflectionClass;
use ReflectionProperty;
use Shredio\ObjectMapper\Exception\LogicException;

final readonly class DefaultObjectMapper implements ObjectMapper
{

	/**
	 * @param array{ values?: array<non-empty-string, mixed>, valuesFn?: array<non-empty-string, callable(object $source): mixed>, allowNullableWithoutValue?: bool } $options
	 */
	public function map(object $source, string|object $target, array $options = []): object
	{
		$sourceClassName = $source::class;

		/** @var array<non-empty-string, mixed> $values */
		$values = get_object_vars($source);

		if (isset($options['valuesFn'])) {
			foreach ($options['valuesFn'] as $name => $callback) {
				$values[$name] = $callback($source);
			}
		}

		$values = array_merge($values, $options['values'] ?? []);

		$reflectionClass = new ReflectionClass($target);
		if (is_string($target)) {
			$object = $this->createInstance($sourceClassName, $reflectionClass, $values, $options);
		} else {
			$object = $target;
		}

		foreach ($values as $name => $value) {
			if (!$reflectionClass->hasProperty($name)) {
				continue;
			}

			$reflectionProperty = $reflectionClass->getProperty($name);
			if (!$this->isWritableFromOutside($reflectionProperty)) {
				continue;
			}

			$object->$name = $value;
		}

		return $object;
	}

	public function mapMany(iterable $sources, string $target, array $options = []): array
	{
		$result = [];
		foreach ($sources as $source) {
			$result[] = $this->map($source, $target, $options); // @phpstan-ignore argument.type
		}

		return $result;
	}

	/**
	 * @template TSource of object
	 * @template TTarget of object
	 * @param class-string<TSource> $sourceClassName
	 * @param ReflectionClass<TTarget> $reflectionClass
	 * @param array<non-empty-string, mixed> $values
	 * @param array{ values?: array<non-empty-string, mixed>, valuesFn?: array<non-empty-string, callable(TSource $object): mixed>, allowNullableWithoutValue?: bool } $options
	 * @return TTarget
	 */
	private function createInstance(string $sourceClassName, ReflectionClass $reflectionClass, array &$values, array $options = []): object
	{
		$constructor = $reflectionClass->getConstructor();
		if ($constructor === null) {
			return $reflectionClass->newInstance();
		}

		$allowNullableWithoutValue = ($options['allowNullableWithoutValue'] ?? false) === true;
		$parameters = $constructor->getParameters();
		$args = [];
		foreach ($parameters as $parameter) {
			$name = $parameter->getName();
			if (array_key_exists($name, $values)) {
				$args[] = $values[$name];
				unset($values[$name]);
				continue;
			}
			if ($parameter->isDefaultValueAvailable()) {
				$args[] = $parameter->getDefaultValue();
				continue;
			}
			if ($allowNullableWithoutValue && $parameter->allowsNull()) {
				$args[] = null;
				continue;
			}

			throw new LogicException(sprintf(
				'Cannot map object of class %s to %s: missing value for constructor parameter $%s.',
				$sourceClassName,
				$reflectionClass->getName(),
				$name,
			));
		}

		return $reflectionClass->newInstanceArgs($args);
	}

	private function isWritableFromOutside(ReflectionProperty $property): bool
	{
		if ($property->isReadOnly()) {
			return false;
		}
		if (PHP_VERSION_ID >= 80400) {
			if ($property->hasHooks()) {
				return $property->hasHook(\PropertyHookType::Set);
			}
			if ($property->isProtectedSet() || $property->isPrivateSet()) {
				return false;
			}
		}

		return $property->isPublic();
	}

}
