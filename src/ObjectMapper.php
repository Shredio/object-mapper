<?php declare(strict_types = 1);

namespace Shredio\ObjectMapper;

/**
 * @phpstan-type OptionsType array{ values?: array<non-empty-string, mixed>, allowNullableWithoutValue?: bool }
 */
interface ObjectMapper
{

	/**
	 * @template T of object
	 * @param class-string<T>|T $target
	 * @param OptionsType $options
	 * @return T
	 */
	public function map(object $source, string|object $target, array $options = []): object;

}
