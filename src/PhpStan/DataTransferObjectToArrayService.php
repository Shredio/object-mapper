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
use Shredio\ObjectMapper\ConvertableToArray;
use Shredio\ObjectMapper\Exception\InvalidExtensionTypeException;
use Shredio\ObjectMapper\Helper\PropertyPicker;
use Shredio\ObjectMapper\PhpStan\Error\ErrorCollector;
use Shredio\PhpStanHelpers\Exception\EmptyTypeException;
use Shredio\PhpStanHelpers\Exception\InvalidTypeException;
use Shredio\PhpStanHelpers\Exception\NonConstantTypeException;
use Shredio\PhpStanHelpers\PhpStanReflectionHelper;

/**
 * @phpstan-type OptionsType array{ values: array<non-empty-string, Type>, deep: bool, converters: list<DataTransferObjectConverter>, pick: list<non-empty-string>, omit: list<non-empty-string> }
 */
final class DataTransferObjectToArrayService
{

	public const string ClassName = ConvertableToArray::class;
	public const string MethodName = 'toArray';
	public const string OptionsName = 'options';

	private bool $canThrowException = false;

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
	public function collectErrors(Scope $scope, ?Type $optionsType): array
	{
		$this->canThrowException = false;
		$this->parseOptions($scope, $optionsType, $collector = new ErrorCollector('dto'));
		return $collector->errors;
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
		$this->canThrowException = true;

		try {
			$types = [];
			$options = $this->parseOptions($scope, $optionsType);
			foreach ($classReflections as $classReflection) {
				$types[] = $this->createType($classReflection, $options);
			}
		} catch (InvalidExtensionTypeException) { // @phpstan-ignore catch.neverThrown
			return null;
		} finally {
			$this->canThrowException = false;
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
	private function createType(ClassReflection $classReflection, array $options): Type
	{
		$values = $options['values'];
		$options['values'] = [];
		$picker = new PropertyPicker($options['pick'], $options['omit']);
		$newOptions = [
			'values' => [],
			'deep' => $options['deep'],
			'converters' => $options['converters'],
			'pick' => [],
			'omit' => [],
		];

		$builder = ConstantArrayTypeBuilder::createEmpty();
		$selectedProperties = $this->reflectionHelper->getReadablePropertiesFromReflection($classReflection);
		foreach ($selectedProperties as $propertyName => $reflectionProperty) {
			if (isset($values[$propertyName])) {
				continue;
			}
			if (!$picker->shouldPick($propertyName)) {
				continue;
			}

			$readableType = $reflectionProperty->getReadableType();
			if ($options['deep'] && $readableType->isObject()->yes() && $this->baseObjectType->isSuperTypeOf($readableType)->yes()) {
				$reflections = $readableType->getObjectClassReflections();
				if (count($reflections) === 1) {
					$readableType = $this->createType($reflections[0], $newOptions);
				}
			} else {
				foreach ($options['converters'] as $converter) {
					if ($converter->acceptType->isSuperTypeOf($readableType)->yes()) {
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
	 * @return OptionsType
	 */
	private function parseOptions(Scope $scope, ?Type $type, ?ErrorCollector $errorCollector = null): array
	{
		if ($type === null) {
			return $this->getDefaultOptions();
		}

		try {
			$options = $this->reflectionHelper->getNonEmptyStringKeyWithTypeFromConstantArray($type);
		} catch (NonConstantTypeException|InvalidTypeException) {
			$errorCollector?->addError(
				sprintf('The second argument $%s of %s::%s() must be a constant array, but %s given.',
					self::OptionsName,
					self::ClassName,
					self::MethodName,
					$type->describe(VerbosityLevel::typeOnly()),
				),
				'options.invalidType',
			);

			$this->errorOccurred();
			return $this->getDefaultOptions(); // not a constant array
		}

		// values
		try {
			if (isset($options['values'])) {
				$values = $this->reflectionHelper->getNonEmptyStringKeyWithTypeFromConstantArray($options['values']);
			} else {
				$values = [];
			}
		} catch (NonConstantTypeException|InvalidTypeException) {
			$errorCollector?->addError(
				sprintf('The "values" option in $%s of %s::%s() must be a constant array with string keys, but %s given.',
					self::OptionsName,
					self::ClassName,
					self::MethodName,
					$options['values']->describe(VerbosityLevel::typeOnly()),
				),
				'options.values.invalidType',
			);

			$this->errorOccurred();
			return $this->getDefaultOptions(); // not a constant array
		}

		// deep
		try {
			if (isset($options['deep'])) {
				$deep = $this->reflectionHelper->getTrueOrFalseFromConstantBoolean($options['deep']);
			} else {
				$deep = false;
			}
		} catch (NonConstantTypeException|InvalidTypeException) {
			$errorCollector?->addError(
				sprintf('The "deep" option in $%s of %s::%s() must be a constant boolean (true or false), but %s given.',
					self::OptionsName,
					self::ClassName,
					self::MethodName,
					$options['deep']->describe(VerbosityLevel::typeOnly()),
				),
				'options.deep.invalidType',
			);

			$this->errorOccurred();
			return $this->getDefaultOptions(); // not a constant boolean
		}

		// omit
		try {
			if (isset($options['omit'])) {
				$omit = $this->reflectionHelper->getNonEmptyStringsFromConstantArrayType($options['omit']);
			} else {
				$omit = [];
			}
		} catch (InvalidTypeException|NonConstantTypeException) {
			$errorCollector?->addError(
				sprintf('The "omit" option in $%s of %s::%s() must be a constant list of strings, but %s given.',
					self::OptionsName,
					self::ClassName,
					self::MethodName,
					$options['omit']->describe(VerbosityLevel::typeOnly()),
				),
				'options.omit.invalidType',
			);
			$this->errorOccurred();
			return $this->getDefaultOptions();
		} catch (EmptyTypeException) {
			$omit = [];
		}

		// pick
		try {
			if (isset($options['pick'])) {
				$pick = $this->reflectionHelper->getNonEmptyStringsFromConstantArrayType($options['pick']);
			} else {
				$pick = [];
			}
		} catch (InvalidTypeException|NonConstantTypeException) {
			$errorCollector?->addError(
				sprintf('The "pick" option in $%s of %s::%s() must be a constant list of strings, but %s given.',
					self::OptionsName,
					self::ClassName,
					self::MethodName,
					$options['pick']->describe(VerbosityLevel::typeOnly()),
				),
				'options.pick.invalidType',
			);
			$this->errorOccurred();
			return $this->getDefaultOptions();
		} catch (EmptyTypeException) {
			$pick = [];
		}

		if ($pick !== [] && $omit !== []) {
			$errorCollector?->addError(
				sprintf('The "pick" and "omit" options in $%s of %s::%s() cannot be used together.',
					self::OptionsName,
					self::ClassName,
					self::MethodName,
				),
				'options.pickAndOmit',
			);
			$this->errorOccurred();
			return $this->getDefaultOptions();
		}

		return [
			'values' => $values,
			'deep' => $deep,
			'omit' => $omit,
			'pick' => $pick,
			'converters' => $this->parseConverters($scope,$options['converters'] ?? null, $errorCollector),
		];
	}

	/**
	 * @return list<DataTransferObjectConverter>
	 */
	private function parseConverters(Scope $scope, ?Type $type, ?ErrorCollector $errorCollector = null): array
	{
		if ($type === null) {
			return [];
		}

		try {
			$constantListValues = $this->reflectionHelper->getValueTypesFromConstantList($type);
		} catch (InvalidTypeException) {
			$this->errorOccurred(); // covered by another rule
			return [];
		} catch (NonConstantTypeException) {
			$errorCollector?->addError(
				sprintf('The "converters" option in $%s of %s::%s() must be a constant list, but %s given.',
					self::OptionsName,
					self::ClassName,
					self::MethodName,
					$type->describe(VerbosityLevel::typeOnly()) ,
				),
				'options.converters.type',
			);
			$this->errorOccurred();
			return [];
		}

		$return = [];
		foreach ($constantListValues as $innerType) {
			if (!$innerType->isList()->yes()) {
				$this->errorOccurred(); // covered by another rule
				continue;
			}

			try {
				/** @var list<Type> $innerValueTypes */
				$innerValueTypes = iterator_to_array($this->reflectionHelper->getValueTypesFromConstantList($innerType), false);
			} catch (InvalidTypeException) {
				$this->errorOccurred(); // covered by another rule
				continue;
			} catch (NonConstantTypeException) {
				$errorCollector?->addError(
					sprintf('The "converters" option in $%s of %s::%s() must be a constant list of constant list, but %s given.',
						self::OptionsName,
						self::ClassName,
						self::MethodName,
						$innerType->describe(VerbosityLevel::typeOnly()),
					),
					'options.converters.type',
				);
				$this->errorOccurred();
				continue;
			}

			if (count($innerValueTypes) < 2) {
				$this->errorOccurred(); // covered by another rule
				continue;
			}

			$classNameType = $innerValueTypes[0];
			$callbackType = $innerValueTypes[1];

			if (!$classNameType->isClassString()->yes()) {
				$this->errorOccurred(); // covered by another rule
				continue;
			}

			$acceptType = $classNameType->getClassStringObjectType();
			$acceptTypeClassNames = $acceptType->getObjectClassNames();
			$count = count($acceptTypeClassNames);
			if ($count === 0) {
				$errorCollector?->addError(
					sprintf('The "converters" option in $%s of %s::%s() contains a class-string that does not specify any class.',
						self::OptionsName,
						self::ClassName,
						self::MethodName,
					),
					'options.converters.classStringNoClass',
				);
				$this->errorOccurred();
				continue;
			}
			if ($count > 1) {
				$errorCollector?->addError(
					sprintf('The "converters" option in $%s of %s::%s() contains a class-string with multiple possible classes (%s), but only one is supported.',
						self::OptionsName,
						self::ClassName,
						self::MethodName,
						implode(', ', $acceptTypeClassNames),
					),
					'options.converters.classStringMultipleClasses',
				);
				$this->errorOccurred();
				continue;
			}

			if (!$callbackType->isCallable()->yes()) {
				$this->errorOccurred();
				continue;
			}

			$callableParametersAcceptors = $callbackType->getCallableParametersAcceptors($scope);
			$count = count($callableParametersAcceptors);
			if ($count === 0) {
				$this->errorOccurred();
				continue; // covered by another rule
			}
			if ($count !== 1) {
				$errorCollector?->addError(
					sprintf('The "converters" option in $%s of %s::%s() contains a callable with %d variants, but only one is supported.',
						self::OptionsName,
						self::ClassName,
						self::MethodName,
						$count,
					),
					'options.converters.multipleVariants',
				);
				$this->errorOccurred();
				continue;
			}
			$parametersAcceptor = $callableParametersAcceptors[0];
			$firstParameter = $parametersAcceptor->getParameters()[0] ?? null;
			if ($firstParameter !== null && !$firstParameter->getType()->isSuperTypeOf($acceptType)->yes()) {
				$errorCollector?->addError(
					sprintf(
						'The "converters" option in $%s of %s::%s() contains a callable where the first parameter is %s, but it must be %s or its supertype.',
						self::OptionsName,
						self::ClassName,
						self::MethodName,
						$firstParameter->getType()->describe(VerbosityLevel::typeOnly()),
						$acceptType->describe(VerbosityLevel::typeOnly()),
					),
					'options.converters.parameterType',
				);
				$this->errorOccurred();
				continue;
			}

			$return[] = new DataTransferObjectConverter(
				acceptType: $acceptType,
				returnType: $parametersAcceptor->getReturnType(),
			);
		}

		return $return;
	}

	private function errorOccurred(): void
	{
		if ($this->canThrowException) {
			throw new InvalidExtensionTypeException();
		}
	}

	/**
	 * @return OptionsType
	 */
	private function getDefaultOptions(): array
	{
		return ['values' => [], 'deep' => false, 'converters' => [], 'omit' => [], 'pick' => []];
	}

}
