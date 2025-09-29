<?php declare(strict_types = 1);

namespace Tests;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Shredio\ObjectMapper\DefaultObjectMapper;
use Shredio\ObjectMapper\Exception\LogicException;

final class DefaultObjectMapperTest extends TestCase
{

	private DefaultObjectMapper $mapper;

	protected function setUp(): void
	{
		$this->mapper = new DefaultObjectMapper();
	}

	public function testMapSimpleObjectToClassString(): void
	{
		$source = new SimpleSource();
		$source->name = 'John';
		$source->age = 30;

		$result = $this->mapper->map($source, SimpleTarget::class);

		$this->assertInstanceOf(SimpleTarget::class, $result);
		$this->assertSame('John', $result->name);
		$this->assertSame(30, $result->age);
	}

	public function testMapSimpleObjectToExistingInstance(): void
	{
		$source = new SimpleSource();
		$source->name = 'Jane';
		$source->age = 25;

		$target = new SimpleTarget();
		$target->name = 'Original';
		$target->age = 99;

		$result = $this->mapper->map($source, $target);

		$this->assertSame($target, $result);
		$this->assertSame('Jane', $result->name);
		$this->assertSame(25, $result->age);
	}

	public function testMapWithAdditionalValuesFromOptions(): void
	{
		$source = new SimpleSource();
		$source->name = 'Bob';

		$result = $this->mapper->map($source, SimpleTarget::class, [
			'values' => ['age' => 40]
		]);

		$this->assertSame('Bob', $result->name);
		$this->assertSame(40, $result->age);
	}

	public function testOptionsValuesOverrideSourceProperties(): void
	{
		$source = new SimpleSource();
		$source->name = 'Original';
		$source->age = 30;

		$result = $this->mapper->map($source, SimpleTarget::class, [
			'values' => ['name' => 'Override']
		]);

		$this->assertSame('Override', $result->name);
		$this->assertSame(30, $result->age);
	}

	public function testMapToObjectWithConstructorParameters(): void
	{
		$source = new SimpleSource();
		$source->name = 'Alice';
		$source->id = 123;

		$result = $this->mapper->map($source, ConstructorTarget::class);

		$this->assertInstanceOf(ConstructorTarget::class, $result);
		$this->assertSame(123, $result->id);
		$this->assertSame('Alice', $result->name);
	}

	public function testMapToObjectWithDefaultConstructorParameters(): void
	{
		$source = new SimpleSource();
		$source->name = 'Charlie';

		$result = $this->mapper->map($source, DefaultValueTarget::class);

		$this->assertInstanceOf(DefaultValueTarget::class, $result);
		$this->assertSame('Charlie', $result->name);
		$this->assertSame(0, $result->age);
	}

	public function testMapToObjectWithNullableConstructorParameters(): void
	{
		$source = new SimpleSource();
		$source->name = 'Dave';

		$result = $this->mapper->map($source, NullableTarget::class);

		$this->assertInstanceOf(NullableTarget::class, $result);
		$this->assertSame('Dave', $result->name);
		$this->assertNull($result->description);
	}

	public function testMapToObjectWithNullableConstructorParameterWithoutDefaultThrowsException(): void
	{
		$source = new SimpleSource();
		$source->name = 'Dave';

		$this->expectException(LogicException::class);
		$this->expectExceptionMessage('Cannot map object of class Tests\SimpleSource to Tests\StrictNullableTarget: missing value for constructor parameter $description.');

		$this->mapper->map($source, StrictNullableTarget::class);
	}

	public function testMapToObjectWithNullableConstructorParameterWhenAllowed(): void
	{
		$source = new SimpleSource();
		$source->name = 'Dave';

		$result = $this->mapper->map($source, StrictNullableTarget::class, [
			'allowNullableWithoutValue' => true
		]);

		$this->assertInstanceOf(StrictNullableTarget::class, $result);
		$this->assertSame('Dave', $result->name);
		$this->assertNull($result->description);
	}

