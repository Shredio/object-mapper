<?php declare(strict_types = 1);

namespace Tests\Unit\Rule;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Shredio\ObjectMapper\PhpStan\ObjectMapperRule;
use Shredio\PhpStanHelpers\PhpStanReflectionHelper;

final class ObjectMapperRuleTest extends RuleTestCase
{

	protected function getRule(): Rule
	{
		return new ObjectMapperRule(new PhpStanReflectionHelper());
	}

	public function testRule(): void
	{
		$this->analyse([__DIR__ . '/ObjectMapperRuleTestCases.php'], [
			[
				'Method Shredio\ObjectMapper\ObjectMapper::map() expects the second argument to be a single class-string type, but got class-string<Tests\Unit\Rule\Article|Tests\Unit\Rule\Post>.',
				21,
			],
			[
				'Method Shredio\ObjectMapper\ObjectMapper::map() expects the second argument to be a single object type, but got Tests\Unit\Rule\Article|Tests\Unit\Rule\Post.',
				26,
			],
			[
				'Method Shredio\ObjectMapper\DefaultObjectMapper::map() expects the second argument to be a single object type, but got Tests\Unit\Rule\Article|Tests\Unit\Rule\Post.',
				31,
			],
			[
				'Method Shredio\ObjectMapper\ObjectMapper::map() expects the third argument to be a constant array type, but got array<string, mixed>.',
				39,
			],
			[
				'Method Shredio\ObjectMapper\DefaultObjectMapper::map() expects the second argument to be a single class-string type, but got class-string<Tests\Unit\Rule\Article|Tests\Unit\Rule\Post>.',
				47,
			],
			[
				'Method Shredio\ObjectMapper\ObjectMapper::map() expects the first argument to be a single object type, but got Tests\Unit\Rule\UnionTypeInvalidSource|Tests\Unit\Rule\UnionTypeValidSource.',
				52,
			],
			[
				'Missing value for constructor parameter Tests\Unit\Rule\ArticleExtraRequireProperty::$required.',
				57,
				'• Check if Tests\Unit\Rule\Article has a public property or getter for it.
• You can provide a value for it in the \'values\' of $options argument.',
			],
			[
				'Incompatible types for property Tests\Unit\Rule\ArticleWrongType::$title: string is not assignable to int.',
				62,
				'• You can provide a value for it in the \'values\' key of $options argument.
• The source value is from Tests\Unit\Rule\Article::$title.',
			],
			[
				'Incompatible types for property Tests\Unit\Rule\Article::$id: string is not assignable to int.',
				67,
				'Check the value you provided in the \'values.id\' of $options argument.',
			],
			[
				'The \'values\' key of $options contains an extra key \'extra\' that does not exist in the target class Tests\Unit\Rule\Article.',
				76,
			],
			[
				'Incompatible types for property Tests\Unit\Rule\UnionTypeTarget::$value: bool|DateTimeInterface|float|int|stdClass|string is not assignable to bool|float|int|string.',
				85,
				'• You can provide a value for it in the \'values\' key of $options argument.
• The source value is from Tests\Unit\Rule\UnionTypeInvalidSource::$value.',
			],
			[
				'Missing value for constructor parameter Tests\Unit\Rule\StrictNullableTarget::$description.',
				99,
				'• Check if Tests\Unit\Rule\DefaultValueTarget has a public property or getter for it.
• You can provide a value for it in the \'values\' of $options argument.
• The constructor parameter is nullable, but without a default value. You can allow nullable without value by setting \'allowNullableWithoutValue\' to true in the $options argument.',
			],
			[
				'The "allowNullableWithoutValue" option passed must be a constant boolean (true or false), but got bool.',
				106,
			],
			[
				'Incompatible types for property Tests\Unit\Rule\UnionTypeTarget::$value: bool|DateTimeInterface|float|int|stdClass|string is not assignable to bool|float|int|string.',
				127,
				'• You can provide a value for it in the \'values\' key of $options argument.
• The source value is from Tests\Unit\Rule\UnionTypeInvalidSource::$value.',
			],
		]);
	}

}
