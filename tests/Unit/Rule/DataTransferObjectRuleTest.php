<?php declare(strict_types = 1);

namespace Tests\Unit\Rule;

use PHPStan\Testing\RuleTestCase;
use Shredio\ObjectMapper\PhpStan\DataTransferObjectRule;
use Shredio\PhpStanHelpers\PhpStanReflectionHelper;

final class DataTransferObjectRuleTest extends RuleTestCase
{

	protected function getRule(): DataTransferObjectRule
	{
		return new DataTransferObjectRule(new PhpStanReflectionHelper());
	}

	public function testRule(): void
	{
		$this->analyse([__DIR__ . '/DataTransferObjectRuleTestCases.php'], [
			[
				'The second argument $options of Shredio\\ObjectMapper\\ConvertableToArray::toArray() must be a constant array, but array<string, mixed> given.',
				17,
			],
			[
				'The "values" option in $options of Shredio\\ObjectMapper\\ConvertableToArray::toArray() must be a constant array with string keys, but array<string, mixed> given.',
				26,
			],
			[
				'The "deep" option in $options of Shredio\\ObjectMapper\\ConvertableToArray::toArray() must be a constant boolean (true or false), but bool given.',
				34,
			],
			[
				'The "omit" option in $options of Shredio\\ObjectMapper\\ConvertableToArray::toArray() must be a constant list of strings, but array<string, mixed> given.',
				45,
			],
			[
				'The "pick" option in $options of Shredio\\ObjectMapper\\ConvertableToArray::toArray() must be a constant list of strings, but array<string, mixed> given.',
				56,
			],
			[
				'The "pick" and "omit" options in $options of Shredio\\ObjectMapper\\ConvertableToArray::toArray() cannot be used together.',
				64,
			],
			[
				'The "converters" option in $options of Shredio\\ObjectMapper\\ConvertableToArray::toArray() contains a class-string with multiple possible classes (Tests\\Unit\\Rule\\AnotherTestDto, Tests\\Unit\\Rule\\TestDto), but only one is supported.',
				77,
			],
			[
				'The "converters" option in $options of Shredio\\ObjectMapper\\ConvertableToArray::toArray() contains a callable where the first parameter is string, but it must be Tests\\Unit\\Rule\\TestDto or its supertype.',
				88,
			],
		]);
	}

}
