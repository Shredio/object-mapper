<?php declare(strict_types = 1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Shredio\ObjectMapper\DataTransferObject;
use Shredio\ObjectMapper\Exception\InvalidPropertyNameException;
use Shredio\ObjectMapper\Exception\InvalidTypeException;
use Shredio\ObjectMapper\Exception\MissingPropertyException;

final class DataTransferObjectWithClone extends TestCase
{

	public function testCloneWithBasic(): void
	{
		$dto = new ReadonlyTestDto(id: 1, name: 'Original', isActive: true);

		$cloned = $dto->cloneWith(['name' => 'Modified']);

		$this->assertSame(1, $cloned->id);
		$this->assertSame('Modified', $cloned->name);
		$this->assertTrue($cloned->isActive);
		$this->assertNotSame($dto, $cloned);
	}

	public function testCloneWithMultipleFields(): void
	{
		$dto = new ReadonlyTestDto(id: 1, name: 'Original', isActive: false);

		$cloned = $dto->cloneWith([
			'name' => 'New Name',
			'isActive' => true,
		]);

		$this->assertSame(1, $cloned->id);
		$this->assertSame('New Name', $cloned->name);
		$this->assertTrue($cloned->isActive);
	}

	public function testCloneWithEmptyArray(): void
	{
		$dto = new ReadonlyTestDto(id: 42, name: 'Test', isActive: true);

		$cloned = $dto->cloneWith([]);

		$this->assertSame(42, $cloned->id);
		$this->assertSame('Test', $cloned->name);
		$this->assertTrue($cloned->isActive);
		$this->assertNotSame($dto, $cloned);
	}

	public function testCloneWithNestedObject(): void
	{
		$nested = new ReadonlyNestedDto(value: 'original');
		$dto = new ReadonlyTestDtoWithNested(id: 1, nested: $nested);

		$newNested = new ReadonlyNestedDto(value: 'modified');
		$cloned = $dto->cloneWith(['nested' => $newNested]);

		$this->assertSame(1, $cloned->id);
		$this->assertSame('modified', $cloned->nested->value);
		$this->assertNotSame($dto, $cloned);
		$this->assertNotSame($nested, $cloned->nested);
	}

	public function testCloneWithArrayProperty(): void
	{
		$dto = new ReadonlyTestDtoWithArray(id: 1, items: ['a', 'b']);

		$cloned = $dto->cloneWith(['items' => ['x', 'y', 'z']]);

		$this->assertSame(1, $cloned->id);
		$this->assertSame(['x', 'y', 'z'], $cloned->items);
	}

	public function testOriginalObjectUnchanged(): void
	{
		$dto = new ReadonlyTestDto(id: 1, name: 'Original', isActive: false);

		$cloned = $dto->cloneWith(['name' => 'Modified', 'isActive' => true]);

		$this->assertSame(1, $dto->id);
		$this->assertSame('Original', $dto->name);
		$this->assertFalse($dto->isActive);

		$this->assertSame(1, $cloned->id);
		$this->assertSame('Modified', $cloned->name);
		$this->assertTrue($cloned->isActive);
	}

	public function testCloneWithInvalidType(): void
	{
		$dto = new ReadonlyTestDto(id: 1, name: 'Test', isActive: false);

		$this->expectException(InvalidTypeException::class);

		$dto->cloneWith(['id' => 'invalid_string_for_int']);
	}

	public function testCloneWithNonExistentProperty(): void
	{
		$dto = new ReadonlyTestDto(id: 1, name: 'Test', isActive: false);

		$this->expectException(MissingPropertyException::class);

		$dto->cloneWith(['nonExistentProperty' => 'value']);
	}

	public function testCloneWithNumericProperty(): void
	{
		$dto = new ReadonlyTestDto(id: 1, name: 'Test', isActive: false);

		$this->expectException(InvalidPropertyNameException::class);

		$dto->cloneWith([0 => 'value']);
	}

}

final readonly class ReadonlyTestDto extends DataTransferObject
{
	public function __construct(
		public int $id,
		public string $name,
		public bool $isActive,
	) {
	}
}

final readonly class ReadonlyNestedDto extends DataTransferObject
{
	public function __construct(
		public string $value,
	) {
	}
}

final readonly class ReadonlyTestDtoWithNested extends DataTransferObject
{
	public function __construct(
		public int $id,
		public ReadonlyNestedDto $nested,
	) {
	}
}

final readonly class ReadonlyTestDtoWithArray extends DataTransferObject
{
	public function __construct(
		public int $id,
		public array $items,
	) {
	}
}
