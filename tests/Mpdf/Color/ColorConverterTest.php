<?php

namespace Mpdf\Color;

use Mockery;
use Mpdf\Mpdf;

class ColorConverterTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * @var \Mpdf\SizeConverter
	 */
	private $converter;

	private $mpdf;

	private $modeConverter;

	private $restrictor;

	protected function set_up()
	{
		parent::set_up();

		$this->mpdf = Mockery::spy(Mpdf::class);
		$this->restrictor = Mockery::mock(ColorSpaceRestrictor::class);
		$this->modeConverter = Mockery::mock(ColorModeConverter::class);
		$this->converter = new ColorConverter(
			$this->mpdf,
			$this->modeConverter,
			$this->restrictor
		);
	}

	protected function tear_down()
	{
		parent::tear_down();

		Mockery::close();
	}

	/**
	 * @dataProvider convertColorsProvider
	 *
	 * @param string $input
	 * @param string $output
	 */
	public function testConvert($input, $output, $method = null, $times = null, $result = null)
	{
		if ($method) {
			$this->modeConverter->shouldReceive($method)->times($times)->andReturn($result);
		}

		$this->assertSame($output, $this->converter->convert($input));
	}

	public function convertColorsProvider()
	{
		return [

			['#22pp44', "3\"\x00D\x00\x00"],
			['#aaaazz', "3\xaa\xaa\x00\x00\x00"],
			['#gghhii', "3\x00\x00\x00\x00\x00"],

			['#220044', "3\"\x00D\x00\x00"],
			[255, "1\xff\x00\x00\x00\x00"],
			[85, "1U\x00\x00\x00\x00"],
			[0, "1\x00\x00\x00\x00\x00"],

			['#CCC', "3\xcc\xcc\xcc\x00\x00"],
			['#aaaacc', "3\xaa\xaa\xcc\x00\x00"],

			['aqua', "3\x00\xff\xff\x00\x00"],

			['rgb(123, 147, 156)', "3{\x93\x9c\x00\x00"],
			['rgb(66%, 80%, 20%)', "3\xa8\xcc3\x00\x00"],

			['rgba(123, 147, 156, 0.55)', "5{\x93\x9c7\x00"],

			['cmyk(84, 74, 68, 74)', "4TJDJ\x00"],
			['cmyka(46%, 20%, 88%, 22%, 0.6)', "6.\x14X\x16<"],

			['device-cmyk(84, 74, 68, 74)', "4TJDJ\x00"],
			['device-cmyka(46%, 20%, 88%, 22%, 0.33)', "6.\x14X\x16!"],

			['hsl(123, 55%, 20%)', "3\x17O\x1a\x00\x00", 'hsl2rgb', 1, [23, 79, 26]],
			['hsla(123, 55%, 20%, 0.25)', "5\x17O\x1a\x19\x00", 'hsl2rgb', 1, [23, 79, 26]],
			['hsl(66%, 80%, 20%)', "3T\\\n\x00\x00", 'hsl2rgb', 1, [84, 92, 10]],

			// ['spot(PANTONE 534 EC, 100%, 85, 65, 47, 9)', "2\x00d\x00\x00\x00"], // move Mpdf::$spotColors to colorconverter for better testability

			['inherit', false],
			['transparent', false],

		];
	}

	/**
	 * rgb(), rgba(), hsl() and hsla() written with spaces and a slash before the alpha, with a fourth comma-separated
	 * argument as the alpha, with a percentage alpha, and with the hue as an angle
	 *
	 * @dataProvider modernSyntaxProvider
	 *
	 * @param string $input
	 * @param string $output
	 */
	public function testConvertModernSyntax($input, $output)
	{
		$converter = new ColorConverter($this->mpdf, new ColorModeConverter(), $this->restrictor);

		$this->assertSame($output, $converter->convert($input));
	}

	/**
	 * Each colour, and its binary string: the mode, then the channels, then the alpha as a percentage
	 *
	 * @return string[][]
	 */
	public function modernSyntaxProvider()
	{
		return [
			['rgb(255 0 0)', "3\xff\x00\x00\x00\x00"],
			['rgb( 255  128 0 )', "3\xff\x80\x00\x00\x00"],
			['rgb(100% 0% 50%)', "3\xff\x00\x7f\x00\x00"],
			['rgb(255 0 0 / 50%)', "5\xff\x00\x002\x00"],
			['rgb(255 0 0/.25)', "5\xff\x00\x00\x19\x00"],
			['rgba(255 0 0 / 0.5)', "5\xff\x00\x002\x00"],
			['rgba(255 0 0)', "3\xff\x00\x00\x00\x00"],
			['rgb(255, 0, 0, 0.5)', "5\xff\x00\x002\x00"],
			['rgba(255, 0, 0, 50%)', "5\xff\x00\x002\x00"],

			['hsl(120 100% 50%)', "3\x00\xff\x00\x00\x00"],
			['hsl(120 100% 50% / .5)', "5\x00\xff\x002\x00"],
			['hsla(120 100% 50% / 50%)', "5\x00\xff\x002\x00"],
			['hsl(120, 100%, 50%, 0.5)', "5\x00\xff\x002\x00"],
			['hsla(120, 100%, 50%)', "3\x00\xff\x00\x00\x00"],

			['hsl(120deg 100% 50%)', "3\x00\xff\x00\x00\x00"],
			['hsl(120deg, 100%, 50%)', "3\x00\xff\x00\x00\x00"],
			['hsl(0.5turn 100% 50%)', "3\x00\xff\xff\x00\x00"],
			['hsl(200grad 100% 50%)', "3\x00\xff\xff\x00\x00"],
			['hsl(3.14159265rad 100% 50%)', "3\x00\xff\xff\x00\x00"],
			['hsl(-120 100% 50%)', "3\x00\x00\xff\x00\x00"],
			['hsl(480 100% 50%)', "3\x00\xff\x00\x00\x00"],

			['rgb(var(--red))', "3\x00\x00\x00\x00\x00"],
			['rgba(var(--red))', "3\x00\x00\x00\x00\x00"],
			['cmyk(var(--black))', "4\x00\x00\x00d\x00"],
		];
	}

	/**
	 * Hex colours with an alpha digit or pair, channels and alphas outside their range clamped to it rather than
	 * wrapped, and rebeccapurple beside the named colour mPDF already knew
	 *
	 * @dataProvider colorValuesProvider
	 *
	 * @param string $input
	 * @param string $output
	 */
	public function testConvertColorValues($input, $output)
	{
		$converter = new ColorConverter($this->mpdf, new ColorModeConverter(), $this->restrictor);

		$this->assertSame($output, $converter->convert($input));
	}

	/**
	 * Each colour, and its binary string: the mode, then the channels, then the alpha as a percentage
	 *
	 * @return string[][]
	 */
	public function colorValuesProvider()
	{
		return [
			['#f008', "5\xff\x00\x005\x00"],
			['#F00F', "5\xff\x00\x00d\x00"],
			['#ff000080', "5\xff\x00\x002\x00"],
			['#ff0000ff', "5\xff\x00\x00d\x00"],
			['#ff000000', "5\xff\x00\x00\x00\x00"],

			['rgb(255.5, 0, 0)', "3\xff\x00\x00\x00\x00"],
			['rgb(300, -20, 127.6)', "3\xff\x00\x80\x00\x00"],
			['rgb(150%, 0%, 0%)', "3\xff\x00\x00\x00\x00"],
			['rgba(0, 0, 255, 1.5)', "5\x00\x00\xffd\x00"],
			['rgba(0, 0, 255, -1)', "5\x00\x00\xff\x00\x00"],
			['rgba(0, 0, 255, 150%)', "5\x00\x00\xffd\x00"],
			['hsl(120, 150%, 50%)', "3\x00\xff\x00\x00\x00"],
			['hsl(120, 100%, 150%)', "3\xff\xff\xff\x00\x00"],
			['cmyk(150, 0, -5, 100)', "4d\x00\x00d\x00"],
			['cmyka(0, 0, 0, 100, 2)', "6\x00\x00\x00dd"],
			['300', "1\xff\x00\x00\x00\x00"],

			['rebeccapurple', "3f3\x99\x00\x00"],
			['violetred', "3\xd0\x20\x90\x00\x00"],
		];
	}

	public function testConvertUnknownSpotColor()
	{
		$this->expectException(\Mpdf\MpdfException::class);
		$this->expectExceptionMessage('Undefined spot color "PANTONE 534 EC"');

		$this->converter->convert('spot(PANTONE 534 EC, 100%, 85, 65, 47)');
	}

	/**
	 * @dataProvider colAtoStringProvider
	 *
	 * @param string $output
	 * @param string $input
	 */
	public function testColAtoString($output, $input)
	{
		$this->assertSame($output, $this->converter->colAtoString($input));
	}

	public function colAtoStringProvider()
	{
		return [
			['rgb(255, 255, 255)', "1\xff\x00\x00\x00\x00"],
			['rgb(85, 85, 85)', "1U\x00\x00\x00\x00"],
			['rgb(0, 0, 0)', "1\x00\x00\x00\x00\x00"],
			['rgb(204, 204, 204)', "3\xcc\xcc\xcc\x00\x00"],
			['rgb(170, 170, 204)', "3\xaa\xaa\xcc\x00\x00"],
			['rgb(0, 255, 255)', "3\x00\xff\xff\x00\x00"],
			['rgb(123, 147, 156)', "3{\x93\x9c\x00\x00"],
			['rgb(168, 204, 51)', "3\xa8\xcc3\x00\x00"],
			['rgba(123, 147, 156, 0.55)', "5{\x93\x9c7\x00"],
			['cmyk(84, 74, 68, 74)', "4TJDJ\x00"],
			['cmyka(46, 20, 88, 22, 0.60)', "6.\x14X\x16<"],
			['rgb(23, 79, 26)', "3\x17O\x1a\x00\x00"],
			['rgba(23, 79, 26, 0.25)', "5\x17O\x1a\x19\x00"],
			['rgb(84, 92, 10)', "3T\\\n\x00\x00"],
			['cmyk(84, 74, 68, 74)', "4TJDJ\x00"],
			['cmyka(46, 20, 88, 22, 0.33)', "6.\x14X\x16!"],
			['spot(0, 100)', "2\x00d\x00\x00\x00"],
		];
	}

	/**
	 * @dataProvider lightenColorsProvider
	 *
	 * @param string $input
	 * @param string $output
	 */
	public function testLighten($input, $output, $method = null, $times = null, $result = null, $method2 = null, $times2 = null, $result2 = null)
	{
		if ($method) {
			$this->modeConverter->shouldReceive($method)->times($times)->andReturn($result);
		}

		if ($method2) {
			$this->modeConverter->shouldReceive($method2)->times($times2)->andReturn($result2);
		}

		$this->assertSame($output, $this->converter->lighten($input));
	}

	public function testLightenWithArray()
	{
		$this->expectException(\Mpdf\MpdfException::class);

		$this->converter->lighten([]);
	}

	public function lightenColorsProvider()
	{
		return [
			["3\x00\x00\xff\x00\x00", "3\xcc\xcc\xcc\x00\x00", 'rgb2hsl', 1, [1, 2, 3], 'hsl2rgb', 1, [204, 204, 204]],
			["4TJDJ\x00", "4@606\x00"],
			["1\xff\x00\x00\x00\x00", "1\xff\x00\x00\x00\x00"],
			["1U\x00\x00\x00\x00", "1u\x00\x00\x00\x00"],
			["1\x00\x00\x00\x00\x00", "1 \x00\x00\x00\x00"],
		];
	}

	/**
	 * @dataProvider darkenColorsProvider
	 *
	 * @param string $input
	 * @param string $output
	 */
	public function testDarken($input, $output, $method = null, $times = null, $result = null, $method2 = null, $times2 = null, $result2 = null)
	{
		if ($method) {
			$this->modeConverter->shouldReceive($method)->times($times)->andReturn($result);
		}

		if ($method2) {
			$this->modeConverter->shouldReceive($method2)->times($times2)->andReturn($result2);
		}

		$this->assertSame($output, $this->converter->darken($input));
	}

	public function testDarkenWithArray()
	{
		$this->expectException(\Mpdf\MpdfException::class);

		$this->converter->darken([]);
	}

	public function darkenColorsProvider()
	{
		return [
			["3\x00\x00\xff\x00\x00", "3TTT\x00\x00", 'rgb2hsl', 1, [1, 2, 3], 'hsl2rgb', 1, [84, 84, 84]],
			["4TJDJ\x00", "4d^X^\x00"],
			["1\xff\x00\x00\x00\x00", "1\xdf\x00\x00\x00\x00"],
			["1U\x00\x00\x00\x00", "15\x00\x00\x00\x00"],
			["1\x00\x00\x00\x00\x00", "1\x00\x00\x00\x00\x00"],
		];
	}

	/**
	 * @dataProvider invertColorsProvider
	 *
	 * @param string $input
	 * @param string $output
	 */
	public function testInvert($input, $output)
	{
		$this->assertSame($output, $this->converter->invert($input));
	}

	public function testInvertWithArray()
	{
		$this->expectException(\Mpdf\MpdfException::class);

		$this->converter->invert([]);
	}

	public function testInvertNonRgb()
	{
		$this->expectException(\Mpdf\MpdfException::class);

		$this->converter->invert("2\x00d\x00\x00\x00");
	}

	public function invertColorsProvider()
	{
		return [
			["3\x00\x00\xff\x00\x00", [3, 255, 255, 0]],
			["4TJDJ\x00", [4, 16, 26, 32, 26]],
			["3\xaa\xaa\xcc\x00\x00", [3, 85, 85, 51]],
			["1\xff\x00\x00\x00\x00", [1, 0]],
			["1U\x00\x00\x00\x00", [1, 170]],
			["1\x00\x00\x00\x00\x00", [1, 255]],

		];
	}

	/**
	 * @dataProvider colorFormsProvider
	 *
	 * @param string $color
	 * @param bool $isColor
	 */
	public function testIsColor($color, $isColor)
	{
		$this->assertSame($isColor, $this->converter->isColor($color));
	}

	/**
	 * A value, and whether convert() knows its form
	 *
	 * @return array[]
	 */
	public function colorFormsProvider()
	{
		return [
			['red', true],
			[' ForestGreen ', true],
			['#0f0', true],
			['#0F08', true],
			['#00ff00', true],
			['#00ff0080', true],
			['128', true],
			['rgb(0, 255, 0)', true],
			['RGBA(0, 255, 0, 0.5)', true],
			['hsl(120, 100%, 50%)', true],
			['cmyk(0, 0, 0, 100)', true],
			['device-cmyka(0, 0, 0, 100, 0.5)', true],
			['spot(PANTONE 185 C, 100%)', true],
			['bogus', false],
			['none', false],
			['#00ff0', false],
			['#ggg', false],
			['lab(50% 40 59)', false],
			['oklch(0.7 0.1 120)', false],
			['rgb(0, 255, 0) extra', false],
			['', false],
		];
	}

	public function testRestrictColorSpace()
	{
		$mpdf = Mockery::mock(Mpdf::class);
		$mpdf->PDFA = true;

		$this->restrictor->shouldReceive('restrictColorSpace')
			->with([ColorConverter::MODE_RGB, 123, 147, 156], 'rgb(123, 147, 156)', [])
			->once();

		$converter = new ColorConverter(
			$mpdf,
			$this->modeConverter,
			$this->restrictor
		);
		$this->assertSame('', $converter->convert('rgb(123, 147, 156)'));
	}

}
