<?php declare(strict_types = 1);

namespace Shredio\ObjectMapper\PhpStan;

use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\VerbosityLevel;
use Shredio\ObjectMapper\Attribute\ToArraySkipProperties;
use Shredio\ObjectMapper\ConvertableToArray;
use Shredio\ObjectMapper\Exception\InvalidExtensionTypeException;
use Shredio\ObjectMapper\PhpStan\Error\CollectErrorReporter;
use Shredio\ObjectMapper\PhpStan\Error\ErrorReporter;
use Shredio\ObjectMapper\PhpStan\Error\ThrowErrorReporter;
use Shredio\PhpStanHelpers\Exception\CannotCombinePickWithOmitException;
use Shredio\PhpStanHelpers\Exception\EmptyTypeException;
use Shredio\PhpStanHelpers\Exception\InvalidTypeException;
use Shredio\PhpStanHelpers\Exception\NonConstantTypeException;
use Shredio\PhpStanHelpers\Helper\PropertyPicker;
use Shredio\PhpStanHelpers\PhpStanReflectionHelper;

/**
 * @phpstan-type OptionsType array{ values: array<non-empty-string, Type>, deep: bool, converters: list<DataTransferObjectConverter>, pick: list<non-empty-string>|null, omit: list<non-empty-string>|null }
 */
final class DataTransferObjectToArrayService
{

	public const string ClassName = ConvertableToArray::class;
	public const string MethodName = 'toArray';
	public const string OptionsName = 'options';

	private ObjectType $baseObjectType;

	public function __construct(
		private readonly PhpStanReflectionHelper $reflectionHelper,
	)
	{
		$this->baseObjectType = new ObjectType(self::ClassName);
	}

	/**
	 * @return list<IdentifierRuleError>
	 */
	public function collectErrors(Scope $scope, ClassReflection $classReflection, ?Type $optionsType): array
	{
		$this->parseOptions($scope, $optionsType, $reporter = new CollectErrorReporter('dto'));
		$this->parseSkipPropertiesFromAttribute($classReflection, $reporter);
		return $reporter->errors;
	}

	/**
	 * @param list<ClassReflection> $classReflections
	 */
	public function execute(
		Scope $scope,
		array $classReflections,
		?Type $optionsType,
	): ?Type
	{
		try {
			$reporter = new ThrowErrorReporter();
			$types = [];
			$options = $this->parseOptions($scope, $optionsType, $reporter);
			foreach ($classReflections as $classReflection) {
				$types[] = $this->createType($classReflection, $options, $reporter);
			}
		} catch (InvalidExtensionTypeException) { // @phpstan-ignore catch.neverThrown
			return null;
		}

		$count = count($types);
		if ($count === 0) {
			return null;
		} else if ($count === 1) {
			return $types[0];
		}

		return TypeCombinator::union(...$types);
	}

	/**
	 * @param OptionsType $options
	 */
	private function createType(ClassReflection $classReflection, array $options, ErrorReporter $errorReporter): Type
	{
		$skipProperties = $this->parseSkipPropertiesFromAttribute($classReflection, $errorReporter);
		$values = $options['values'];
		try {
			$picker = new PropertyPicker($options['pick'], $options['omit']);
		} catch (CannotCombinePickWithOmitException) {
			$picker = PropertyPicker::pick($options['pick']); // should not happen
		}

		$newOptions = [
			'values' => [],
			'deep' => $options['deep'],
			'converters' => $options['converters'],
			'pick' => null,
			'omit' => null,
		];

		$builder = ConstantArrayTypeBuilder::createEmpty();
		$selectedProperties = $this->reflectionHelper->getReadablePropertiesFromReflection($classReflection, $picker);
		foreach ($selectedProperties as $propertyName => $reflectionProperty) {
			if (isset($values[$propertyName])) {
				continue;
			}
			if (isset($skipProperties[$propertyName])) {
				continue;
			}

			$readableType = $reflectionProperty->getReadableType();
			if ($options['deep'] && $readableType->isObject()->yes() && $this->baseObjectType->isSuperTypeOf($readableType)->yes()) {
				$reflections = $readableType->getObjectClassReflections();
				if (count($reflections) === 1) {
					$readableType = $this->createType($reflections[0], $newOptions, $errorReporter);
				}
			} else {
				foreach ($options['converters'] as $converter) {
					if ($converter->acceptType->accepts($readableType, true)->yes()) {
						$readableType = $converter->returnType;
						break;
					}
				}
			}

			$builder->setOffsetValueType(new ConstantStringType($propertyName), $readableType);
		}

		foreach ($values as $key => $type) {
			$builder->setOffsetValueType(new ConstantStringType($key), $type);
		}

		return $builder->getArray();
	}

