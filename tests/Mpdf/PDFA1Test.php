<?php

namespace Mpdf;

use Mpdf\Fonts\BlobReader;

/**
 * What PDF/A-1 asks of a document that PDF/A-2 does not: no transparency however it is reached, a CIDSet for each
 * CID font subset, one cmap subtable in a symbolic TrueType font, and no annotation colour without an RGB output
 * intent
 */
class PDFA1Test extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;

	/**
	 * An SVG's opacity, fill-opacity and stroke-opacity are painted opaque under PDF/A-1, and kept under PDF/A-2
	 *
	 * @dataProvider versions
	 */
	public function testSvgOpacity($version, $pdfa2)
	{
		$pdf = $this->renderPdfa($version, $this->translucentSvg());

		preg_match_all('/\/(ca|CA) ([\d.]+)/', $pdf, $alphas);
		$this->assertSame($pdfa2, array_unique($alphas[2]) !== ['1']);
	}

	/**
	 * A PDF/A-1 document that is not to fix itself reports the SVG opacity it could not keep
	 */
	public function testSvgOpacityIsReported()
	{
		$mpdf = $this->pdfa('1-B', ['PDFAauto' => false]);
		$mpdf->WriteHTML($this->translucentSvg());

		$this->assertContains('Image opacity must be 100% (Opacity changed to 100%)', $mpdf->PDFAXwarnings);
	}

	/**
	 * An SVG gradient stop at part opacity fades through a soft mask where transparency is allowed, and is painted
	 * opaque under PDF/A-1 and PDF/X-1a
	 *
	 * @dataProvider gradientDocuments
	 */
	public function testSvgGradientStopOpacity($config, $gradient, $translucent)
	{
		$pdf = $this->render($this->translucentGradient($gradient), $config + ['mode' => '']);

		$this->assertSame($translucent, strpos($pdf, '/SMask') !== false);
	}

	/**
	 * A PDF/A-1 document that is not to fix itself reports the gradient stop opacity it could not keep
	 *
	 * @dataProvider gradients
	 */
	public function testSvgGradientStopOpacityIsReported($gradient)
	{
		$mpdf = $this->pdfa('1-B', ['PDFAauto' => false]);
		$mpdf->WriteHTML($this->translucentGradient($gradient));

		$this->assertContains('Image opacity must be 100% (Opacity changed to 100%)', $mpdf->PDFAXwarnings);
	}

	/**
	 * Each SVG gradient under PDF/A-1b, PDF/A-2b and PDF/X-1a, with whether its stop keeps its opacity
	 *
	 * @return mixed[][]
	 */
	public function gradientDocuments()
	{
		$documents = [
			'PDF/A-1b' => [['PDFA' => true, 'PDFAauto' => true, 'PDFAversion' => '1-B'], false],
			'PDF/A-2b' => [['PDFA' => true, 'PDFAauto' => true, 'PDFAversion' => '2-B'], true],
			'PDF/X-1a' => [['PDFX' => true, 'PDFXauto' => true], false],
		];

		$cases = [];
		foreach ($documents as $document => $case) {
			list($config, $translucent) = $case;
			foreach ($this->gradients() as $gradient => $args) {
				$cases[$document . ', ' . $gradient] = [$config, $args[0], $translucent];
			}
		}

		return $cases;
	}

	/**
	 * The SVG gradient elements
	 *
	 * @return string[][]
	 */
	public function gradients()
	{
		return [
			'linear' => ['linearGradient'],
			'radial' => ['radialGradient'],
		];
	}

	/**
	 * A blurred box-shadow fades out through a soft mask under PDF/A-2, and under PDF/A-1 keeps a hard edge instead
	 *
	 * @dataProvider versions
	 */
	public function testBlurredShadow($version, $pdfa2)
	{
		$pdf = $this->renderPdfa($version, $this->blurredShadow());

		$this->assertSame($pdfa2, strpos($pdf, '/SMask') !== false);
		$this->assertSame($pdfa2, strpos($pdf, '/ShadingType 6') !== false);
	}

	/**
	 * A PDF/A-1 document that is not to fix itself reports the blur it left out
	 */
	public function testBlurredShadowIsReported()
	{
		$mpdf = $this->pdfa('1-B', ['PDFAauto' => false]);
		$mpdf->WriteHTML($this->blurredShadow());

		$this->assertContains('A box-shadow cannot be blurred without transparency (Shadow drawn without blur)', $mpdf->PDFAXwarnings);
	}

	/**
	 * PDF/A-1 lets an annotation carry a colour only with an RGB output intent; PDF/A-2 lets it with any
	 *
	 * @dataProvider annotationColours
	 */
	public function testAnnotationColour($version, $config, $coloured)
	{
		$mpdf = $this->pdfa($version, $config);
		$mpdf->WriteHTML('<p>Note <annotation content="Note" /></p>');
		$annotations = $this->annotations($this->output($mpdf));

		$this->assertStringContainsString('/AP <</N', $annotations[0]);
		$this->assertSame($coloured, strpos($annotations[0], '/C [') !== false);
	}

	/**
	 * Versions and output intents, and whether an annotation carries a colour under them
	 *
	 * @return mixed[][]
	 */
	public function annotationColours()
	{
		$cmyk = ['restrictColorSpace' => 3, 'ICCProfile' => __DIR__ . '/../../data/iccprofiles/SWOP2006_Coated3v2.icc'];

		return [
			'PDF/A-1b RGB' => ['1-B', [], true],
			'PDF/A-1b CMYK' => ['1-B', $cmyk, false],
			'PDF/A-2b CMYK' => ['2-B', $cmyk, true],
		];
	}

	/**
	 * Under PDF/A-1 a CID font subset's descriptor has a CIDSet naming exactly the CIDs its CIDToGIDMap maps to a
	 * glyph, and .notdef's; PDF/A-2 does not require one, so none is written
	 *
	 * @dataProvider versions
	 */
	public function testCidSet($version, $pdfa2)
	{
		$pdf = $this->renderPdfa($version, '<p>Text “quoted” Ελληνικά</p>');

		$this->assertSame(1, preg_match('/\/Subtype \/CIDFontType2\n(?:.*\n)*?\/CIDToGIDMap (\d+) 0 R/', $pdf, $font));
		$descriptor = $this->fontDescriptor($pdf, $font[0]);

		if ($pdfa2) {
			$this->assertStringNotContainsString('/CIDSet', $descriptor);

			return;
		}

		$this->assertSame(1, preg_match('/\/CIDSet (\d+) 0 R/', $descriptor, $cidSet));

		$map = gzuncompress($this->streamOf($pdf, $font[1]));
		$mapped = [0];
		for ($cid = 1; $cid < strlen($map) / 2; $cid++) {
			if ($map[2 * $cid] !== "\x00" || $map[2 * $cid + 1] !== "\x00") {
				$mapped[] = $cid;
			}
		}

		$this->assertSame($mapped, $this->bitsSet($this->streamOf($pdf, $cidSet[1])));
	}

	/**
	 * A font subset written as a symbolic TrueType font, for characters past the Basic Multilingual Plane, carries
	 * one cmap subtable under PDF/A-1 and a second one, for older readers, under PDF/A-2
	 *
	 * @dataProvider versions
	 */
	public function testSymbolicTrueTypeCmap($version, $pdfa2)
	{
		$pdf = $this->renderPdfa($version, '<p style="font-family: freeserif">नमस्ते</p>');

		$this->assertSame(1, preg_match('/\/Subtype \/TrueType\n(?:.*\n)*?\/FontDescriptor (\d+) 0 R/', $pdf, $font));
		$this->assertSame(1, preg_match('/\/FontFile2 (\d+) 0 R/', $this->object($pdf, $font[1]), $file));

		$this->assertSame($pdfa2 ? ['1,0', '3,0'] : ['3,0'], $this->cmapEncodings(gzuncompress($this->streamOf($pdf, $file[1]))));
	}

	/**
	 * PDF/A-1, which forbids transparency, and PDF/A-2, which does not, each with whether it is PDF/A-2
	 *
	 * @return mixed[][]
	 */
	public function versions()
	{
		return [
			'PDF/A-1b' => ['1-B', false],
			'PDF/A-2b' => ['2-B', true],
		];
	}

	/**
	 * An SVG shape at part opacity through each of the attributes that set one
	 *
	 * @return string
	 */
	private function translucentSvg()
	{
		return '<svg width="100" height="50"><rect width="80" height="40" fill="red" fill-opacity="0.3" stroke="blue"'
			. ' stroke-opacity="0.4" opacity="0.5" /></svg>';
	}

	/**
	 * An SVG shape filled with a gradient whose first stop is at part opacity
	 *
	 * @param string $gradient linearGradient or radialGradient
	 *
	 * @return string
	 */
	private function translucentGradient($gradient)
	{
		return '<svg width="100" height="50"><' . $gradient . ' id="g"><stop offset="0" stop-color="red" stop-opacity="0.2" />'
			. '<stop offset="1" stop-color="blue" /></' . $gradient . '><rect width="80" height="40" fill="url(#g)" /></svg>';
	}

	/**
	 * A block whose shadow is blurred
	 *
	 * @return string
	 */
	private function blurredShadow()
	{
		return '<div style="box-shadow: 2mm 2mm 2mm rgba(0, 0, 0, 0.5)">Shadow</div>';
	}

	/**
	 * The font descriptor a font dictionary names
	 *
	 * @param string $pdf
	 * @param string $font
	 *
	 * @return string
	 */
	private function fontDescriptor($pdf, $font)
	{
		preg_match('/\/FontDescriptor (\d+) 0 R/', $font, $descriptor);

		return $this->object($pdf, $descriptor[1]);
	}

	/**
	 * The bytes of a stream object, as written
	 *
	 * @param string $pdf
	 * @param string $number
	 *
	 * @return string
	 */
	private function streamOf($pdf, $number)
	{
		preg_match('/\n' . $number . ' 0 obj\n<<\/Length (\d+).*?>>\nstream\n/s', $pdf, $match, PREG_OFFSET_CAPTURE);

		return substr($pdf, $match[0][1] + strlen($match[0][0]), (int) $match[1][0]);
	}

	/**
	 * The numbers of the bits a bit string sets, the high bit of its first byte being 0
	 *
	 * @param string $bits
	 *
	 * @return int[]
	 */
	private function bitsSet($bits)
	{
		$set = [];
		for ($bit = 0; $bit < strlen($bits) * 8; $bit++) {
			if (ord($bits[$bit >> 3]) & (0x80 >> ($bit & 7))) {
				$set[] = $bit;
			}
		}

		return $set;
	}

	/**
	 * The platform and encoding of each subtable a font program's cmap lists, as "platform,encoding"
	 *
	 * @param string $program
	 *
	 * @return string[]
	 */
	private function cmapEncodings($program)
	{
		$reader = new BlobReader($program);
		$reader->skip(4); // sfntVersion
		$tableCount = $reader->readUInt16();
		$reader->skip(6); // searchRange, entrySelector, rangeShift

		for ($i = 0; $i < $tableCount; $i++) {
			$tag = $reader->read(4);
			$reader->skip(4); // checksum
			$offset = $reader->readUInt32();
			$reader->skip(4); // length
			if ($tag === 'cmap') {
				break;
			}
		}

		$reader->seek($offset + 2); // past the version
		$encodings = [];
		for ($i = $reader->readUInt16(); $i > 0; $i--) {
			$encodings[] = $reader->readUInt16() . ',' . $reader->readUInt16();
			$reader->skip(4); // offset
		}

		return $encodings;
	}

	/**
	 * A PDF/A document of the given version that writes uncompressed and fixes what it can
	 *
	 * @param string $version
	 * @param mixed[] $config
	 *
	 * @return \Mpdf\Mpdf
	 */
	private function pdfa($version, $config = [])
	{
		return $this->mpdf($config + ['mode' => '', 'PDFA' => true, 'PDFAauto' => true, 'PDFAversion' => $version]);
	}

	/**
	 * The PDF of a document of the given version holding the given HTML
	 *
	 * @param string $version
	 * @param string $html
	 *
	 * @return string
	 */
	private function renderPdfa($version, $html)
	{
		$mpdf = $this->pdfa($version);
		$mpdf->WriteHTML($html);

		return $this->output($mpdf);
	}

}
