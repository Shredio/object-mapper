<?php declare(strict_types = 1);

namespace Tests;

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