	/**
	 * @return array<non-empty-string, true>
	 */
	private function parseSkipPropertiesFromAttribute(ClassReflection $classReflection, ErrorReporter $errorReporter): array
	{
		$skip = [];
		foreach ($classReflection->getAttributes() as $attribute) {
			if ($attribute->getName() === ToArraySkipProperties::class) {
				$arguments = $attribute->getArgumentTypes();
				if (!isset($arguments['properties'])) {
					continue; // covered by another rule
				}

				try {
					$skip = array_merge(
						$skip,
						$this->reflectionHelper->getNonEmptyStringsFromConstantArrayType($arguments['properties']),
					);
				} catch (EmptyTypeException) {
					continue; // empty list
				} catch (InvalidTypeException|NonConstantTypeException) {
					$errorReporter->addError(
						sprintf('The "properties" argument of the %s attribute in class %s must be a constant list of strings, but %s given.',
							ToArraySkipProperties::class,
							$classReflection->getName(),
							$arguments['properties']->describe(VerbosityLevel::typeOnly()),
						),
						'attribute.properties.invalidType',
					);

					continue; // not a constant array
				}
			}
		}

		if ($errorReporter->isCollector()) {
			foreach ($skip as $propertyName) {
				if (!$classReflection->hasInstanceProperty($propertyName)) {
					$errorReporter->addError(
						sprintf('The property "%s" listed in the %s attribute in class %s does not exist.',
							$propertyName,
							ToArraySkipProperties::class,
							$classReflection->getName(),
						),
						'attribute.missingProperty',
					);
				}
			}
		}

		$return = [];
		foreach ($skip as $propertyName) {
			$return[$propertyName] = true;
		}

		return $return;
	}

	/**
	 * @return OptionsType
	 */
	private function parseOptions(Scope $scope, ?Type $type, ErrorReporter $errorReporter): array
	{
		if ($type === null) {
			return $this->getDefaultOptions();
		}

		try {
			$options = $this->reflectionHelper->getNonEmptyStringKeyWithTypeFromConstantArray($type);
		} catch (NonConstantTypeException|InvalidTypeException) {
			$errorReporter->addError(
				sprintf('The second argument $%s of %s::%s() must be a constant array, but %s given.',
					self::OptionsName,
					self::ClassName,
					self::MethodName,
					$type->describe(VerbosityLevel::typeOnly()),
				),
				'options.invalidType',
			);

			return $this->getDefaultOptions(); // not a constant array
		}

		$optionsParser = new PhpStanOptionsParser(
			$this->reflectionHelper,
			$scope,
			$options,
			self::ClassName,
			self::MethodName,
			self::OptionsName,
			$errorReporter,
		);

		$values = $optionsParser->values('values');
		$deep = $optionsParser->bool('deep', false);
		$converters = $optionsParser->converters('converters');
		$omit = $optionsParser->listOfNonEmptyStrings('omit');
		$pick = $optionsParser->listOfNonEmptyStrings('pick');

		if ($pick !== null && $omit !== null) {
			$errorReporter->addError(
				sprintf('The "pick" and "omit" options in $%s of %s::%s() cannot be used together.',
					self::OptionsName,
					self::ClassName,
					self::MethodName,
				),
				'options.pickAndOmit',
			);

			return $this->getDefaultOptions();
		}


		return [
			'values' => $values,
			'deep' => $deep,
			'omit' => $omit,
			'pick' => $pick,
			'converters' => $converters,
		];
	}

	/**
	 * @return OptionsType
	 */
	private function getDefaultOptions(): array
	{
		return ['values' => [], 'deep' => false, 'converters' => [], 'omit' => null, 'pick' => null];
	}

}
