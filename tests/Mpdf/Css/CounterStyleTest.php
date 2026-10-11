<?php

namespace Mpdf\Css;

/**
 * The counter styles of CSS Counter Styles that mPDF's number conversions do not cover, as browsers write them
 */
class CounterStyleTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * Each style writes a counter value as CSS Counter Styles defines it
	 *
	 * @dataProvider providerRepresentations
	 *
	 * @param string $type
	 * @param int $n
	 * @param string $expected
	 */
	public function testRepresentation($type, $n, $expected)
	{
		$this->assertSame($expected, CounterStyle::represent($n, $type));
	}

	/**
	 * @return array[] A style, a counter value and how it is written
	 */
	public function providerRepresentations()
	{
		return [
			['decimal-leading-zero', 1, '01'],
			['decimal-leading-zero', 0, '00'],
			['decimal-leading-zero', 123, '123'],
			['decimal-leading-zero', -1, '-1'],
			['upper-greek', 1, 'Α'],
			['upper-greek', 24, 'Ω'],
			['upper-greek', 25, 'ΑΑ'],
			['upper-greek', 0, '0'],
			['armenian', 1, 'Ա'],
			['armenian', 1984, 'ՌՋՁԴ'],
			['upper-armenian', 10, 'Ժ'],
			['lower-armenian', 3, 'գ'],
			['armenian', 9999, 'ՔՋՂԹ'],
			['armenian', 10000, '10000'],
			['georgian', 19, 'ით'],
			['georgian', 10000, 'ჵ'],
			['georgian', 19999, 'ჵჰშჟთ'],
			['georgian', 20000, '20000'],
			['hiragana', 1, 'あ'],
			['hiragana', 48, 'ん'],
			['hiragana', 49, 'ああ'],
			['katakana', 2, 'イ'],
			['hiragana-iroha', 3, 'は'],
			['katakana-iroha', 47, 'ス'],
			['katakana-iroha', -2, '-2'],
			['disclosure-open', 5, '▾'],
			['disclosure-closed', 5, '▸'],
		];
	}

	/**
	 * The Japanese styles end in an ideographic comma, the disclosure triangles in nothing, and the others in the
	 * suffix mPDF is configured with
	 */
	public function testMarker()
	{
		$this->assertSame('い、', CounterStyle::marker(2, 'hiragana', '.'));
		$this->assertSame('02)', CounterStyle::marker(2, 'decimal-leading-zero', ')'));
		$this->assertSame('▾', CounterStyle::marker(2, 'disclosure-open', '.'));
		$this->assertSame('Ab ', CounterStyle::marker(2, '"Ab "', '.'));
	}

	/**
	 * A string is drawn and is not a style; mPDF's own styles and unknown names are left to the code that drew them
	 */
	public function testWhatIsDrawnHere()
	{
		$this->assertTrue(CounterStyle::draws('upper-greek'));
		$this->assertTrue(CounterStyle::draws("'→ '"));
		$this->assertFalse(CounterStyle::isStyle("'→ '"));
		$this->assertFalse(CounterStyle::draws('decimal'));
		$this->assertFalse(CounterStyle::draws('lower-greek'));
		$this->assertFalse(CounterStyle::draws('upper-greekish'));
		$this->assertFalse(CounterStyle::draws('"a" "b"'));
	}
}
