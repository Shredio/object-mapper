<?php declare(strict_types = 1);

namespace Shredio\ObjectMapper\PhpStan\Error;

use Shredio\ObjectMapper\Exception\InvalidExtensionTypeException;

final readonly class ThrowErrorReporter implements ErrorReporter
{

	/**
	 * @throws InvalidExtensionTypeException
	 */
	public function addError(string $message, string $identifier, array $tips = []): void
	{
		throw new InvalidExtensionTypeException();
	}

	/**
	 * @throws InvalidExtensionTypeException
	 */
	public function error(): void
	{
		throw new InvalidExtensionTypeException();
	}

	public function isCollector(): bool
	{
		return false;
	}

}
