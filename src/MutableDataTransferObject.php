<?php declare(strict_types = 1);

namespace Shredio\ObjectMapper;

use Shredio\ObjectMapper\Trait\DataTransferObjectToArrayMethod;

class MutableDataTransferObject implements ConvertableToArray
{
	use DataTransferObjectToArrayMethod;
}
