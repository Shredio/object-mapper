<?php declare(strict_types = 1);

namespace Shredio\ObjectMapper\Exception;

use Exception;
use PHPStan\Rules\IdentifierRuleError;

final class RuleErrorException extends Exception
{
	/**
	 * @param non-empty-list<IdentifierRuleError> $ruleErrors
	 */
	public function __construct(
		public readonly array $ruleErrors,
	)
	{
		parent::__construct();
	}
}
