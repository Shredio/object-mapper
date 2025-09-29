<?php declare(strict_types = 1);

namespace Shredio\ObjectMapper\Trait;

trait DataTransferObjectCloneWithMethod
{

	public function cloneWith(array $values): static
	{
		return new static(array_merge(get_object_vars($this), $values)); // @phpstan-ignore new.static (intentional use of new static)
	}

}
