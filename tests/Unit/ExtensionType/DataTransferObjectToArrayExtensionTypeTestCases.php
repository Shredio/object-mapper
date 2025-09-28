<?php declare(strict_types = 1);

namespace Tests\Unit\ExtensionType;

use DateTimeImmutable;
use DateTimeInterface;
use Shredio\ObjectMapper\MutableDataTransferObject;
use Tests\AccessObject;
use function PHPStan\Testing\assertType;

final readonly class DataTransferObjectToArrayExtensionTypeTestCases
{

	public function testAllVisibilities(): void
	{
		assertType('array{regularPublic: string, readonlyPublic: string, hookGet: string, hookBoth: string, protectedSet: string, privateSet: string}', (new AccessObject())->toArray());
	}

	public function testBasic(): void
	{
		assertType('array{id: int, title: string, createdAt: DateTimeImmutable, isPublished: bool, author: Tests\Unit\ExtensionType\Author, category: Tests\Unit\ExtensionType\Category}', (new Article())->toArray());
	}

	public function testDeep(): void
	{
		assertType('array{id: int, title: string, createdAt: DateTimeImmutable, isPublished: bool, author: Tests\Unit\ExtensionType\Author, category: array{id: int, name: string}}', (new Article())->toArray([
			'deep' => true,
		]));
	}

	public function testConverters(): void
	{
		assertType('array{id: int, title: string, createdAt: non-falsy-string, isPublished: bool, author: Tests\Unit\ExtensionType\Author, category: Tests\Unit\ExtensionType\Category}', (new Article())->toArray([
			'converters' => [
				[DateTimeImmutable::class, fn(DateTimeInterface $date) => $date->format('Y-m-d H:i:s')],
			],
		]));
	}

	public function testUnionTypes(SinglePropertyObject|SingleOtherPropertyObject $dto): void
	{
		assertType('array{id: int}|array{other: string}', $dto->toArray());
	}

	/**
	 * @param array{
	 *     array{ class-string<DateTimeImmutable>, callable(): string },
	 *     array{ class-string<Author>, callable(): int },
	 * } $converters
	 */
	public function testConvertersFromVariable(array $converters): void
	{
		assertType('array{id: int, title: string, createdAt: string, isPublished: bool, author: int, category: Tests\Unit\ExtensionType\Category}', (new Article())->toArray([
			'converters' => $converters,
		]));
	}

	public function testInvalidConverter(): void
	{
		assertType('array<non-empty-string, mixed>', (new Article())->toArray([
			'converters' => [
				DateTimeInterface::class => fn(DateTimeImmutable $date) => $date->format('Y-m-d H:i:s'),
			],
		]));
	}

	public function testStaticValue(string $title): void
	{
		assertType('array{id: int, title: string}', (new SinglePropertyObject())->toArray([
			'values' => ['title' => $title],
		]));
	}

	public function testStaticValueOverrideProperty(string $id): void
	{
		assertType('array{id: string}', (new SinglePropertyObject())->toArray([
			'values' => ['id' => $id],
		]));
	}

	public function testNoStrict(): void
	{
		assertType('array<non-empty-string, mixed>', (new Article())->toArrayNoStrict());
	}

	public function testOmit(): void
	{
		assertType('array{title: string, createdAt: DateTimeImmutable, author: Tests\Unit\ExtensionType\Author}', (new Article())->toArray([
			'omit' => ['id', 'isPublished', 'category'],
		]));
	}

	public function testPick(): void
	{
		assertType('array{id: int, title: string}', (new Article())->toArray([
			'pick' => ['id', 'title'],
		]));
	}

	public function testDeepNested(): void
	{
		assertType('array{id: int, nested: array{id: int}}', (new TestDtoWithNested())->toArray([
			'deep' => true,
		]));
	}

	public function testDeepDisabled(): void
	{
		assertType('array{id: int, nested: Tests\\Unit\\ExtensionType\\SinglePropertyObject}', (new TestDtoWithNested())->toArray([
			'deep' => false,
		]));
	}

	public function testDeepArrays(): void
	{
		assertType('array{id: int, items: array}', (new TestDtoWithArray())->toArray([
			'deep' => true,
		]));
	}

	public function testConvertersAndDeep(): void
	{
		assertType('array{id: int, nested: array{id: int, date: non-falsy-string}}', (new TestDtoWithNestedMixed())->toArray([
			'deep' => true,
			'converters' => [
				[DateTimeImmutable::class, fn(DateTimeInterface $date) => $date->format('Y-m-d')],
			],
		]));
	}

	public function testConvertersOnArrayItems(): void
	{
		assertType('array{id: int, dates: array}', (new TestDtoWithDateArray())->toArray([
			'deep' => true,
			'converters' => [
				[DateTimeImmutable::class, fn(DateTimeInterface $date) => $date->format('Y-m-d')],
			],
		]));
	}

	public function testMultipleConverters(): void
	{
		assertType('array{id: int, date: non-falsy-string}', (new TestDtoWithMixed())->toArray([
			'converters' => [
				[DateTimeImmutable::class, fn(DateTimeInterface $date) => $date->format('Y-m-d')],
				[TestDtoWithMixed::class, fn(TestDtoWithMixed $dto) => 'converted'],
			],
		]));
	}

	public function testEmptyDto(): void
	{
		assertType('array{}', (new EmptyDto())->toArray());
	}

	public function testEmptyPick(): void
	{
		assertType('array{id: int}', (new SinglePropertyObject())->toArray([
			'pick' => [],
		]));
	}

	public function testOmitNonExistentProperty(): void
	{
		assertType('array{id: int}', (new SinglePropertyObject())->toArray([
			'omit' => ['nonExistent'],
		]));
	}

	public function testPickNonExistentProperty(): void
	{
		assertType('array{id: int}', (new SinglePropertyObject())->toArray([
			'pick' => ['id', 'nonExistent'],
		]));
	}

	public function testNoConverters(): void
	{
		assertType('array{id: int, date: DateTimeImmutable}', (new TestDtoWithMixed())->toArray([
			'converters' => [],
		]));
	}

	public function testNonConvertableObjects(): void
	{
		assertType('array{id: int, date: DateTimeImmutable}', (new TestDtoWithMixed())->toArray([
			'deep' => true,
		]));
	}

	public function testCombinedAllOptions(): void
	{
		assertType('array{nested: array{id: int, date: non-falsy-string}, extra: \'added\'}', (new TestDtoWithNestedMixed())->toArray([
			'deep' => true,
			'pick' => ['nested', 'extra'],
			'values' => ['extra' => 'added'],
			'converters' => [
				[DateTimeImmutable::class, fn(DateTimeInterface $date) => $date->format('Y-m-d')],
			],
		]));
	}

	public function testInsideMethodDataTransferObject(): void
	{
		$class = new class extends SinglePropertyObject {

			/**
			 * @return array{ override: int }
			 */
			public function toArray(array $options = []): array
			{
				$values = parent::toArray();
				assertType('array{id: int}', $values);
				return ['override' => $values['id']];
			}
		};

		assertType('array{override: int}', $class->toArray());
	}

}

