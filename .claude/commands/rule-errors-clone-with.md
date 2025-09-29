Run command `vendor/bin/phpunit tests/Unit/Rule/DataTransferObjectCloneWithRuleTest.php` and fix the analyse errors in the second argument of `$this->analyse(...)` method in test file so that the test passes.

The test cases are correct, but the error messages in the second argument of `$this->analyse(...)` do not match the actual errors produced by the analyser. Update the error messages to reflect the current state of the code being analysed.


## Example
Output of phpunit:

```
'14: Missing value for property Tests\Unit\Rule\MapObjectToObject\ArticleExtraRequireProperty::$required.
    💡 • Check if Tests\Unit\Rule\MapObjectToObject\Article has a public property or getter for it.
• You can provide a value for it in the 'values' of $options argument.
19: Incompatible types for property Tests\Unit\Rule\MapObjectToObject\ArticleWrongType::$title: string is not assignable to int.
    💡 • You can provide a value for it in the 'values' key of $options argument.
• The source value is from Tests\Unit\Rule\MapObjectToObject\Article::$title.
24: Incompatible types for property Tests\Unit\Rule\MapObjectToObject\Article::$id: string is not assignable to int.
    💡 Check the value you provided in the 'values.id' of $options argument.
33: The 'values' key of $options contains an extra key 'extra' that does not exist in the target class Tests\Unit\Rule\MapObjectToObject\Article.
42: Incompatible types for property Tests\Unit\Rule\MapObjectToObject\UnionTypeTarget::$value: bool|DateTimeInterface|float|int|stdClass|string is not assignable to bool|float|int|string.
    💡 • You can provide a value for it in the 'values' key of $options argument.
• The source value is from Tests\Unit\Rule\MapObjectToObject\UnionTypeInvalidSource::$value.
'
```

output:
```php
$this->analyse(..., [
    [
        'Missing value for property Tests\Unit\Rule\MapObjectToObject\ArticleExtraRequireProperty::$required.',
        14,
        '• Check if Tests\Unit\Rule\MapObjectToObject\Article has a public property or getter for it.
• You can provide a value for it in the \'values\' of $options argument.',
    ],
    [
        'Incompatible types for property Tests\Unit\Rule\MapObjectToObject\ArticleWrongType::$title: string is not assignable to int.',
        19,
        '• You can provide a value for it in the \'values\' key of $options argument.
• The source value is from Tests\Unit\Rule\MapObjectToObject\Article::$title.',
    ],
    [
        'Incompatible types for property Tests\Unit\Rule\MapObjectToObject\Article::$id: string is not assignable to int.',
        24,
        'Check the value you provided in the \'values.id\' of $options argument.',
    ],
    [
        'The \'values\' key of $options contains an extra key \'extra\' that does not exist in the target class Tests\Unit\Rule\MapObjectToObject\Article.',
        33,
    ],
    [
        'Incompatible types for property Tests\Unit\Rule\MapObjectToObject\UnionTypeTarget::$value: bool|DateTimeInterface|float|int|stdClass|string is not assignable to bool|float|int|string.',
        42,
        '• You can provide a value for it in the \'values\' key of $options argument.
• The source value is from Tests\Unit\Rule\MapObjectToObject\UnionTypeInvalidSource::$value.',
    ],
]);
```
