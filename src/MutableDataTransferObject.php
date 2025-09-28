<?php declare(strict_types = 1);

namespace Shredio\ObjectMapper;

use Shredio\ObjectMapper\Trait\DataTransferObjectMethods;

class MutableDataTransferObject implements ConvertableToArray
{
	use DataTransferObjectMethods;
}