	public function testMissingRequiredConstructorParameterThrowsException(): void
	{
		$source = new SimpleSource();
		$source->name = 'Eve';

		$this->expectException(LogicException::class);
		$this->expectExceptionMessage('Cannot map object of class Tests\SimpleSource to Tests\ConstructorTarget: missing value for constructor parameter $id.');

		$this->mapper->map($source, ConstructorTarget::class);
	}

	public function testMapIgnoresNonExistentProperties(): void
	{
		$source = new SourceWithExtraProperties();
		$source->name = 'Frank';
		$source->age = 35;
		$source->extraProperty = 'ignored';

		$result = $this->mapper->map($source, SimpleTarget::class);

		$this->assertSame('Frank', $result->name);
		$this->assertSame(35, $result->age);
	}

	public function testMapSkipsReadOnlyProperties(): void
	{
		$source = new SimpleSource();
		$source->name = 'Grace';
		$source->id = 456;

		$target = new ReadOnlyTarget(999, 'Original');
		$result = $this->mapper->map($source, $target);

		$this->assertSame($target, $result);
		$this->assertSame(999, $result->id);
		$this->assertSame('Original', $result->name);
	}

	public function testMapSkipsPrivateAndProtectedProperties(): void
	{
		$source = new SourceWithAllProperties();
		$source->publicProperty = 'public value';
		$source->protectedProperty = 'protected value';
		$source->privateProperty = 'private value';

		$result = $this->mapper->map($source, MixedVisibilityTarget::class);

		$this->assertSame('public value', $result->publicProperty);
		$this->assertNull($result->getProtectedProperty());
		$this->assertNull($result->getPrivateProperty());
	}

	public function testMapEmptyObject(): void
	{
		$source = new EmptySource();
		$result = $this->mapper->map($source, NoConstructorTarget::class);

		$this->assertInstanceOf(NoConstructorTarget::class, $result);
		$this->assertNull($result->optionalProperty);
	}

	public function testMapWithComplexScenario(): void
	{
		$source = new ComplexSource();
		$source->name = 'Complex';
		$source->value = 100;
		$source->extraData = 'should be ignored';

		$result = $this->mapper->map($source, ComplexTarget::class, [
			'values' => [
				'description' => 'Added via options',
				'value' => 200
			]
		]);

		$this->assertSame('Complex', $result->name);
		$this->assertSame(200, $result->value);
		$this->assertSame('Added via options', $result->description);
	}

	public function testMapManyWithSimpleObjects(): void
	{
		$source1 = new SimpleSource();
		$source1->name = 'John';
		$source1->age = 30;

		$source2 = new SimpleSource();
		$source2->name = 'Jane';
		$source2->age = 25;

		$sources = [$source1, $source2];
		$results = $this->mapper->mapMany($sources, SimpleTarget::class);

		$this->assertCount(2, $results);
		$this->assertInstanceOf(SimpleTarget::class, $results[0]);
		$this->assertInstanceOf(SimpleTarget::class, $results[1]);
		$this->assertSame('John', $results[0]->name);
		$this->assertSame(30, $results[0]->age);
		$this->assertSame('Jane', $results[1]->name);
		$this->assertSame(25, $results[1]->age);
	}

	public function testMapManyWithEmptyArray(): void
	{
		$results = $this->mapper->mapMany([], SimpleTarget::class);

		$this->assertSame([], $results);
	}

	public function testMapManyWithOptions(): void
	{
		$source1 = new SimpleSource();
		$source1->name = 'Alice';

		$source2 = new SimpleSource();
		$source2->name = 'Bob';

		$sources = [$source1, $source2];
		$results = $this->mapper->mapMany($sources, SimpleTarget::class, [
			'values' => ['age' => 40]
		]);

		$this->assertCount(2, $results);
		$this->assertSame('Alice', $results[0]->name);
		$this->assertSame(40, $results[0]->age);
		$this->assertSame('Bob', $results[1]->name);
		$this->assertSame(40, $results[1]->age);
	}

