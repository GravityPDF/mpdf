<?php

namespace Mpdf\Color;

use Mpdf\Mpdf;

class ColorSpaceRestrictorTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * A colour with an alpha value keeps it where the standard allows transparency, and in either case ends up in the
	 * colour space of the output intent
	 *
	 * @dataProvider alphaColors
	 */
	public function testAlphaColor($config, $color, $expected)
	{
		$mpdf = new Mpdf($config + ['PDFAauto' => true, 'PDFXauto' => true]);
		$restrictor = new ColorSpaceRestrictor($mpdf, new ColorModeConverter());

		$this->assertEquals($expected, $restrictor->restrictColorSpace($color, 'test'));
	}

	/**
	 * Configurations, an rgba() or cmyka() colour as mPDF holds it, and what the restrictor turns it into
	 *
	 * @return mixed[][]
	 */
	public function alphaColors()
	{
		$pdfa1 = ['PDFA' => true, 'PDFAversion' => '1-B'];
		$pdfa2 = ['PDFA' => true, 'PDFAversion' => '2-B'];
		$cmyk = ['restrictColorSpace' => 3];

		return [
			'PDF/A-1 draws rgba() as RGB' => [$pdfa1, [5, 255, 0, 0, 0.5], [3, 255, 0, 0]],
			'PDF/A-2 keeps rgba()' => [$pdfa2, [5, 255, 0, 0, 0.5], [5, 255, 0, 0, 0.5]],
			'PDF/A-1 draws cmyka() as RGB' => [$pdfa1, [6, 0, 100, 100, 0, 0.5], [3, 255, 0, 0]],
			'PDF/A-2 draws cmyka() as rgba()' => [$pdfa2, [6, 0, 100, 100, 0, 0.5], [5, 255, 0, 0, 0.5]],
			'CMYK PDF/A-1 draws rgba() as CMYK' => [$pdfa1 + $cmyk, [5, 255, 0, 0, 0.5], [4, 0, 100, 100, 0]],
			'CMYK PDF/A-2 draws rgba() as cmyka()' => [$pdfa2 + $cmyk, [5, 255, 0, 0, 0.5], [6, 0, 100, 100, 0, 0.5]],
			'CMYK PDF/A-1 draws cmyka() as CMYK' => [$pdfa1 + $cmyk, [6, 0, 100, 100, 0, 0.5], [4, 0, 100, 100, 0]],
			'CMYK PDF/A-2 keeps cmyka()' => [$pdfa2 + $cmyk, [6, 0, 100, 100, 0, 0.5], [6, 0, 100, 100, 0, 0.5]],
			'PDF/X draws cmyka() as CMYK' => [['PDFX' => true], [6, 0, 100, 100, 0, 0.5], [4, 0, 100, 100, 0]],
		];
	}

	/**
	 * Without PDFAauto, PDF/A-2 still reports an alpha colour it moves into another colour space, but not the alpha
	 * it keeps
	 */
	public function testPdfa2ReportsTheColorSpaceItChanges()
	{
		$mpdf = new Mpdf(['PDFA' => true, 'PDFAversion' => '2-B']);
		$restrictor = new ColorSpaceRestrictor($mpdf, new ColorModeConverter());

		$warnings = [];
		$restrictor->restrictColorSpace([5, 255, 0, 0, 0.5], 'rgba(255, 0, 0, 0.5)', $warnings);
		$restrictor->restrictColorSpace([6, 0, 100, 100, 0, 0.5], 'cmyka(0, 100, 100, 0, 0.5)', $warnings);

		$this->assertSame(["CMYK color with transparency specified 'cmyka(0, 100, 100, 0, 0.5)' (converted to RGB)"], $warnings);
	}

	/**
	 * Without PDFAauto, PDF/A-1 reports the alpha it drops from a translucent colour, but not from an opaque one
	 *
	 * @dataProvider opaqueAlphaColors
	 */
	public function testOpaqueAlphaIsNotReported($config, $opaque, $translucent)
	{
		$mpdf = new Mpdf($config + ['PDFA' => true, 'PDFAversion' => '1-B']);
		$restrictor = new ColorSpaceRestrictor($mpdf, new ColorModeConverter());

		$warnings = [];
		$restrictor->restrictColorSpace($opaque, 'opaque', $warnings);
		$this->assertSame([], $warnings);

		$restrictor->restrictColorSpace($translucent, 'translucent', $warnings);
		$this->assertCount(1, $warnings);
	}

	/**
	 * Configurations with an opaque and a translucent colour in the colour space each keeps: rgba() for an RGB output
	 * intent, cmyka() for a CMYK one
	 *
	 * @return mixed[][]
	 */
	public function opaqueAlphaColors()
	{
		return [
			'rgba()' => [[], [5, 255, 0, 0, 100], [5, 255, 0, 0, 50]],
			'cmyka()' => [['restrictColorSpace' => 3], [6, 0, 100, 100, 0, 100], [6, 0, 100, 100, 0, 50]],
		];
	}

}