class SinglePropertyObject extends MutableDataTransferObject {
	public int $id = 1;
}

class SingleOtherPropertyObject extends MutableDataTransferObject {
	public string $other = 'other';
}

class Article extends MutableDataTransferObject {
	public int $id = 1;
	public string $title = 'Test Article';
	public DateTimeImmutable $createdAt;
	public bool $isPublished = false;
	public Author $author;
	public Category $category;
}

class Author {
	public int $id = 1;
	public string $name = 'Test Author';
}

class Category extends MutableDataTransferObject {
	public int $id = 1;
	public string $name = 'Test Category';
}

class TestDtoWithNested extends MutableDataTransferObject {
	public int $id = 0;
	public SinglePropertyObject $nested;
}

class TestDtoWithArray extends MutableDataTransferObject {
	public int $id = 0;
	public array $items = [];
}

class TestDtoWithMixed extends MutableDataTransferObject {
	public int $id = 0;
	public DateTimeImmutable $date;
}

class TestDtoWithNestedMixed extends MutableDataTransferObject {
	public int $id = 0;
	public TestDtoWithMixed $nested;
}

class TestDtoWithDateArray extends MutableDataTransferObject {
	public int $id = 0;
	public array $dates = [];
}

class EmptyDto extends MutableDataTransferObject {
}
