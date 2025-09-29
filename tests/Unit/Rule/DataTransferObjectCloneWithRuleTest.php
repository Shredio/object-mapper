<?php declare(strict_types = 1);

namespace Tests\Unit\Rule;

use PHPStan\Testing\RuleTestCase;
use Shredio\ObjectMapper\PhpStan\DataTransferObjectCloneWithRule;
use Shredio\PhpStanHelpers\PhpStanNodeHelper;
use Shredio\PhpStanHelpers\PhpStanReflectionHelper;

final class DataTransferObjectCloneWithRuleTest extends RuleTestCase
{

	protected function getRule(): DataTransferObjectCloneWithRule
	{
		return new DataTransferObjectCloneWithRule(new PhpStanReflectionHelper(), new PhpStanNodeHelper(), self::createReflectionProvider());
	}

	public function testRule(): void
	{
		$this->analyse([__DIR__ . '/DataTransferObjectCloneWithRuleTestCases.php'], [
			[
				'The class Tests\Unit\Rule\NoConstructor does not have a constructor, so it cannot be used with cloneWith().',
				13,
			],
			[
				'Cannot clone Tests\Unit\Rule\SinglePropertyWithConstructorParameter::$name, it is not set via constructor.',
				19,
			],
			[
				'Property to clone Tests\Unit\Rule\SimpleDto::$value is expected to be of type int, but string given.',
				30,
			],
			[
				'Unknown property $nonExisting on class Tests\Unit\Rule\SimpleDto.',
				36,
			],
			[
				'Cannot clone Tests\Unit\Rule\SinglePropertyWithConstructorParameter::$name, it is not set via constructor.',
				47,
			],
			[
				'Cannot determine the class for cloneWith() call, multiple classes possible: Tests\Unit\Rule\SingleProperty, Tests\Unit\Rule\SinglePropertyWithConstructorParameter.',
				52,
			],
			[
				'The argument $values passed to cloneWith() in class Tests\Unit\Rule\SimpleDto must be a constant array, but array<string, mixed> given.',
				63,
			],
			[
				'Property to clone Tests\Unit\Rule\SimpleDto::$value is expected to be of type int, but string given.',
				72,
			],
			[
				'Property to clone Tests\Unit\Rule\UnionTypesDto::$name is expected to be of type float|int|string|null, but bool|int|string|null given.',
				78,
			],
			[
				'Property to clone Tests\Unit\Rule\UnionObjectTypesDto::$name is expected to be of type Tests\Unit\Rule\BoolValue|Tests\Unit\Rule\IntValue|Tests\Unit\Rule\StringValue, but Tests\Unit\Rule\FloatValue|Tests\Unit\Rule\IntValue|Tests\Unit\Rule\StringValue given.',
				86,
			],
		]);
	}

}
