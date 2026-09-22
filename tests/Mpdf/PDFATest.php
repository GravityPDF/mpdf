<?php

namespace Mpdf;

class PDFATest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * @var \Mpdf\Mpdf
	 */
	private $mpdf;

	protected function set_up()
	{
		$this->mpdf = new Mpdf();
		$this->mpdf->writeHtml('<html><body>PDFA Test</body></html>');
		$this->mpdf->PDFA = true;
		$this->mpdf->PDFAauto = true;
	}

	public function testOriginalPDFA_1B()
	{
		$output = $this->mpdf->Output(null, 'S');
		$output = preg_replace('/rdf:about="uuid:[\w-]+"/', 'rdf:about="uuid:fake-uuid"', $output);

		$expected = '   <rdf:Description rdf:about="uuid:fake-uuid" xmlns:pdfaid="http://www.aiim.org/pdfa/ns/id/" >' . "\n";
		$expected .= '    <pdfaid:part>1</pdfaid:part>' . "\n";
		$expected .= '    <pdfaid:conformance>B</pdfaid:conformance>' . "\n";
		$expected .= '    <pdfaid:amd>2005</pdfaid:amd>' . "\n";
		$expected .= '   </rdf:Description>' . "\n";

		$this->assertStringContainsString($expected, $output);
	}

	public function testPDFA_1B_DoesNotSetCatalogVersion()
	{
		$output = $this->mpdf->Output(null, 'S');

		$this->assertStringNotContainsString('/Version /1.7', $output);
	}

	public function testPDFA_2B_SetsCatalogVersion17()
	{
		$this->mpdf->PDFAversion = '2-B';

		$output = $this->mpdf->Output(null, 'S');

		$this->assertStringContainsString('/Version /1.7', $output);
	}

	public function testPDFA_3B_SetsCatalogVersion17()
	{
		$this->mpdf->PDFAversion = '3-B';

		$output = $this->mpdf->Output(null, 'S');

		$this->assertStringContainsString('/Version /1.7', $output);
	}

	public function testPDFA_Version_Fail()
	{
		$this->mpdf->PDFAversion = '11';
		try {
			$this->mpdf->Output(null, 'S');
		} catch (\Exception $e) {
			$this->assertSame('PDFA version (11) is not valid. (Use: 1-B, 2-B, 2-U, 3-B or 3-U)', $e->getMessage());
		}
	}

	/**
	 * PDF/A-2 at levels B and U declares its part and level in the XMP metadata, without the PDF/A-1 amendment
	 *
	 * @dataProvider pdfa2Levels
	 */
	public function testPDFA_2_DeclaresItsPartAndLevel($version, $conformance)
	{
		$this->mpdf->PDFAversion = $version;

		$output = $this->mpdf->Output(null, 'S');

		$this->assertStringContainsString("<pdfaid:part>2</pdfaid:part>\n    <pdfaid:conformance>" . $conformance . "</pdfaid:conformance>\n   </rdf:Description>", $output);
	}

	/**
	 * The PDF/A-2 levels mPDF produces, as written in PDFAversion and as the metadata declares them
	 *
	 * @return string[][]
	 */
	public function pdfa2Levels()
	{
		return [
			['2-B', 'B'],
			['2-U', 'U'],
			['2-u', 'U'],
		];
	}

	/**
	 * A part or level mPDF does not produce is refused rather than claimed: level A needs tagged PDF, and PDF/A-1
	 * has no level U
	 *
	 * @dataProvider unsupportedVersions
	 */
	public function testPDFA_UnsupportedVersionIsRefused($version)
	{
		$this->mpdf->PDFAversion = $version;

		$this->expectException(MpdfException::class);
		$this->expectExceptionMessage(sprintf('PDFA version (%s) is not valid.', $version));

		$this->mpdf->Output(null, 'S');
	}

	/**
	 * PDFAversion values naming a part or level mPDF cannot produce
	 *
	 * @return string[][]
	 */
	public function unsupportedVersions()
	{
		return [['1-A'], ['1-U'], ['2-A'], ['3-A'], ['4'], ['2B']];
	}

	public function testOriginalPDFA_3B()
	{
		$this->mpdf->PDFAversion = '3-B';

		$output = $this->mpdf->Output(null, 'S');
		$output = preg_replace('/rdf:about="uuid:[\w-]+"/', 'rdf:about="uuid:fake-uuid"', $output);

		$expected = '   <rdf:Description rdf:about="uuid:fake-uuid" xmlns:pdfaid="http://www.aiim.org/pdfa/ns/id/" >' . "\n";
		$expected .= '    <pdfaid:part>3</pdfaid:part>' . "\n";
		$expected .= '    <pdfaid:conformance>B</pdfaid:conformance>' . "\n";
		$expected .= '   </rdf:Description>' . "\n";

		$this->assertStringContainsString($expected, $output);
	}

}
