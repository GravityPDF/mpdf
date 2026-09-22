<?php

namespace Mpdf\Color;

use Mockery;

class ColorModeConverterTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * @var \Mpdf\SizeConverter
	 */
	private $converter;

	protected function set_up()
	{
		parent::set_up();

		$this->converter = new ColorModeConverter();
	}

	/**
	 * @dataProvider hsl2rgbProvider
	 *
	 * @param string $input
	 * @param string $output
	 */
	public function testHsl2rgb($input, $output)
	{
		$this->assertEquals($output, $this->converter->hsl2rgb(...$input));
	}

	public function hsl2rgbProvider()
	{
		return [

			[[1.23, 0.55, 0.20], [58, 79, 23]],
			[[0.3416666666666667, 0.55, 0.2], [23, 79, 26]],
			[[0.18333333333333332, 0.8, 0.2], [84, 92, 10]],
			[[0, 0, 0.8], [204, 204, 204]],
			[[0, 1, 0.5], [255, 0, 0]],

		];
	}

	/**
	 * @dataProvider rgb2hslProvider
	 *
	 * @param string $input
	 * @param string $output
	 */
	public function testRgb2hsl($input, $output)
	{
		$this->assertEquals($output, $this->converter->rgb2hsl(...$input));
	}

	public function rgb2hslProvider()
	{
		return [

			[[58, 79, 23], [0.22916666666666652, -0.56, 51]],
			[[23, 79, 26], [0.34226190476190477, -0.56, 51]],
			[[84, 92, 10], [0.18292682926829273, -0.82, 51]],
			[[204, 204, 204], [0, 0, 204]],

		];
	}

	/**
	 * @dataProvider rgb2grayProvider
	 *
	 * @param string $input
	 * @param string $output
	 */
	public function testRgb2gray($input, $output)
	{
		$this->assertEquals($output, $this->converter->rgb2gray($input));
	}

	public function rgb2grayProvider()
	{
		return [

			[[ColorConverter::MODE_RGB, 255, 124, 175], [1, 153.83999999999997]],

		];
	}

	/**
	 * @dataProvider cmyk2grayProvider
	 *
	 * @param string $input
	 * @param string $output
	 */
	public function testCmyk2gray($input, $output)
	{
		$this->assertEquals($output, $this->converter->cmyk2gray($input));
	}

	public function cmyk2grayProvider()
	{
		return [

			[[ColorConverter::MODE_CMYK, 75, 12, 75, 74], [1, 45.66]],

		];
	}

	/**
	 * @dataProvider rgb2cmykProvider
	 *
	 * @param string $input
	 * @param string $output
	 */
	public function testRgb2cmyk($input, $output)
	{
		$this->assertEquals($output, $this->converter->rgb2cmyk($input));
	}

	public function rgb2cmykProvider()
	{
		return [

			[[ColorConverter::MODE_RGB, 75, 12, 75], [ColorConverter::MODE_CMYK, 0, 83.99999999999999, 0.0, 70.58823529411764]],
			[[ColorConverter::MODE_RGBA, 75, 12, 75, 0.5], [ColorConverter::MODE_CMYKA, 0.0, 83.99999999999999, 0.0, 70.58823529411764, 0.5]],
			[[ColorConverter::MODE_RGB, 16, 58, 16], [ColorConverter::MODE_CMYK, 72.41379310344828, 0.0, 72.41379310344828, 77.25490196078432]],

		];
	}

	/**
	 * A neutral colour is the black ink alone, black included: painting black as all four inks at once
	 * is 400% total area coverage, past what any press allows, and a jump away from every colour near it
	 *
	 * @dataProvider blackProvider
	 *
	 * @param int   $grey  The red, green and blue of a neutral colour
	 * @param float $black The percentage of black ink it is made of
	 */
	public function testANeutralColourIsTheBlackInkAlone($grey, $black)
	{
		$cmyk = $this->converter->rgb2cmyk([ColorConverter::MODE_RGB, $grey, $grey, $grey]);

		$this->assertSame(ColorConverter::MODE_CMYK, $cmyk[0]);
		$this->assertEqualsWithDelta([0, 0, 0], [$cmyk[1], $cmyk[2], $cmyk[3]], 0.001, 'no cyan, magenta or yellow');
		$this->assertEqualsWithDelta($black, $cmyk[4], 0.05);
	}

	/**
	 * @return array[] Neutral colours from black upwards, and the black ink each is made of
	 */
	public function blackProvider()
	{
		return [
			'black' => [0, 100.0],
			'all but black' => [1, 99.6],
			'near black' => [8, 96.9],
			'mid grey' => [128, 49.8],
		];
	}

	/**
	 * Black is continuous with the colours beside it, rather than jumping from one ink to four
	 */
	public function testBlackIsContinuousWithTheColoursBesideIt()
	{
		$black = $this->converter->rgb2cmyk([ColorConverter::MODE_RGB, 0, 0, 0]);
		$next = $this->converter->rgb2cmyk([ColorConverter::MODE_RGB, 1, 1, 1]);

		$this->assertEqualsWithDelta($next[1], $black[1], 0.5);
		$this->assertEqualsWithDelta($next[2], $black[2], 0.5);
		$this->assertEqualsWithDelta($next[3], $black[3], 0.5);
		$this->assertEqualsWithDelta($next[4], $black[4], 0.5);
	}

	/**
	 * Black with transparency keeps its alpha, and is the black ink alone
	 */
	public function testBlackWithTransparencyIsTheBlackInkAlone()
	{
		$this->assertSame(
			[ColorConverter::MODE_CMYKA, 0, 0, 0, 100, 50],
			$this->converter->rgb2cmyk([ColorConverter::MODE_RGBA, 0, 0, 0, 50])
		);
	}

	/**
	 * @dataProvider cmyk2rgbProvider
	 *
	 * @param string $input
	 * @param string $output
	 */
	public function testCmyk2rgb($input, $output)
	{
		$this->assertEquals($output, $this->converter->cmyk2rgb($input));
	}

	public function cmyk2rgbProvider()
	{
		return [

			[[ColorConverter::MODE_CMYK, 75, 12, 75, 74], [3, 16, 58, 16]],

		];
	}

	/**
	 * @dataProvider hue2rgbProvider
	 *
	 * @param string $input
	 * @param string $output
	 */
	public function testHue2rgb($input, $output)
	{
		$this->assertEquals($output, $this->converter->hue2rgb(...$input));
	}

	public function hue2rgbProvider()
	{
		return [

			[[75, 12, 75], 75],

		];
	}

}
