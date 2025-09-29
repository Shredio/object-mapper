<?php declare(strict_types = 1);

namespace Shredio\ObjectMapper\PhpStan;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ExtendedParameterReflection;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\VerbosityLevel;
use Shredio\ObjectMapper\Exception\RuleErrorException;
use Shredio\ObjectMapper\ObjectMapper;
use Shredio\ObjectMapper\PhpStan\Error\CollectErrorReporter;
use Shredio\PhpStanHelpers\Exception\InvalidTypeException;
use Shredio\PhpStanHelpers\Exception\NonConstantTypeException;
use Shredio\PhpStanHelpers\PhpStanReflectionHelper;

/**
 * @implements Rule<Node\Expr\MethodCall>
 */
final readonly class ObjectMapperRule implements Rule
{

	private const string ClassName = ObjectMapper::class;
	private const string MethodName = 'map';
	private const string MethodNameMany = 'mapMany';

	public function __construct(
		private PhpStanReflectionHelper $reflectionHelper,
	)
	{
	}

	public function getNodeType(): string
	{
		return Node\Expr\MethodCall::class;
	}

	public function processNode(Node $node, Scope $scope): array
	{
		$args = $node->getArgs();
		$sourceArg = $args[0] ?? null;
		$targetArg = $args[1] ?? null;
		$optionsArg = $args[2] ?? null;

		if ($sourceArg === null || $targetArg === null) {
			return [];
		}

		$calledOnClassNameType = $scope->getType($node->var);
		$isSuperType = (new ObjectType(self::ClassName))->isSuperTypeOf($calledOnClassNameType)->yes();
		if (!$isSuperType) {
			return [];
		}

		$methodNameNode = $node->name;
		if (!$methodNameNode instanceof Node\Identifier) {
			return [];
		}
		if ($methodNameNode->name === self::MethodName) {
			$sourceType = $scope->getType($sourceArg->value);
		} else if ($methodNameNode->name === self::MethodNameMany) {
			$iterableType = $scope->getType($sourceArg->value);
			if (!$iterableType->isIterable()->yes()) {
				return []; // covered by another rule
			}

			$sourceType = $iterableType->getIterableValueType();
		} else {
			return [];
		}

		$targetType = $scope->getType($targetArg->value);
		if ($targetType->isClassString()->yes()) {
			$targetClassReflections = $targetType->getClassStringObjectType()->getObjectClassReflections();
		} else if ($targetType->isObject()->yes()) {
			$targetClassReflections = $targetType->getObjectClassReflections();
		} else {
			return []; // covered by another rule
		}

		if (count($targetClassReflections) !== 1) {
			if (count($targetClassReflections) === 0) {
				return []; // covered by another rule
			}

			$type = $targetType->isClassString()->yes() ? 'class-string' : 'object';
			// Union type is not supported
			return [
				RuleErrorBuilder::message(sprintf(
					'Method %s::%s() expects the second argument to be a single %s type, but got %s.',
					$calledOnClassNameType->describe(VerbosityLevel::typeOnly()),
					self::MethodName,
					$type,
					$targetType->describe(VerbosityLevel::typeOnly()),
				))
					->identifier('shredio.mapObjectToObject.unionTarget')
					->build()
			];
		}

		$sourceClassReflections = $sourceType->getObjectClassReflections();
		if (count($sourceClassReflections) !== 1) {
			if (count($sourceClassReflections) === 0) {
				return []; // covered by another rule
			}

			// Union type is not supported
			return [
				RuleErrorBuilder::message(sprintf(
					'Method %s::%s() expects the first argument to be a single object type, but got %s.',
					$calledOnClassNameType->describe(VerbosityLevel::typeOnly()),
					self::MethodName,
					$sourceType->describe(VerbosityLevel::typeOnly()),
				))
					->identifier('shredio.mapObjectToObject.unionSource')
					->build()
			];
		}

		$sourceClassReflection = $sourceClassReflections[0];
		$targetClassReflection = $targetClassReflections[0];

		$writableProperties = $this->reflectionHelper->getWritablePropertiesWithConstructorFromReflection($targetClassReflection);
		$readableProperties = iterator_to_array($this->reflectionHelper->getReadablePropertiesFromReflection($sourceClassReflection), preserve_keys: true);

		$options = $this->getOptions(
			$scope,
			$optionsArg === null ? null : $scope->getType($optionsArg->value),
			$calledOnClassNameType->describe(VerbosityLevel::typeOnly()),
			$errorReporter = new CollectErrorReporter('objectMapper'),
		);
		if ($errorReporter->errors !== []) {
			return $errorReporter->errors;
		}

		$staticValues = $options['values'];
		$allowNullableWithoutValue = $options['allowNullableWithoutValue'];

		$errors = [];
		foreach ($writableProperties as $propertyToWrite) {
			if (isset($staticValues[$propertyToWrite->getName()])) {
				$isStaticValue = true;
				$propertyTypeToRead = $staticValues[$propertyToWrite->getName()];
				unset($staticValues[$propertyToWrite->getName()]);
			} else if (isset($readableProperties[$propertyToWrite->getName()])) {
				$isStaticValue = false;
				$propertyTypeToRead = $readableProperties[$propertyToWrite->getName()]->getReadableType();
			} else { // missing property
				if ($propertyToWrite instanceof ExtendedParameterReflection) {
					if ($propertyToWrite->isOptional()) {
						continue; // optional constructor parameter
					}
					$isNullable = TypeCombinator::containsNull($propertyToWrite->getType());
					if ($allowNullableWithoutValue && $isNullable) {
						continue; // nullable constructor parameter and allowed
					}
					$isParameter = true;
				} else {
					$nativeReflection = $targetClassReflection->getNativeProperty($propertyToWrite->getName());
					if ($nativeReflection->getNativeReflection()->hasDefaultValue()) {
						continue; // property has default value
					}
					$isNullable = TypeCombinator::containsNull($propertyToWrite->getWritableType());
					if ($allowNullableWithoutValue && $isNullable) {
						continue; // nullable property and allowed
					}
					$isParameter = false;
				}

				$parameterLocation = $isParameter ? 'constructor parameter' : 'property';
				$builder = RuleErrorBuilder::message(sprintf(
					'Missing value for %s %s::$%s.',
					$parameterLocation,
					$targetClassReflection->getName(),
					$propertyToWrite->getName(),
				))
					->identifier($this->id($isParameter ? 'missingConstructorParameter' : 'missingProperty'))
					->tip(sprintf('Check if %s has a public property or getter for it.', $sourceClassReflection->getName()))
					->addTip('You can provide a value for it in the \'values\' or \'valuesFn\' of $options argument.');

				if ($isNullable) {
					$builder->addTip(sprintf(
						'The %s is nullable, but without a default value. You can allow nullable without value by setting \'allowNullableWithoutValue\' to true in the $options argument.',
						$parameterLocation,
					));
				}

				$errors[] = $builder->build();

				continue;
			}

			$writableType = $propertyToWrite instanceof ExtendedParameterReflection ? $propertyToWrite->getType() : $propertyToWrite->getWritableType();

			if (!$writableType->accepts($propertyTypeToRead, true)->yes()) {
				$builder = RuleErrorBuilder::message(sprintf(
					'Incompatible types for property %s::$%s: %s is not assignable to %s.',
					$targetClassReflection->getName(),
					$propertyToWrite->getName(),
					$propertyTypeToRead->describe(VerbosityLevel::typeOnly()),
					$writableType->describe(VerbosityLevel::typeOnly()),
				))
					->identifier($this->id('incompatibleProperty'));

				if (!$isStaticValue) {
					$builder->tip('You can provide a value for it in the \'values\' or \'valuesFn\' key of $options argument.');
					$builder->addTip(sprintf('The source value is from %s::$%s.', $sourceClassReflection->getName(), $propertyToWrite->getName()));
				} else {
					$builder->tip('Check the value you provided in the $options argument.');
				}

				$errors[] = $builder->build();
			}
		}

		foreach ($staticValues as $extraKey => $_) {
			$errors[] = RuleErrorBuilder::message(sprintf(
				'The \'values\' key of $options contains an extra key \'%s\' that does not exist in the target class %s.',
				$extraKey,
				$targetClassReflection->getName(),
			))
				->identifier($this->id('extraValue'))
				->build();
		}

		return $errors;
	}

	/**
	 * @return array{ values: array<non-empty-string, Type>, allowNullableWithoutValue: bool }
	 */
	private function getOptions(Scope $scope, ?Type $type, string $className, CollectErrorReporter $errorReporter): array
	{
		if ($type === null) {
			return $this->defaultOptions();
		}

		try {
			$options = $this->reflectionHelper->getNonEmptyStringKeyWithTypeFromConstantArray($type);
		} catch (InvalidTypeException|NonConstantTypeException) {
			$errorReporter->addError(
				sprintf(
					'Method %s::%s() expects the third argument to be a constant array type, but got %s.',
					$className,
					self::MethodName,
					$type->describe(VerbosityLevel::typeOnly()),
				),
				'invalidOptions',
			);

			return $this->defaultOptions();
		}

		$optionsParser = new PhpStanOptionsParser(
			$this->reflectionHelper,
			$scope,
			$options,
			$className,
			self::MethodName,
			'options',
			$errorReporter,
		);

		$values = $optionsParser->values('values');
		$values = $optionsParser->valuesFn('valuesFn', 'values', $values);
		$allowNullableWithoutValue = $optionsParser->bool('allowNullableWithoutValue', false);

		return [
			'values' => $values,
			'allowNullableWithoutValue' => $allowNullableWithoutValue,
		];
	}

	/**
	 * @return array{ values: array<non-empty-string, Type>, allowNullableWithoutValue: bool }
	 */
	private function defaultOptions(): array
	{
		return ['values' => [], 'allowNullableWithoutValue' => false];
	}

	private function id(string $name): string
	{
		return sprintf('objectMapper.%s', $name);
	}

}
