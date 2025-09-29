<?php declare(strict_types = 1);

namespace Shredio\ObjectMapper\PhpStan;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\Type;
use PHPStan\Type\VerbosityLevel;
use Shredio\ObjectMapper\DataTransferObject;
use Shredio\PhpStanHelpers\Exception\InvalidTypeException;
use Shredio\PhpStanHelpers\Exception\NonConstantTypeException;
use Shredio\PhpStanHelpers\PhpStanNodeHelper;
use Shredio\PhpStanHelpers\PhpStanReflectionHelper;

/**
 * @implements Rule<Node\Expr\MethodCall>
 */
final readonly class DataTransferObjectCloneWithRule implements Rule
{

	private const string MethodName = 'cloneWith';

	private ClassReflection $baseClass;

	public function __construct(
		private PhpStanReflectionHelper $reflectionHelper,
		private PhpStanNodeHelper $nodeHelper,
		ReflectionProvider $reflectionProvider,
	)
	{
		$this->baseClass = $reflectionProvider->getClass(DataTransferObject::class);
	}

	public function getNodeType(): string
	{
		return Node\Expr\MethodCall::class;
	}

	public function processNode(Node $node, Scope $scope): array
	{
		$arguments = $this->nodeHelper->getArgumentTypes($node, $scope);
		$valuesType = $arguments['values'] ?? $arguments[0] ?? null;
		if ($valuesType === null) {
			return [];
		}

		$methodName = $this->nodeHelper->getMethodName($node);
		if ($methodName !== self::MethodName) {
			return [];
		}

		$classReflections = $this->nodeHelper->getClassReflectionsFromMethodCall($node, $scope);
		if ($classReflections === []) {
			return [];
		}

		$isSubclass = false;
		foreach ($classReflections as $classReflection) {
			if ($classReflection->isSubclassOfClass($this->baseClass) || $classReflection->getName() === $this->baseClass->getName()) {
				$isSubclass = true;
			}
		}

		if (!$isSubclass) {
			return [];
		}

		if (count($classReflections) > 1) {
			return [
				RuleErrorBuilder::message(sprintf(
					'Cannot determine the class for %s() call, multiple classes possible: %s.',
					self::MethodName,
					implode(', ', array_map(fn (ClassReflection $r): string => $r->getName(), $classReflections)),
				))
					->identifier($this->id('multipleClasses'))
					->build(),
			];
		}

		return $this->checkClass($classReflections[0], $valuesType);
	}

	/**
	 * @return list<IdentifierRuleError>
	 */
	private function checkClass(ClassReflection $classReflection, Type $valuesType): array
	{
		if (!$classReflection->hasConstructor()) {
			return [
				RuleErrorBuilder::message(sprintf(
					'The class %s does not have a constructor, so it cannot be used with %s().',
					$classReflection->getName(),
					self::MethodName,
				))
					->identifier($this->id('missingConstructor'))
					->build(),
			];
		}

		try {
			$fieldsToClone = $this->reflectionHelper->getNonEmptyStringKeyWithTypeFromConstantArray($valuesType);
		} catch (InvalidTypeException) {
			return []; // covered by other rule
		} catch (NonConstantTypeException) {
			return [
				RuleErrorBuilder::message(sprintf(
					'The argument $values passed to %s() in class %s must be a constant array, but %s given.',
					self::MethodName,
					$classReflection->getName(),
					$valuesType->describe(VerbosityLevel::typeOnly()),
				))
					->identifier($this->id('nonConstantArray'))
					->build(),
			];
		}

		$errors = [];
		foreach ($this->reflectionHelper->getParametersFromMethod($classReflection->getConstructor()) as $parameterName => $parameter) {
			if (!isset($fieldsToClone[$parameterName])) {
				continue;
			}

			$parameterType = $parameter->getType();
			if (!$parameterType->accepts($fieldsToClone[$parameterName], true)->yes()) {
				$errors[] = RuleErrorBuilder::message(sprintf(
					'Property to clone %s::$%s is expected to be of type %s, but %s given.',
					$classReflection->getName(),
					$parameterName,
					$parameterType->describe(VerbosityLevel::typeOnly()),
					$fieldsToClone[$parameterName]->describe(VerbosityLevel::typeOnly()),
				))
					->identifier($this->id('propertyType'))
					->build();
			}

			unset($fieldsToClone[$parameterName]);
		}

		foreach ($fieldsToClone as $propertyName => $_) {
			if ($classReflection->hasInstanceProperty($propertyName)) {
				$errors[] = RuleErrorBuilder::message(sprintf(
					'Cannot clone %s::$%s, it is not set via constructor.',
					$classReflection->getName(),
					$propertyName,
				))
					->identifier($this->id('missingConstructorProperty'))
					->build();
			} else if ($classReflection->hasStaticProperty($propertyName)) {
				$errors[] = RuleErrorBuilder::message(sprintf(
					'Cannot clone static property %s::$%s.',
					$classReflection->getName(),
					$propertyName,
				))
					->identifier($this->id('staticProperty'))
					->build();
			} else {
				$errors[] = RuleErrorBuilder::message(sprintf(
					'Unknown property $%s on class %s.',
					$propertyName,
					$classReflection->getName(),
				))
					->identifier($this->id('unknownProperty'))
					->build();
			}
		}

		return $errors;
	}

	/**
	 * @param non-empty-string $id
	 * @return non-empty-string
	 */
	private function id(string $id): string
	{
		return sprintf('cloneWith.%s', $id);
	}

}
