<?php declare(strict_types = 1);

namespace Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Shredio\ObjectMapper\MutableDataTransferObject;

final class DataTransferObjectToArrayTest extends TestCase
{

	public function testToArrayBasic(): void
	{
		$dto = new TestDto();
		$dto->id = 1;
		$dto->name = 'Test';
		$dto->isActive = true;

		$result = $dto->toArray();

		$this->assertSame([
			'id' => 1,
			'name' => 'Test',
			'isActive' => true,
		], $result);
	}

	public function testToArrayWithValues(): void
	{
		$dto = new TestDto();
		$dto->id = 1;
		$dto->name = 'Original';

		$result = $dto->toArray([
			'values' => [
				'name' => 'Override',
				'extra' => 'added',
			],
		]);

		$this->assertSame([
			'id' => 1,
			'isActive' => false,
			'name' => 'Override',
			'extra' => 'added',
		], $result);
	}

	public function testToArrayWithOmit(): void
	{
		$dto = new TestDto();
		$dto->id = 1;
		$dto->name = 'Test';
		$dto->isActive = true;

		$result = $dto->toArray([
			'omit' => ['id', 'isActive'],
		]);

		$this->assertSame([
			'name' => 'Test',
		], $result);
	}

	public function testToArrayWithPick(): void
	{
		$dto = new TestDto();
		$dto->id = 1;
		$dto->name = 'Test';
		$dto->isActive = true;

		$result = $dto->toArray([
			'pick' => ['id', 'name'],
		]);

		$this->assertSame([
			'id' => 1,
			'name' => 'Test',
		], $result);
	}

	public function testToArrayWithEmptyPick(): void
	{
		$dto = new TestDto();
		$dto->id = 1;
		$dto->name = 'Test';

		$result = $dto->toArray([
			'pick' => [],
		]);

		$this->assertSame([
			'id' => 1,
			'name' => 'Test',
			'isActive' => false,
		], $result);
	}

	public function testToArrayWithDeepDisabled(): void
	{
		$nested = new NestedDto();
		$nested->value = 'nested';

		$dto = new TestDtoWithNested();
		$dto->id = 1;
		$dto->nested = $nested;

		$result = $dto->toArray([
			'deep' => false,
		]);

		$this->assertSame([
			'id' => 1,
			'nested' => $nested,
		], $result);
	}

	public function testToArrayWithDeepEnabled(): void
	{
		$nested = new NestedDto();
		$nested->value = 'nested';

		$dto = new TestDtoWithNested();
		$dto->id = 1;
		$dto->nested = $nested;

		$result = $dto->toArray([
			'deep' => true,
		]);

		$this->assertSame([
			'id' => 1,
			'nested' => [
				'value' => 'nested',
			],
		], $result);
	}

	public function testToArrayWithDeepArrays(): void
	{
		$nested1 = new NestedDto();
		$nested1->value = 'first';

		$nested2 = new NestedDto();
		$nested2->value = 'second';

		$dto = new TestDtoWithArray();
		$dto->id = 1;
		$dto->items = [$nested1, 'string', $nested2, 42];

		$result = $dto->toArray([
			'deep' => true,
		]);

		$this->assertSame([
			'id' => 1,
			'items' => [
				['value' => 'first'],
				'string',
				['value' => 'second'],
				42,
			],
		], $result);
	}

	public function testToArrayWithConverters(): void
	{
		$date = new DateTimeImmutable('2023-01-01T12:00:00+00:00');

		$dto = new TestDtoWithMixed();
		$dto->id = 1;
		$dto->date = $date;

		$result = $dto->toArray([
			'converters' => [
				[DateTimeImmutable::class, fn(DateTimeImmutable $date) => $date->format('Y-m-d H:i:s')],
			],
		]);

		$this->assertSame([
			'id' => 1,
			'date' => '2023-01-01 12:00:00',
		], $result);
	}

	public function testToArrayWithConvertersAndDeep(): void
	{
		$date = new DateTimeImmutable('2023-01-01T12:00:00+00:00');

		$nested = new TestDtoWithMixed();
		$nested->id = 2;
		$nested->date = $date;

		$dto = new TestDtoWithNestedMixed();
		$dto->id = 1;
		$dto->nested = $nested;

		$result = $dto->toArray([
			'deep' => true,
			'converters' => [
				[DateTimeImmutable::class, fn(DateTimeImmutable $date) => $date->format('Y-m-d')],
			],
		]);

		$this->assertSame([
			'id' => 1,
			'nested' => [
				'id' => 2,
				'date' => '2023-01-01',
			],
		], $result);
	}

	public function testToArrayCombinedOptions(): void
	{
		$nested = new NestedDto();
		$nested->value = 'nested';

		$dto = new TestDtoWithNested();
		$dto->id = 1;
		$dto->nested = $nested;

		$result = $dto->toArray([
			'deep' => true,
			'omit' => ['id'],
			'values' => ['extra' => 'added'],
		]);

		$this->assertSame([
			'nested' => [
				'value' => 'nested',
			],
			'extra' => 'added',
		], $result);
	}

