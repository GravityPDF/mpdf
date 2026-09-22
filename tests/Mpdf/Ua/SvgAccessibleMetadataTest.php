<?php

namespace Mpdf\Ua;

/**
 * The top-level <title> and <desc> of an SVG give its Figure the /Alt an <img> alt did not
 *
 * @group pdfua
 */
class SvgAccessibleMetadataTest extends PdfUaTestCase
{

	/**
	 * An SVG with only a <title> takes it as its /Alt.
	 */
	public function testSvgWithTitleOnlyPopulatesFigureAlt()
	{
		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$svg  = $this->buildInlineSvg('Company logo', null);
		$pdf  = $this->getOutput($mpdf, '<p>' . $svg . '</p>');

		$this->assertStringContainsString('/S /Figure', $pdf);
		$this->assertStringContainsString('/Alt', $pdf);
		$this->assertContainsUtf16BeAlt($pdf, 'Company logo');
	}

	/**
	 * An SVG with only a <desc> takes it as its /Alt.
	 */
	public function testSvgWithDescOnlyPopulatesFigureAlt()
	{
		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$svg  = $this->buildInlineSvg(null, 'A blue circle.');
		$pdf  = $this->getOutput($mpdf, '<p>' . $svg . '</p>');

		$this->assertStringContainsString('/S /Figure', $pdf);
		$this->assertStringContainsString('/Alt', $pdf);
		$this->assertContainsUtf16BeAlt($pdf, 'A blue circle.');
	}

	/**
	 * An SVG with both takes its title and description, a blank line between them, as its /Alt.
	 */
	public function testSvgWithTitleAndDescConcatenatesIntoAlt()
	{
		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$svg  = $this->buildInlineSvg('Logo', 'Blue circle, company initial.');
		$pdf  = $this->getOutput($mpdf, '<p>' . $svg . '</p>');

		$this->assertStringContainsString('/S /Figure', $pdf);
		$this->assertContainsUtf16BeAlt($pdf, "Logo\n\nBlue circle, company initial.");
	}

	/**
	 * With PDFUAauto, an SVG with neither is drawn as an artifact.
	 */
	public function testSvgWithNeitherFallsBackToArtifactInAutoMode()
	{
		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$svg  = $this->buildInlineSvg(null, null);
		$pdf  = $this->getOutput($mpdf, '<p>' . $svg . '</p>');

		$this->assertStringContainsString('/Artifact BMC', $pdf);
	}

	/**
	 * Without PDFUAauto, an SVG with neither throws for its missing alt.
	 */
	public function testSvgWithNeitherThrowsInStrictMode()
	{
		$mpdf = $this->makeMpdf();
		$svg  = $this->buildInlineSvg(null, null);

		$this->expectException(\Mpdf\MpdfException::class);
		$this->expectExceptionMessageMatches('/missing the alt attribute/');
		$this->getOutput($mpdf, '<p>' . $svg . '</p>');
	}

	/**
	 * The alt of the <img> is preferred to the SVG's own title.
	 *
	 * An SVG file is used as an inline <svg> becomes an <img> without an alt.
	 */
	public function testHtmlAltOverridesSvgTitle()
	{
		$mpdf    = $this->makeMpdf(['PDFUAauto' => true]);
		$svgFile = $this->writeTempSvg('<title>SvgTitle</title><desc>SvgDesc</desc>');
		$pdf     = $this->getOutput(
			$mpdf,
			'<p><img src="' . $svgFile . '" alt="HtmlOverride" width="20" height="20"></p>'
		);
		@unlink($svgFile);

		$this->assertStringContainsString('/S /Figure', $pdf);
		$this->assertContainsUtf16BeAlt($pdf, 'HtmlOverride');
		$this->assertNotContainsUtf16BeSubstring($pdf, 'SvgTitle');
	}

	/**
	 * An empty alt on the <img> makes the SVG an artifact even when it has a title.
	 */
	public function testHtmlEmptyAltStillForcesArtifact()
	{
		$mpdf    = $this->makeMpdf(['PDFUAauto' => true]);
		$svgFile = $this->writeTempSvg('<title>Decorative-but-nonempty-title</title>');
		$pdf     = $this->getOutput(
			$mpdf,
			'<p><img src="' . $svgFile . '" alt="" width="20" height="20"></p>'
		);
		@unlink($svgFile);

		$this->assertStringContainsString('/Artifact BMC', $pdf);
		$this->assertNotContainsUtf16BeSubstring($pdf, 'Decorative-but-nonempty-title');
	}

	/**
	 * An inline <svg> takes its /Alt from its own <title>, the only name it can have.
	 */
	public function testInlineSvgUsesItsOwnTitle()
	{
		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$svg  = $this->buildInlineSvg('InlineLogo', null);
		$pdf  = $this->getOutput($mpdf, '<p>' . $svg . '</p>');

		$this->assertStringContainsString('/S /Figure', $pdf);
		$this->assertContainsUtf16BeAlt($pdf, 'InlineLogo');
	}

	/**
	 * An SVG without a top-level <title> or <desc> is drawn as an artifact.
	 *
	 * That a nested <title> is not taken is pinned in SvgTest, as a nested <title> here would be
	 * read as the document title.
	 */
	public function testNestedTitleInGroupIsIgnored()
	{
		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$svg = '<svg width="20" height="20" xmlns="http://www.w3.org/2000/svg">'
			 . '<g>'
			 . '<circle cx="10" cy="10" r="8" fill="blue"/>'
			 . '</g>'
			 . '</svg>';
		$pdf = $this->getOutput($mpdf, '<p>' . $svg . '</p>');

		$this->assertStringContainsString('/Artifact BMC', $pdf);
		$this->assertStringNotContainsString('/S /Figure', $pdf);
	}

