<?php

namespace Mpdf;

/**
 * mPDF's rotate property turns an absolutely positioned block a quarter or half turn, whether the angle is written
 * with "deg" or without, and on every PHP version
 */
class PositionedBlockRotateTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * The first four numbers of the matrix transformRotate() writes for each turn
	 */
	const CLOCKWISE = '0.0000 -1.0000 1.0000 0.0000';
	const COUNTER_CLOCKWISE = '0.0000 1.0000 -1.0000 0.0000';
	const HALF_TURN = '-1.0000 -0.0000 0.0000 -1.0000';

	/**
	 * The turns a positioned block with the given rotate value is drawn with
	 *
	 * @param string $rotate
	 *
	 * @return string[]
	 */
	private function turns($rotate)
	{
		$mpdf = new Mpdf();
		$mpdf->compress = false;
		$mpdf->WriteHTML('<div style="position: absolute; top: 50mm; left: 50mm; width: 40mm; rotate: ' . $rotate . '">Turned</div>');
		$pdf = $mpdf->Output('', 'S');

		return array_values(array_filter([self::CLOCKWISE, self::COUNTER_CLOCKWISE, self::HALF_TURN], function ($turn) use ($pdf) {
			return strpos($pdf, $turn . ' ') !== false;
		}));
	}

	/**
	 * The block is drawn turned the way the value gives, or not turned at all
	 *
	 * @dataProvider rotateProvider
	 *
	 * @param string $rotate
	 * @param string[] $expected
	 */
	public function testRotate($rotate, $expected)
	{
		$this->assertSame($expected, $this->turns($rotate));
	}

	/**
	 * rotate values, with the turn each gives
	 *
	 * @return array
	 */
	public function rotateProvider()
	{
		return [
			'90' => ['90', [self::CLOCKWISE]],
			'90deg' => ['90deg', [self::CLOCKWISE]],
			'-90' => ['-90', [self::COUNTER_CLOCKWISE]],
			'-90deg' => ['-90deg', [self::COUNTER_CLOCKWISE]],
			'270deg is -90deg' => ['270deg', [self::COUNTER_CLOCKWISE]],
			'-270deg is 90deg' => ['-270deg', [self::CLOCKWISE]],
			'180' => ['180', [self::HALF_TURN]],
			'180deg' => ['180deg', [self::HALF_TURN]],
			'-180deg is 180deg' => ['-180deg', [self::HALF_TURN]],
			'45deg is not supported' => ['45deg', []],
			'not an angle' => ['auto', []],
		];
	}

}
