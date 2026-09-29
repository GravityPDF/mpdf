<?php

namespace Mpdf\Css;

use Mpdf\Color\ColorConverter;
use Mpdf\Color\ColorModeConverter;
use Mpdf\Color\ColorSpaceRestrictor;
use Mpdf\Mpdf;
use Mpdf\SizeConverter;
use Psr\Log\NullLogger;

class NormalizePropertiesTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

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
			['solid', 'medium solid #000000'],
			['#ff0000', '#ff0000 none #000000'], // Note: internal logic might produce this weird output, verified from CssManagerTest
			['2px', '2px none #000000'],
			['2px solid', '2px solid #000000'],
			['solid #ff0000', 'medium solid #ff0000'],
			['2px #ff0000', '2px none #ff0000'],
			['2px solid #ff0000', '2px solid #ff0000'],
			['#ff0000 2px solid', '2px solid #ff0000'],
			['none', 'medium none #000000'],
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

}
