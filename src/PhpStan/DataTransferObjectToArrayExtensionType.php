<?php declare(strict_types = 1);

namespace Shredio\ObjectMapper\PhpStan;

use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\Type;
use Shredio\ObjectMapper\DataTransferObject;
use Shredio\ObjectMapper\MutableDataTransferObject;
use Shredio\PhpStanHelpers\PhpStanReflectionHelper;

final readonly class DataTransferObjectToArrayExtensionType implements DynamicMethodReturnTypeExtension, DynamicStaticMethodReturnTypeExtension
{

	private DataTransferObjectToArrayService $service;

	public function __construct(
		PhpStanReflectionHelper $reflectionHelper,
	)
	{
		$this->service = new DataTransferObjectToArrayService($reflectionHelper);
	}

	public function getClass(): string
	{
		return $this->service::ClassName;
	}

	public function isMethodSupported(MethodReflection $methodReflection): bool
	{
		return $methodReflection->getName() === $this->service::MethodName;
	}

	public function isStaticMethodSupported(MethodReflection $methodReflection): bool
	{
		return $methodReflection->getName() === $this->service::MethodName;
	}

	public function getTypeFromMethodCall(
		MethodReflection $methodReflection,
		MethodCall $methodCall,
		Scope $scope,
	): ?Type
	{
		$calledOnType = $scope->getType($methodCall->var);
		$optionsArg = $methodCall->getArgs()[0] ?? null;
		$optionsType = $optionsArg === null ? null : $scope->getType($optionsArg->value);
		$declaringClassName = $methodReflection->getDeclaringClass()->getName();
		if (!in_array($declaringClassName, [MutableDataTransferObject::class, DataTransferObject::class], true)) {
			return null; // method is overridden in a child class, use type-hint from there
		}

		return $this->service->execute($scope, $calledOnType->getObjectClassReflections(), $optionsType);
	}

	public function getTypeFromStaticMethodCall( // covers parent:: calls
		MethodReflection $methodReflection,
		StaticCall $methodCall,
		Scope $scope,
	): ?Type
	{
		$classReflection = $scope->getClassReflection();
		if ($classReflection === null) {
			return null;
		}

		$optionsArg = $methodCall->getArgs()[0] ?? null;
		$optionsType = $optionsArg === null ? null : $scope->getType($optionsArg->value);

		return $this->service->execute($scope, [$classReflection], $optionsType);
	}

}
