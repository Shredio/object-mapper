<?php declare(strict_types = 1);

namespace Shredio\ObjectMapper;

use ReflectionClass;
use ReflectionProperty;
use Shredio\ObjectMapper\Exception\LogicException;

/**
 * @phpstan-import-type OptionsType from ObjectMapper
 */
final readonly class DefaultObjectMapper implements ObjectMapper
{

	public function map(object $source, string|object $target, array $options = []): object
	{
		$sourceClassName = $source::class;
		/** @var array<non-empty-string, mixed> $values */
		$values = array_merge(get_object_vars($source), $options['values'] ?? []);

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

	/**
	 * @template T of object
	 * @param class-string $sourceClassName
	 * @param ReflectionClass<T> $reflectionClass
	 * @param array<non-empty-string, mixed> $values
	 * @param OptionsType $options
	 * @return T
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
