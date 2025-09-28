<?php declare(strict_types = 1);

namespace Shredio\ObjectMapper\PhpStan;

use PHPStan\Type\Type;

final readonly class DataTransferObjectConverter
{

	public function __construct(
		public Type $acceptType,
		public Type $returnType,
	)
	{
	}

}
