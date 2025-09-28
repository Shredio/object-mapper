<?php declare(strict_types = 1);

namespace Shredio\ObjectMapper\PhpStan\Error;

use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\RuleErrorBuilder;

final class ErrorCollector
{

	/** @var list<IdentifierRuleError> */
	public array $errors = [];

	public function __construct(
		private readonly string $identifierPrefix,
	)
	{
	}

	/**
	 * @param non-empty-string $message
	 * @param list<non-empty-string|null> $tips
	 */
	public function addError(string $message, string $identifier, array $tips = []): void
	{
		$builder = RuleErrorBuilder::message($message);
		$builder->identifier(sprintf('%s.%s', $this->identifierPrefix, $identifier));
		foreach ($tips as $tip) {
			if ($tip !== null) {
				$builder->addTip($tip);
			}
		}

		$this->errors[] = $builder->build();
	}

}
