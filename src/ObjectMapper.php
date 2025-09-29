<?php declare(strict_types = 1);

namespace Shredio\ObjectMapper;

interface ObjectMapper
{

	/**
	 * @template TSource of object
	 * @template TTarget of object
	 * @param TSource $source
	 * @param class-string<TTarget>|TTarget $target
	 * @param array{ values?: array<non-empty-string, mixed>, valuesFn?: array<non-empty-string, callable(TSource $object): mixed>, allowNullableWithoutValue?: bool, converters?: list<array{class-string, callable(object): mixed}> } $options
	 * @return TTarget
	 */
	public function map(object $source, string|object $target, array $options = []): object;

	/**
	 * @template TSource of object
	 * @template TTarget of object
	 * @param iterable<TSource> $sources
	 * @param class-string<TTarget> $target
	 * @param array{ values?: array<non-empty-string, mixed>, valuesFn?: array<non-empty-string, callable(TSource $object): mixed>, allowNullableWithoutValue?: bool, converters?: list<array{class-string, callable(object): mixed}> } $options
	 * @return list<TTarget>
	 */
	public function mapMany(iterable $sources, string $target, array $options = []): array;

}
