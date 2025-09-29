<?php declare(strict_types = 1);

namespace Tests\Unit\Rule;

use DateTimeInterface;
use Shredio\ObjectMapper\DefaultObjectMapper;
use Shredio\ObjectMapper\ObjectMapper;
use stdClass;

final class ObjectMapperRuleTestCases
{

	public ObjectMapper $mapper;
	public DefaultObjectMapper $instance;

	/**
	 * @param class-string<Article|Post> $target
	 */
	public function unionClassStringTypeTarget(string $target): void
	{
		$this->mapper->map(new EmptyClass(), $target);
	}

	public function unionObjectTypesTarget(Article|Post $target): void
	{
		$this->mapper->map(new EmptyClass(), $target);
	}

	public function unionObjectTypesTargetForInstanceMapper(Article|Post $target): void
	{
		$this->instance->map(new EmptyClass(), $target);
	}

	/**
	 * @param array<non-empty-string, mixed> $options
	 */
	public function nonConstantArrayForOptions(array $options): void
	{
		$this->mapper->map(new EmptyClass(), Article::class, $options);
	}

	/**
	 * @param class-string<Article|Post> $target
	 */
	public function unionClassStringTypeTargetForInstanceMapper(string $target): void
	{
		$this->instance->map(new EmptyClass(), $target);
	}

	public function unionObjectTypesSource(UnionTypeValidSource|UnionTypeInvalidSource $source): void
	{
		$this->mapper->map($source, UnionTypeTarget::class);
	}

	public function missingProperty(): void
	{
		$this->mapper->map(new Article(), ArticleExtraRequireProperty::class);
	}

	public function wrongType(): void
	{
		$this->mapper->map(new Article(), ArticleWrongType::class);
	}

	public function wrongValueType(): void
	{
		$this->mapper->map(new Article(), Article::class, [
			'values' => [
				'id' => 'string-instead-of-int',
			],
		]);
	}

	public function extraValue(): void
	{
		$this->mapper->map(new Article(), Article::class, [
			'values' => [
				'extra' => 'extra-value',
			],
		]);
	}

	public function invalidUnion(): void
	{
		$this->mapper->map(new UnionTypeInvalidSource(), UnionTypeTarget::class);
	}

	public function wrongValueTypeForProperty(): void
	{
		$this->mapper->map(new SinglePropertyClass(), SinglePropertyClass::class, [
			'values' => [
				'value' => 'string-instead-of-int',
			],
		]);
	}

	public function invalidAllowNullableWithoutValue(): void
	{
		$this->mapper->map(new DefaultValueTarget(), StrictNullableTarget::class, [
			'allowNullableWithoutValue' => false,
		]);
	}

	public function nonConstantBooleanForAllowNullableWithoutValue(bool $allow): void
	{
		$this->mapper->map(new DefaultValueTarget(), StrictNullableTarget::class, [
			'allowNullableWithoutValue' => $allow,
		]);
	}

	public function wrongTypeFromMany(): void
	{
		$this->mapper->mapMany([new Article()], ArticleWrongType::class);
	}

	public function unionObjectTypesSourceForMany(UnionTypeValidSource|UnionTypeInvalidSource $source): void
	{
		$this->mapper->mapMany([$source], UnionTypeTarget::class);
	}

	public function wrongValueTypeFromCallbackForProperty(): void
	{
		$this->mapper->map(new SinglePropertyClass(), SinglePropertyClass::class, [
			'valuesFn' => [
				'value' => fn () => 'string-instead-of-int',
			],
		]);
	}

	public function wrongValueUnionTypeFromCallbackForProperty(): void
	{
		if (mt_rand(0, 1) === 1) {
			$callback = fn () => 'string-instead-of-int';
		} else {
			$callback = fn () => 15;
		}

		$this->mapper->map(new SinglePropertyClass(), SinglePropertyClass::class, [
			'valuesFn' => [
				'value' => $callback,
			],
		]);
	}

	public function invalidConverter(): void
	{
		$this->mapper->map(new DateTimeProperty(), IntProperty::class, [
			'converters' => [
				[DateTimeInterface::class, fn (DateTimeInterface $date) => $date->format('Y-m-d')]
			],
		]);
	}

	public function invalidUnionTypesFromConverter(): void
	{
		$this->mapper->map(new ComplexDateTimeProperty(), StringProperty::class, [
			'converters' => [
				[DateTimeInterface::class, fn (DateTimeInterface $date) => $date->format('Y-m-d')]
			],
		]);
	}

	public function invalidCallableType(): void
	{
		$this->mapper->map(new StringProperty(), StringProperty::class, [
			'converters' => [
				[DateTimeInterface::class, true]
			],
		]);
	}

	// valid cases

	public function validUnionObjectTypesTarget(Article|Post $target): void
	{
		if ($target instanceof Article) {
			$this->mapper->map(new Article(), $target);
		} else {
			$this->mapper->map(new Post(), $target);
		}
	}

	public function validUnionProperty(): void
	{
		$this->mapper->map(new UnionTypeValidSource(), UnionTypeTarget::class);
	}

