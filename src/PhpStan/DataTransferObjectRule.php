<?php declare(strict_types = 1);

namespace Shredio\ObjectMapper\PhpStan;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use Shredio\PhpStanHelpers\PhpStanReflectionHelper;

/**
 * @implements Rule<Node\Expr\MethodCall>
 */
final readonly class DataTransferObjectRule implements Rule
{

	private DataTransferObjectToArrayService $service;

	public function __construct(
		PhpStanReflectionHelper $reflectionHelper,
	)
	{
		$this->service = new DataTransferObjectToArrayService($reflectionHelper);
	}

	public function getNodeType(): string
	{
		return Node\Expr\MethodCall::class;
	}

	public function processNode(Node $node, Scope $scope): array
	{
		$optionsArg = $node->getArgs()[0] ?? null;
		$optionsType = $optionsArg === null ? null : $scope->getType($optionsArg->value);

		return $this->service->collectErrors($scope, $optionsType);
	}

}
