<?php

namespace Mpdf;

use Mpdf\Fonts\FontRegistry;
use Mpdf\Utils\UtfString;

/**
 * A colour font written as Type3 fonts, read back out of the PDF it is written into.
 *
 * TestEmoji-CBDT draws each emoji as a 64 pixel bitmap at 1000 units to the em; its space is glyph 1
 * and draws nothing.
 */
class ColorFontTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * @param array $config Merged over the configuration below
	 *
	 * @return Mpdf
	 */
	private function mpdf(array $config = [])
	{
		$mpdf = new Mpdf($config + [
			'mode' => 'utf-8',
			'fontRegistry' => new FontRegistry([]),
			'fontDir' => [__DIR__ . '/../data/ttf/color', __DIR__ . '/../../packages/Emoji/fonts'],
			'fontdata' => [
				'cbdt' => ['R' => 'TestEmoji-CBDT.ttf', 'useOTL' => 0xFF],
				'sbix' => ['R' => 'TestEmoji-sbix.ttf', 'useOTL' => 0xFF],
				'colr' => ['R' => 'TestEmoji-COLRv0.ttf', 'useOTL' => 0xFF],
				'colrv1' => ['R' => 'TestEmoji-COLRv1.ttf', 'useOTL' => 0xFF],
				'svg' => ['R' => 'TestEmoji-SVG.ttf', 'useOTL' => 0xFF],
				'notoemoji' => ['R' => 'NotoEmoji-Regular.ttf'],
			],
			'default_font' => 'cbdt',
		]);
		$mpdf->SetCompression(false);

		return $mpdf;
	}

	/**
	 * @param int[] $codepoints The paragraph's text
	 * @param array $config     Merged over mpdf()'s configuration
	 *
	 * @return string[] The document's objects, by number
	 */
	private function objects(array $codepoints, array $config = [])
	{
		$mpdf = $this->mpdf($config);
		$mpdf->WriteHTML('<p>' . implode('', array_map('Mpdf\Utils\UtfString::code2utf', $codepoints)) . '</p>');

		return $this->objectsOf($mpdf);
	}

	/**
	 * @param Mpdf $mpdf A document, its content written
	 *
	 * @return string[] Its objects, by number
	 */
	private function objectsOf(Mpdf $mpdf)
	{
		preg_match_all('/(?:^|\n)(\d+) 0 obj\n(.*?)\nendobj/s', $mpdf->OutputBinaryData(), $matches, PREG_SET_ORDER);

		$objects = [];
		foreach ($matches as $match) {
			$objects[(int) $match[1]] = $match[2];
		}

		return $objects;
	}

	/**
	 * @param string[] $objects The document's objects, by number
	 * @param string   $pattern A regular expression
	 *
	 * @return string The one object whose body matches
	 */
	private function objectMatching(array $objects, $pattern)
	{
		$found = preg_grep($pattern, $objects);
		$this->assertCount(1, $found, 'one object matching ' . $pattern);

		return reset($found);
	}

	/**
	 * @param string[] $objects The document's objects, by number
	 * @param string   $font    The body of the object holding the reference
	 * @param string   $key     The key it is under, e.g. 'ToUnicode'
	 *
	 * @return string The body of the object a reference names, e.g. '/ToUnicode 12 0 R'
	 */
	private function referenced(array $objects, $font, $key)
	{
		$this->assertSame(1, preg_match('/\/' . $key . ' (\d+) 0 R/', $font, $match), $key);

		return $objects[(int) $match[1]];
	}

	/**
	 * @param string[] $objects The document's objects, by number
	 * @param int      $glyph   A glyph of the document's one Type3 font
	 *
	 * @return string The body of the glyph's procedure
	 */
	private function procedure(array $objects, $glyph)
	{
		$procedures = $this->referenced($objects, $this->objectMatching($objects, '/\/Subtype \/Type3/'), 'CharProcs');
		$this->assertSame(1, preg_match('/\/g' . $glyph . ' (\d+) 0 R/', $procedures, $match), 'glyph ' . $glyph);

		return $objects[(int) $match[1]];
	}

	/**
	 * The font is scaled from font units by its matrix, and draws its glyphs' images from a resource
	 * dictionary of its own. The page's would list the font itself, which Acrobat refuses to load.
	 */
	public function testAColourFontIsWrittenAsAType3FontWithResourcesOfItsOwn()
	{
		$objects = $this->objects([0x1F600]);
		$font = $this->objectMatching($objects, '/\/Subtype \/Type3/');
		$resources = $this->referenced($objects, $font, 'Resources');

		$this->assertStringContainsString('/FontMatrix [0.0010000000 0 0 0.0010000000 0 0]', $font);
		$this->assertStringNotContainsString('/Resources 2 0 R', $font);
		$this->assertMatchesRegularExpression('/\/XObject <<\/I2 \d+ 0 R >>/', $resources);
		$this->assertStringNotContainsString('/Font', $resources);
	}

	/**
	 * d0 leaves the procedure free to set colours; the bitmap is placed in font units, and its alpha
	 * is an image of its own that the bitmap names as its /SMask
	 */
	public function testAGlyphIsItsWidthAndItsBitmap()
	{
		$objects = $this->objects([0x1F600]);
		$font = $this->objectMatching($objects, '/\/Subtype \/Type3/');
		$procedures = $this->referenced($objects, $font, 'CharProcs');

		$this->assertSame(1, preg_match('/\/g12 (\d+) 0 R/', $procedures, $match), 'the grinning face is glyph 12');
		$this->assertStringContainsString("1000.000 0 d0\nq 1000.000 0 0 1000.000 0.000 -93.750 cm /I2 Do Q", $objects[(int) $match[1]]);

		$image = $this->objectMatching($objects, '/\/Subtype \/Image.*\/SMask/s');
		$this->assertStringContainsString('/Width 64', $image);
		$this->assertStringContainsString('/ColorSpace /DeviceRGB', $image);
		$this->assertMatchesRegularExpression('/\/I2 \d+ 0 R/', $objects[2]);
	}

	/**
	 * The first 128 bytes are ASCII, so word spacing stretches the space at byte 32
	 */
	public function testTheSpaceIsByte32()
	{
		$font = $this->objectMatching($this->objects([0x1F600, 0x20, 0x1F600]), '/\/Subtype \/Type3/');

		$this->assertStringContainsString('32 /g1 ', $font);
	}

	/**
	 * Copied out of the PDF, the family is the ZWJ sequence it was typed as rather than the Private
	 * Use code its ligature was handed
	 */
	public function testALigatureIsCopiedAsTheSequenceItWasFormedFrom()
	{
		$objects = $this->objects([0x1F468, 0x200D, 0x1F469, 0x200D, 0x1F467]);
		$toUnicode = $this->referenced($objects, $this->objectMatching($objects, '/\/Subtype \/Type3/'), 'ToUnicode');

		$this->assertStringContainsString('<D83DDC68200DD83DDC69200DD83DDC67>', $toUnicode);
	}

	/**
	 * Each ligature copies out as what was typed: a flag's two regional indicators, a keycap's digit
	 * and U+20E3 (its U+FE0F went before shaping), a thumb and its skin tone, and the flag of England
	 * with its tags rather than the Private Use codes the shaper was handed for them
	 *
	 * @dataProvider sequences
	 *
	 * @param int[]  $codepoints What is typed
	 * @param string $utf16      What the ToUnicode map gives back, as UTF-16BE hex
	 */
	public function testEachSequenceIsCopiedAsItWasTyped(array $codepoints, $utf16)
	{
		$objects = $this->objects($codepoints);
		$toUnicode = $this->referenced($objects, $this->objectMatching($objects, '/\/Subtype \/Type3/'), 'ToUnicode');

		$this->assertStringContainsString('<' . $utf16 . '>', $toUnicode);
	}

	/**
	 * @return array[] Each sequence the fixture forms a ligature of, and what it copies out as
	 */
	public function sequences()
	{
		return [
			'a flag' => [[0x1F1E6, 0x1F1FA], 'D83CDDE6D83CDDFA'],
			'a keycap' => [[0x31, 0xFE0F, 0x20E3], '003120E3'],
			'a skin tone' => [[0x1F44D, 0x1F3FD], 'D83DDC4DD83CDFFD'],
			'the flag of England' => [
				[0x1F3F4, 0xE0067, 0xE0062, 0xE0065, 0xE006E, 0xE0067, 0xE007F],
				'D83CDFF4DB40DC67DB40DC62DB40DC65DB40DC6EDB40DC67DB40DC7F',
			],
		];
	}

	/**
	 * The girl of TestEmoji-sbix is a JPEG, which is written as it stands, and the woman a 'dupe' of the
	 * man, whose image is written once for both
	 */
	public function testAnSbixJpegIsWrittenAsItStandsAndADupeSharesItsImage()
	{
		$objects = $this->objects([0x1F467, 0x1F468, 0x1F469], ['default_font' => 'sbix']);

		$jpeg = $this->objectMatching($objects, '/\/Filter \/DCTDecode/');
		$this->assertStringContainsString('/ColorSpace /DeviceRGB', $jpeg);
		$this->assertStringNotContainsString('/SMask', $jpeg);

		$this->assertCount(3, preg_grep('/\/Subtype \/Image/', $objects), 'the JPEG, and the man and his mask once');
	}

	/**
	 * The number sign of TestEmoji-COLRv0 has no layers, so it is its outline in the colour of the
	 * text; the heart's highlight is half-opaque, through a graphics state the font's own resources name
	 */
	public function testAColrGlyphIsItsLayersAndAPlainGlyphItsOutline()
	{
		$objects = $this->objects([0x23, 0x2764], ['default_font' => 'colr']);

		$numberSign = $this->procedure($objects, 2);
		$this->assertStringContainsString("600.000 0 d0\n100 0 m\n100 700 l\n", $numberSign);
		$this->assertStringNotContainsString(' rg', $numberSign);

		$this->assertSame(1, preg_match('/q \/GS(\d+) gs 1\.000 1\.000 1\.000 rg/', $this->procedure($objects, 13), $gs));

		$resources = $this->referenced($objects, $this->objectMatching($objects, '/\/Subtype \/Type3/'), 'Resources');
		$this->assertSame(1, preg_match('/\/ExtGState <<\/GS' . $gs[1] . ' (\d+) 0 R >>/', $resources, $state));
		$this->assertStringNotContainsString('/XObject', $resources, 'the font draws no image');
		$this->assertMatchesRegularExpression('/\/Type \/ExtGState\s*\/BM \/Normal\s*\/ca 0\.50/', $objects[(int) $state[1]]);
	}

	/**
	 * TestEmoji-COLRv1's heart is a gradient kept to the heart by SRC_IN, its woman a gradient whose
	 * alpha varies, and its family faces multiplied onto a square. The heart's shading, the woman's soft
	 * mask and the family's groups are each named by the font's own resources, and the groups draw with
	 * those same resources.
	 */
	public function testAColrV1GlyphsShadingsGroupsAndMasksAreTheFontsResources()
	{
		$objects = $this->objects([0x2764, 0x1F469, 0x1F468, 0x200D, 0x1F469, 0x200D, 0x1F467], ['default_font' => 'colrv1']);
		$font = $this->objectMatching($objects, '/\/Subtype \/Type3/');
		$resources = $this->referenced($objects, $font, 'Resources');

		$this->assertSame(1, preg_match('/W n\n\/(Sh\d+) sh\n/', $this->procedure($objects, 13), $heart));
		$this->assertStringStartsWith('<</ShadingType 2 /ColorSpace /DeviceRGB /Coords [500.000 750.000 500.000 -50.000] /Function <</FunctionType 2 /Domain [0 1] /C0 [0.878 0.141 0.369] /C1 [1.000 0.800 0.200] /N 1>> /Extend [true true]>>', $this->referenced($objects, $resources, $heart[1]));

		$this->assertSame(1, preg_match('/q \/(SM\d+) gs\n/', $this->procedure($objects, 15), $woman));
		$this->assertMatchesRegularExpression('/^<<\/Type \/ExtGState \/SMask <<\/Type \/Mask \/S \/Luminosity \/G \d+ 0 R>>>>$/', $this->referenced($objects, $resources, $woman[1]));

		$this->assertSame(1, preg_match('/\/(Fx\d+) Do/', $this->procedure($objects, 22), $family));
		$group = $this->referenced($objects, $resources, $family[1]);
		$this->assertMatchesRegularExpression('/^<<\/Type \/XObject \/Subtype \/Form \/BBox \[20\.000 -100\.000 980\.000 850\.000\] \/Group <<\/S \/Transparency \/I true>> \/Resources \d+ 0 R /', $group);
		$this->assertSame($resources, $this->referenced($objects, $group, 'Resources'));
	}

	/**
	 * TestEmoji-COLRv1's skin tone, a sweep gradient, is a free-form triangle mesh of 90 wedges whose
	 * corners carry an offset its function takes from skin tone to red and back, 7 bytes to a corner: a
	 * flag, x and y, and the offset
	 */
	public function testAColrV1SweepGradientIsATriangleMesh()
	{
		$objects = $this->objects([0x1F3FD], ['default_font' => 'colrv1']);
		$resources = $this->referenced($objects, $this->objectMatching($objects, '/\/Subtype \/Type3/'), 'Resources');
		$mesh = $this->referenced($objects, $resources, 'Sh1');

		$this->assertStringStartsWith('<</ShadingType 4 /ColorSpace /DeviceRGB /BitsPerCoordinate 16 /BitsPerComponent 16 /BitsPerFlag 8 /Decode [', $mesh);
		$this->assertStringContainsString(' 0 1] /Function <</FunctionType 3 /Domain [0 1] /Functions [<</FunctionType 2 /Domain [0 1] /C0 [0.776 0.525 0.259] /C1 [0.878 0.141 0.369] /N 1>> ', $mesh);
		$this->assertStringContainsString('/Length ' . 90 * 3 * 7 . '>>', $mesh);
	}

	/**
	 * TestEmoji-SVG's girl is a PNG, its heart a gradient and its woman clipped by a mask: each is named
	 * by the font's own resources, and the glyphs draw them by those names. The flag of England, which
	 * has no SVG document, is its outline.
	 */
	public function testAnSvgGlyphsImagesShadingsAndMasksAreTheFontsResources()
	{
		$objects = $this->objects([0x1F467, 0x2764, 0x1F469, 0x1F3F4, 0xE0067, 0xE0062, 0xE0065, 0xE006E, 0xE0067, 0xE007F], ['default_font' => 'svg']);
		$resources = $this->referenced($objects, $this->objectMatching($objects, '/\/Subtype \/Type3/'), 'Resources');

		$this->assertSame(1, preg_match('/(\/I\d+) Do/', $this->procedure($objects, 16), $girl));
		$this->assertStringContainsString('/Subtype /Image', $this->referenced($objects, $resources, substr($girl[1], 1)));

		$this->assertStringContainsString("W n\n/Sh1 sh\n", $this->procedure($objects, 13));
		$this->assertStringStartsWith('<</ShadingType 2 /ColorSpace /DeviceRGB /Coords [500.000 -750.000 500.000 50.000]', $this->referenced($objects, $resources, 'Sh1'));

		$this->assertSame(1, preg_match('/q\n\/(SM\d+) gs\n/', $this->procedure($objects, 15), $woman));
		$this->assertStringContainsString('/S /Alpha', $this->referenced($objects, $resources, $woman[1]));

		$this->assertStringContainsString("0 d0\n50 -100 m\n50 800 l\n950 800 l\n950 -100 l\nh\n420 -100 m\n", $this->procedure($objects, 26), 'the flag of England, from its outline');
	}

	/**
	 * Where the document may not draw colour, a COLR font is drawn from its outlines, with no colour at
	 * all, and there is nothing to warn of
	 *
	 * @dataProvider restrictions
	 *
	 * @param array $config What keeps colour out
	 */
	public function testAColrFontIsDrawnFromItsOutlinesWhereColourIsOff(array $config)
	{
		$logger = new TestLogger();
		$mpdf = $this->mpdf($config + ['default_font' => 'colr']);
		$mpdf->setLogger($logger);
		$mpdf->WriteHTML('<p>' . UtfString::code2utf(0x1F600) . '</p>');

		$objects = $this->objectsOf($mpdf);

		$face = $this->procedure($objects, 12);
		$this->assertStringContainsString(' c', $face);
		$this->assertStringNotContainsString(' rg', $face);
		$this->assertStringNotContainsString(' gs', $face);

		$this->assertSame([], $mpdf->PDFAXwarnings);
		$this->assertFalse($logger->hasWarningRecords(), 'nothing is left blank');
	}

	/**
	 * With compression on, a glyph's procedure is written deflated, and inflates to what it draws
	 */
	public function testACompressedProcedureInflatesToTheGlyph()
	{
		$mpdf = $this->mpdf();
		$mpdf->SetCompression(true);
		$mpdf->WriteHTML('<p>' . UtfString::code2utf(0x1F600) . '</p>');
		$pdf = $mpdf->OutputBinaryData();

		$this->assertSame(1, preg_match('/\/g12 (\d+) 0 R/', $pdf, $match), 'the grinning face is glyph 12');
		$this->assertSame(1, preg_match('/\n' . $match[1] . ' 0 obj\n<<\/Filter \/FlateDecode \/Length (\d+)>>\nstream\n/', $pdf, $stream, PREG_OFFSET_CAPTURE));

		$deflated = substr($pdf, $stream[0][1] + strlen($stream[0][0]), (int) $stream[1][0]);
		$this->assertStringStartsWith("1000.000 0 d0\nq ", gzuncompress($deflated));
	}

	/**
	 * Where the document may not draw colour, TestEmoji-CBDT - bitmaps and nothing else - has nothing
	 * to draw. It is still written as a Type3 font, at its widths and with its ToUnicode map, but each
	 * glyph draws nothing: the document is generated, the text keeps its place and copies out, and a
	 * warning is logged.
	 *
	 * @dataProvider restrictions
	 *
	 * @param array $config What keeps colour out
	 */
	public function testAColourFontThatMayNotBeDrawnInColourDrawsNothingAndSaysSo(array $config)
	{
		$logger = new TestLogger();
		$mpdf = $this->mpdf($config);
		$mpdf->setLogger($logger);
		$mpdf->WriteHTML('<p>' . UtfString::code2utf(0x1F600) . '</p>');

		$objects = $this->objectsOf($mpdf);

		$font = $this->objectMatching($objects, '/\/Subtype \/Type3/');
		$procedures = $this->referenced($objects, $font, 'CharProcs');
		$this->assertSame(1, preg_match('/\/g12 (\d+) 0 R/', $procedures, $match));
		$this->assertStringContainsString("stream\n1000.000 0 d0\n\nendstream", $objects[(int) $match[1]]);
		$this->assertStringContainsString('<D83DDE00>', $this->referenced($objects, $font, 'ToUnicode'));
		$this->assertEmpty(preg_grep('/\/Subtype \/Image/', $objects), 'no bitmap is written');
		$this->assertStringContainsString('/Resources <<>>', $font, 'a glyph that draws nothing names no resource');

		$this->assertSame([], $mpdf->PDFAXwarnings, 'a glyph that draws nothing is no conformance issue');
		$this->assertTrue($logger->hasWarningThatContains('Colour font "cbdt" cannot be drawn in colour'));
	}

	/**
	 * @return array[] Each setting that keeps colour out of a document
	 */
	public function restrictions()
	{
		return [
			'restrictColorSpace' => [['restrictColorSpace' => 1]],
			'PDF/A' => [['PDFA' => true]],
			'PDF/A, fixed automatically' => [['PDFA' => true, 'PDFAauto' => true]],
			'PDF/X' => [['PDFX' => true]],
			'PDF/X, fixed automatically' => [['PDFX' => true, 'PDFXauto' => true]],
		];
	}

	/**
	 * In debug mode, a colour font drawing nothing stops the document rather than being logged
	 */
	public function testDebugModeRefusesAColourFontThatDrawsNothing()
	{
		$mpdf = $this->mpdf(['restrictColorSpace' => 1, 'debug' => true]);
		$mpdf->WriteHTML('<p>' . UtfString::code2utf(0x1F600) . '</p>');

		$this->expectException(MpdfException::class);
		$this->expectExceptionMessage('Colour font "cbdt" cannot be drawn in colour');

		$mpdf->OutputBinaryData();
	}

	/**
	 * PDF/A is often switched on after the document is made, once the fonts it starts with are added,
	 * and the colour font is still kept out of colour
	 */
	public function testPdfaSetAfterTheDocumentIsMadeStillKeepsColourOut()
	{
		$mpdf = $this->mpdf();
		$mpdf->PDFA = true;
		$mpdf->WriteHTML('<p>' . UtfString::code2utf(0x1F600) . '</p>');

		$pdf = $mpdf->OutputBinaryData();

		$this->assertStringContainsString('/Subtype /Type3', $pdf);
		$this->assertStringNotContainsString('/Subtype /Image', $pdf);
	}

	/**
	 * A font whose glyph images cannot be decoded - here every PNG's header renamed - still makes a
	 * document: each glyph is left blank at its width, and a warning is logged. Under showImageErrors
	 * the document stops instead.
	 */
	public function testAFontWhoseImagesCannotBeDecodedLeavesItsGlyphsBlank()
	{
		$dir = sys_get_temp_dir() . '/mpdf-broken-cbdt-' . getmypid();
		if (!is_dir($dir)) {
			mkdir($dir);
		}
		$broken = str_replace('IHDR', 'IHDX', file_get_contents(__DIR__ . '/../data/ttf/color/TestEmoji-CBDT.ttf'));
		file_put_contents($dir . '/TestEmoji-CBDT-broken.ttf', $broken);

		$config = [
			'fontDir' => [$dir],
			'fontdata' => ['broken' => ['R' => 'TestEmoji-CBDT-broken.ttf', 'useOTL' => 0xFF]],
			'default_font' => 'broken',
		];

		try {
			$logger = new TestLogger();
			$mpdf = $this->mpdf($config);
			$mpdf->setLogger($logger);
			$mpdf->WriteHTML('<p>' . UtfString::code2utf(0x1F600) . '</p>');
			$pdf = $mpdf->OutputBinaryData();

			$this->assertStringContainsString("stream\n1000.000 0 d0\n\nendstream", $pdf);
			$this->assertStringNotContainsString('/Subtype /Image', $pdf);
			$this->assertTrue($logger->hasWarningThatContains('A glyph of colour font "broken" is left blank'));

			$mpdf = $this->mpdf($config + ['showImageErrors' => true]);
			$mpdf->WriteHTML('<p>' . UtfString::code2utf(0x1F600) . '</p>');

			$this->expectException(MpdfException::class);
			$mpdf->OutputBinaryData();
		} finally {
			unlink($dir . '/TestEmoji-CBDT-broken.ttf');
			rmdir($dir);
		}
	}

	/**
	 * A backup font that has the character draws it instead, and the colour font draws nothing and
	 * says nothing
	 */
	public function testABackupFontDrawsWhatAColourFontMayNot()
	{
		$logger = new TestLogger();
		$mpdf = $this->mpdf(['restrictColorSpace' => 1, 'useSubstitutions' => true, 'backupSubsFont' => ['notoemoji']]);
		$mpdf->setLogger($logger);
		$mpdf->WriteHTML('<p>' . UtfString::code2utf(0x1F600) . '</p>');
		$mpdf->OutputBinaryData();

		$this->assertTrue($mpdf->fonts['notoemoji']['used']);
		$this->assertFalse($logger->hasWarningRecords());
	}
}