	public function optionalConstructorValue(): void
	{
		$this->mapper->map(new EmptyClass(), SingleConstructorParameterClass::class);
	}

	public function optionalPropertyValue(): void
	{
		$this->mapper->map(new EmptyClass(), SinglePropertyClass::class);
	}

	/**
	 * @param iterable<EmptyClass> $values
	 */
	public function mapManyFromIterableParameter(iterable $values): void
	{
		$this->mapper->mapMany($values, SinglePropertyClass::class);
	}

	/**
	 * @param array<EmptyClass> $values
	 */
	public function mapManyFromArrayParameter(array $values): void
	{
		$this->mapper->mapMany($values, SinglePropertyClass::class);
	}

	public function optionalPropertyValueMany(): void
	{
		$this->mapper->mapMany([new EmptyClass(), new EmptyClass()], SinglePropertyClass::class);
	}

	public function validAllowNullableWithoutValue(): void
	{
		$this->mapper->map(new DefaultValueTarget(), StrictNullableTarget::class, [
			'allowNullableWithoutValue' => true,
		]);
	}

	public function validValueTypeForProperty(): void
	{
		$this->mapper->map(new SinglePropertyClass(), SinglePropertyClass::class, [
			'values' => [
				'value' => 15,
			],
		]);
	}

	public function validValueTypeFromCallbackForProperty(): void
	{
		$this->mapper->map(new SinglePropertyClass(), SinglePropertyClass::class, [
			'valuesFn' => [
				'value' => fn () => 15,
			],
		]);
	}

	public function validConverter(): void
	{
		$this->mapper->map(new DateTimeProperty(), StringProperty::class, [
			'converters' => [
				[DateTimeInterface::class, fn (DateTimeInterface $date) => $date->format('Y-m-d')]
			],
		]);
	}

	public function validNullableConverter(): void
	{
		$this->mapper->map(new DateTimeOrNullProperty(), StringOrNullProperty::class, [
			'converters' => [
				[DateTimeInterface::class, fn (DateTimeInterface $date) => $date->format('Y-m-d')]
			],
		]);
	}

	public function validNullableWithUselessConverter(): void
	{
		$this->mapper->map(new StdClassOrNullProperty(), StdClassOrNullProperty::class, [
			'converters' => [
				[DateTimeInterface::class, fn (DateTimeInterface $date) => $date->format('Y-m-d')]
			],
		]);
	}

}

class EmptyClass {}

class SingleConstructorParameterClass {
	public function __construct(
		public int $value = 42,
	)
	{
	}
}

class SinglePropertyClass {
	public int $value = 42;
}

class StringProperty {
	public string $value = 'default';
}

class StringOrNullProperty {
	public ?string $value = 'default';
}

class DateTimeProperty {
	public DateTimeInterface $value;
}

class ComplexDateTimeProperty {
	public DateTimeInterface|stdClass|null $value;
}

class DateTimeOrNullProperty {
	public ?DateTimeInterface $value;
}

class StdClassOrNullProperty {
	public ?stdClass $value;
}

class IntProperty {
	public int $value = 42;
}

class UnionTypeTarget {
	public function __construct(
		public int|float|bool|string $value = 42,
	)
	{
	}
}

class UnionTypeInvalidSource {
	public function __construct(
		public int|float|bool|string|DateTimeInterface|stdClass $value = new stdClass(),
	)
	{
	}
}

class UnionTypeValidSource {
	public function __construct(
		public int|float $value = 12,
	)
	{
	}
}

class Article {

	public function __construct(
		public int $id = 1,
		public string $title = 'Test Article',
		public string $content = 'This is the content of the article.',
		public ?string $image = null,
		public \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
		public bool $isPublished = false,
		public array $tags = [],
	)
	{
	}

}

class ArticleWrongType {

	public function __construct(
		public int $id = 1,
		public int $title = 2,
		public string $content = 'This is the content of the article.',
		public ?string $image = null,
		public \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
		public bool $isPublished = false,
		public array $tags = [],
	)
	{
	}

}

class ArticleExtraRequireProperty {

	public function __construct(
		public string $required,
		public int $id = 1,
		public string $title = 'Test Article',
		public string $content = 'This is the content of the article.',
		public ?string $image = null,
		public \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
		public bool $isPublished = false,
		public array $tags = [],
	)
	{
	}

}

class Post {

	public function __construct(
		public int $id = 1,
		public string $content = 'This is the content of the post.',
		public \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
		public bool $isPublished = false,
	)
	{
	}
}

final class DefaultValueTarget
{
	public string $name;
	public readonly int $age;

	public function __construct(string $name = 'name', int $age = 0)
	{
		$this->name = $name;
		$this->age = $age;
	}
}

final class NullableTarget
{
	public string $name;
	public readonly ?string $description;

	public function __construct(string $name, ?string $description = null)
	{
		$this->name = $name;
		$this->description = $description;
	}
}

final class StrictNullableTarget
{
	public string $name;
	public readonly ?string $description;

	public function __construct(string $name, ?string $description)
	{
		$this->name = $name;
		$this->description = $description;
	}
}
