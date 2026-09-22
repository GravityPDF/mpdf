<?php

namespace Mpdf;

use Mpdf\Fonts\FontRegistry;
use Mpdf\Utils\UtfString;

/**
 * PDF/X-4, which keeps the transparency, layers and colour fonts PDF/X-1a strips, beside PDF/X-1a
 */
class PdfX4Test extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;

	/**
	 * The bundled sRGB profile, which a PDF/X-4 document embeds where it names none of its own
	 */
	const SRGB = __DIR__ . '/../../data/iccprofiles/sRGB_IEC61966-2-1.icc';

	/**
	 * @var string The path a CMYK profile is written to, for a CMYK output intent
	 */
	private $cmykProfile;

	/**
	 * Writes the CMYK profile the tests that ask for a CMYK output intent name.
	 *
	 * No CMYK profile is bundled - the smallest in the ICC registry is 2.7 MB - and mPDF reads only the
	 * header of the profile it is given, to count its colour components, before embedding it whole. So
	 * the tests write a profile that is a header alone: a CMYK printer profile carrying no tags.
	 */
	public function set_up()
	{
		$this->cmykProfile = sys_get_temp_dir() . '/mpdf-test-cmyk.icc';

		$header = str_repeat("\0", 128);
		$header = substr_replace($header, pack('N', 0x02100000), 8, 4); // ICC version 2.1
		$header = substr_replace($header, 'prtr', 12, 4); // device class: printer
		$header = substr_replace($header, 'CMYK', 16, 4); // data colour space
		$header = substr_replace($header, 'Lab ', 20, 4); // profile connection space
		$header = substr_replace($header, 'acsp', 36, 4); // the file signature every profile carries

		$profile = $header . pack('N', 0); // a tag table of no tags
		$profile = substr_replace($profile, pack('N', strlen($profile)), 0, 4);

		file_put_contents($this->cmykProfile, $profile);
	}

	/**
	 * Leaves no profile behind
	 */
	public function tear_down()
	{
		if (file_exists($this->cmykProfile)) {
			unlink($this->cmykProfile);
		}
	}

	/**
	 * @param array $config Merged over automatic fixing and no compression
	 *
	 * @return Mpdf A document titled, as PDF/X requires - the fallback where it is not is tested below
	 */
	private function mpdf(array $config = [])
	{
		$mpdf = new Mpdf($config + ['mode' => 'utf-8', 'PDFXauto' => true]);
		$mpdf->SetCompression(false);
		$mpdf->SetTitle('Document');

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
	 * PDF/X-4 permits an RGB output intent, so a document that names no profile prints to sRGB, and
	 * embeds the bundled sRGB profile, which is 3 kB, as its output condition
	 */
	public function testPdfx4EmbedsTheSrgbProfileByDefault()
	{
		$pdf = $this->pdf(['PDFX' => '4']);

		$this->assertStringContainsString('/S /GTS_PDFX', $pdf);
		$this->assertStringContainsString('/Info (sRGB IEC61966-2.1)', $pdf);
		$this->assertStringContainsString('/OutputConditionIdentifier (sRGB IEC61966-2.1)', $pdf);
		$this->assertSame(1, preg_match('/\/DestOutputProfile (\d+) 0 R/', $pdf, $match));
		$this->assertStringContainsString("\n" . $match[1] . " 0 obj\n<<\n/N 3\n/Length 3052>>", $pdf);
	}

	/**
	 * PDF/X-1a names a registered CMYK condition and embeds no profile, as it always has
	 */
	public function testPdfx1aNamesTheRegisteredConditionWithNoProfile()
	{
		$pdf = $this->pdf(['PDFX' => true]);

		$this->assertStringContainsString('/Info (CGATS TR 001)', $pdf);
		$this->assertStringContainsString('/OutputConditionIdentifier (CGATS TR 001)', $pdf);
		$this->assertStringContainsString('/OutputCondition (CGATS TR 001 (SWOP))', $pdf);
		$this->assertStringContainsString('/RegistryName (http://www.color.org)', $pdf);
		$this->assertStringNotContainsString('/DestOutputProfile', $pdf);
	}

	/**
	 * The default document prints to RGB throughout: nothing in it is written in DeviceCMYK
	 */
	public function testTheDefaultPdfx4DocumentIsRgbThroughout()
	{
		$pdf = $this->pdf(['PDFX' => '4'], '<p style="color: #ff0000">Text</p>');

		$this->assertTrue($this->mpdf(['PDFX' => '4'])->pdfxRgbIntent());
		$this->assertStringNotContainsString('DeviceCMYK', $pdf);
		$this->assertSame(0, preg_match('/[\d.]+ [\d.]+ [\d.]+ [\d.]+ k\b/', $pdf));
	}

	/**
	 * A CMYK output intent is had by naming a CMYK profile, which is embedded and counted
	 */
	public function testACmykOutputIntentIsHadByNamingAProfile()
	{
		$mpdf = $this->mpdf(['PDFX' => '4', 'ICCProfile' => $this->cmykProfile]);
		$this->assertSame(4, $mpdf->pdfxOutputChannels());
		$this->assertFalse($mpdf->pdfxRgbIntent());

		$pdf = $this->pdf(['PDFX' => '4', 'ICCProfile' => $this->cmykProfile]);
		$this->assertStringContainsString('/Info (mpdf-test-cmyk)', $pdf);
		$this->assertStringContainsString('/OutputConditionIdentifier (Custom)', $pdf);
		$this->assertSame(1, preg_match('/\/DestOutputProfile (\d+) 0 R/', $pdf, $match));
		$this->assertStringStartsWith("<<\n/N 4\n", $this->object($pdf, $match[1]));
	}

	/**
	 * PDF/X-1a prints to CMYK whatever profile the document names
	 */
	public function testPdfx1aPrintsToCmyk()
	{
		$this->assertSame(4, $this->mpdf(['PDFX' => true])->pdfxOutputChannels());
		$this->assertSame(4, $this->mpdf(['PDFX' => true, 'ICCProfile' => self::SRGB])->pdfxOutputChannels());
		$this->assertFalse($this->mpdf(['PDFX' => true])->pdfxRgbIntent());
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
	 * A colour with transparency keeps it, where PDF/X-1a converts it to CMYK and loses it
	 */
	public function testAColourWithTransparencyKeepsItsTransparency()
	{
		$html = '<div style="background-color: rgba(255, 0, 0, 0.5)">Text</div>';

		$pdf = $this->pdf(['PDFX' => '4'], $html);
		$this->assertStringContainsString('1.000 0.000 0.000 rg', $pdf);
		$this->assertStringContainsString('/ca 0.5', $pdf);

		$cmyk = $this->pdf(['PDFX' => '4', 'ICCProfile' => $this->cmykProfile], $html);
		$this->assertStringContainsString('0.000 1.000 1.000 0.000 k', $cmyk);
		$this->assertStringContainsString('/ca 0.5', $cmyk);

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
	 * PDF/X-4 keeps a PNG's transparency, its RGB written as it is for the RGB output intent, and in an
	 * ICC-based sRGB space for a CMYK one, which permits no DeviceRGB. PDF/X-1a converts it to CMYK
	 * without transparency.
	 */
	public function testPdfx4KeepsATranslucentPng()
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
		$this->assertStringContainsString('/ColorSpace /DeviceRGB', $pdf);

		$cmyk = $this->pdf(['PDFX' => '4', 'ICCProfile' => $this->cmykProfile], $html);
		$this->assertStringContainsString('/SMask', $cmyk);
		$this->assertStringNotContainsString('/DeviceRGB', $cmyk);
		$this->assertSame(1, preg_match('/\/ColorSpace (\d+) 0 R/', $cmyk, $space));
		$this->assertSame(1, preg_match('/^\[\/ICCBased (\d+) 0 R\]/', $this->object($cmyk, $space[1]), $profile));
		$this->assertStringStartsWith('<</N 3 /Length 3052>>', $this->object($cmyk, $profile[1]));

		$pdfx1a = $this->pdf(['PDFX' => true], $html);
		$this->assertStringNotContainsString('/SMask', $pdfx1a);
		$this->assertStringContainsString('/ColorSpace /DeviceCMYK', $pdfx1a);
	}

	/**
	 * A palette image's palette is in sRGB, calibrated for a CMYK output intent
	 */
	public function testAPaletteImageIsInSrgb()
	{
		$image = imagecreate(4, 4);
		imagecolorallocate($image, 0, 128, 255);
		ob_start();
		imagepng($image);
		$html = '<img src="data:image/png;base64,' . base64_encode(ob_get_clean()) . '" />';

		$this->assertStringContainsString('/ColorSpace [/Indexed /DeviceRGB ', $this->pdf(['PDFX' => '4'], $html));

		$cmyk = $this->pdf(['PDFX' => '4', 'ICCProfile' => $this->cmykProfile], $html);
		$this->assertSame(1, preg_match('/\/ColorSpace \[\/Indexed (\d+) 0 R 0 /', $cmyk, $match));
		$this->assertStringStartsWith('[/ICCBased ', $this->object($cmyk, $match[1]));
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
		$this->assertStringContainsString('/Group << /Type /Group /S /Transparency /CS /DeviceRGB >>', $this->pdf(['PDFX' => '4']));

		$cmyk = $this->pdf(['PDFX' => '4', 'ICCProfile' => $this->cmykProfile]);
		$this->assertStringContainsString('/Group << /Type /Group /S /Transparency /CS /DeviceCMYK >>', $cmyk);

		$this->assertStringNotContainsString('/Group', $this->pdf(['PDFX' => true]));
	}

	/**
	 * RGB is written as it is for the RGB output intent, and converted to CMYK for a CMYK one
	 */
	public function testRgbIsKeptOnlyForAnRgbOutputIntent()
	{
		$html = '<p style="color: #ff0000">Text</p>';

		$rgb = $this->pdf(['PDFX' => '4'], $html);
		$this->assertStringContainsString('1.000 0.000 0.000 rg', $rgb);

		$cmyk = $this->pdf(['PDFX' => '4', 'ICCProfile' => $this->cmykProfile], $html);
		$this->assertStringContainsString('0.000 1.000 1.000 0.000 k', $cmyk);
		$this->assertStringNotContainsString('1.000 0.000 0.000 rg', $cmyk);
	}

	/**
	 * CMYK is converted to RGB for the RGB output intent
	 */
	public function testCmykIsConvertedForAnRgbOutputIntent()
	{
		$html = '<p style="color: cmyk(0, 100, 100, 0)">Text</p>';

		$this->assertStringContainsString('1.000 0.000 0.000 rg', $this->pdf(['PDFX' => '4'], $html));
		$this->assertStringContainsString('0.000 1.000 1.000 0.000 k', $this->pdf(['PDFX' => '4', 'ICCProfile' => $this->cmykProfile], $html));
	}

	/**
	 * A bitmap colour font is drawn in colour, its images in sRGB where the output intent is CMYK
	 */
	public function testABitmapColourFontIsDrawnInColour()
	{
		$this->assertStringContainsString('/ColorSpace /DeviceRGB', $this->colourFontPdf(['PDFX' => '4', 'PDFXauto' => false]));

		$pdf = $this->colourFontPdf(['PDFX' => '4', 'PDFXauto' => false, 'ICCProfile' => $this->cmykProfile]);
		$this->assertStringNotContainsString('/DeviceRGB', $pdf);
		$this->assertStringContainsString('/SMask', $pdf);
		$this->assertSame(1, preg_match('/\/ColorSpace (\d+) 0 R/', $pdf, $match));
		$this->assertStringStartsWith('[/ICCBased ', $this->object($pdf, $match[1]));
	}

	/**
	 * A COLR font is drawn in colour, each fill in sRGB set by name where the output intent is CMYK
	 */
	public function testAColrFontIsDrawnInColour()
	{
		$rgb = $this->colourFontPdf(['PDFX' => '4', 'PDFXauto' => false, 'default_font' => 'colr']);
		$this->assertStringContainsString('1.000 0.800 0.200 rg', $rgb, 'the face, yellow');
		$this->assertStringNotContainsString('CsRGB', $rgb);

		$pdf = $this->colourFontPdf(['PDFX' => '4', 'PDFXauto' => false, 'default_font' => 'colr', 'ICCProfile' => $this->cmykProfile]);
		$this->assertStringNotContainsString(' rg', $pdf);
		$this->assertStringContainsString('/CsRGB cs 1.000 0.800 0.200 sc', $pdf, 'the face, yellow');
		$this->assertSame(1, preg_match('/\/ColorSpace <<\/CsRGB (\d+) 0 R >>/', $pdf, $match));
		$this->assertStringStartsWith('[/ICCBased ', $this->object($pdf, $match[1]));
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
	 * PDF/X requires a document title, so an untitled document is refused where mPDF is not to fix it
	 *
	 * @param string $version As the PDFX setting names it
	 *
	 * @dataProvider pdfxVersions
	 */
	public function testAnUntitledPdfxDocumentIsRefused($version)
	{
		$file = sys_get_temp_dir() . '/mpdf-test-untitled.pdf';

		$mpdf = $this->untitled(['PDFX' => $version, 'PDFXauto' => false]);
		$mpdf->WriteHTML('<p>Text</p>');

		$refused = null;
		try {
			$mpdf->OutputFile($file);
		} catch (MpdfException $e) {
			$refused = $e;
		}

		$this->assertNotNull($refused, 'the untitled document was written');
		$this->assertStringContainsString('PDFA/PDFX warnings generated', $refused->getMessage());
		$this->assertContains(
			sprintf('A document title is required in %s files, and SetTitle() set none. (Title set to the file name "mpdf-test-untitled")', $mpdf->pdfxVersionLabel()),
			$mpdf->PDFAXwarnings
		);

		$titled = $this->untitled(['PDFX' => $version, 'PDFXauto' => false]);
		$titled->SetTitle('Document');
		$titled->WriteHTML('<p>Text</p>');
		$titled->OutputFile($file);

		$this->assertFileExists($file, 'the same document with a title is written');
		unlink($file);
	}

	/**
	 * @return array[] Each PDF/X version, as the PDFX setting names it
	 */
	public function pdfxVersions()
	{
		return ['PDF/X-1a' => ['1a'], 'PDF/X-4' => ['4']];
	}

	/**
	 * Where mPDF is to fix the document, an untitled one is titled after the file it is written to, so
	 * that what is produced conforms rather than is merely tolerated
	 */
	public function testAnUntitledPdfxDocumentIsTitledAfterItsFile()
	{
		$file = sys_get_temp_dir() . '/Quarterly Report.pdf';

		$mpdf = $this->untitled(['PDFX' => '4']);
		$mpdf->WriteHTML('<p>Text</p>');
		$mpdf->OutputFile($file);

		$pdf = file_get_contents($file);
		unlink($file);

		$this->assertStringContainsString('<rdf:li xml:lang="x-default">Quarterly Report</rdf:li>', $pdf);
		$this->assertStringContainsString('/Title ' . $this->textString('Quarterly Report'), $pdf);
	}

	/**
	 * Written to no file, an untitled document is titled after the name mPDF sends a document under
	 */
	public function testAnUntitledPdfxDocumentWrittenToNoFileTakesThePdfxDefaultName()
	{
		$mpdf = $this->untitled(['PDFX' => '4']);
		$mpdf->WriteHTML('<p>Text</p>');
		$pdf = $mpdf->OutputBinaryData();

		$this->assertStringContainsString('<rdf:li xml:lang="x-default">mpdf</rdf:li>', $pdf);
		$this->assertStringContainsString('/Title ' . $this->textString('mpdf'), $pdf);
	}

	/**
	 * A title the document sets is the title, whatever file it is written to
	 */
	public function testASetTitleIsKept()
	{
		$file = sys_get_temp_dir() . '/mpdf-test-titled.pdf';

		$mpdf = $this->untitled(['PDFX' => '4']);
		$mpdf->SetTitle('Annual Accounts');
		$mpdf->WriteHTML('<p>Text</p>');
		$mpdf->OutputFile($file);

		$pdf = file_get_contents($file);
		unlink($file);

		$this->assertStringContainsString('<rdf:li xml:lang="x-default">Annual Accounts</rdf:li>', $pdf);
		$this->assertStringNotContainsString('mpdf-test-titled', $pdf);
	}

	/**
	 * A document that is not PDF/X is titled only where it says so
	 */
	public function testANonPdfxDocumentIsNotTitled()
	{
		$file = sys_get_temp_dir() . '/mpdf-test-plain.pdf';

		$mpdf = $this->untitled([]);
		$mpdf->WriteHTML('<p>Text</p>');
		$mpdf->OutputFile($file);

		$pdf = file_get_contents($file);
		unlink($file);

		$this->assertStringNotContainsString('/Title', $pdf);
		$this->assertStringNotContainsString('<dc:title>', $pdf);
	}

	/**
	 * @param array $config Merged over automatic fixing and no compression
	 *
	 * @return Mpdf A document with no title of its own
	 */
	private function untitled(array $config)
	{
		$mpdf = new Mpdf($config + ['mode' => 'utf-8', 'PDFXauto' => true]);
		$mpdf->SetCompression(false);

		return $mpdf;
	}

	/**
	 * @param string $text
	 *
	 * @return string That text as the Info dictionary carries it, UTF-16BE behind a byte order mark
	 */
	private function textString($text)
	{
		return '(' . "\xFE\xFF" . mb_convert_encoding($text, 'UTF-16BE', 'UTF-8') . ')';
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
