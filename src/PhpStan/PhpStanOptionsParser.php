<?php declare(strict_types = 1);

namespace Shredio\ObjectMapper\PhpStan;

use PHPStan\Analyser\Scope;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\VerbosityLevel;
use Shredio\ObjectMapper\PhpStan\Error\ErrorReporter;
use Shredio\PhpStanHelpers\Exception\EmptyTypeException;
use Shredio\PhpStanHelpers\Exception\InvalidTypeException;
use Shredio\PhpStanHelpers\Exception\NonConstantTypeException;
use Shredio\PhpStanHelpers\PhpStanReflectionHelper;

final readonly class PhpStanOptionsParser
{

	/**
	 * @param array<non-empty-string, Type> $options
	 */
	public function __construct(
		private PhpStanReflectionHelper $reflectionHelper,
		private Scope $scope,
		private array $options,
		private string $className,
		private string $methodName,
		private string $optionsName,
		private ErrorReporter $errorReporter,
	)
	{
	}

	/**
	 * @param non-empty-string $optionName
	 */
	public function bool(string $optionName, bool $default): bool
	{
		$type = $this->options[$optionName] ?? null;
		if ($type === null) {
			return $default;
		}

		try {
			return $this->reflectionHelper->getTrueOrFalseFromConstantBoolean($type);
		} catch (NonConstantTypeException|InvalidTypeException) {
			$this->errorReporter->addError(
				sprintf('The "%s" option in $%s of %s::%s() must be a constant boolean (true or false), but %s given.',
					$optionName,
					$this->optionsName,
					$this->className,
					$this->methodName,
					$type->describe(VerbosityLevel::typeOnly()),
				),
				sprintf('options.%s.invalidType', $optionName),
			);

			return $default;
		}
	}

	/**
	 * @param non-empty-string $optionName
	 * @return list<non-empty-string>|null
	 */
	public function listOfNonEmptyStrings(string $optionName): ?array
	{
		$type = $this->options[$optionName] ?? null;
		if ($type === null) {
			return null;
		}

		try {
			return $this->reflectionHelper->getNonEmptyStringsFromConstantArrayType($type);
		} catch (NonConstantTypeException|InvalidTypeException) {
			$this->errorReporter->addError(
				sprintf('The "%s" option in $%s of %s::%s() must be a constant list of strings, but %s given.',
					$optionName,
					$this->optionsName,
					$this->className,
					$this->methodName,
					$type->describe(VerbosityLevel::typeOnly()),
				),
				sprintf('options.%s.invalidType', $optionName),
			);

			return null;
		} catch (EmptyTypeException) {
			return [];
		}
	}

	/**
	 * @param non-empty-string $optionName
	 * @return array<non-empty-string, Type>
	 */
	public function values(string $optionName): array
	{
		$type = $this->options[$optionName] ?? null;
		if ($type === null) {
			return [];
		}

		try {
			return $this->reflectionHelper->getNonEmptyStringKeyWithTypeFromConstantArray($type);
		} catch (NonConstantTypeException|InvalidTypeException) {
			$this->errorReporter->addError(
				sprintf('The "%s" option in $%s of %s::%s() must be a constant array with string keys, but %s given.',
					$optionName,
					$this->optionsName,
					$this->className,
					$this->methodName,
					$type->describe(VerbosityLevel::typeOnly()),
				),
				sprintf('options.%s.invalidType', $optionName),
			);

			return [];
		}
	}

	/**
	 * @param string $optionName
	 * @param array<non-empty-string, Type> $values
	 * @return array<non-empty-string, Type>
	 */
	public function valuesFn(string $optionName, ?string $valuesOptionName = null, array $values = []): array
	{
		$type = $this->options[$optionName] ?? null;
		if ($type === null) {
			return $values;
		}

		try {
			$valuesFn = $this->reflectionHelper->getNonEmptyStringKeyWithTypeFromConstantArray($type);
		} catch (InvalidTypeException|NonConstantTypeException) {
			$this->errorReporter->addError(
				sprintf(
					'The "%s" option passed must be a constant array, but got %s.',
					$optionName,
					$type->describe(VerbosityLevel::typeOnly()),
				),
				sprintf('options.%s.invalid%s', $optionName, ucfirst($optionName)),
			);

			return $values;
		}

		foreach ($valuesFn as $key => $type) {
			if ($valuesOptionName !== null && isset($values[$key])) {
				$this->errorReporter->addError(
					sprintf(
						'The "%s" option in $%s of %s::%s() contains a key "%s" that is also present in the "%s" option. A key can be present in only one of these options.',
						$optionName,
						$this->optionsName,
						$this->className,
						$this->methodName,
						$key,
						$valuesOptionName,
					),
					sprintf('options.%s.duplicateValueKey', $optionName),
				);

				continue;
			}

			$types = [];
			foreach ($type->getCallableParametersAcceptors($this->scope) as $parametersAcceptor) {
				$types[] = $parametersAcceptor->getReturnType();
			}
			$values[$key] = TypeCombinator::union(...$types);
		}

		return $values;
	}

	/**
	 * @param non-empty-string $optionName
	 */
	public function converters(string $optionName): DataTransferObjectConverterCollection
	{
		$type = $this->options[$optionName] ?? null;
		if ($type === null) {
			return new DataTransferObjectConverterCollection();
		}

		try {
			$constantListValues = $this->reflectionHelper->getValueTypesFromConstantList($type);
		} catch (InvalidTypeException) {
			$this->errorReporter->error();
			return new DataTransferObjectConverterCollection(); // covered by another rule
		} catch (NonConstantTypeException) {
			$this->errorReporter->addError(
				sprintf('The "%s" option in $%s of %s::%s() must be a constant list, but %s given.',
					$optionName,
					$this->optionsName,
					$this->className,
					$this->methodName,
					$type->describe(VerbosityLevel::typeOnly()) ,
				),
				sprintf('options.%s.type', $optionName),
			);
			return new DataTransferObjectConverterCollection();
		}

		$return = [];
		foreach ($constantListValues as $innerType) {
			if (!$innerType->isList()->yes()) {
				$this->errorReporter->error();
				continue; // covered by another rule
			}

			try {
				/** @var list<Type> $innerValueTypes */
				$innerValueTypes = iterator_to_array($this->reflectionHelper->getValueTypesFromConstantList($innerType), false);
			} catch (InvalidTypeException) {
				$this->errorReporter->error();
				continue; // covered by another rule
			} catch (NonConstantTypeException) {
				$this->errorReporter->addError(
					sprintf('The "%s" option in $%s of %s::%s() must be a constant list of constant list, but %s given.',
						$optionName,
						$this->optionsName,
						$this->className,
						$this->methodName,
						$innerType->describe(VerbosityLevel::typeOnly()),
					),
					sprintf('options.%s.type', $optionName),
				);
				continue;
			}

			if (count($innerValueTypes) < 2) {
				$this->errorReporter->error();
				continue; // covered by another rule
			}

			$classNameType = $innerValueTypes[0];
			$callbackType = $innerValueTypes[1];

			if (!$classNameType->isClassString()->yes()) {
				$this->errorReporter->error();
				continue; // covered by another rule
			}

			$acceptType = $classNameType->getClassStringObjectType();
			$acceptTypeClassNames = $acceptType->getObjectClassNames();
			$count = count($acceptTypeClassNames);
			if ($count === 0) {
				$this->errorReporter->addError(
					sprintf('The "%s" option in $%s of %s::%s() contains a class-string that does not specify any class.',
						$optionName,
						$this->optionsName,
						$this->className,
						$this->methodName,
					),
					sprintf('options.%s.classStringNoClass', $optionName),
				);
				continue;
			}
			if ($count > 1) {
				$this->errorReporter->addError(
					sprintf('The "%s" option in $%s of %s::%s() contains a class-string with multiple possible classes (%s), but only one is supported.',
						$optionName,
						$this->optionsName,
						$this->className,
						$this->methodName,
						implode(', ', $acceptTypeClassNames),
					),
					sprintf('options.%s.classStringMultipleClasses', $optionName),
				);
				continue;
			}

			if (!$callbackType->isCallable()->yes()) {
				$this->errorReporter->error();
				continue;
			}

			$callableParametersAcceptors = $callbackType->getCallableParametersAcceptors($this->scope);
			$count = count($callableParametersAcceptors);
			if ($count === 0) {
				$this->errorReporter->error();
				continue; // covered by another rule
			}
			if ($count !== 1) {
				$this->errorReporter->addError(
					sprintf('The "%s" option in $%s of %s::%s() contains a callable with %d variants, but only one is supported.',
						$optionName,
						$this->optionsName,
						$this->className,
						$this->methodName,
						$count,
					),
					sprintf('options.%s.multipleVariants', $optionName),
				);
				continue;
			}
			$parametersAcceptor = $callableParametersAcceptors[0];
			$firstParameter = $parametersAcceptor->getParameters()[0] ?? null;
			if ($firstParameter !== null && !$firstParameter->getType()->accepts($acceptType, true)->yes()) {
				$this->errorReporter->addError(
					sprintf(
						'The "%s" option in $%s of %s::%s() contains a callable where the first parameter is %s, but it must be %s or its supertype.',
						$optionName,
						$this->optionsName,
						$this->className,
						$this->methodName,
						$firstParameter->getType()->describe(VerbosityLevel::typeOnly()),
						$acceptType->describe(VerbosityLevel::typeOnly()),
					),
					sprintf('options.%s.parameterType', $optionName),
				);
				continue;
			}

			$return[] = new DataTransferObjectConverter(
				acceptType: $acceptType,
				returnType: $parametersAcceptor->getReturnType(),
			);
		}

		return new DataTransferObjectConverterCollection($return);
	}

}
