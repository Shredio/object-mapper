<?php declare(strict_types = 1);

namespace Shredio\ObjectMapper;

use Shredio\ObjectMapper\Trait\DataTransferObjectMethods;

abstract readonly class DataTransferObject implements ConvertableToArray
{
	use DataTransferObjectMethods;
}
