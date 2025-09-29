<?php declare(strict_types = 1);

namespace Tests\Unit\Helper;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Shredio\ObjectMapper\Helper\ConverterLookup;

final class ConverterLookupTest extends TestCase
{

	public function testConstructorWithEmptyConverters(): void
	{
		$lookup = new ConverterLookup([]);

		$this->assertSame('primitive', $lookup->tryToConvert('primitive'));
		$this->assertSame(42, $lookup->tryToConvert(42));
		$this->assertSame(true, $lookup->tryToConvert(true));
		$this->assertNull($lookup->tryToConvert(null));
	}

	public function testConstructorWithSmallNumberOfConverters(): void
	{
		$converters = [
			[TestClassA::class, fn(TestClassA $obj) => 'converted_A'],
			[TestClassB::class, fn(TestClassB $obj) => 'converted_B'],
		];
		$lookup = new ConverterLookup($converters);

		$objA = new TestClassA();
		$objB = new TestClassB();

		$this->assertSame('converted_A', $lookup->tryToConvert($objA));
		$this->assertSame('converted_B', $lookup->tryToConvert($objB));
	}

	public function testConstructorWithManyConverters(): void
	{
		$converters = [];
		for ($i = 1; $i <= 15; $i++) {
			$converters[] = ["TestClass$i", fn($obj) => "converted_$i"];
		}
		$converters[] = [TestClassA::class, fn(TestClassA $obj) => 'converted_A'];

		$lookup = new ConverterLookup($converters);
		$objA = new TestClassA();

		$this->assertSame('converted_A', $lookup->tryToConvert($objA));
	}

	public function testConstructorThrowsExceptionForDuplicateConverter(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Converter for class Tests\Unit\Helper\TestClassA is already defined.');

		new ConverterLookup([
			[TestClassA::class, fn(TestClassA $obj) => 'first'],
			[TestClassA::class, fn(TestClassA $obj) => 'second'],
		]);
	}

	public function testTryToConvertWithPrimitiveValues(): void
	{
		$lookup = new ConverterLookup([
			[TestClassA::class, fn(TestClassA $obj) => 'converted'],
		]);

		$this->assertSame('string', $lookup->tryToConvert('string'));
		$this->assertSame(42, $lookup->tryToConvert(42));
		$this->assertSame(3.14, $lookup->tryToConvert(3.14));
		$this->assertSame(true, $lookup->tryToConvert(true));
		$this->assertSame(false, $lookup->tryToConvert(false));
		$this->assertNull($lookup->tryToConvert(null));
		$this->assertSame([], $lookup->tryToConvert([]));
	}

	public function testTryToConvertWithExactClassMatch(): void
	{
		$lookup = new ConverterLookup([
			[TestClassA::class, fn(TestClassA $obj) => 'converted_A'],
			[TestClassB::class, fn(TestClassB $obj) => 'converted_B'],
		]);

		$objA = new TestClassA();
		$objB = new TestClassB();

		$this->assertSame('converted_A', $lookup->tryToConvert($objA));
		$this->assertSame('converted_B', $lookup->tryToConvert($objB));
	}

	public function testTryToConvertWithObjectWithoutConverter(): void
	{
		$lookup = new ConverterLookup([
			[TestClassA::class, fn(TestClassA $obj) => 'converted_A'],
		]);

		$objB = new TestClassB();
		$this->assertSame($objB, $lookup->tryToConvert($objB));
	}

	public function testTryToConvertWithMultipleConverters(): void
	{
		$converters = [
			[TestClassA::class, fn(TestClassA $obj) => 'converted_A'],
			[TestClassB::class, fn(TestClassB $obj) => 'converted_B'],
		];

		$lookup = new ConverterLookup($converters);
		$objA = new TestClassA();
		$objB = new TestClassB();

		$this->assertSame('converted_A', $lookup->tryToConvert($objA));
		$this->assertSame('converted_B', $lookup->tryToConvert($objB));
	}

	public function testTryToConvertWithParentClassMatch(): void
	{
		$lookup = new ConverterLookup([
			[TestParentClass::class, fn(TestParentClass $obj) => 'converted_parent'],
		]);

		$childObj = new TestChildClass();
		$this->assertSame('converted_parent', $lookup->tryToConvert($childObj));
	}

	public function testTryToConvertWithInterfaceMatch(): void
	{
		$lookup = new ConverterLookup([
			[TestInterface::class, fn(TestInterface $obj) => 'converted_interface'],
		]);

		$implementingObj = new TestImplementingClass();
		$this->assertSame('converted_interface', $lookup->tryToConvert($implementingObj));
	}

	public function testTryToConvertFirstMatchWins(): void
	{
		$lookup = new ConverterLookup([
			[TestInterface::class, fn(TestInterface $obj) => 'converted_interface'],
			[TestParentClass::class, fn(TestParentClass $obj) => 'converted_parent'],
			[TestChildImplementingClass::class, fn(TestChildImplementingClass $obj) => 'converted_exact'],
		]);

		$obj = new TestChildImplementingClass();
		// First matching instanceof wins
		// TestChildImplementingClass implements TestInterface, so first match wins
		$this->assertSame('converted_interface', $lookup->tryToConvert($obj));
	}