	public function testMapManyWithIterator(): void
	{
		$source1 = new SimpleSource();
		$source1->name = 'First';
		$source1->age = 10;

		$source2 = new SimpleSource();
		$source2->name = 'Second';
		$source2->age = 20;

		$iterator = new \ArrayIterator([$source1, $source2]);
		$results = $this->mapper->mapMany($iterator, SimpleTarget::class);

		$this->assertCount(2, $results);
		$this->assertSame('First', $results[0]->name);
		$this->assertSame(10, $results[0]->age);
		$this->assertSame('Second', $results[1]->name);
		$this->assertSame(20, $results[1]->age);
	}

	public function testMapWithValuesFnBasic(): void
	{
		$source = new SimpleSource();
		$source->name = 'John';

		$result = $this->mapper->map($source, SimpleTarget::class, [
			'valuesFn' => [
				'age' => fn(object $source): int => 25
			]
		]);

		$this->assertSame('John', $result->name);
		$this->assertSame(25, $result->age);
	}

	public function testMapWithValuesFnAccessingSourceProperties(): void
	{
		$source = new SimpleSource();
		$source->name = 'Alice';
		$source->age = 30;

		$result = $this->mapper->map($source, SimpleTarget::class, [
			'valuesFn' => [
				'name' => fn(object $source): string => strtoupper($source->name),
				'age' => fn(object $source): int => $source->age * 2
			]
		]);

		$this->assertSame('ALICE', $result->name);
		$this->assertSame(60, $result->age);
	}

	public function testMapWithValuesFnAndRegularValues(): void
	{
		$source = new SimpleSource();
		$source->name = 'Bob';
		$source->age = 25;

		$result = $this->mapper->map($source, SimpleTarget::class, [
			'valuesFn' => [
				'name' => fn(object $source): string => 'From Function'
			],
			'values' => [
				'name' => 'From Values',
				'age' => 99
			]
		]);

		$this->assertSame('From Values', $result->name);
		$this->assertSame(99, $result->age);
	}

	public function testMapWithValuesFnOverridingSourceProperties(): void
	{
		$source = new SimpleSource();
		$source->name = 'Charlie';
		$source->age = 35;

		$result = $this->mapper->map($source, SimpleTarget::class, [
			'valuesFn' => [
				'age' => fn(object $source): int => 100
			]
		]);

		$this->assertSame('Charlie', $result->name);
		$this->assertSame(100, $result->age);
	}

	public function testMapWithValuesFnForConstructorParameters(): void
	{
		$source = new SimpleSource();
		$source->name = 'David';

		$result = $this->mapper->map($source, ConstructorTarget::class, [
			'valuesFn' => [
				'id' => fn(object $source): int => 777
			]
		]);

		$this->assertInstanceOf(ConstructorTarget::class, $result);
		$this->assertSame(777, $result->id);
		$this->assertSame('David', $result->name);
	}

	public function testMapWithValuesFnComplexScenario(): void
	{
		$source = new ComplexSource();
		$source->name = 'Eve';
		$source->value = 50;
		$source->extraData = 'ignored';

		$result = $this->mapper->map($source, ComplexTarget::class, [
			'valuesFn' => [
				'description' => fn(object $source): string => sprintf('Generated for %s', $source->name),
				'value' => fn(object $source): int => $source->value + 10
			],
			'values' => [
				'value' => 200
			]
		]);

		$this->assertSame('Eve', $result->name);
		$this->assertSame(200, $result->value);
		$this->assertSame('Generated for Eve', $result->description);
	}

	public function testMapManyWithValuesFn(): void
	{
		$source1 = new SimpleSource();
		$source1->name = 'First';
		$source1->age = 10;

		$source2 = new SimpleSource();
		$source2->name = 'Second';
		$source2->age = 20;

		$sources = [$source1, $source2];
		$results = $this->mapper->mapMany($sources, SimpleTarget::class, [
			'valuesFn' => [
				'age' => fn(object $source): int => $source->age * 3
			]
		]);

		$this->assertCount(2, $results);
		$this->assertSame('First', $results[0]->name);
		$this->assertSame(30, $results[0]->age);
		$this->assertSame('Second', $results[1]->name);
		$this->assertSame(60, $results[1]->age);
	}

