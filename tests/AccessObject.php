<?php declare(strict_types = 1);

namespace Tests;

use Shredio\ObjectMapper\MutableDataTransferObject;

final class AccessObject extends MutableDataTransferObject
{

	// Regular properties
	public string $regularPublic = 'foo';
	protected string $regularProtected = 'foo';
	private string $regularPrivate = 'foo';

	// Readonly properties
	public readonly string $readonlyPublic;

	// Properties with hooks
	public string $hookGet {
		get => 'foo';
	}

	public string $hookSet {
		set => $this->_value = $value;
	}

	public string $hookBoth {
		get => $this->_value;
		set => $this->_value = $value;
	}

	// Asymmetric visibility
	protected(set) string $protectedSet = 'foo';
	private(set) string $privateSet = 'foo';

	// Static properties
	public static string $staticPublic = 'foo';
	protected static string $staticProtected = 'foo';
	private static string $staticPrivate = 'foo';

	private string $_value = 'foo';

	public function __construct()
	{
		$this->readonlyPublic = 'foo';
	}

}