	public function testTryToConvertExactClassFirstWins(): void
	{
		$lookup = new ConverterLookup([
			[TestChildImplementingClass::class, fn(TestChildImplementingClass $obj) => 'converted_exact'],
			[TestInterface::class, fn(TestInterface $obj) => 'converted_interface'],
			[TestParentClass::class, fn(TestParentClass $obj) => 'converted_parent'],
		]);

		$obj = new TestChildImplementingClass();
		// When exact class is first, it wins
		$this->assertSame('converted_exact', $lookup->tryToConvert($obj));
	}

	public function testConvertArrayWithEmptyLookup(): void
	{
		$lookup = new ConverterLookup([]);

		$values = [
			'string' => 'value',
			'number' => 42,
			'object' => new TestClassA(),
		];

		$result = $lookup->convertArray($values);
		$this->assertSame($values, $result);
	}

	public function testConvertArrayWithObjectConversion(): void
	{
		$lookup = new ConverterLookup([
			[TestClassA::class, fn(TestClassA $obj) => 'converted_A'],
			[TestClassB::class, fn(TestClassB $obj) => 'converted_B'],
		]);

		$objA = new TestClassA();
		$objB = new TestClassB();

		$values = [
			'string' => 'value',
			'objA' => $objA,
			'objB' => $objB,
			'number' => 42,
		];

		$result = $lookup->convertArray($values);

		$this->assertSame([
			'string' => 'value',
			'objA' => 'converted_A',
			'objB' => 'converted_B',
			'number' => 42,
		], $result);
	}

	public function testConvertArrayWithNestedArrays(): void
	{
		$lookup = new ConverterLookup([
			[TestClassA::class, fn(TestClassA $obj) => 'converted_A'],
		]);

		$objA1 = new TestClassA();
		$objA2 = new TestClassA();
		$objB = new TestClassB();

		$values = [
			'nested' => [$objA1, 'string', $objA2, $objB, 42],
			'simple' => 'value',
		];

		$result = $lookup->convertArray($values);

		$this->assertSame([
			'nested' => ['converted_A', 'string', 'converted_A', $objB, 42],
			'simple' => 'value',
		], $result);
	}

	public function testConvertArrayWithObjectsWithoutConverter(): void
	{
		$lookup = new ConverterLookup([
			[TestClassA::class, fn(TestClassA $obj) => 'converted_A'],
		]);

		$objA = new TestClassA();
		$objB = new TestClassB();

		$values = [
			'objA' => $objA,
			'objB' => $objB,
		];

		$result = $lookup->convertArray($values);

		$this->assertSame([
			'objA' => 'converted_A',
			'objB' => $objB,
		], $result);
	}

	public function testConvertArrayMixedContent(): void
	{
		$lookup = new ConverterLookup([
			[TestClassA::class, fn(TestClassA $obj) => ['converted' => true]],
		]);

		$objA = new TestClassA();

		$values = [
			'string' => 'value',
			'number' => 42,
			'bool' => true,
			'null' => null,
			'array' => ['nested', $objA],
			'object' => $objA,
		];

		$result = $lookup->convertArray($values);

		$this->assertSame([
			'string' => 'value',
			'number' => 42,
			'bool' => true,
			'null' => null,
			'array' => ['nested', ['converted' => true]],
			'object' => ['converted' => true],
		], $result);
	}

	public function testEdgeCaseWithNullValues(): void
	{
		$lookup = new ConverterLookup([
			[TestClassA::class, fn(TestClassA $obj) => null],
		]);

		$objA = new TestClassA();
		$this->assertNull($lookup->tryToConvert($objA));

		$values = ['obj' => $objA];
		$result = $lookup->convertArray($values);
		$this->assertSame(['obj' => null], $result);
	}

	public function testConverterReturningDifferentTypes(): void
	{
		$lookup = new ConverterLookup([
			[TestClassA::class, fn(TestClassA $obj) => 'string'],
			[TestClassB::class, fn(TestClassB $obj) => 42],
		]);

		$objA = new TestClassA();
		$objB = new TestClassB();

		$this->assertSame('string', $lookup->tryToConvert($objA));
		$this->assertSame(42, $lookup->tryToConvert($objB));
	}

	public function testTryToConvertWithManyConverters(): void
	{
		$converters = [];
		for ($i = 1; $i <= 15; $i++) {
			$converters[] = ["TestClass$i", fn($obj) => "converted_$i"];
		}
		$converters[] = [TestClassA::class, fn(TestClassA $obj) => 'converted_A'];

		$lookup = new ConverterLookup($converters);
		$objA = new TestClassA();

		$this->assertSame('converted_A', $lookup->tryToConvert($objA));
	}

}

final class TestClassA
{
}

final class TestClassB
{
}

class TestParentClass
{
}

final class TestChildClass extends TestParentClass
{
}

interface TestInterface
{
}

final class TestImplementingClass implements TestInterface
{
}

final class TestChildImplementingClass extends TestParentClass implements TestInterface
{
}