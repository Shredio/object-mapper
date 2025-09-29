<?php declare(strict_types = 1);

namespace Shredio\ObjectMapper\PhpStan;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use Shredio\ObjectMapper\ConvertableToArray;
use Shredio\PhpStanHelpers\PhpStanNodeHelper;
use Shredio\PhpStanHelpers\PhpStanReflectionHelper;

/**
 * @implements Rule<Node\Expr\MethodCall>
 */
final readonly class DataTransferObjectRule implements Rule
{

	private DataTransferObjectToArrayService $service;

	private ClassReflection $baseClass;

	public function __construct(
		PhpStanReflectionHelper $reflectionHelper,
		private PhpStanNodeHelper $nodeHelper,
		ReflectionProvider $reflectionProvider,
	)
	{
		$this->service = new DataTransferObjectToArrayService($reflectionHelper);
		$this->baseClass = $reflectionProvider->getClass(ConvertableToArray::class);
	}

	public function getNodeType(): string
	{
		return Node\Expr\MethodCall::class;
	}

	public function processNode(Node $node, Scope $scope): array
	{
		$optionsArg = $node->getArgs()[0] ?? null;
		$optionsType = $optionsArg === null ? null : $scope->getType($optionsArg->value);

		$classReflections = $this->nodeHelper->getClassReflectionsFromMethodCall($node, $scope);
		foreach ($classReflections as $classReflection) {
			if (!$classReflection->isSubclassOfClass($this->baseClass)) {
				continue;
			}

			return $this->service->collectErrors($scope, $classReflection, $optionsType);
		}

		return [];
	}

}
