<?php declare(strict_types = 1);

namespace Shredio\ObjectMapper\PhpStan\Error;

interface ErrorReporter
{

	/**
	 * @param non-empty-string $message
	 * @param list<non-empty-string|null> $tips
	 */
	public function addError(string $message, string $identifier, array $tips = []): void;

	public function isCollector(): bool;

	public function error(): void;

}
