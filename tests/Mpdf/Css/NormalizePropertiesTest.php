<?php

namespace Mpdf\Css;

use Mpdf\Color\ColorConverter;
use Mpdf\Color\ColorModeConverter;
use Mpdf\Color\ColorSpaceRestrictor;
use Mpdf\CssMode;
use Mpdf\Mpdf;
use Mpdf\SizeConverter;
use Psr\Log\NullLogger;

class NormalizePropertiesTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * The longhands the background shorthand sets, with their initial values, in the order it sets them
	 */
	const BACKGROUND_INITIAL_VALUES = [
		'BACKGROUND-COLOR' => 'transparent',
		'BACKGROUND-IMAGE' => '',
		'BACKGROUND-REPEAT' => 'repeat',
		'BACKGROUND-POSITION' => '0% 0%',
		'BACKGROUND-SIZE' => 'auto',
		'BACKGROUND-ORIGIN' => 'padding-box',
		'BACKGROUND-CLIP' => 'border-box',
	];

	/**
	 * @var \Mpdf\Css\NormalizeProperties
	 */
	private $normalizeProperties;

	private $mpdf;

	public function set_up()
	{
		parent::set_up();

		$this->mpdf = new Mpdf();
		$logger = new NullLogger();
		$sizeConverter = new SizeConverter(96, 11, $this->mpdf, $logger);
		$colorModeConverter = new ColorModeConverter();
		$colorSpaceRestrictor = new ColorSpaceRestrictor($this->mpdf, $colorModeConverter);
		$colorConverter = new ColorConverter($this->mpdf, $colorModeConverter, $colorSpaceRestrictor);

		$this->normalizeProperties = new NormalizeProperties($this->mpdf, $sizeConverter, $colorConverter);
	}

	public function tear_down()
	{
		unset($this->normalizeProperties, $this->mpdf);

		parent::tear_down();
	}

	public function testNormalize()
	{
		$prop = [
			'MARGIN' => '10px',
			'PADDING' => '5px 10px',
			'BORDER' => '1px solid #000000',
			'BACKGROUND' => '#ffffff url(bg.jpg) no-repeat top left',
			'FONT' => '12px/1.5 Arial, sans-serif'
		];

		$expected = [
			'MARGIN-TOP' => '10px',
			'MARGIN-RIGHT' => '10px',
			'MARGIN-BOTTOM' => '10px',
			'MARGIN-LEFT' => '10px',
			'PADDING-TOP' => '5px',
			'PADDING-RIGHT' => '10px',
			'PADDING-BOTTOM' => '5px',
			'PADDING-LEFT' => '10px',
			'BORDER-TOP' => '1px solid #000000',
			'BORDER-RIGHT' => '1px solid #000000',
			'BORDER-BOTTOM' => '1px solid #000000',
			'BORDER-LEFT' => '1px solid #000000',
			'BACKGROUND-COLOR' => '#ffffff',
			'BACKGROUND-IMAGE' => 'bg.jpg',
			'BACKGROUND-REPEAT' => 'no-repeat',
			'BACKGROUND-POSITION' => '0% 0%',
			'FONT-FAMILY' => 'arial',
			'FONT-SIZE' => '12px',
			'LINE-HEIGHT' => '1.5',
			'FONT-STYLE' => 'normal',
			'FONT-WEIGHT' => 'normal',
		];

		$result = $this->normalizeProperties->normalize($prop);

		foreach ($expected as $k => $v) {
			$this->assertArrayHasKey($k, $result);
			$this->assertEquals($v, $result[$k]);
		}
	}

	public function testNormalizeEmpty()
	{
		$this->assertEquals([], $this->normalizeProperties->normalize([]));
		$this->assertEquals([], $this->normalizeProperties->normalize(null));
	}

	/**
	 * @dataProvider providerBorderRadius
	 */
	public function testNormalizeBorderRadius($prop, $expected)
	{
		$result = $this->normalizeProperties->normalize($prop);

		foreach ($expected as $k => $v) {
			$this->assertArrayHasKey($k, $result, "Missing key: $k");
			$this->assertEquals($v, $result[$k], "Mismatch for key: $k");
		}
	}

	public function providerBorderRadius()
	{
		return [
			[
				['BORDER-RADIUS' => '10px'],
				[
					'BORDER-TOP-LEFT-RADIUS-H' => '10px', 'BORDER-TOP-LEFT-RADIUS-V' => '10px',
					'BORDER-TOP-RIGHT-RADIUS-H' => '10px', 'BORDER-TOP-RIGHT-RADIUS-V' => '10px',
					'BORDER-BOTTOM-RIGHT-RADIUS-H' => '10px', 'BORDER-BOTTOM-RIGHT-RADIUS-V' => '10px',
					'BORDER-BOTTOM-LEFT-RADIUS-H' => '10px', 'BORDER-BOTTOM-LEFT-RADIUS-V' => '10px',
				]
			],
			[
				['BORDER-RADIUS' => '10px 20px'],
				[
					'BORDER-TOP-LEFT-RADIUS-H' => '10px', 'BORDER-TOP-LEFT-RADIUS-V' => '10px',
					'BORDER-TOP-RIGHT-RADIUS-H' => '20px', 'BORDER-TOP-RIGHT-RADIUS-V' => '20px',
					'BORDER-BOTTOM-RIGHT-RADIUS-H' => '10px', 'BORDER-BOTTOM-RIGHT-RADIUS-V' => '10px',
					'BORDER-BOTTOM-LEFT-RADIUS-H' => '20px', 'BORDER-BOTTOM-LEFT-RADIUS-V' => '20px',
				]
			],
			[
				['BORDER-RADIUS' => '10px 20px / 5px 15px'],
				[
					'BORDER-TOP-LEFT-RADIUS-H' => '10px', 'BORDER-TOP-LEFT-RADIUS-V' => '5px',
					'BORDER-TOP-RIGHT-RADIUS-H' => '20px', 'BORDER-TOP-RIGHT-RADIUS-V' => '15px',
					'BORDER-BOTTOM-RIGHT-RADIUS-H' => '10px', 'BORDER-BOTTOM-RIGHT-RADIUS-V' => '5px',
					'BORDER-BOTTOM-LEFT-RADIUS-H' => '20px', 'BORDER-BOTTOM-LEFT-RADIUS-V' => '15px',
				]
			],
			[
				['BORDER-RADIUS' => '10px 20px 30px'],
				[
					'BORDER-TOP-LEFT-RADIUS-H' => '10px', 'BORDER-TOP-LEFT-RADIUS-V' => '10px',
					'BORDER-TOP-RIGHT-RADIUS-H' => '20px', 'BORDER-TOP-RIGHT-RADIUS-V' => '20px',
					'BORDER-BOTTOM-RIGHT-RADIUS-H' => '30px', 'BORDER-BOTTOM-RIGHT-RADIUS-V' => '30px',
					'BORDER-BOTTOM-LEFT-RADIUS-H' => '20px', 'BORDER-BOTTOM-LEFT-RADIUS-V' => '20px',
				]
			],
			[
				['BORDER-RADIUS' => '10px 20px 30px 40px'],
				[
					'BORDER-TOP-LEFT-RADIUS-H' => '10px', 'BORDER-TOP-LEFT-RADIUS-V' => '10px',
					'BORDER-TOP-RIGHT-RADIUS-H' => '20px', 'BORDER-TOP-RIGHT-RADIUS-V' => '20px',
					'BORDER-BOTTOM-RIGHT-RADIUS-H' => '30px', 'BORDER-BOTTOM-RIGHT-RADIUS-V' => '30px',
					'BORDER-BOTTOM-LEFT-RADIUS-H' => '40px', 'BORDER-BOTTOM-LEFT-RADIUS-V' => '40px',
				]
			],
		];
	}

	public function testNormalizeListStyle()
	{
		$prop = ['LIST-STYLE' => 'square inside url(bullet.png)'];
		$expected = [
			'LIST-STYLE-TYPE' => 'square',
			'LIST-STYLE-POSITION' => 'inside',
			'LIST-STYLE-IMAGE' => 'bullet.png'
		];

		$result = $this->normalizeProperties->normalize($prop);

		foreach ($expected as $k => $v) {
			$this->assertEquals($v, $result[$k]);
		}
	}

	public function testNormalizeTextAlign()
	{
		$prop = ['TEXT-ALIGN' => 'center'];
		$result = $this->normalizeProperties->normalize($prop);
		$this->assertEquals('center', $result['TEXT-ALIGN']);

		$prop = ['TEXT-ALIGN' => 'decimal "DP"'];
		$result = $this->normalizeProperties->normalize($prop);
		$this->assertEquals('decimal "dp"', $result['TEXT-ALIGN']);
	}

	/**
	 * @dataProvider providerMarginShorthand
	 */
	public function testMarginShorthand($input, $expected)
	{
		$result = $this->normalizeProperties->normalize(['MARGIN' => $input]);
		$this->assertEquals($expected['T'], $result['MARGIN-TOP']);
		$this->assertEquals($expected['R'], $result['MARGIN-RIGHT']);
		$this->assertEquals($expected['B'], $result['MARGIN-BOTTOM']);
		$this->assertEquals($expected['L'], $result['MARGIN-LEFT']);
	}

	public function providerMarginShorthand()
	{
		return [
			['10px', ['T' => '10px', 'R' => '10px', 'B' => '10px', 'L' => '10px']],
			['10px 20px', ['T' => '10px', 'R' => '20px', 'B' => '10px', 'L' => '20px']],
			['10px 20px 30px', ['T' => '10px', 'R' => '20px', 'B' => '30px', 'L' => '20px']],
			['10px 20px 30px 40px', ['T' => '10px', 'R' => '20px', 'B' => '30px', 'L' => '40px']],
			['10px 20px 30px 40px 50px', ['T' => '10px', 'R' => '20px', 'B' => '30px', 'L' => '40px']],
		];
	}

	/**
	 * @dataProvider providerBorderString
	 */
	public function testBorderStringNormalization($input, $expected)
	{
		$result = $this->normalizeProperties->normalize(['BORDER-TOP' => $input]);
		$this->assertEquals($expected, $result['BORDER-TOP']);
	}

	public function providerBorderString()
	{
		return [
			['solid', 'medium solid currentcolor'],
			['#ff0000', 'medium none #ff0000'],
			['2px', '2px none currentcolor'],
			['2px solid', '2px solid currentcolor'],
			['solid #ff0000', 'medium solid #ff0000'],
			['2px #ff0000', '2px none #ff0000'],
			['2px solid #ff0000', '2px solid #ff0000'],
			['#ff0000 2px solid', '2px solid #ff0000'],
			['none', 'medium none currentcolor'],
			['solid #c00 2mm', '2mm solid #c00'],
			['2mm #c00 solid', '2mm solid #c00'],
			['red 2mm solid', '2mm solid red'],
			['solid 2mm red', '2mm solid red'],
			['#c00 solid', 'medium solid #c00'],
			['dashed thick', 'thick dashed currentcolor'],
			['solid rgb(204 0 0) 0.5mm', '0.5mm solid rgb(204,0,0)'],
			['transparent 1px solid', '1px solid transparent'],
			['SOLID 2MM #C00', '2mm solid #c00'],
		];
	}

	/**
	 * In legacy mode a border with no colour is black, as it was in mPDF v7
	 *
	 * @dataProvider providerBorderWithNoColour
	 *
	 * @param string $input
	 * @param string $expected
	 */
	public function testABorderWithNoColourIsBlackInLegacyMode($input, $expected)
	{
		$this->mpdf->cssMode = CssMode::LEGACY;

		$result = $this->normalizeProperties->normalize(['BORDER' => $input]);

		$this->assertSame($expected, $result['BORDER-TOP']);
	}

	/**
	 * Border values that name no colour, and a CSS-wide keyword, with what legacy mode normalizes them to
	 *
	 * @return string[][]
	 */
	public function providerBorderWithNoColour()
	{
		return [
			'a style' => ['solid', 'medium solid #000000'],
			'a width and a style' => ['2px solid', '2px solid #000000'],
			'none' => ['none', 'medium none #000000'],
			'inherit' => ['inherit', 'medium none #000000'],
			'a colour' => ['2px solid #c00', '2px solid #c00'],
		];
	}

	/**
	 * A border value with a part that is no width, style or colour, or with a part twice, is dropped
	 *
	 * @dataProvider providerInvalidBorder
	 *
	 * @param string $property
	 * @param string $value
	 */
	public function testDropsInvalidBorder($property, $value)
	{
		$this->assertSame([], $this->normalizeProperties->normalize([$property => $value]));
	}

	/**
	 * Border values that are not borders
	 *
	 * @return string[][]
	 */
	public function providerInvalidBorder()
	{
		return [
			'unknown colour' => ['BORDER', '1px solid bogus'],
			'unknown colour on one side' => ['BORDER-LEFT', 'bogus 1px solid'],
			'two styles' => ['BORDER', 'solid dashed'],
			'two widths' => ['BORDER-TOP', '1px 2px solid'],
			'two colours' => ['BORDER', '1px solid red blue'],
			'four parts' => ['BORDER', '1px solid red !important'],
			'a slash' => ['BORDER', '1px / solid'],
			'a negative width' => ['BORDER', '-1px solid red'],
			'a keyword with a part' => ['BORDER', 'inherit solid'],
		];
	}

	public function testParseCSSbackground()
	{
		// Color only
		$res = $this->normalizeProperties->normalize(['BACKGROUND' => '#ff0000']);
		$this->assertEquals('#ff0000', $res['BACKGROUND-COLOR']);
		$this->assertEquals('', $res['BACKGROUND-IMAGE']);

		// URL
		$res = $this->normalizeProperties->normalize(['BACKGROUND' => 'url(image.jpg)']);
		$this->assertEquals('image.jpg', $res['BACKGROUND-IMAGE']);
		$this->assertEquals('transparent', $res['BACKGROUND-COLOR']);

		// URL and Color
		$res = $this->normalizeProperties->normalize(['BACKGROUND' => '#fff url(bg.png)']);
		$this->assertEquals('#fff', $res['BACKGROUND-COLOR']);
		$this->assertEquals('bg.png', $res['BACKGROUND-IMAGE']);

		// URL and Repeat
		$res = $this->normalizeProperties->normalize(['BACKGROUND' => 'url(bg.png) repeat-x']);
		$this->assertEquals('bg.png', $res['BACKGROUND-IMAGE']);
		$this->assertEquals('repeat-x', $res['BACKGROUND-REPEAT']);

		// URL and Position
		$res = $this->normalizeProperties->normalize(['BACKGROUND' => 'url(bg.png) center top']);
		$this->assertEquals('bg.png', $res['BACKGROUND-IMAGE']);
		$this->assertEquals('50% 0%', $res['BACKGROUND-POSITION']);

		// Gradient
		$gradient = 'linear-gradient(to bottom, #fff, #000)';
		$res = $this->normalizeProperties->normalize(['BACKGROUND' => $gradient]);
		$this->assertEquals($gradient, $res['BACKGROUND-IMAGE']);
	}

	/**
	 * The parts of the background shorthand, in any order
	 *
	 * @dataProvider providerBackground
	 *
	 * @param string $value
	 * @param array  $expected The properties it sets to other than their initial values
	 */
	public function testBackgroundPartsInAnyOrder($value, array $expected)
	{
		$this->assertSame(array_merge(self::BACKGROUND_INITIAL_VALUES, $expected), $this->normalizeProperties->normalize(['BACKGROUND' => $value]));
	}

	/**
	 * The background shorthand sets every part it does not name to its initial value, and leaves mPDF's own
	 * background properties alone
	 */
	public function testBackgroundResetsThePartsItDoesNotName()
	{
		$longhands = [
			'BACKGROUND-REPEAT' => 'no-repeat',
			'BACKGROUND-POSITION' => 'right bottom',
			'BACKGROUND-SIZE' => 'cover',
			'BACKGROUND-ORIGIN' => 'content-box',
			'BACKGROUND-CLIP' => 'content-box',
			'BACKGROUND-IMAGE-RESIZE' => '6',
			'BACKGROUND-IMAGE-OPACITY' => '0.5',
			'BACKGROUND-IMAGE-RESOLUTION' => '300dpi',
		];

		$result = $this->normalizeProperties->normalize($longhands + ['BACKGROUND' => '#fff url(bg.png)']);

		$this->assertEquals(
			array_merge(self::BACKGROUND_INITIAL_VALUES, ['BACKGROUND-COLOR' => '#fff', 'BACKGROUND-IMAGE' => 'bg.png']),
			array_intersect_key($result, self::BACKGROUND_INITIAL_VALUES)
		);
		$this->assertSame('6', $result['BACKGROUND-IMAGE-RESIZE']);
		$this->assertSame('0.5', $result['BACKGROUND-IMAGE-OPACITY']);
		$this->assertSame('300dpi', $result['BACKGROUND-IMAGE-RESOLUTION']);
	}

	/**
	 * The border shorthands set the width, style and colour longhands of each side they cover, and leave the others
	 * and the radius alone
	 */
	public function testBorderSetsTheLonghandsOfItsSides()
	{
		$result = $this->normalizeProperties->normalize([
			'BORDER-RADIUS' => '2mm',
			'BORDER-COLOR' => 'red',
			'BORDER' => 'dashed',
			'BORDER-LEFT' => '1mm solid #00f',
		]);

		foreach (['TOP', 'RIGHT', 'BOTTOM'] as $side) {
			$this->assertSame('medium dashed currentcolor', $result['BORDER-' . $side]);
			$this->assertSame('medium', $result['BORDER-' . $side . '-WIDTH']);
			$this->assertSame('dashed', $result['BORDER-' . $side . '-STYLE']);
			$this->assertSame('currentcolor', $result['BORDER-' . $side . '-COLOR']);
		}

		$this->assertSame('1mm solid #00f', $result['BORDER-LEFT']);
		$this->assertSame('1mm', $result['BORDER-LEFT-WIDTH']);
		$this->assertSame('solid', $result['BORDER-LEFT-STYLE']);
		$this->assertSame('#00f', $result['BORDER-LEFT-COLOR']);
		$this->assertSame('2mm', $result['BORDER-TOP-LEFT-RADIUS-H']);
	}

	/**
	 * Background shorthands and what they expand to
	 *
	 * @return array[]
	 */
	public function providerBackground()
	{
		return [
			'colour after the image' => [
				'url(bg.png) no-repeat #0f0',
				['BACKGROUND-COLOR' => '#0f0', 'BACKGROUND-IMAGE' => 'bg.png', 'BACKGROUND-REPEAT' => 'no-repeat'],
			],
			'position and size' => [
				'url(bg.png) no-repeat center / cover',
				['BACKGROUND-COLOR' => 'transparent', 'BACKGROUND-IMAGE' => 'bg.png', 'BACKGROUND-REPEAT' => 'no-repeat', 'BACKGROUND-POSITION' => '50% 50%', 'BACKGROUND-SIZE' => 'cover'],
			],
			'two size values' => [
				'left top/50% auto url(bg.png)',
				['BACKGROUND-COLOR' => 'transparent', 'BACKGROUND-IMAGE' => 'bg.png', 'BACKGROUND-POSITION' => '0% 0%', 'BACKGROUND-SIZE' => '50% auto'],
			],
			'quoted image and lengths' => [
				'repeat-x URL("Sub Dir/A (1).png") #FFF 10mm 20mm',
				['BACKGROUND-COLOR' => '#fff', 'BACKGROUND-IMAGE' => 'Sub Dir/A (1).png', 'BACKGROUND-REPEAT' => 'repeat-x', 'BACKGROUND-POSITION' => '10mm 20mm'],
			],
			'attachment' => [
				'fixed url(bg.png) rgb(0 0 255)',
				['BACKGROUND-COLOR' => 'rgb(0,0,255)', 'BACKGROUND-IMAGE' => 'bg.png'],
			],
			'origin and clip' => [
				'content-box url(bg.png) padding-box',
				['BACKGROUND-COLOR' => 'transparent', 'BACKGROUND-IMAGE' => 'bg.png', 'BACKGROUND-ORIGIN' => 'content-box', 'BACKGROUND-CLIP' => 'padding-box'],
			],
			'one box for both' => [
				'#eee border-box',
				['BACKGROUND-COLOR' => '#eee', 'BACKGROUND-IMAGE' => '', 'BACKGROUND-ORIGIN' => 'border-box', 'BACKGROUND-CLIP' => 'border-box'],
			],
			'colour after a gradient' => [
				'linear-gradient(to right, red, blue) #fff',
				['BACKGROUND-COLOR' => '#fff', 'BACKGROUND-IMAGE' => 'linear-gradient(to right, red, blue)'],
			],
			'the colour of the last layer under the first' => [
				'url(a.png) no-repeat, url(b.png) repeat-y #fff',
				['BACKGROUND-COLOR' => '#fff', 'BACKGROUND-IMAGE' => 'a.png', 'BACKGROUND-REPEAT' => 'no-repeat'],
			],
			'none' => [
				'none',
				['BACKGROUND-COLOR' => 'transparent', 'BACKGROUND-IMAGE' => ''],
			],
			'a -webkit- gradient keeps its prefix for its legacy angles' => [
				'-webkit-linear-gradient(left, red, blue)',
				['BACKGROUND-COLOR' => 'transparent', 'BACKGROUND-IMAGE' => '-webkit-linear-gradient(left, red, blue)'],
			],
			'a gradient with another vendor prefix' => [
				'-ms-linear-gradient(to right, red, blue)',
				['BACKGROUND-COLOR' => 'transparent', 'BACKGROUND-IMAGE' => 'linear-gradient(to right, red, blue)'],
			],
			'a -moz- gradient' => [
				'-moz-linear-gradient(left, red, blue)',
				['BACKGROUND-COLOR' => 'transparent', 'BACKGROUND-IMAGE' => '-moz-linear-gradient(left, red, blue)'],
			],
			'a four-value position mPDF cannot place' => [
				'url(bg.png) right 5mm bottom 5mm #fff',
				['BACKGROUND-COLOR' => '#fff', 'BACKGROUND-IMAGE' => 'bg.png'],
			],
		];
	}

	/**
	 * A background value with a part that is none of its parts, or with a part twice, is dropped
	 *
	 * @dataProvider providerInvalidBackground
	 *
	 * @param string $value
	 */
	public function testDropsInvalidBackground($value)
	{
		$this->assertSame([], $this->normalizeProperties->normalize(['BACKGROUND' => $value]));
	}

	/**
	 * Background values that are not backgrounds
	 *
	 * @return string[][]
	 */
	public function providerInvalidBackground()
	{
		return [
			'unknown colour' => ['url(bg.png) bogus'],
			'two colours' => ['#fff #000'],
			'two images' => ['url(a.png) url(b.png)'],
			'a colour in a layer before the last' => ['#fff url(a.png), url(b.png)'],
			'an empty layer' => ['url(a.png),'],
			'a slash with no size' => ['url(bg.png) center /'],
			'a slash with no position' => ['url(bg.png) / cover'],
			'a position split by another part' => ['url(bg.png) left no-repeat top'],
			'an image mPDF cannot draw' => ['#fff conic-gradient(red, blue)'],
			'an unclosed parenthesis' => ['#fff url(' . str_repeat('a b ', 5000)],
		];
	}

	public function testNonExistentFontFamily()
	{
		$result = $this->normalizeProperties->normalize(['FONT-FAMILY' => 'abc']);
		$this->assertArrayNotHasKey('FONT-FAMILY', $result);
	}

	/**
	 * The font shorthand keeps a family name whole, falls back through the list to the first name mPDF knows, and
	 * sets no family when it knows none of them
	 *
	 * @dataProvider providerFontShorthand
	 *
	 * @param string $value The shorthand's value
	 * @param array $expected The properties it should give, null for one it should leave unset
	 */
	public function testFontShorthand($value, $expected)
	{
		$result = $this->normalizeProperties->normalize(['FONT' => $value]);

		$actual = [];
		foreach (array_keys($expected) as $k) {
			$actual[$k] = isset($result[$k]) ? $result[$k] : null;
		}

		$this->assertSame($expected, $actual);
	}

	/**
	 * @return array[] Shorthand values and the properties they should give
	 */
	public function providerFontShorthand()
	{
		return [
			'unregistered family' => ['12pt Roboto', ['FONT-FAMILY' => null, 'FONT-SIZE' => '12pt']],
			'double-quoted name with spaces' => ['16px "DejaVu Sans Mono"', ['FONT-FAMILY' => 'dejavusansmono', 'FONT-SIZE' => '16px']],
			'single-quoted name with spaces' => ["16px 'DejaVu Sans Mono'", ['FONT-FAMILY' => 'dejavusansmono', 'FONT-SIZE' => '16px']],
			'unquoted name with spaces' => ['12pt DejaVu Sans Mono', ['FONT-FAMILY' => 'dejavusansmono', 'FONT-SIZE' => '12pt']],
			'mapped name with spaces' => ['12pt Times New Roman', ['FONT-FAMILY' => 'timesnewroman', 'FONT-SIZE' => '12pt']],
			'list falls back to a registered name' => ['12pt Roboto, "DejaVu Serif", serif', ['FONT-FAMILY' => 'dejavuserif', 'FONT-SIZE' => '12pt']],
			'list falls back to a generic family' => ['12pt Roboto, Lato, monospace', ['FONT-FAMILY' => 'monospace', 'FONT-SIZE' => '12pt']],
			'list of unregistered names' => ['12pt Roboto, "Segoe UI"', ['FONT-FAMILY' => null, 'FONT-SIZE' => '12pt']],
			'style, weight and line-height' => [
				'italic bold 12pt/1.5 "DejaVu Sans Mono", monospace',
				['FONT-FAMILY' => 'dejavusansmono', 'FONT-SIZE' => '12pt', 'LINE-HEIGHT' => '1.5', 'FONT-STYLE' => 'italic', 'FONT-WEIGHT' => 'bold'],
			],
			'spaces around the slash' => ['12pt / 2 serif', ['FONT-FAMILY' => 'serif', 'FONT-SIZE' => '12pt', 'LINE-HEIGHT' => '2']],
			'numeric weight before the size' => ['italic 700 12pt serif', ['FONT-FAMILY' => 'serif', 'FONT-SIZE' => '12pt', 'FONT-STYLE' => 'italic']],
			'keyword inside a family name' => ['12pt "Bold Italic Sans", serif', ['FONT-FAMILY' => 'serif', 'FONT-STYLE' => 'normal', 'FONT-WEIGHT' => 'normal']],
			'small-caps' => ['small-caps 12pt serif', ['FONT-VARIANT-CAPS' => 'small-caps', 'TEXT-TRANSFORM' => null]],
			'parts not named are reset' => [
				'12pt serif',
				[
					'LINE-HEIGHT' => 'normal',
					'FONT-STYLE' => 'normal',
					'FONT-WEIGHT' => 'normal',
					'FONT-VARIANT-CAPS' => 'normal',
					'FONT-VARIANT-LIGATURES' => 'normal',
					'FONT-VARIANT-NUMERIC' => 'normal',
					'FONT-VARIANT-ALTERNATES' => 'normal',
					'FONT-VARIANT-POSITION' => 'normal',
				],
			],
			'negative line-height' => ['12pt/-1 serif', ['FONT-FAMILY' => null, 'FONT-SIZE' => null, 'LINE-HEIGHT' => null, 'FONT-STYLE' => null]],
			'system font' => ['caption', ['FONT-FAMILY' => null, 'FONT-SIZE' => null, 'LINE-HEIGHT' => null, 'FONT-STYLE' => null]],
		];
	}

	/**
	 * The standard mode keeps the weight the font shorthand names for it to be computed from the parent's; the legacy
	 * mode reads anything with bold in it as bold, and any other weight as normal
	 *
	 * @dataProvider providerFontShorthandWeight
	 *
	 * @param string $value The shorthand's value
	 * @param string $standard The weight it should give in the standard mode
	 * @param string $legacy The weight it should give in the legacy mode
	 */
	public function testFontShorthandWeight($value, $standard, $legacy)
	{
		$weights = [];
		foreach ([\Mpdf\CssMode::STANDARD, \Mpdf\CssMode::LEGACY] as $mode) {
			$this->mpdf->cssMode = $mode;
			$result = $this->normalizeProperties->normalize(['FONT' => $value]);
			$weights[] = $result['FONT-WEIGHT'];
		}

		$this->assertSame([$standard, $legacy], $weights);
	}

	/**
	 * @return array[] Shorthand values and the weight each gives in the standard mode, then in the legacy mode
	 */
	public function providerFontShorthandWeight()
	{
		return [
			'none' => ['12pt serif', 'normal', 'normal'],
			'normal' => ['normal 12pt serif', 'normal', 'normal'],
			'bold' => ['bold 12pt serif', 'bold', 'bold'],
			'bold after the style' => ['italic bold 12pt serif', 'bold', 'bold'],
			'bolder' => ['bolder 12pt serif', 'bolder', 'bold'],
			'lighter' => ['lighter 12pt serif', 'lighter', 'normal'],
			'number' => ['600 12pt serif', '600', 'normal'],
			'number after normal and the style' => ['normal italic 300 12pt serif', '300', 'normal'],
			'keyword in capitals' => ['BOLDER 12pt serif', 'bolder', 'bold'],
		];
	}

	/**
	 * An unquoted family name with spaces is read whole before its words are tried on their own
	 */
	public function testUnquotedFamilyNameWithSpaces()
	{
		$result = $this->normalizeProperties->normalize(['FONT-FAMILY' => 'DejaVu Sans Mono, serif']);
		$this->assertSame('dejavusansmono', $result['FONT-FAMILY']);
	}

	/**
	 * A page-size name in the size property sets the sheet in millimetres, portrait unless landscape is given, and
	 * the page box is left at auto
	 *
	 * @dataProvider providerPageSizeName
	 */
	public function testPageSizeNameSetsTheSheet($size, $sheet)
	{
		$result = $this->normalizeProperties->normalize(['SIZE' => $size]);

		$this->assertSame('AUTO', $result['SIZE']);
		$this->assertSame($sheet, array_map('round', $result['SHEET-SIZE']));
	}

	/**
	 * Page-size names, with and without an orientation on either side
	 *
	 * @return array
	 */
	public function providerPageSizeName()
	{
		return [
			'A4' => ['A4', [210.0, 297.0]],
			'lower case' => ['a4', [210.0, 297.0]],
			'letter' => ['letter', [216.0, 279.0]],
			'portrait' => ['A5 portrait', [148.0, 210.0]],
			'landscape' => ['A5 landscape', [210.0, 148.0]],
			'landscape first' => ['landscape A5', [210.0, 148.0]],
			'ledger is portrait, as in CSS' => ['ledger', [279.0, 432.0]],
		];
	}

	/**
	 * A size that is not one known page-size name with at most one orientation is dropped
	 *
	 * @dataProvider providerInvalidPageSizeName
	 */
	public function testInvalidPageSizeNameIsDropped($size)
	{
		$result = $this->normalizeProperties->normalize(['SIZE' => $size]);

		$this->assertArrayNotHasKey('SIZE', $result);
		$this->assertArrayNotHasKey('SHEET-SIZE', $result);
	}

	/**
	 * Sizes that name no page size, or more than one
	 *
	 * @return array
	 */
	public function providerInvalidPageSizeName()
	{
		return [
			'unknown name' => ['bogus'],
			'two names' => ['A4 A5'],
			'two orientations' => ['A4 portrait landscape'],
			'name and length' => ['A4 100mm'],
		];
	}

	/**
	 * break-before, break-after and break-inside are read as the page-break-* property of the same name
	 *
	 * @dataProvider providerBreakProperty
	 */
	public function testBreakPropertyIsReadAsPageBreak($value, $expected)
	{
		foreach (['BEFORE', 'AFTER', 'INSIDE'] as $side) {
			$this->assertSame(['PAGE-BREAK-' . $side => $expected], $this->normalizeProperties->normalize(['BREAK-' . $side => $value]));
		}
	}

	/**
	 * Each break value and the page-break-* value it becomes
	 *
	 * @return array
	 */
	public function providerBreakProperty()
	{
		return [
			['auto', 'auto'],
			['avoid', 'avoid'],
			['avoid-page', 'avoid'],
			['page', 'always'],
			['left', 'left'],
			['right', 'right'],
			['recto', 'right'],
			['verso', 'left'],
			['column', 'auto'],
			['avoid-column', 'auto'],
			['region', 'auto'],
			['avoid-region', 'auto'],
			['PAGE', 'always'],
		];
	}

	/**
	 * A break value that is not a CSS one is dropped
	 */
	public function testUnknownBreakValueIsDropped()
	{
		$this->assertSame([], $this->normalizeProperties->normalize(['BREAK-BEFORE' => 'always']));
		$this->assertSame([], $this->normalizeProperties->normalize(['BREAK-AFTER' => 'sideways']));
	}

	/**
	 * Keywords and lengths are read as they were before page-size names
	 */
	public function testPageSizeKeywordsAndLengths()
	{
		$this->assertSame(['SIZE' => 'AUTO'], $this->normalizeProperties->normalize(['SIZE' => 'auto']));
		$this->assertSame(['SIZE' => 'LANDSCAPE'], $this->normalizeProperties->normalize(['SIZE' => 'landscape']));

		$result = $this->normalizeProperties->normalize(['SIZE' => '100mm 150mm']);
		$this->assertArrayNotHasKey('SHEET-SIZE', $result);
		$this->assertEquals(['W' => 100, 'H' => 150], array_map('round', $result['SIZE']));
	}

	/**
	 * @dataProvider declarationsProvider
	 *
	 * @param string $property
	 * @param string $value
	 * @param bool $canParse
	 */
	public function testCanParse($property, $value, $canParse)
	{
		$this->assertSame($canParse, $this->normalizeProperties->canParse($property, $value));
	}

	/**
	 * A declaration, and whether mPDF can read its value
	 *
	 * @return array[]
	 */
	public function declarationsProvider()
	{
		return [
			'lengths and keywords' => ['MARGIN', '0 auto 5mm -1.5em', true],
			'a length with a sign and an exponent' => ['MARGIN-LEFT', '+1e+1mm', true],
			'a unit mPDF resolves' => ['WIDTH', '50vw', true],
			'a unitless line height' => ['LINE-HEIGHT', '1.5', true],
			'a radius with two values per corner' => ['BORDER-RADIUS', '5mm / 2mm', true],
			'an inline !important' => ['MARGIN-TOP', '5mm !important', true],
			'calc()' => ['MARGIN', 'calc(5mm + 5mm)', false],
			'calc() in one of several values' => ['PADDING', '1mm calc(2mm + 1mm)', false],
			'var() among lengths' => ['MARGIN', '1mm 2mm var(--x) 4mm', false],
			'min()' => ['WIDTH', 'min(50%, 80mm)', false],
			'max()' => ['WIDTH', 'max(50%, 80mm)', false],
			'clamp()' => ['FONT-SIZE', 'clamp(9pt, 2vw, 14pt)', false],
			'a comma for a decimal point' => ['MARGIN-LEFT', '1,5mm', false],
			'a unit mPDF does not know' => ['HEIGHT', '10dvh', false],
			'a colour' => ['COLOR', '#00ff00', true],
			'a named colour' => ['BACKGROUND-COLOR', 'LightGrey', true],
			'transparent' => ['BACKGROUND-COLOR', 'transparent', true],
			'currentColor' => ['BORDER-TOP-COLOR', 'currentColor', true],
			'inherit' => ['COLOR', 'inherit', true],
			'revert' => ['COLOR', 'revert', true],
			'revert-layer' => ['BACKGROUND-COLOR', 'revert-layer', true],
			'a colour with an inline !important' => ['COLOR', '#00f !important', true],
			'a word that is not a colour' => ['COLOR', 'bogus', false],
			'none as a background colour' => ['BACKGROUND-COLOR', 'none', false],
			'a colour function mPDF does not know' => ['COLOR', 'oklch(0.7 0.1 120)', false],
			'var() as a colour' => ['COLOR', 'var(--c)', false],
			'var() in a shorthand' => ['BORDER', '1px solid var(--c)', false],
			'var() in a font list' => ['FONT-FAMILY', 'var(--font), sans-serif', false],
			'an invalid colour in a shorthand' => ['BORDER', '1px solid bogus', true],
			'a function name in a url()' => ['BACKGROUND-IMAGE', 'url(images/var(1).png)', true],
			'a function name in a string' => ['FONT-FAMILY', '"calc(x)", serif', true],
			'a function whose name ends in one of them' => ['GRID-TEMPLATE-COLUMNS', 'minmax(10mm, 1fr)', true],
			'a property with no checks' => ['TEXT-ALIGN', 'center', true],
		];
	}

	/**
	 * A plus sign on a length is dropped, so code that looks for a digit first still reads it as a number
	 */
	public function testNormalizeDropsThePlusSignOfALength()
	{
		$result = $this->normalizeProperties->normalize(['FONT-SIZE' => '+20pt', 'MARGIN' => '+1mm -2mm', 'LINE-HEIGHT' => '+1.5']);

		$this->assertSame('20pt', $result['FONT-SIZE']);
		$this->assertSame('1mm', $result['MARGIN-TOP']);
		$this->assertSame('-2mm', $result['MARGIN-RIGHT']);
		$this->assertSame('1.5', $result['LINE-HEIGHT']);
	}

	/**
	 * In the standard CSS mode a CSS-wide keyword, in any case, is passed on to a property mPDF reads as it is, for
	 * CssMerger to resolve. text-outline is read as itself and its two parts
	 *
	 * @dataProvider keywordProvider
	 *
	 * @param string $property
	 * @param string $value
	 * @param string[] $longhands
	 */
	public function testAKeywordIsPassedOnToEachLonghand($property, $value, array $longhands)
	{
		$this->assertSame(array_fill_keys($longhands, strtolower($value)), $this->normalizeProperties->normalize([$property => $value]));
	}

	/**
	 * A property that is not a shorthand of the ones fullShorthandProvider() gives, with a CSS-wide keyword, and the
	 * properties it is passed on to
	 *
	 * @return array[]
	 */
	public function keywordProvider()
	{
		return [
			'font-family' => ['FONT-FAMILY', 'inherit', ['FONT-FAMILY']],
			'background-image' => ['BACKGROUND-IMAGE', 'initial', ['BACKGROUND-IMAGE']],
			'text-outline' => ['TEXT-OUTLINE', 'unset', ['TEXT-OUTLINE', 'TEXT-OUTLINE-WIDTH', 'TEXT-OUTLINE-COLOR']],
			'a longhand' => ['COLOR', 'INITIAL', ['COLOR']],
		];
	}

	/**
	 * In the standard CSS mode a keyword, in any case, is passed on to each longhand a full value of the shorthand
	 * writes, so the keyword and the value it resolves to set the same properties. A break-* property is read as its
	 * page-break-* property
	 *
	 * @dataProvider fullShorthandProvider
	 *
	 * @param string $property
	 * @param string $value A value that names every part of the shorthand
	 * @param string $keyword
	 */
	public function testAKeywordReachesTheLonghandsAValueWrites($property, $value, $keyword)
	{
		$written = array_fill_keys(array_keys($this->normalizeProperties->normalize([$property => $value])), strtolower($keyword));
		$passedOn = $this->normalizeProperties->normalize([$property => $keyword]);
		ksort($written);
		ksort($passedOn);

		$this->assertSame($written, $passedOn);
	}

	/**
	 * Each shorthand the keywords are passed on through, with a value that names every part, and a keyword
	 *
	 * @return string[][]
	 */
	public function fullShorthandProvider()
	{
		return [
			'font' => ['FONT', 'italic bold small-caps 12pt/1.5 serif', 'initial'],
			'font-variant' => ['FONT-VARIANT', 'normal', 'unset'],
			'margin' => ['MARGIN', '1mm 2mm 3mm 4mm', 'revert'],
			'padding' => ['PADDING', '1mm', 'Inherit'],
			'border' => ['BORDER', '1mm solid red', 'inherit'],
			'border-top' => ['BORDER-TOP', '1mm solid red', 'inherit'],
			'border-right' => ['BORDER-RIGHT', '1mm solid red', 'initial'],
			'border-bottom' => ['BORDER-BOTTOM', '1mm solid red', 'unset'],
			'border-left' => ['BORDER-LEFT', '1mm solid red', 'revert'],
			'border-width' => ['BORDER-WIDTH', '1mm', 'initial'],
			'border-style' => ['BORDER-STYLE', 'solid', 'revert-layer'],
			'border-color' => ['BORDER-COLOR', 'red', 'unset'],
			'border-spacing' => ['BORDER-SPACING', '1mm 2mm', 'inherit'],
			'border-radius' => ['BORDER-RADIUS', '1mm 2mm 3mm 4mm / 1mm', 'initial'],
			'a corner' => ['BORDER-TOP-LEFT-RADIUS', '1mm 2mm', 'inherit'],
			'background' => ['BACKGROUND', 'red url(a.png) no-repeat left top / cover padding-box content-box', 'inherit'],
			'list-style' => ['LIST-STYLE', 'square inside url(a.png)', 'revert-layer'],
			'break-before' => ['BREAK-BEFORE', 'page', 'inherit'],
		];
	}

	/**
	 * In the standard CSS mode a shorthand with a keyword among other parts is dropped, as a browser drops it, and a
	 * keyword in an @page rule's size is not passed on
	 *
	 * @dataProvider keywordAmongPartsProvider
	 *
	 * @param string $property
	 * @param string $value
	 */
	public function testAShorthandWithAKeywordAmongItsPartsIsDropped($property, $value)
	{
		$this->assertSame([], $this->normalizeProperties->normalize([$property => $value]));
	}

	/**
	 * Shorthands with a CSS-wide keyword among other parts, and an @page size that is a keyword
	 *
	 * @return string[][]
	 */
	public function keywordAmongPartsProvider()
	{
		return [
			'border' => ['BORDER', 'inherit solid'],
			'margin' => ['MARGIN', '0 inherit'],
			'padding' => ['PADDING', 'initial 2mm'],
			'background' => ['BACKGROUND', 'red unset'],
			'font' => ['FONT', 'revert 12pt serif'],
			'an @page size' => ['SIZE', 'inherit'],
		];
	}

	/**
	 * A family name that is a keyword in quotes is a family name
	 */
	public function testAQuotedKeywordIsAFamilyName()
	{
		$this->assertArrayHasKey('FONT-SIZE', $this->normalizeProperties->normalize(['FONT' => '12pt "inherit", serif']));
	}

	/**
	 * The legacy CSS mode reads a border or background shorthand with a CSS-wide keyword as its initial value, and
	 * leaves a keyword out of the properties it cannot read, such as font-family. As it resets no longhand the
	 * shorthand does not name, background sets only its colour and image
	 */
	public function testTheLegacyModeReadsAKeywordShorthandAsItsInitialValue()
	{
		$this->mpdf->cssMode = CssMode::LEGACY;

		$this->assertSame('medium none #000000', $this->normalizeProperties->normalize(['BORDER-TOP' => 'inherit'])['BORDER-TOP']);
		$this->assertSame(['BACKGROUND-COLOR' => 'transparent', 'BACKGROUND-IMAGE' => ''], $this->normalizeProperties->normalize(['BACKGROUND' => 'inherit']));
		$this->assertSame([], $this->normalizeProperties->normalize(['FONT-FAMILY' => 'inherit']));
		$this->assertFalse($this->normalizeProperties->canParse('COLOR', 'revert'));
	}

	/**
	 * font-variant: normal resets font-variant-position with the other longhands
	 */
	public function testFontVariantNormalResetsPosition()
	{
		$result = $this->normalizeProperties->normalize(['FONT-VARIANT' => 'normal']);
		$this->assertSame('normal', $result['FONT-VARIANT-POSITION']);
	}

}
