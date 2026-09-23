<?php

namespace Mpdf;

use Mpdf\Fonts\FontRegistry;
use Mpdf\Utils\UtfString;
use setasign\Fpdi\PdfParser\StreamReader;

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

	const GRAY = __DIR__ . '/../../data/iccprofiles/Gray_sRGB_TRC.icc';

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
		$this->cmykProfile = $this->writeProfile('cmyk', 'CMYK');
	}

	/**
	 * Leaves no profile behind
	 */
	public function tear_down()
	{
		foreach (glob(sys_get_temp_dir() . '/mpdf-test-*.icc') as $profile) {
			unlink($profile);
		}
	}

	/**
	 * @param string $name  Names the file
	 * @param string $space The data colour space the profile prints to, e.g. 'CMYK' or 'Lab '
	 *
	 * @return string The path of a printer profile that is a header alone, carrying no tags
	 */
	private function writeProfile($name, $space)
	{
		$path = sys_get_temp_dir() . '/mpdf-test-' . $name . '.icc';

		$header = str_repeat("\0", 128);
		$header = substr_replace($header, pack('N', 0x02100000), 8, 4); // ICC version 2.1
		$header = substr_replace($header, 'prtr', 12, 4); // device class: printer
		$header = substr_replace($header, $space, 16, 4); // data colour space
		$header = substr_replace($header, 'Lab ', 20, 4); // profile connection space
		$header = substr_replace($header, 'acsp', 36, 4); // the file signature every profile carries

		$profile = $header . pack('N', 0); // a tag table of no tags
		$profile = substr_replace($profile, pack('N', strlen($profile)), 0, 4);

		file_put_contents($path, $profile);

		return $path;
	}

	/**
	 * @param array  $config Merged over automatic fixing and no compression
	 * @param string $class  Mpdf, or a subclass of it
	 *
	 * @return Mpdf A document titled, as PDF/X requires - the fallback where it is not is tested below
	 */
	private function mpdf(array $config = [], $class = Mpdf::class)
	{
		$mpdf = new $class($config + ['mode' => 'utf-8', 'PDFXauto' => true]);
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
	 * A page imported from a later version of PDF leaves the version at the one the PDF/X version is
	 * written as, and the strict mode refuses it
	 */
	public function testAnImportedPageKeepsThePdfxVersion()
	{
		$source = new Mpdf(['pdf_version' => '1.7']);
		$source->WriteHTML('<p>Imported</p>');
		$data = $source->OutputBinaryData();

		foreach ([['1a', '1.4'], ['4', '1.6']] as $case) {
			list($version, $pdfVersion) = $case;
			$mpdf = $this->mpdf(['PDFX' => $version]);
			$mpdf->setSourceFile(StreamReader::createByString($data));
			$mpdf->AddPage();
			$mpdf->useTemplate($mpdf->importPage(1));
			$this->assertStringStartsWith('%PDF-' . $pdfVersion . "\n", $mpdf->OutputBinaryData());
		}

		$mpdf = $this->mpdf(['PDFX' => '4', 'PDFXauto' => false]);
		$mpdf->setSourceFile(StreamReader::createByString($data));

		$this->expectException(MpdfException::class);
		$mpdf->OutputBinaryData();
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
	 * The printing condition of PDF/X-4 is grey, RGB or CMYK, so a profile printing to Lab or to six
	 * inks is refused rather than taken for CMYK
	 *
	 * @dataProvider unprintableSpaces
	 *
	 * @param string $space
	 */
	public function testAnOutputIntentOfAnotherColourSpaceIsRefused($space)
	{
		$profile = $this->writeProfile('other', $space);

		$this->expectException(MpdfException::class);
		$this->expectExceptionMessage('must print to grey, RGB or CMYK');

		$this->pdf(['PDFX' => '4', 'ICCProfile' => $profile]);
	}

	/**
	 * @return string[][] Data colour spaces no PDF/X-4 printing condition has
	 */
	public function unprintableSpaces()
	{
		return [['Lab '], ['XYZ '], ['6CLR']];
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
	 * An active check box or radio button is removed like any other field, rather than refused for
	 * the ZapfDingbats it would be drawn with
	 *
	 * @dataProvider pdfxVersions
	 *
	 * @param string $version
	 */
	public function testActiveCheckBoxesAndRadioButtonsAreRemoved($version)
	{
		$html = '<form><input type="checkbox" name="box" value="1" checked="checked" /><input type="radio" name="radio" value="1" /></form>';

		$pdf = $this->pdf(['PDFX' => $version, 'useActiveForms' => true], $html);
		$this->assertStringNotContainsString('/Subtype /Widget', $pdf);
		$this->assertStringNotContainsString('ZapfDingbats', $pdf);
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
		$this->assertStringContainsString('/CSRGB cs 1.000 0.000 0.000 sc', $cmyk);
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
	 * Print-only or hidden content needs optional content with usage application dictionaries, the /AS
	 * PDF/X-4 forbids and the optional content PDF/X-1a has none of, so it is shown in full instead
	 *
	 * @dataProvider pdfxVersions
	 *
	 * @param string $version
	 */
	public function testVisibilityIsLeftFull($version)
	{
		$html = '<div style="visibility: printonly; background: #ccc">Print only</div><p style="visibility: hidden">Hidden</p>';

		$pdf = $this->pdf(['PDFX' => $version], $html);
		$this->assertStringNotContainsString('/OC /OC', $pdf);
		$this->assertStringNotContainsString('/OCProperties', $pdf);

		$this->expectException(MpdfException::class);
		$this->pdf(['PDFX' => $version, 'PDFXauto' => false], $html);
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
	 * RGB is DeviceRGB for the RGB output intent, and written in the ICC-based sRGB colour space the
	 * page's resources name for a CMYK one, rather than converted to CMYK by a formula that knows
	 * nothing of the press. PDF/X-1a, which permits no ICC-based colour space, still converts it.
	 */
	public function testRgbIsWrittenColourManagedForACmykOutputIntent()
	{
		$html = '<p style="color: #ff0000">Text</p>';

		$rgb = $this->pdf(['PDFX' => '4'], $html);
		$this->assertStringContainsString('1.000 0.000 0.000 rg', $rgb);
		$this->assertStringNotContainsString('CSRGB', $rgb);

		$cmyk = $this->pdf(['PDFX' => '4', 'ICCProfile' => $this->cmykProfile], $html);
		$this->assertStringContainsString('/CSRGB cs 1.000 0.000 0.000 sc', $cmyk);
		$this->assertStringNotContainsString('0.000 1.000 1.000 0.000 k', $cmyk);

		$this->assertSame(1, preg_match('/\/ColorSpace <<\n\/CSRGB (\d+) 0 R/', $cmyk, $match), 'the page resources name it');
		$this->assertSame(1, preg_match('/^\[\/ICCBased (\d+) 0 R\]/', $this->object($cmyk, $match[1]), $profile));
		$this->assertStringStartsWith('<</N 3 /Length 3052>>', $this->object($cmyk, $profile[1]));

		$this->assertStringContainsString('0.000 1.000 1.000 0.000 k', $this->pdf(['PDFX' => true], $html));
	}

	/**
	 * A neutral colour goes to DeviceGray, which a CMYK output condition takes as its black separation,
	 * so that black text is printed from one plate rather than made out of four
	 */
	public function testBlackIsTheBlackInkAlone()
	{
		$html = '<p style="color: #000000">Black</p><p style="color: #808080">Grey</p>';

		$cmyk = $this->pdf(['PDFX' => '4', 'ICCProfile' => $this->cmykProfile], $html);
		$this->assertStringContainsString('0.000 g', $cmyk);
		$this->assertStringContainsString('0.502 g', $cmyk);
		$this->assertStringNotContainsString('0.000 0.000 0.000 sc', $cmyk);

		$this->assertStringContainsString('0.000 0.000 0.000 1.000 k', $this->pdf(['PDFX' => true], $html));
	}

	/**
	 * A colour the document gives as CMYK is left in CMYK for a CMYK output intent
	 */
	public function testACmykColourIsLeftAloneForACmykOutputIntent()
	{
		$html = '<p style="color: cmyk(10, 20, 30, 40)">Text</p>';

		$this->assertStringContainsString('0.100 0.200 0.300 0.400 k', $this->pdf(['PDFX' => '4', 'ICCProfile' => $this->cmykProfile], $html));
	}

	/**
	 * Nothing is converted, so an RGB colour is no longer a conformance issue for PDF/X-4, where it
	 * still is for PDF/X-1a
	 */
	public function testAnRgbColourIsNoConformanceIssueForPdfx4()
	{
		$html = '<p style="color: #ff0000">Text</p>';

		$strict = ['PDFX' => '4', 'PDFXauto' => false, 'ICCProfile' => $this->cmykProfile];
		$this->assertStringContainsString('/CSRGB cs', $this->pdf($strict, $html));

		$this->expectException(MpdfException::class);
		$this->pdf(['PDFX' => true, 'PDFXauto' => false], $html);
	}

	/**
	 * A gradient's stops are in the same colour space as the colours beside them
	 */
	public function testAGradientIsWrittenInTheSameColourSpaceAsTheContent()
	{
		$html = '<div style="background: linear-gradient(#ff0000, #0000ff); height: 20mm">Text</div>';

		$this->assertStringContainsString('/ColorSpace /DeviceRGB', $this->pdf(['PDFX' => '4'], $html));

		$cmyk = $this->pdf(['PDFX' => '4', 'ICCProfile' => $this->cmykProfile], $html);
		$this->assertStringNotContainsString('/ColorSpace /DeviceRGB', $cmyk);
		$this->assertSame(1, preg_match('/\/ShadingType \d\n\/ColorSpace (\d+) 0 R/', $cmyk, $match));
		$this->assertStringStartsWith('[/ICCBased ', $this->object($cmyk, $match[1]));
	}

	/**
	 * CMYK is converted to RGB for the RGB output intent
	 */
	public function testCmykIsConvertedForAnRgbOutputIntent()
	{
		$html = '<p style="color: cmyk(0, 100, 100, 0)">Text</p>';

		$this->assertStringContainsString('1.000 0.000 0.000 rg', $this->pdf(['PDFX' => '4'], $html));
		$this->assertStringContainsString('0.000 1.000 1.000 0.000 k', $this->pdf(['PDFX' => '4', 'ICCProfile' => $this->cmykProfile], $html), 'a CMYK intent leaves it alone');
	}

	/**
	 * A grey output intent permits no DeviceCMYK either, so CMYK colours and gradients are converted
	 * to RGB, which is then written in the ICC-based sRGB colour space
	 */
	public function testCmykIsConvertedForAGreyOutputIntent()
	{
		$html = '<p style="color: cmyk(0, 100, 100, 0)">Text</p>'
			. '<div style="background: linear-gradient(cmyk(0, 100, 0, 0), cmyk(100, 0, 0, 0)); height: 10mm">Gradient</div>';

		$pdf = $this->pdf(['PDFX' => '4', 'ICCProfile' => $this->writeProfile('grey', 'GRAY')], $html);

		$this->assertStringNotContainsString('DeviceCMYK', $pdf);
		$this->assertSame(0, preg_match('/[\d.]+ [\d.]+ [\d.]+ [\d.]+ k\b/', $pdf));
		$this->assertStringContainsString('/CSRGB cs 1.000 0.000 0.000 sc', $pdf);
	}

	/**
	 * A spot colour falls back to RGB where the output intent is not CMYK: DeviceRGB for an RGB intent,
	 * the ICC-based sRGB colour space for a grey one
	 */
	public function testASpotColourFallsBackToTheColourSpaceOfTheOutputIntent()
	{
		$html = '<p style="color: spot(PANTONE 300 C, 80%)">Text</p>';
		$spot = function (Mpdf $mpdf) {
			$mpdf->AddSpotColor('PANTONE 300 C', 100, 44, 0, 0);
		};

		$pdf = $this->pdf(['PDFX' => '4'], $html, $spot);
		$this->assertStringContainsString("/Separation /PANTONE#20300#20C\n/DeviceRGB <<\n/Range [0 1 0 1 0 1] /C0 [1 1 1] \n/C1 [0.000 0.557 1.000]", $pdf);
		$this->assertStringNotContainsString('DeviceCMYK', $pdf);

		$pdf = $this->pdf(['PDFX' => '4', 'ICCProfile' => $this->writeProfile('grey', 'GRAY')], $html, $spot);
		$this->assertSame(1, preg_match('/\/Separation \/PANTONE#20300#20C\n(\d+) 0 R <</', $pdf, $match));
		$this->assertSame('[/ICCBased', substr($this->object($pdf, $match[1]), 0, 10));

		$pdf = $this->pdf(['PDFX' => '4', 'ICCProfile' => $this->cmykProfile], $html, $spot);
		$this->assertStringContainsString("/Separation /PANTONE#20300#20C\n/DeviceCMYK <<", $pdf, 'a CMYK intent keeps the CMYK');
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
		$this->assertStringNotContainsString('CSRGB', $rgb);

		$pdf = $this->colourFontPdf(['PDFX' => '4', 'PDFXauto' => false, 'default_font' => 'colr', 'ICCProfile' => $this->cmykProfile]);
		$this->assertStringNotContainsString(' rg', $pdf);
		$this->assertStringContainsString('/CSRGB cs 1.000 0.800 0.200 sc', $pdf, 'the face, yellow');
		$this->assertSame(1, preg_match('/\/ColorSpace <<\/CSRGB (\d+) 0 R >>/', $pdf, $match));
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
	 * DeviceGray is permitted only where the output intent is grey or CMYK, so under the default sRGB
	 * one each neutral colour - the default text colour among them - is set in an ICC-based grey colour
	 * space the page's resources name, on the grey profile mPDF ships
	 */
	public function testGreyIsSetInAnIccBasedColourSpaceForAnRgbOutputIntent()
	{
		$mpdf = $this->mpdf(['PDFX' => '4']);
		$this->assertTrue($mpdf->writesCalibratedGray());
		$mpdf->WriteHTML('<p>Text</p>');
		$mpdf->SetDrawColor(0);
		$mpdf->SetFillColor(128);
		$mpdf->Rect(20, 60, 30, 10, 'DF');
		$pdf = $mpdf->OutputBinaryData();

		$this->assertStringContainsString('/CSGRAY cs 0.000 sc', $pdf);
		$this->assertStringContainsString('/CSGRAY CS 0.000 SC', $pdf);
		$this->assertStringContainsString('/CSGRAY cs 0.502 sc', $pdf);
		$this->assertNoDeviceGrayButSoftMasks($pdf);

		$this->assertSame(1, preg_match('/\/ColorSpace <<\n\/CSGRAY (\d+) 0 R/', $pdf, $match), 'the page resources name it');
		$this->assertSame(1, preg_match('/^\[\/ICCBased (\d+) 0 R\]/', $this->object($pdf, $match[1]), $profile));
		$this->assertStringStartsWith('<</N 1 /Length ' . filesize(self::GRAY) . '>>', $this->object($pdf, $profile[1]));
		$this->assertSame(file_get_contents(self::GRAY), $this->stream($pdf, $profile[1]));
		$this->assertSame(1, substr_count($pdf, 'Gray, sRGB tone curve (mPDF)'), 'written once');
	}

	/**
	 * Anywhere DeviceGray is permitted, grey stays DeviceGray and no grey profile is written
	 *
	 * @dataProvider deviceGrayDocuments
	 *
	 * @param array       $config
	 * @param string|null $space  The data colour space of an ICCProfile to print to, if any
	 */
	public function testGreyStaysDeviceGrayWhereItIsPermitted(array $config, $space = null)
	{
		if ($space) {
			$config['ICCProfile'] = $this->writeProfile(strtolower($space), $space);
		}

		$mpdf = $this->mpdf($config + ['PDFAauto' => true]);
		$this->assertFalse($mpdf->writesCalibratedGray());
		$mpdf->WriteHTML('<p>Text</p><div style="background: linear-gradient(0, 255); height: 10mm">Gradient</div>');
		$pdf = $mpdf->OutputBinaryData();

		$this->assertStringContainsString('0.000 g', $pdf);
		$this->assertStringNotContainsString('CSGRAY', $pdf);
		$this->assertStringNotContainsString('Gray, sRGB tone curve (mPDF)', $pdf);
	}

	/**
	 * @return array[] Documents that may use DeviceGray
	 */
	public function deviceGrayDocuments()
	{
		return [
			'not PDF/X' => [[]],
			'PDF/X-1a' => [['PDFX' => true]],
			'PDF/X-4 printing to CMYK' => [['PDFX' => '4'], 'CMYK'],
			'PDF/X-4 printing to grey' => [['PDFX' => '4'], 'GRAY'],
			'PDF/A' => [['PDFA' => true]],
		];
	}

	/**
	 * A greyscale JPEG or PNG keeps its data and names the ICC-based grey colour space; the soft mask of
	 * a PNG with alpha, which mPDF draws in RGB, stays DeviceGray, as ISO 32000 has a soft mask be
	 */
	public function testGreyImagesAreInTheIccBasedGreyColourSpace()
	{
		$html = '<img src="' . __DIR__ . '/../data/img/exif-orientation-6-gray.jpg" />'
			. '<img src="data:image/png;base64,' . base64_encode($this->greyPng(false)) . '" />'
			. '<img src="data:image/png;base64,' . base64_encode($this->greyPng(true)) . '" />';

		$pdf = $this->pdf(['PDFX' => '4'], $html);
		$this->assertNoDeviceGrayButSoftMasks($pdf);

		$images = $this->imageObjects($pdf);
		$this->assertCount(4, $images, 'three images and a soft mask');
		$this->assertSame(1, preg_match('/\/CSGRAY (\d+) 0 R/', $pdf, $gray));
		$spaces = [];
		foreach ($images as $image) {
			preg_match('/\/ColorSpace (\S+(?: 0 R)?)\n/', $image[0], $space);
			$spaces[] = $space[1];
		}
		$this->assertSame([$gray[1] . ' 0 R', $gray[1] . ' 0 R', '/DeviceGray', '/DeviceRGB'], $spaces, 'the JPEG, the PNG, then the soft mask and the PNG with alpha');

		$mpdf = $this->mpdf(['PDFX' => '4'], DeviceGrayMpdf::class);
		$mpdf->WriteHTML($html);
		$this->assertSame(array_column($this->imageObjects($mpdf->OutputBinaryData()), 1), array_column($images, 1), 'not encoded again');
	}

	/**
	 * A gradient of grey stops, and the luminosity soft mask behind an rgba gradient or a box-shadow - its
	 * shading and the transparency group it is blended in - are in the ICC-based grey colour space
	 */
	public function testGradientsAndTheirSoftMasksAreInTheIccBasedGreyColourSpace()
	{
		$html = '<div style="background: linear-gradient(0, 255); height: 10mm">Linear</div>'
			. '<div style="background: radial-gradient(0, 200); height: 10mm">Radial</div>'
			. '<div style="background: linear-gradient(rgba(255, 0, 0, 0.5), rgba(0, 0, 255, 1)); height: 10mm">Translucent</div>'
			. '<div style="box-shadow: 2mm 2mm 2mm 0; height: 10mm; width: 40mm">Grey shadow</div>'
			. '<div style="box-shadow: 2mm 2mm 2mm rgba(0, 0, 0, 0.5); height: 10mm; width: 40mm">Translucent shadow</div>';

		$pdf = $this->pdf(['PDFX' => '4'], $html);
		$this->assertNoDeviceGrayButSoftMasks($pdf);

		$this->assertSame(1, preg_match('/\/CSGRAY (\d+) 0 R/', $pdf, $gray));
		$grey = $gray[1] . ' 0 R';
		$this->assertStringContainsString("/ShadingType 2\n/ColorSpace " . $grey . "\n", $pdf, 'the grey linear gradient');
		$this->assertStringContainsString("/ShadingType 3\n/ColorSpace " . $grey . "\n", $pdf, 'the grey radial gradient');
		$this->assertStringContainsString("/ShadingType 6\n/ColorSpace " . $grey . "\n", $pdf, 'the grey shadow');
		$this->assertGreaterThan(1, substr_count($pdf, '/Group << /Type /Group /S /Transparency /CS ' . $grey . ' >>'), 'the groups of the soft masks');
	}

	/**
	 * A colour font's soft masks - of a gradient's alpha, of the shapes of a clip path - are drawn in the
	 * ICC-based grey colour space its Type3 font's resources name; the soft masks of its images stay
	 * DeviceGray
	 */
	public function testColourFontSoftMasksAreInTheIccBasedGreyColourSpace()
	{
		$emoji = '';
		foreach ([0x1F600, 0x2764, 0x1F468, 0x1F469, 0x1F467, 0x1F44D] as $codepoint) {
			$emoji .= UtfString::code2utf($codepoint);
		}

		$colrV1 = $this->colourFontPdf(['PDFX' => '4', 'default_font' => 'colrv1'], $emoji);
		$this->assertNoDeviceGrayButSoftMasks($colrV1);
		$this->assertSame(1, preg_match('/\/CSGRAY (\d+) 0 R/', $colrV1, $gray));
		$this->assertStringContainsString('/Group <</S /Transparency /CS ' . $gray[1] . ' 0 R>>', $colrV1, 'a luminosity mask');

		$svg = $this->colourFontPdf(['PDFX' => '4', 'default_font' => 'svg'], $emoji);
		$this->assertNoDeviceGrayButSoftMasks($svg);
		$this->assertStringContainsString("/CSGRAY cs 0 sc\n", $svg, 'the clip path of several shapes');
		$this->assertSame(1, preg_match('/\/ColorSpace <<\/CSGRAY \d+ 0 R >>/', $svg), 'named by the Type3 font');

		$cbdt = $this->colourFontPdf(['PDFX' => '4']);
		$this->assertNoDeviceGrayButSoftMasks($cbdt);
		$this->assertStringContainsString('/SMask', $cbdt);
	}

	/**
	 * A content stream starts in black in DeviceGray, so each page - headers, footers and watermark
	 * included - and each form drawn from SVG sets black in the ICC-based grey colour space before it
	 * draws anything. A form drawn as a background looks the colour space up in its pattern's resources.
	 */
	public function testEachPageAndFormStartsInTheIccBasedGreyColourSpace()
	{
		$svg = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="40" height="20"><rect width="20" height="20"/><text x="22" y="15">SVG</text></svg>');

		$mpdf = $this->mpdf(['PDFX' => '4']);
		$mpdf->SetHTMLHeader('<div>Header</div>');
		$mpdf->SetHTMLFooter('<div>Footer</div>');
		$mpdf->SetWatermarkText('Watermark');
		$mpdf->showWatermarkText = true;
		$mpdf->WriteHTML('<p>Page one</p><img src="' . $svg . '" /><pagebreak /><div style="background: url(' . $svg . '); height: 20mm">Page two</div>');
		$pdf = $mpdf->OutputBinaryData();

		$this->assertNoDeviceGrayButSoftMasks($pdf);

		$start = "/CSGRAY cs 0.000 sc /CSGRAY CS 0.000 SC\n";
		$this->assertSame(2, preg_match_all('/\/Contents (\d+) 0 R/', $pdf, $contents));
		foreach ($contents[1] as $number) {
			$this->assertStringStartsWith($start, $this->stream($pdf, $number));
		}

		$this->assertSame(1, preg_match('/(\d+) 0 obj\n<<\/Type \/XObject\n\/Subtype \/Form\n/', $pdf, $form));
		$this->assertStringStartsWith($start, $this->stream($pdf, $form[1]));

		$this->assertSame(1, preg_match('/\/XObject <<\/FO\d+ \d+ 0 R >>\n\/ColorSpace <<\/CSGRAY \d+ 0 R >>/', $pdf), 'the background pattern\'s resources');
	}

	/**
	 * Grey in the ICC-based colour space draws as it did in DeviceGray, pixel for pixel
	 */
	public function testGreyDrawsAsItDidInDeviceGray()
	{
		if (!class_exists('Imagick')) {
			$this->markTestSkipped('Imagick is not installed');
		}

		$html = '<p>Text in the default colour</p>'
			. '<div style="background: linear-gradient(0, 255); height: 10mm">A grey gradient</div>'
			. '<img src="' . __DIR__ . '/../data/img/exif-orientation-6-gray.jpg" width="20mm" />'
			. '<img src="data:image/png;base64,' . base64_encode($this->greyPng(false)) . '" width="40mm" />'
			. '<img src="data:image/png;base64,' . base64_encode($this->greyPng(true)) . '" width="40mm" />'
			. '<div style="background: linear-gradient(rgba(255, 0, 0, 0.5), rgba(0, 0, 255, 1)); height: 10mm">Translucent</div>'
			. '<div style="box-shadow: 2mm 2mm 2mm 0; height: 10mm; width: 40mm">Shadow</div>';

		$pixels = [];
		foreach ([Mpdf::class, DeviceGrayMpdf::class] as $class) {
			$mpdf = $this->mpdf(['PDFX' => '4'], $class);
			$mpdf->SetWatermarkText('Watermark');
			$mpdf->showWatermarkText = true;
			$mpdf->WriteHTML($html);
			$mpdf->SetFillColor(128);
			$mpdf->Rect(20, 250, 30, 10, 'F');
			$pdf = $mpdf->OutputBinaryData();
			$this->assertSame($mpdf->writesCalibratedGray(), strpos($pdf, 'CSGRAY') !== false);

			$image = new \Imagick();
			$image->setResolution(120, 120);
			try {
				$image->readImageBlob($pdf);
			} catch (\ImagickException $e) {
				$this->markTestSkipped('Imagick cannot read a PDF here (' . trim($e->getMessage()) . ')');
			}
			$pixels[] = $image;
		}

		$difference = $pixels[0]->compareImages($pixels[1], \Imagick::METRIC_ROOTMEANSQUAREDERROR);
		$this->assertSame(0.0, (float) $difference[1]);
	}

	/**
	 * mPDF reads back what it writes: a page imported into another document keeps its grey in the
	 * ICC-based colour space
	 */
	public function testADocumentWithIccBasedGreyIsImportedByMpdf()
	{
		$source = $this->pdf(['PDFX' => '4'], '<p>Text</p><div style="background: linear-gradient(0, 255); height: 10mm">Gradient</div>');

		$mpdf = $this->mpdf();
		$pages = $mpdf->setSourceFile(StreamReader::createByString($source));
		$mpdf->AddPage();
		$mpdf->useTemplate($mpdf->importPage(1));
		$pdf = $mpdf->OutputBinaryData();

		$this->assertSame(1, $pages);
		$this->assertStringContainsString('/CSGRAY cs 0.000 sc', $pdf);
		$this->assertStringContainsString('Gray, sRGB tone curve (mPDF)', $pdf);
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
	 * @param array       $config Merged over mpdf()'s configuration
	 * @param string|null $text   What to draw, the grinning face where not given
	 *
	 * @return string A document drawing the text in a test colour font, the CBDT one by default
	 */
	private function colourFontPdf(array $config, $text = null)
	{
		return $this->pdf($config + [
			'fontRegistry' => new FontRegistry([]),
			'fontDir' => [__DIR__ . '/../data/ttf/color'],
			'fontdata' => [
				'cbdt' => ['R' => 'TestEmoji-CBDT.ttf', 'useOTL' => 0xFF],
				'colr' => ['R' => 'TestEmoji-COLRv0.ttf', 'useOTL' => 0xFF],
				'colrv1' => ['R' => 'TestEmoji-COLRv1.ttf', 'useOTL' => 0xFF],
				'svg' => ['R' => 'TestEmoji-SVG.ttf', 'useOTL' => 0xFF],
			],
			'default_font' => 'cbdt',
		], '<p>' . ($text === null ? UtfString::code2utf(0x1F600) : $text) . '</p>');
	}

	/**
	 * Asserts that no content stream sets a colour with g or G, and that nothing names /DeviceGray but
	 * the soft masks of images
	 *
	 * @param string $pdf An uncompressed document
	 */
	private function assertNoDeviceGrayButSoftMasks($pdf)
	{
		preg_match_all('/(\d+) 0 obj\n((?:(?!endobj).)*?)stream\n(.*?)\nendstream/s', $pdf, $streams, PREG_SET_ORDER);
		foreach ($streams as $stream) {
			// Images, fonts and ICC profiles are binary
			if (!preg_match('/\/Subtype \/Image|\/Length1|\/N \d/', $stream[2])) {
				$this->assertSame(0, preg_match('/(^|\s)[\d.]+ [gG]\s/', $stream[3]), sprintf('object %d sets a colour in DeviceGray', $stream[1]));
			}
		}

		$softMasks = $this->softMasks($pdf);
		preg_match_all('/\n(\d+) 0 obj\n((?:(?!endobj).)*)/s', $pdf, $objects, PREG_SET_ORDER);
		foreach ($objects as $object) {
			if (strpos($object[2], '/DeviceGray') !== false) {
				$this->assertContains($object[1], $softMasks, sprintf('object %d names /DeviceGray and is not a soft mask', $object[1]));
			}
		}
	}

	/**
	 * @param string $pdf
	 *
	 * @return string[] The object number of each image an image names as its /SMask
	 */
	private function softMasks($pdf)
	{
		preg_match_all('/\/SMask (\d+) 0 R/', $pdf, $masks);

		return $masks[1];
	}

	/**
	 * @param string $pdf An uncompressed document
	 *
	 * @return array[] Each image, as [its dictionary, its data], by object number
	 */
	private function imageObjects($pdf)
	{
		preg_match_all('/\n(\d+) 0 obj\n<<\/Type \/XObject\n\/Subtype \/Image\n(.*?)>>\nstream\n(.*?)\nendstream/s', $pdf, $images, PREG_SET_ORDER);

		$byNumber = [];
		foreach ($images as $image) {
			$byNumber[$image[1]] = [$image[2], $image[3]];
		}

		return $byNumber;
	}

	/**
	 * @param string     $pdf    An uncompressed document
	 * @param int|string $number An object with a stream
	 *
	 * @return string Its stream
	 */
	private function stream($pdf, $number)
	{
		preg_match('/stream\n(.*)\nendstream/s', $this->object($pdf, $number), $match);

		return $match[1];
	}

	/**
	 * @param bool $alpha Whether it has an alpha channel, which mPDF writes as a soft mask
	 *
	 * @return string A 16 by 16 greyscale PNG of every grey level, made here
	 */
	private function greyPng($alpha)
	{
		$rows = '';
		for ($y = 0; $y < 16; $y++) {
			$rows .= "\0"; // no filter
			for ($x = 0; $x < 16; $x++) {
				$rows .= chr($y * 16 + $x) . ($alpha ? chr(255 - $x * 16) : '');
			}
		}

		$chunk = function ($type, $data) {
			return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
		};

		// Colour type 0 is grey, 4 grey with alpha
		return "\x89PNG\r\n\x1a\n"
			. $chunk('IHDR', pack('NNCCCCC', 16, 16, 8, $alpha ? 4 : 0, 0, 0, 0))
			. $chunk('IDAT', gzcompress($rows))
			. $chunk('IEND', '');
	}
}
