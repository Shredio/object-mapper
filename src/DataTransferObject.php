<?php declare(strict_types = 1);

namespace Shredio\ObjectMapper;

use Error;
use Shredio\ObjectMapper\Exception\InvalidPropertyNameException;
use Shredio\ObjectMapper\Exception\InvalidTypeException;
use Shredio\ObjectMapper\Exception\MissingPropertyException;
use Shredio\ObjectMapper\Trait\DataTransferObjectCloneWithMethod;
use Shredio\ObjectMapper\Trait\DataTransferObjectToArrayMethod;
use TypeError;

abstract readonly class DataTransferObject implements ConvertableToArray
{

	use DataTransferObjectToArrayMethod;
	use DataTransferObjectCloneWithMethod;

	/**
	 * Creates a clone of the current object with modified fields.
	 *
	 * @param array<non-empty-string, mixed> $values
	 */
	public function cloneWith(array $values): static
	{
		try {
			return new static(...array_merge(get_object_vars($this), $values)); // @phpstan-ignore new.static (intentional use of new static)
		} catch (TypeError $exception) { // @phpstan-ignore catch.neverThrown
			throw new InvalidTypeException($exception->getMessage(), previous: $exception);
		} catch (Error $error) { // @phpstan-ignore catch.neverThrown
			$message = $error->getMessage();
			if (str_starts_with($message, 'Unknown named parameter')) {
				throw new MissingPropertyException($message, previous: $error);
			}
			if (str_starts_with($message, 'Cannot use positional argument')) {
				throw new InvalidPropertyNameException('Cannot use numeric keys in cloneWith', previous: $error);
			}

			throw $error;
		}
	}

}
