<?php declare(strict_types = 1);

namespace Shredio\ObjectMapper\Helper;

final readonly class PropertyPicker
{

	/** @var array<non-empty-string, bool> */
	private array $pick;

	/** @var array<non-empty-string, bool> */
	private array $omit;


	/**
	 * @param list<non-empty-string> $pick
	 * @param list<non-empty-string> $omit
	 */
	public function __construct(array $pick = [], array $omit = [])
	{
		$this->pick = array_fill_keys($pick, true);
		$this->omit = array_fill_keys($omit, true);
	}

	public function shouldPick(string $property): bool
	{
		if ($this->pick !== []) {
			return isset($this->pick[$property]);
		}
		if ($this->omit !== []) {
			return !isset($this->omit[$property]);
		}

		return true;
	}

}
