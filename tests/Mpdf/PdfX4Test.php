<?php

namespace Mpdf;

use Mpdf\Fonts\FontRegistry;
use Mpdf\Utils\UtfString;

/**
 * PDF/X-4, which unlike PDF/X-1a keeps transparency, layers and colour fonts, beside PDF/X-1a, which is
 * left as it was
 */
class PdfX4Test extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;

	/**
	 * The bundled sRGB profile, for an RGB output intent
	 */
	const SRGB = __DIR__ . '/../../data/iccprofiles/sRGB_IEC61966-2-1.icc';

	/**
	 * @param array $config Merged over automatic fixing and no compression
	 *
	 * @return Mpdf
	 */
	private function mpdf(array $config = [])
	{
		$mpdf = new Mpdf($config + ['mode' => 'utf-8', 'PDFXauto' => true]);
		$mpdf->SetCompression(false);

		return $mpdf;
	}

	/**
	 * @param array         $config Merged over mpdf()'s configuration
	 * @param string        $html
	 * @param callable|null $before Handed the document before the HTML is written
	 *
	 * @return string The document
	 */
	private function pdf(array $config, $html = '<p>Text</p>', $before = null)
	{
		$mpdf = $this->mpdf($config);
		if ($before) {
			$before($mpdf);
		}
		$mpdf->WriteHTML($html);

		return $mpdf->OutputBinaryData();
	}

	/**
	 * true and '1a' ask for PDF/X-1a, and '4' for PDF/X-4
	 */
	public function testThePdfxSettingNamesTheVersion()
	{
		$pdfx1a = $this->mpdf(['PDFX' => '1a']);
		$this->assertSame('PDF/X-1a:2003', $pdfx1a->pdfxVersionLabel());
		$this->assertTrue($pdfx1a->isPdfx1a());
		$this->assertSame('PDF/X-1a:2003', $this->mpdf(['PDFX' => true])->pdfxVersionLabel());

		$pdfx4 = $this->mpdf(['PDFX' => '4']);
		$this->assertSame('PDF/X-4', $pdfx4->pdfxVersionLabel());
		$this->assertTrue($pdfx4->isPdfx4());
		$this->assertFalse($pdfx4->isPdfx1a());
	}

	/**
	 * A version mPDF does not write is refused
	 */
	public function testAnUnknownVersionIsRefused()
	{
		$this->expectException(MpdfException::class);
		$this->expectExceptionMessage('PDFX "3" is not valid.');

		$this->mpdf(['PDFX' => '3']);
	}

	/**
	 * PDF/X-4 is based on PDF 1.6, and PDF/X-1a stays at PDF 1.4
	 */
	public function testPdfx4IsPdf16()
	{
		$this->assertStringStartsWith('%PDF-1.6', $this->pdf(['PDFX' => '4']));

		$pdfx1a = $this->pdf(['PDFX' => true]);
		$this->assertStringStartsWith('%PDF-1.4', $pdfx1a);
		$this->assertStringNotContainsString('/Version /', $pdfx1a);
	}

	/**
	 * Set once the header is written, PDF/X-4 has the catalog say PDF 1.6
	 */
	public function testPdfx4SetAfterTheDocumentIsMadeRaisesTheCatalogVersion()
	{
		$mpdf = $this->mpdf();
		$mpdf->WriteHTML('<p>Text</p>');
		$mpdf->PDFX = '4';
		$mpdf->PDFXauto = true;

		$this->assertStringContainsString('/Version /1.6', $mpdf->OutputBinaryData());
	}

	/**
	 * PDF/X-4 embeds its output intent's profile, the bundled SWOP CMYK profile where it names none
	 */
	public function testPdfx4EmbedsTheSwopProfileByDefault()
	{
		$pdf = $this->pdf(['PDFX' => '4']);

		$this->assertStringContainsString('/S /GTS_PDFX', $pdf);
		$this->assertStringContainsString('/Info (SWOP2006 Coated3v2)', $pdf);
		$this->assertSame(1, preg_match('/\/DestOutputProfile (\d+) 0 R/', $pdf, $match));
		$this->assertStringContainsString("\n" . $match[1] . " 0 obj\n<<\n/N 4\n/Length 2747952>>", $pdf, 'too long for PageStreams::object() to match');

		$this->assertStringNotContainsString('/DestOutputProfile', $this->pdf(['PDFX' => true]), 'PDF/X-1a names a registered condition instead');
	}

	/**
	 * The bundled profile is a CMYK printer profile, as an output intent's must be
	 */
	public function testTheBundledProfileIsACmykPrinterProfile()
	{
		$header = file_get_contents(__DIR__ . '/../../data/iccprofiles/SWOP2006_Coated3v2.icc', false, null, 0, 20);

		$this->assertSame('prtr', substr($header, 12, 4));
		$this->assertSame('CMYK', substr($header, 16, 4));
	}

	/**
	 * The metadata identify PDF/X-4 with the schema it defines, and carry what it requires of them
	 */
	public function testPdfx4IsIdentifiedInItsMetadata()
	{
		$pdf = $this->pdf(['PDFX' => '4']);

		$this->assertStringContainsString('xmlns:pdfxid="http://www.npes.org/pdfx/ns/id/" pdfxid:GTS_PDFXVersion="PDF/X-4"', $pdf);
		$this->assertStringContainsString('<pdf:Trapped>False</pdf:Trapped>', $pdf);
		$this->assertStringContainsString('<xmpMM:VersionID>1</xmpMM:VersionID>', $pdf);
		$this->assertStringContainsString('<xmpMM:RenditionClass>default</xmpMM:RenditionClass>', $pdf);
		$this->assertStringContainsString('/GTS_PDFXVersion(PDF/X-4)', $pdf);
		$this->assertStringNotContainsString('ns.adobe.com/pdfx/1.3', $pdf);

		$pdfx1a = $this->pdf(['PDFX' => true]);
		$this->assertStringContainsString('pdfx:GTS_PDFXConformance="PDF/X-1a:2003"', $pdfx1a);
		$this->assertStringContainsString('/GTS_PDFXVersion(PDF/X-1a:2003)', $pdfx1a);
		$this->assertStringNotContainsString('pdfxid', $pdfx1a);
	}

	/**
	 * PDF/X permits no JavaScript: it is removed, or refused where mPDF is not to fix the document
	 */
	public function testJavaScriptIsRemoved()
	{
		$script = function (Mpdf $mpdf) {
			$mpdf->SetJS('app.alert("x");');
		};

		$this->assertStringNotContainsString('/JavaScript', $this->pdf(['PDFX' => '4'], '<p>Text</p>', $script));

		$this->expectException(MpdfException::class);
		$this->pdf(['PDFX' => '4', 'PDFXauto' => false], '<p>Text</p>', $script);
	}

	/**
	 * PDF/X permits no annotation within the printed area, so a link on the page is removed, or refused
	 * where mPDF is not to fix the document
	 */
	public function testALinkOnThePageIsRemoved()
	{
		$html = '<p><a href="https://example.com">Link</a></p>';

		$this->assertStringContainsString('/Subtype /Link', $this->pdf([], $html));
		$this->assertStringNotContainsString('/Subtype /Link', $this->pdf(['PDFX' => '4'], $html));

		$this->expectException(MpdfException::class);
		$this->pdf(['PDFX' => '4', 'PDFXauto' => false], $html);
	}

	/**
	 * A link on the sheet outside the page's BleedBox is kept
	 */
	public function testALinkOutsideTheBleedBoxIsKept()
	{
		$mpdf = $this->mpdf(['PDFX' => '4', 'PDFXauto' => false]);
		$mpdf->WriteHTML('<style>@page { size: 150mm 200mm; sheet-size: A4; marks: crop; }</style><p>Text</p>');
		$mpdf->Link(1, 1, 5, 5, 'https://example.com');

		$this->assertStringContainsString('/Subtype /Link /Rect [2.835 839.055 17.008 824.882]', $mpdf->OutputBinaryData());
	}

	/**
	 * PDF/X permits no interactive form field, so an active form's fields are removed
	 */
	public function testActiveFormFieldsAreRemoved()
	{
		$html = '<form><input type="text" name="field" value="Value" /></form>';

		$this->assertStringContainsString('/AcroForm', $this->pdf(['useActiveForms' => true], $html));

		$pdf = $this->pdf(['PDFX' => '4', 'useActiveForms' => true], $html);
		$this->assertStringNotContainsString('/AcroForm', $pdf);
		$this->assertStringNotContainsString('/Subtype /Widget', $pdf);
	}

	/**
	 * PDF/X-4 keeps opacity, which PDF/X-1a sets to full
	 */
	public function testPdfx4KeepsOpacity()
	{
		$alpha = function (Mpdf $mpdf) {
			$mpdf->SetAlpha(0.4);
		};

		$this->assertStringContainsString('/ca 0.4', $this->pdf(['PDFX' => '4'], '<p>Text</p>', $alpha));
		$this->assertStringNotContainsString('/ca 0.4', $this->pdf(['PDFX' => true], '<p>Text</p>', $alpha));
	}

	/**
	 * A colour with transparency is converted to CMYK and keeps its transparency, where PDF/X-1a loses it
	 */
	public function testAColourWithTransparencyKeepsItsTransparency()
	{
		$html = '<div style="background-color: rgba(255, 0, 0, 0.5)">Text</div>';

		$pdf = $this->pdf(['PDFX' => '4'], $html);
		$this->assertStringContainsString('0.000 1.000 1.000 0.000 k', $pdf);
		$this->assertStringContainsString('/ca 0.5', $pdf);

		$this->assertStringNotContainsString('/ca 0.5', $this->pdf(['PDFX' => true], $html));
	}

	/**
	 * PDF/X-4 permits a watermark, drawn with transparency, which PDF/X-1a refuses
	 */
	public function testPdfx4PermitsAWatermark()
	{
		$watermark = function (Mpdf $mpdf) {
			$mpdf->SetWatermarkText('DRAFT');
			$mpdf->showWatermarkText = true;
		};

		$this->assertStringContainsString('/ca 0.2', $this->pdf(['PDFX' => '4'], '<p>Text</p>', $watermark));

		$this->expectException(MpdfException::class);
		$this->pdf(['PDFX' => true], '<p>Text</p>', $watermark);
	}

	/**
	 * PDF/X-4 keeps a PNG's transparency, and its RGB is written in sRGB, as the CMYK output intent
	 * permits no DeviceRGB. PDF/X-1a converts it to CMYK without transparency.
	 */
	public function testPdfx4KeepsATranslucentPngInSrgb()
	{
		$image = imagecreatetruecolor(16, 16);
		imagesavealpha($image, true);
		imagealphablending($image, false);
		imagefilledrectangle($image, 0, 0, 15, 15, imagecolorallocatealpha($image, 220, 40, 40, 63));
		ob_start();
		imagepng($image);
		$html = '<img src="data:image/png;base64,' . base64_encode(ob_get_clean()) . '" />';

		$pdf = $this->pdf(['PDFX' => '4'], $html);
		$this->assertStringContainsString('/SMask', $pdf);
		$this->assertStringNotContainsString('/DeviceRGB', $pdf);
		$this->assertSame(1, preg_match('/\/ColorSpace (\d+) 0 R/', $pdf, $space));
		$this->assertSame(1, preg_match('/^\[\/ICCBased (\d+) 0 R\]/', $this->object($pdf, $space[1]), $profile));
		$this->assertStringStartsWith('<</N 3 /Length 3052>>', $this->object($pdf, $profile[1]));

		$pdfx1a = $this->pdf(['PDFX' => true], $html);
		$this->assertStringNotContainsString('/SMask', $pdfx1a);
		$this->assertStringContainsString('/ColorSpace /DeviceCMYK', $pdfx1a);
	}

	/**
	 * A palette image's palette is in sRGB
	 */
	public function testAPaletteImageIsInSrgb()
	{
		$image = imagecreate(4, 4);
		imagecolorallocate($image, 0, 128, 255);
		ob_start();
		imagepng($image);
		$html = '<img src="data:image/png;base64,' . base64_encode(ob_get_clean()) . '" />';

		$pdf = $this->pdf(['PDFX' => '4'], $html);
		$this->assertSame(1, preg_match('/\/ColorSpace \[\/Indexed (\d+) 0 R 0 /', $pdf, $match));
		$this->assertStringStartsWith('[/ICCBased ', $this->object($pdf, $match[1]));
	}

	/**
	 * PDF/X permits no interpolation of an image
	 */
	public function testNoImageIsInterpolated()
	{
		$this->assertStringContainsString('/Interpolate true', $this->colourFontPdf([]), 'a colour glyph\'s image is interpolated elsewhere');
		$this->assertStringNotContainsString('/Interpolate', $this->colourFontPdf(['PDFX' => '4']));
	}

	/**
	 * PDF/X-4 permits layers, each configuration named, which PDF/X-1a refuses
	 */
	public function testPdfx4PermitsLayers()
	{
		$html = '<div style="position: absolute; top: 60mm; left: 20mm; z-index: 1">Layer</div><p>Text</p>';

		$pdf = $this->pdf(['PDFX' => '4', 'PDFXauto' => false], $html);
		$this->assertStringContainsString('/Type /OCG', $pdf);
		$this->assertStringContainsString('/Name (Layers)', $pdf);

		$this->expectException(MpdfException::class);
		$this->pdf(['PDFX' => true, 'PDFXauto' => false], $html);
	}

	/**
	 * Each page is blended in the colour space of the output intent
	 */
	public function testPagesAreBlendedInTheColourSpaceOfTheOutputIntent()
	{
		$this->assertStringContainsString('/Group << /Type /Group /S /Transparency /CS /DeviceCMYK >>', $this->pdf(['PDFX' => '4']));

		$rgb = $this->pdf(['PDFX' => '4', 'ICCProfile' => self::SRGB]);
		$this->assertStringContainsString('/Group << /Type /Group /S /Transparency /CS /DeviceRGB >>', $rgb);

		$this->assertStringNotContainsString('/Group', $this->pdf(['PDFX' => true]));
	}

	/**
	 * RGB is converted to CMYK for a CMYK output intent, and written as it is for an RGB one, with
	 * the profile's three components
	 */
	public function testRgbIsKeptOnlyForAnRgbOutputIntent()
	{
		$html = '<p style="color: #ff0000">Text</p>';

		$cmyk = $this->pdf(['PDFX' => '4'], $html);
		$this->assertStringContainsString('0.000 1.000 1.000 0.000 k', $cmyk);
		$this->assertStringNotContainsString('1.000 0.000 0.000 rg', $cmyk);

		$rgb = $this->pdf(['PDFX' => '4', 'ICCProfile' => self::SRGB], $html);
		$this->assertStringContainsString('1.000 0.000 0.000 rg', $rgb);
		$this->assertSame(1, preg_match('/\/DestOutputProfile (\d+) 0 R/', $rgb, $match));
		$this->assertStringStartsWith("<<\n/N 3\n", $this->object($rgb, $match[1]));
	}

	/**
	 * A bitmap colour font is drawn in colour, its images in sRGB
	 */
	public function testABitmapColourFontIsDrawnInColourInSrgb()
	{
		$pdf = $this->colourFontPdf(['PDFX' => '4', 'PDFXauto' => false]);

		$this->assertStringNotContainsString('/DeviceRGB', $pdf);
		$this->assertStringContainsString('/SMask', $pdf);
		$this->assertSame(1, preg_match('/\/ColorSpace (\d+) 0 R/', $pdf, $match));
		$this->assertStringStartsWith('[/ICCBased ', $this->object($pdf, $match[1]));
	}

	/**
	 * A COLR font is drawn in colour, each fill in sRGB set by name
	 */
	public function testAColrFontIsDrawnInColourInSrgb()
	{
		$pdf = $this->colourFontPdf(['PDFX' => '4', 'PDFXauto' => false, 'default_font' => 'colr']);

		$this->assertStringNotContainsString(' rg', $pdf);
		$this->assertStringContainsString('/CsRGB cs 1.000 0.800 0.200 sc', $pdf, 'the face, yellow');
		$this->assertSame(1, preg_match('/\/ColorSpace <<\/CsRGB (\d+) 0 R >>/', $pdf, $match));
		$this->assertStringStartsWith('[/ICCBased ', $this->object($pdf, $match[1]));
	}

	/**
	 * For an RGB output intent, a colour font's RGB is written as it is
	 */
	public function testAColourFontIsDrawnInDeviceRgbForAnRgbOutputIntent()
	{
		$pdf = $this->colourFontPdf(['PDFX' => '4', 'default_font' => 'colr', 'ICCProfile' => self::SRGB]);

		$this->assertStringContainsString('1.000 0.800 0.200 rg', $pdf);
		$this->assertStringNotContainsString('CsRGB', $pdf);
	}

	/**
	 * PDF/X-4 permits no encryption
	 */
	public function testEncryptionIsRefused()
	{
		$mpdf = $this->mpdf(['PDFX' => '4']);
		$mpdf->SetProtection(['print']);
		$mpdf->WriteHTML('<p>Text</p>');

		$this->expectException(MpdfException::class);
		$this->expectExceptionMessage('PDF/X-4 does not permit encryption of documents.');

		$mpdf->OutputBinaryData();
	}

	/**
	 * @param array $config Merged over mpdf()'s configuration
	 *
	 * @return string A document drawing the grinning face in a test colour font, the CBDT one by default
	 */
	private function colourFontPdf(array $config)
	{
		return $this->pdf($config + [
			'fontRegistry' => new FontRegistry([]),
			'fontDir' => [__DIR__ . '/../data/ttf/color'],
			'fontdata' => [
				'cbdt' => ['R' => 'TestEmoji-CBDT.ttf', 'useOTL' => 0xFF],
				'colr' => ['R' => 'TestEmoji-COLRv0.ttf', 'useOTL' => 0xFF],
			],
			'default_font' => 'cbdt',
		], '<p>' . UtfString::code2utf(0x1F600) . '</p>');
	}
}