	public function testMapWithEmptyValuesFn(): void
	{
		$source = new SimpleSource();
		$source->name = 'Frank';
		$source->age = 40;

		$result = $this->mapper->map($source, SimpleTarget::class, [
			'valuesFn' => []
		]);

		$this->assertSame('Frank', $result->name);
		$this->assertSame(40, $result->age);
	}

	public function testMapWithValuesFnReturningNull(): void
	{
		$source = new SimpleSource();
		$source->name = 'Grace';

		$result = $this->mapper->map($source, NullableTarget::class, [
			'valuesFn' => [
				'description' => fn(object $source): ?string => null
			]
		]);

		$this->assertSame('Grace', $result->name);
		$this->assertNull($result->description);
	}

	public function testMapWithConvertersBasic(): void
	{
		$source = new SourceWithDate();
		$source->name = 'John';
		$source->date = new DateTimeImmutable('2023-01-01T12:00:00+00:00');

		$result = $this->mapper->map($source, TargetWithDate::class, [
			'converters' => [
				[DateTimeImmutable::class, fn(DateTimeImmutable $date): string => $date->format('Y-m-d')]
			]
		]);

		$this->assertInstanceOf(TargetWithDate::class, $result);
		$this->assertSame('John', $result->name);
		$this->assertSame('2023-01-01', $result->date);
	}

	public function testMapWithConvertersForConstructorParameters(): void
	{
		$source = new SourceWithDate();
		$source->name = 'Alice';
		$source->date = new DateTimeImmutable('2023-06-15T10:30:00+00:00');

		$result = $this->mapper->map($source, ConstructorTargetWithDate::class, [
			'converters' => [
				[DateTimeImmutable::class, fn(DateTimeImmutable $date): string => $date->format('d/m/Y')]
			]
		]);

		$this->assertInstanceOf(ConstructorTargetWithDate::class, $result);
		$this->assertSame('Alice', $result->name);
		$this->assertSame('15/06/2023', $result->date);
	}

	public function testMapWithConvertersAndValues(): void
	{
		$source = new SourceWithDate();
		$source->name = 'Bob';
		$source->date = new DateTimeImmutable('2023-03-20T15:45:00+00:00');

		$result = $this->mapper->map($source, TargetWithDate::class, [
			'converters' => [
				[DateTimeImmutable::class, fn(DateTimeImmutable $date): string => $date->format('Y-m-d')]
			],
			'values' => [
				'name' => 'Override'
			]
		]);

		$this->assertSame('Override', $result->name);
		$this->assertSame('2023-03-20', $result->date);
	}

	public function testMapWithConvertersAndValuesFn(): void
	{
		$source = new SourceWithDate();
		$source->name = 'Charlie';
		$source->date = new DateTimeImmutable('2023-05-10T08:15:00+00:00');

		$result = $this->mapper->map($source, TargetWithDate::class, [
			'converters' => [
				[DateTimeImmutable::class, fn(DateTimeImmutable $date): string => $date->format('Y-m-d')]
			],
			'valuesFn' => [
				'name' => fn(object $source): string => strtoupper($source->name)
			]
		]);

		$this->assertSame('CHARLIE', $result->name);
		$this->assertSame('2023-05-10', $result->date);
	}

	public function testMapWithMultipleConverters(): void
	{
		$source = new ComplexSourceWithObjects();
		$source->name = 'David';
		$source->date = new DateTimeImmutable('2023-07-25T14:20:00+00:00');
		$source->other = new SimpleSource();
		$source->other->name = 'Other';
		$source->other->age = 25;

		$result = $this->mapper->map($source, ComplexTargetWithObjects::class, [
			'converters' => [
				[DateTimeImmutable::class, fn(DateTimeImmutable $date): string => $date->format('Y-m-d')],
				[SimpleSource::class, fn(SimpleSource $source): string => $source->name . ':' . $source->age]
			]
		]);

		$this->assertSame('David', $result->name);
		$this->assertSame('2023-07-25', $result->date);
		$this->assertSame('Other:25', $result->other);
	}