	public function testToArrayWithNonConvertableObjects(): void
	{
		$date = new DateTimeImmutable('2023-01-01');

		$dto = new TestDtoWithMixed();
		$dto->id = 1;
		$dto->date = $date;

		$result = $dto->toArray([
			'deep' => true,
		]);

		$this->assertSame([
			'id' => 1,
			'date' => $date,
		], $result);
	}

	public function testToArrayNoStrict(): void
	{
		$dto = new TestDto();
		$dto->id = 1;
		$dto->name = 'Test';

		$result = $dto->toArrayNoStrict();

		$this->assertSame([
			'id' => 1,
			'name' => 'Test',
			'isActive' => false,
		], $result);
	}

	public function testToArrayNoStrictWithOptions(): void
	{
		$dto = new TestDto();
		$dto->id = 1;
		$dto->name = 'Test';

		$result = $dto->toArrayNoStrict([
			'omit' => ['id'],
		]);

		$this->assertSame([
			'name' => 'Test',
			'isActive' => false,
		], $result);
	}

	public function testOmitNonExistentProperty(): void
	{
		$dto = new TestDto();
		$dto->id = 1;
		$dto->name = 'Test';

		$result = $dto->toArray([
			'omit' => ['nonExistent', 'id'],
		]);

		$this->assertSame([
			'name' => 'Test',
			'isActive' => false,
		], $result);
	}

	public function testPickNonExistentProperty(): void
	{
		$dto = new TestDto();
		$dto->id = 1;
		$dto->name = 'Test';

		$result = $dto->toArray([
			'pick' => ['id', 'nonExistent'],
		]);

		$this->assertSame([
			'id' => 1,
		], $result);
	}

	public function testToArrayWithMultipleConverters(): void
	{
		$date = new DateTimeImmutable('2023-01-01T12:00:00+00:00');

		$dto = new TestDtoWithMixed();
		$dto->id = 1;
		$dto->date = $date;

		$result = $dto->toArray([
			'converters' => [
				[DateTimeImmutable::class, fn(DateTimeImmutable $date) => $date->format('Y-m-d')],
				[TestDtoWithMixed::class, fn(TestDtoWithMixed $dto) => 'converted'],
			],
		]);

		$this->assertSame([
			'id' => 1,
			'date' => '2023-01-01',
		], $result);
	}

	public function testToArrayWithConvertersOnArrayItems(): void
	{
		$date1 = new DateTimeImmutable('2023-01-01T12:00:00+00:00');
		$date2 = new DateTimeImmutable('2023-01-02T12:00:00+00:00');

		$dto = new TestDtoWithDateArray();
		$dto->id = 1;
		$dto->dates = [$date1, $date2, 'string'];

		$result = $dto->toArray([
			'deep' => true,
			'converters' => [
				[DateTimeImmutable::class, fn(DateTimeImmutable $date) => $date->format('Y-m-d')],
			],
		]);

		$this->assertSame([
			'id' => 1,
			'dates' => ['2023-01-01', '2023-01-02', 'string'],
		], $result);
	}

	public function testToArrayWithNoConverters(): void
	{
		$date = new DateTimeImmutable('2023-01-01T12:00:00+00:00');

		$dto = new TestDtoWithMixed();
		$dto->id = 1;
		$dto->date = $date;

		$result = $dto->toArray([
			'converters' => [],
		]);

		$this->assertSame([
			'id' => 1,
			'date' => $date,
		], $result);
	}

	public function testToArrayCombinedAllOptions(): void
	{
		$date = new DateTimeImmutable('2023-01-01T12:00:00+00:00');

		$nested = new TestDtoWithMixed();
		$nested->id = 2;
		$nested->date = $date;

		$dto = new TestDtoWithNestedMixed();
		$dto->id = 1;
		$dto->nested = $nested;

		$result = $dto->toArray([
			'deep' => true,
			'omit' => ['id'],
			'pick' => ['nested', 'extra'],
			'values' => ['extra' => 'added'],
			'converters' => [
				[DateTimeImmutable::class, fn(DateTimeImmutable $date) => $date->format('Y-m-d')],
			],
		]);

		$this->assertSame([
			'nested' => [
				'id' => 2,
				'date' => '2023-01-01',
			],
			'extra' => 'added',
		], $result);
	}

	public function testEmptyDto(): void
	{
		$dto = new EmptyDto();

		$result = $dto->toArray();

		$this->assertSame([], $result);
	}

}

final class TestDto extends MutableDataTransferObject
{
	public int $id = 0;
	public string $name = '';
	public bool $isActive = false;
}

final class NestedDto extends MutableDataTransferObject
{
	public string $value = '';
}

final class TestDtoWithNested extends MutableDataTransferObject
{
	public int $id = 0;
	public NestedDto $nested;
}

final class TestDtoWithArray extends MutableDataTransferObject
{
	public int $id = 0;
	public array $items = [];
}

final class TestDtoWithMixed extends MutableDataTransferObject
{
	public int $id = 0;
	public DateTimeImmutable $date;
}

final class TestDtoWithNestedMixed extends MutableDataTransferObject
{
	public int $id = 0;
	public TestDtoWithMixed $nested;
}

final class TestDtoWithDateArray extends MutableDataTransferObject
{
	public int $id = 0;
	public array $dates = [];
}

final class EmptyDto extends MutableDataTransferObject
{
}