	/**
	 * A <title> in CDATA is read as plain text.
	 */
	public function testTitleWithCdataIsExtractedCorrectly()
	{
		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$svg  = '<svg width="20" height="20" xmlns="http://www.w3.org/2000/svg">'
			  . '<title><![CDATA[A & B]]></title>'
			  . '<circle cx="10" cy="10" r="8" fill="blue"/>'
			  . '</svg>';
		$pdf = $this->getOutput($mpdf, '<p>' . $svg . '</p>');

		$this->assertStringContainsString('/S /Figure', $pdf);
		$this->assertContainsUtf16BeAlt($pdf, 'A & B');
	}

	/**
	 * A numeric character reference in a <title> is decoded.
	 *
	 * Named HTML entities are not, as XML has no DTD for them here.
	 */
	public function testTitleWithNumericEntityIsDecoded()
	{
		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$svg  = '<svg width="20" height="20" xmlns="http://www.w3.org/2000/svg">'
			  . '<title>Caf&#233;</title>'
			  . '<circle cx="10" cy="10" r="8" fill="blue"/>'
			  . '</svg>';
		$pdf = $this->getOutput($mpdf, '<p>' . $svg . '</p>');

		$this->assertStringContainsString('/S /Figure', $pdf);
		$this->assertContainsUtf16BeAlt($pdf, "Caf\xC3\xA9");
	}

	/**
	 * Without PDFUAauto, an SVG with a <title> needs no alt.
	 */
	public function testStrictModeDoesNotThrowWhenSvgHasTitle()
	{
		$mpdf = $this->makeMpdf();
		$svg  = $this->buildInlineSvg('StrictLogo', null);
		$pdf  = $this->getOutput($mpdf, '<p>' . $svg . '</p>');

		$this->assertStringContainsString('/S /Figure', $pdf);
		$this->assertContainsUtf16BeAlt($pdf, 'StrictLogo');
	}

	/**
	 * Without PDFUAauto, an SVG with no alt, title or description throws.
	 */
	public function testStrictModeStillThrowsWhenSvgHasNeitherAndNoHtmlAlt()
	{
		$mpdf = $this->makeMpdf();
		$svg  = $this->buildInlineSvg(null, null);

		$this->expectException(\Mpdf\MpdfException::class);
		$this->expectExceptionMessageMatches('/missing the alt attribute/');
		$this->getOutput($mpdf, '<p>' . $svg . '</p>');
	}

	/**
	 * An inline SVG of one circle.
	 *
	 * @param string|null $title Its <title>, if any
	 * @param string|null $desc  Its <desc>, if any
	 *
	 * @return string
	 */
	private function buildInlineSvg($title, $desc)
	{
		$inner = '';
		if ($title !== null) {
			$inner .= '<title>' . htmlspecialchars($title, ENT_QUOTES) . '</title>';
		}
		if ($desc !== null) {
			$inner .= '<desc>' . htmlspecialchars($desc, ENT_QUOTES) . '</desc>';
		}
		$inner .= '<circle cx="10" cy="10" r="8" fill="blue"/>';
		return '<svg width="20" height="20" xmlns="http://www.w3.org/2000/svg">' . $inner . '</svg>';
	}

	/**
	 * Write an SVG of one circle to a temporary file.
	 *
	 * @param string $body What goes before the circle inside <svg>
	 *
	 * @return string The file's path
	 */
	private function writeTempSvg($body)
	{
		$path = tempnam(sys_get_temp_dir(), 'mpdf-svg-meta-') . '.svg';
		file_put_contents(
			$path,
			'<?xml version="1.0" encoding="UTF-8"?>'
			. '<svg width="20" height="20" xmlns="http://www.w3.org/2000/svg">'
			. $body
			. '<circle cx="10" cy="10" r="8" fill="blue"/>'
			. '</svg>'
		);
		return $path;
	}

	/**
	 * Assert the PDF holds the text as UTF-16BE, the encoding /Alt is written in.
	 *
	 * @param string $pdf
	 * @param string $expected The /Alt text in UTF-8
	 */
	private function assertContainsUtf16BeAlt($pdf, $expected)
	{
		$utf16Body = $this->utf8ToUtf16BeBytes($expected);
		$this->assertNotSame(
			false,
			strpos($pdf, $utf16Body),
			'Expected UTF-16BE body for "' . $expected . '" not found in PDF /Alt.'
		);
	}

	/**
	 * Assert the PDF does not hold the text as UTF-16BE.
	 *
	 * @param string $pdf
	 * @param string $needle The text in UTF-8
	 */
	private function assertNotContainsUtf16BeSubstring($pdf, $needle)
	{
		$utf16Body = $this->utf8ToUtf16BeBytes($needle);
		$this->assertSame(
			false,
			strpos($pdf, $utf16Body),
			'UTF-16BE body for "' . $needle . '" must not appear in PDF.'
		);
	}

	/**
	 * The text as UTF-16BE without a BOM, which is how it appears in a PDF text string when it
	 * has nothing to escape.
	 *
	 * @param string $text
	 *
	 * @return string
	 */
	private function utf8ToUtf16BeBytes($text)
	{
		$bytes = mb_convert_encoding($text, 'UTF-16BE', 'UTF-8');
		return $bytes;
	}
}