	public function testMapWithEmptyConverters(): void
	{
		$source = new SourceWithDate();
		$source->name = 'Eve';
		$source->date = new DateTimeImmutable('2023-09-01T11:00:00+00:00');

		$result = $this->mapper->map($source, TargetWithMixed::class, [
			'converters' => []
		]);

		$this->assertSame('Eve', $result->name);
		$this->assertInstanceOf(DateTimeImmutable::class, $result->date);
		$this->assertSame('2023-09-01T11:00:00+00:00', $result->date->format('c'));
	}

	public function testMapManyWithConverters(): void
	{
		$source1 = new SourceWithDate();
		$source1->name = 'First';
		$source1->date = new DateTimeImmutable('2023-01-01T00:00:00+00:00');

		$source2 = new SourceWithDate();
		$source2->name = 'Second';
		$source2->date = new DateTimeImmutable('2023-02-01T00:00:00+00:00');

		$sources = [$source1, $source2];
		$results = $this->mapper->mapMany($sources, TargetWithDate::class, [
			'converters' => [
				[DateTimeImmutable::class, fn(DateTimeImmutable $date): string => $date->format('M Y')]
			]
		]);

		$this->assertCount(2, $results);
		$this->assertSame('First', $results[0]->name);
		$this->assertSame('Jan 2023', $results[0]->date);
		$this->assertSame('Second', $results[1]->name);
		$this->assertSame('Feb 2023', $results[1]->date);
	}

}

final class SimpleSource
{
	public string $name;
	public int $age;
	public int $id;
}

final class SimpleTarget
{
	public string $name;
	public int $age;
}

final class ConstructorTarget
{
	public readonly int $id;
	public string $name;

	public function __construct(int $id)
	{
		$this->id = $id;
	}
}

final class DefaultValueTarget
{
	public string $name;
	public readonly int $age;

	public function __construct(string $name, int $age = 0)
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

final class SourceWithExtraProperties
{
	public string $name;
	public int $age;
	public string $extraProperty;
}

final class ReadOnlyTarget
{
	public readonly int $id;
	public readonly string $name;

	public function __construct(int $id, string $name)
	{
		$this->id = $id;
		$this->name = $name;
	}
}

final class SourceWithAllProperties
{
	public string $publicProperty;
	public string $protectedProperty;
	public string $privateProperty;
}

final class MixedVisibilityTarget
{
	public string $publicProperty;
	protected ?string $protectedProperty = null;
	private ?string $privateProperty = null;

	public function getProtectedProperty(): ?string
	{
		return $this->protectedProperty;
	}

	public function getPrivateProperty(): ?string
	{
		return $this->privateProperty;
	}
}

final class EmptySource
{
}

final class NoConstructorTarget
{
	public ?string $optionalProperty = null;
}

final class ComplexSource
{
	public string $name;
	public int $value;
	public string $extraData;
}

final class ComplexTarget
{
	public string $name;
	public int $value;
	public string $description;
}

final class SourceWithDate
{
	public string $name;
	public DateTimeImmutable $date;
}

final class TargetWithDate
{
	public string $name;
	public string $date;
}

final class ConstructorTargetWithDate
{
	public readonly string $date;
	public string $name;

	public function __construct(string $date)
	{
		$this->date = $date;
	}
}

final class TargetWithMixed
{
	public string $name;
	public mixed $date;
}

final class ComplexSourceWithObjects
{
	public string $name;
	public DateTimeImmutable $date;
	public SimpleSource $other;
}

final class ComplexTargetWithObjects
{
	public string $name;
	public string $date;
	public string $other;
}
