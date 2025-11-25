<?php declare(strict_types = 1);

namespace Tests\Unit\Rule;

use Shredio\ObjectMapper\DataTransferObject;

final readonly class DataTransferObjectCloneWithRuleTestCases
{

	public function testCloneFromObjectWithoutConstructor(): void
	{
		$object = new NoConstructor();
		$object->cloneWith([]);
	}

	public function testCloneFromProperty(): void
	{
		$object = new SinglePropertyWithConstructorParameter();
		$object->cloneWith([
			'name' => 'changed',
		]);
	}

	/**
	 * @param array{ value: string } $fields
	 */
	public function testWrongParameterTypeHint(array $fields): void
	{
		$object = new SimpleDto();
		$object->cloneWith($fields);
	}

	public function testWrongCloneField(): void
	{
		$object = new SimpleDto();
		$object->cloneWith([
			'nonExisting' => 'changed',
		]);
	}

	/**
	 * @param array{ name: string } $fields
	 */
	public function testWrongCloneFromParameterTypeHint(array $fields): void
	{
		$object = new SinglePropertyWithConstructorParameter();
		$object->cloneWith($fields);
	}

	public function testCloneUnionType(SingleProperty|SinglePropertyWithConstructorParameter $object): void
	{
		$object->cloneWith([
			'name' => 'changed',
		]);
	}

	/**
	 * @param array<non-empty-string, mixed> $fields
	 */
	public function testNonConstantArrays(array $fields): void
	{
		$object = new SimpleDto();
		$object->cloneWith($fields);
	}

	/**
	 * @param array{ name: string }|array{ value: string } $fields
	 */
	public function testUnionValuesType(array $fields): void
	{
		$object = new SimpleDto();
		$object->cloneWith($fields);
	}

	public function testInvalidExtraUnionType(bool|string|int|null $value): void
	{
		$object = new UnionTypesDto();
		$object->cloneWith([
			'name' => $value,
		]);
	}

	public function testInvalidExtraUnionObjectType(IntValue|StringValue|FloatValue $value): void
	{
		$object = new UnionObjectTypesDto();
		$object->cloneWith([
			'name' => $value,
		]);
	}

	// valid cases

	public function testValidClone(): void
	{
		$object = new SimpleDto();
		$object->cloneWith([
			'name' => 'changed',
		]);
	}

	public function testValidMultipleClone(): void
	{
		$object = new SimpleDto();
		$object->cloneWith([
			'name' => 'changed',
			'value' => 123,
		]);
	}

	/**
	 * @param array{ name: string } $fields
	 */
	public function testValidParameterTypeHint(array $fields): void
	{
		$object = new SimpleDto();
		$object->cloneWith($fields);
	}

	/**
	 * @param array{ name: string }|array{ value: int } $fields
	 */
	public function testValidUnionValuesType(array $fields): void
	{
		$object = new SimpleDto();
		$object->cloneWith($fields);
	}

	public function testValidUnionTypes(string|int|null $value): void
	{
		$object = new UnionTypesDto();
		$object->cloneWith([
			'name' => $value,
		]);
	}

	public function testValidUnionObjectTypes(StringValue|IntValue $value): void
	{
		$object = new UnionObjectTypesDto();
		$object->cloneWith([
			'name' => $value,
		]);
	}

	/**
	 * @param list<IntValue> $items
	 */
	public function testValidListType(array $items): void
	{
		$object = new ListTypeDto();
		$object->cloneWith([
			'items' => $items,
		]);
	}

	public function testIgnoreCloneWithOnOtherClass(): void
	{
		$stdClass = new \stdClass();
		$stdClass->cloneWith([]);
	}

}

readonly class SimpleDto extends DataTransferObject
{
	public function __construct(
		public string $name = 'default',
		public int $value = 0,
	) {
	}
}

readonly class NoConstructor extends DataTransferObject {}

readonly class SingleProperty extends DataTransferObject
{
	public string $name;

	public function __construct()
	{
		$this->name = 'default';
	}

}

readonly class SinglePropertyWithConstructorParameter extends DataTransferObject
{
	public string $name;

	public function __construct(
		public int $id = 1,
	) {
		$this->name = 'default';
	}
}

class IntValue {}
class FloatValue {}
class StringValue {}
class BoolValue {}

readonly class UnionObjectTypesDto extends DataTransferObject
{
	public function __construct(
		public IntValue|StringValue|BoolValue $name = new IntValue(),
	) {
	}
}

readonly class UnionTypesDto extends DataTransferObject
{
	public function __construct(
		public string|float|int|null $name = 'default',
	) {
	}
}

readonly class ListTypeDto extends DataTransferObject
{
	/**
	 * @param list<IntValue> $items
	 */
	public function __construct(
		public array $items = [],
	) {
	}
}
