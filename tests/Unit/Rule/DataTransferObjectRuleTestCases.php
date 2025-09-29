<?php declare(strict_types = 1);

namespace Tests\Unit\Rule;

use DateTimeImmutable;
use Shredio\ObjectMapper\Attribute\ToArraySkipProperties;
use Shredio\ObjectMapper\MutableDataTransferObject;

final class DataTransferObjectRuleTestCases
{

	/**
	 * @param array<non-empty-string, mixed> $options
	 */
	public function nonConstantOptions(array $options): void
	{
		$dto = new TestDto();
		$dto->toArray($options);
	}

	/**
	 * @param array<non-empty-string, mixed> $values
	 */
	public function nonConstantValues(array $values): void
	{
		$dto = new TestDto();
		$dto->toArray([
			'values' => $values,
		]);
	}

	public function nonConstantDeep(bool $deep): void
	{
		$dto = new TestDto();
		$dto->toArray([
			'deep' => $deep,
		]);
	}

	/**
	 * @param array<non-empty-string, mixed> $omit
	 */
	public function nonConstantOmit(array $omit): void
	{
		$dto = new TestDto();
		$dto->toArray([
			'omit' => $omit,
		]);
	}

	/**
	 * @param array<non-empty-string, mixed> $pick
	 */
	public function nonConstantPick(array $pick): void
	{
		$dto = new TestDto();
		$dto->toArray([
			'pick' => $pick,
		]);
	}

	public function pickAndOmitTogether(): void
	{
		$dto = new TestDto();
		$dto->toArray([
			'pick' => ['id'],
			'omit' => ['name'],
		]);
	}


	/**
	 * @param class-string<TestDto|AnotherTestDto> $className
	 */
	public function invalidConverterMultipleClasses(string $className): void
	{
		$dto = new TestDto();
		$dto->toArray([
			'converters' => [
				[$className, fn(TestDto $dto) => 'converted'],
			],
		]);
	}


	public function invalidConverterParameterType(): void
	{
		$dto = new TestDto();
		$dto->toArray([
			'converters' => [
				[TestDto::class, fn(string $wrong) => 'converted'],
			],
		]);
	}

	public function invalidSkipPropertyName(): void
	{
		$dto = new ToArraySkipPropertiesDto();
		$dto->toArray();
	}

	public function callToArrayOnOtherClass(): void
	{
		$object = new \stdClass();
		$object->toArray([
			'pick' => true,
		]);
	}

}

class TestDto extends MutableDataTransferObject
{
	public int $id = 1;
	public string $name = 'Test';
	public DateTimeImmutable $createdAt;
}

class AnotherTestDto extends MutableDataTransferObject
{
	public string $value = 'another';
}

#[ToArraySkipProperties(['missingProperty'])]
class ToArraySkipPropertiesDto extends MutableDataTransferObject
{
	public int $id = 1;
	public string $name = 'Test';

	/**
	 * @return array{id: int}
	 */
	public function toArray(array $options = []): array
	{
		return parent::toArray($options);
	}
}
