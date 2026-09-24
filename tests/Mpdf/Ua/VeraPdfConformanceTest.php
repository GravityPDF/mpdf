<?php

namespace Mpdf\Ua;

/**
 * Documents that veraPDF must find conformant to PDF/UA-1.
 *
 * Skipped unless VERAPDF_BIN names the veraPDF binary:
 * VERAPDF_BIN=/path/to/verapdf vendor/bin/phpunit --group=verapdf
 *
 * @group verapdf
 */
class VeraPdfConformanceTest extends PdfUaTestCase
{
	/**
	 * The veraPDF binary
	 *
	 * @var string
	 */
	private $veraPdfBin = '';

	/**
	 * Picks up the veraPDF binary from VERAPDF_BIN, skipping the test when there is none.
	 *
	 * @return void
	 */
	protected function set_up()
	{
		parent::set_up();

		$bin = (string) getenv('VERAPDF_BIN');

		if ($bin === '') {
			$this->markTestSkipped(
				'veraPDF conformance tests require the VERAPDF_BIN environment variable. '
				. 'Set it to the path of the veraPDF CLI binary and re-run: '
				. 'VERAPDF_BIN=/path/to/verapdf vendor/bin/phpunit --group=verapdf'
			);
		}

		if (!is_file($bin) || !is_executable($bin)) {
			$this->markTestSkipped(
				'VERAPDF_BIN is set but "' . $bin . '" is not an executable file. '
				. 'See https://docs.verapdf.org/install/ for installation instructions.'
			);
		}

		$this->veraPdfBin = $bin;
	}

	/**
	 * A heading and a paragraph pass.
	 *
	 * @return void
	 */
	public function testMinimalDocumentPassesUa1()
	{
		$mpdf = $this->makeMpdf();
		$pdf  = $this->getOutput($mpdf, '<h1>Title</h1><p>Body paragraph text.</p>');
		$this->assertVeraPdfCompliant($pdf, 'minimal document');
	}

	/**
	 * Unordered, ordered and definition lists pass.
	 *
	 * @return void
	 */
	public function testDocumentWithListsPassesUa1()
	{
		$html = '<h1>Lists</h1>'
			. '<ul><li>Item one</li><li>Item two</li></ul>'
			. '<ol><li>First</li><li>Second</li><li>Third</li></ol>'
			. '<dl><dt>Term</dt><dd>Definition text</dd><dt>Term 2</dt><dd>Definition 2</dd></dl>';

		$mpdf = $this->makeMpdf();
		$pdf  = $this->getOutput($mpdf, $html);
		$this->assertVeraPdfCompliant($pdf, 'document with lists');
	}

	/**
	 * Ordered lists of every numbering style, and a disc list, pass with the /ListNumbering each is given.
	 *
	 * @return void
	 */
	public function testOrderedListNumberingPassesUa1()
	{
		$html = '<h1>Numbered lists</h1>'
			. '<ol type="1"><li>Decimal one</li><li>Decimal two</li></ol>'
			. '<ol type="I"><li>Upper roman one</li><li>Upper roman two</li></ol>'
			. '<ol type="i"><li>Lower roman one</li><li>Lower roman two</li></ol>'
			. '<ol type="A"><li>Upper alpha one</li><li>Upper alpha two</li></ol>'
			. '<ol type="a"><li>Lower alpha one</li><li>Lower alpha two</li></ol>'
			. '<ul><li>Disc one</li><li>Disc two</li></ul>';

		$mpdf = $this->makeMpdf();
		$pdf  = $this->getOutput($mpdf, $html);
		$this->assertVeraPdfCompliant($pdf, 'ordered list numbering styles');
	}

	/**
	 * A dl inside a dd passes, its term and definition in an LI of its own.
	 *
	 * @return void
	 */
	public function testNestedDefinitionListPassesUa1()
	{
		$html = '<h1>Glossary</h1>'
			. '<dl><dt>Outer term</dt><dd>Outer definition'
			. '<dl><dt>Inner term</dt><dd>Inner definition</dd></dl>'
			. '</dd></dl>';

		$mpdf = $this->makeMpdf();
		$pdf  = $this->getOutput($mpdf, $html);
		$this->assertVeraPdfCompliant($pdf, 'nested definition list');
	}

	/**
	 * A table with header cells, /Scope and headers= associations passes (Matterhorn 09-004/09-005).
	 *
	 * @return void
	 */
	public function testDocumentWithTablesPassesUa1()
	{
		$html = '<h1>Table test</h1>'
			. '<table>'
			. '<thead><tr><th id="col1">Column A</th><th id="col2">Column B</th></tr></thead>'
			. '<tbody>'
			. '<tr><td headers="col1">A1</td><td headers="col2">B1</td></tr>'
			. '<tr><td headers="col1">A2</td><td headers="col2">B2</td></tr>'
			. '</tbody>'
			. '</table>';

		$mpdf = $this->makeMpdf();
		$pdf  = $this->getOutput($mpdf, $html);
		$this->assertVeraPdfCompliant($pdf, 'document with tables');
	}

	/**
	 * A table with thead, tbody and tfoot passes, as does one with rows straight under table, which is
	 * given a TBody so that no TR is a child of Table.
	 *
	 * @return void
	 */
	public function testTableRowGroupStructurePassesUa1()
	{
		$html = '<h1>Row groups</h1>'
			. '<table border="1">'
			. '<thead><tr><th scope="col">Head A</th><th scope="col">Head B</th></tr></thead>'
			. '<tbody>'
			. '<tr><td>A1</td><td>B1</td></tr>'
			. '<tr><td>A2</td><td>B2</td></tr>'
			. '</tbody>'
			. '<tfoot><tr><td>Foot A</td><td>Foot B</td></tr></tfoot>'
			. '</table>'
			. '<table border="1">'
			. '<tr><td>Implicit 1</td><td>Implicit 2</td></tr>'
			. '<tr><td>Implicit 3</td><td>Implicit 4</td></tr>'
			. '</table>';

		$mpdf = $this->makeMpdf();
		$pdf  = $this->getOutput($mpdf, $html);
		$this->assertVeraPdfCompliant($pdf, 'table row-group structure');
	}

	/**
	 * Header cells named by id from body cells pass, alongside a th in the running header, which is an
	 * artifact and so has no id to register.
	 *
	 * @return void
	 */
	public function testHeaderCellAssociationsPassUa1()
	{
		$mpdf = $this->makeMpdf();
		$mpdf->SetHTMLHeader(
			'<table><tr><th scope="col" style="font-size: 9pt;">Running header cell</th></tr></table>'
		);
		$html = '<h1>Header cell associations</h1>'
			. '<table border="1">'
			. '<thead><tr><th id="q1" scope="col">Q1</th><th id="q2" scope="col">Q2</th></tr></thead>'
			. '<tbody>'
			. '<tr><td headers="q1">10</td><td headers="q2">20</td></tr>'
			. '<tr><th id="tot" scope="row">Total</th><td headers="tot q1">30</td></tr>'
			. '</tbody>'
			. '</table>';
		$pdf = $this->getOutput($mpdf, $html);
		$this->assertVeraPdfCompliant($pdf, 'header cell associations');
	}

	/**
	 * A heading, list and link inside table cells pass, each tagged under its TD with content of its own.
	 *
	 * @return void
	 */
	public function testBlockContentInsideTableCellPassesUa1()
	{
		$html = '<h1>Cell block content</h1>'
			. '<table border="1">'
			. '<tr><td><h2>Cell heading</h2>'
			. '<ul><li>Item one</li><li>Item two</li></ul></td>'
			. '<td><p>Plain <a href="https://example.com">link</a> text</p></td></tr>'
			. '</table>';

		$mpdf = $this->makeMpdf();
		$pdf  = $this->getOutput($mpdf, $html);
		$this->assertVeraPdfCompliant($pdf, 'block content inside a table cell');
	}

	/**
	 * A described image, tagged Figure with /Alt, and a decorative one, drawn as an artifact, pass.
	 *
	 * @return void
	 */
	public function testDocumentWithImagesPassesUa1()
	{
		$png = 'data:image/png;base64,'
			. 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8'
			. 'z8BQDwADhQGAWjR9awAAAABJRU5ErkJggg==';

		$html = '<h1>Image test</h1>'
			. '<img src="' . $png . '" alt="A descriptive red pixel" width="20" height="20">'
			. '<p>A paragraph after the image.</p>'
			. '<img src="' . $png . '" alt="" width="20" height="20">';

		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$pdf  = $this->getOutput($mpdf, $html);
		$this->assertVeraPdfCompliant($pdf, 'document with images');
	}

	/**
	 * Links pass, each a Link struct element joined to its annotation by an OBJR.
	 *
	 * @return void
	 */
	public function testDocumentWithLinksPassesUa1()
	{
		$html = '<h1>Link test</h1>'
			. '<p>Visit <a href="https://example.com">Example Domain</a> for more.</p>'
			. '<p>Another link: <a href="https://www.w3.org/TR/WCAG21/">WCAG 2.1</a></p>';

		$mpdf = $this->makeMpdf();
		$pdf  = $this->getOutput($mpdf, $html);
		$this->assertVeraPdfCompliant($pdf, 'document with links');
	}

	/**
	 * A link in a running header, whose annotation is dropped, passes beside a tagged link in the body.
	 *
	 * @return void
	 */
	public function testDocumentWithHeaderLinkPassesUa1()
	{
		$mpdf = $this->makeMpdf();
		$mpdf->SetHTMLHeader('<div style="font-size: 9pt;">Header with <a href="https://example.com">a header link</a></div>');
		$html = '<h1>Header link test</h1>'
			. '<p>Visit <a href="https://www.w3.org/">the W3C</a> in the body.</p>';
		$pdf = $this->getOutput($mpdf, $html);
		$this->assertVeraPdfCompliant($pdf, 'document with header link');
	}

	/**
	 * A link made with Mpdf::Link() passes with the Link struct element made for it.
	 *
	 * @return void
	 */
	public function testDirectPhpLinkApiPassesUa1()
	{
		$mpdf = $this->makeMpdf();
		$mpdf->WriteHTML('<h1>Direct link</h1><p>Paragraph body text for the page.</p>');
		$mpdf->Link(20, 40, 60, 8, 'https://example.com/direct-api');
		$pdf = $mpdf->Output(null, 'S');
		$this->assertVeraPdfCompliant($pdf, 'direct PHP Link() API');
	}

	/**
	 * Text and graphics drawn with the drawing methods after WriteHTML() pass, in strict and in
	 * auto mode.
	 *
	 * @dataProvider directDrawingProvider
	 *
	 * @param callable $draw
	 * @param bool     $auto Whether PDFUAauto is on
	 *
	 * @return void
	 */
	public function testDirectDrawingApiPassesUa1($draw, $auto)
	{
		$mpdf = $this->makeMpdf(['PDFUAauto' => $auto]);
		$mpdf->WriteHTML('<h1>Direct drawing</h1>');
		call_user_func($draw, $mpdf);
		$pdf = $mpdf->Output(null, 'S');
		$this->assertVeraPdfCompliant($pdf, 'direct drawing API');
	}

	/**
	 * Each drawing method, in strict and in auto mode
	 *
	 * @return array<string, array{0: callable, 1: bool}>
	 */
	public function directDrawingProvider()
	{
		$calls = array_merge(
			DirectDrawingTest::textCallProvider(),
			DirectDrawingTest::graphicCallProvider(),
			[
				'Cell with a link' => DirectDrawingTest::linkedTextCallProvider()['Cell'],
				'Write with a link' => DirectDrawingTest::linkedTextCallProvider()['Write'],
				'MultiCell across pages' => [function (\Mpdf\Mpdf $mpdf) {
					$mpdf->MultiCell(60, 5, str_repeat("A line of text\n", 80));
				}],
				'AutosizeText' => [function (\Mpdf\Mpdf $mpdf) {
					$mpdf->AutosizeText('Autosized', 50, 'dejavusans', '', 20);
				}],
			]
		);

		$cases = [];
		foreach ($calls as $name => $args) {
			$cases[$name] = [$args[0], false];
			$cases[$name . ' (auto)'] = [$args[0], true];
		}

		return $cases;
	}

	/**
	 * An image map of rect, circle and poly areas, declared after its image, passes.
	 *
	 * @return void
	 */
	public function testDocumentWithImageMapPassesUa1()
	{
		$html = $this->loadExampleFixture('imagemap');
		$mpdf = $this->makeMpdf();
		$pdf  = $this->getOutput($mpdf, $html);
		$this->assertVeraPdfCompliant($pdf, 'document with image map');
	}

	/**
	 * An image map on a rotated image, its hotspots given as /QuadPoints, passes.
	 *
	 * @return void
	 */
	public function testRotatedImageMapPassesUa1()
	{
		$png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwADhQGAWjR9awAAAABJRU5ErkJggg==';
		$html = '<h1>Rotated image map</h1>'
			. '<p><img src="' . $png . '" alt="Floor plan" usemap="#rooms" '
			. 'width="200" height="200" rotate="90"></p>'
			. '<map name="rooms">'
			. '<area shape="rect"   coords="10,10,100,100"        href="https://example.com/lobby"  alt="Lobby">'
			. '<area shape="circle" coords="150,150,30"           href="https://example.com/atrium" alt="Atrium">'
			. '<area shape="poly"   coords="50,50,150,50,100,150" href="https://example.com/garden" alt="Garden">'
			. '</map>';
		$mpdf = $this->makeMpdf();
		$pdf  = $this->getOutput($mpdf, $html);
		$this->assertVeraPdfCompliant($pdf, 'rotated image map');
	}

	/**
	 * Poly areas on an upright image, covered by several /QuadPoints each, pass.
	 *
	 * @return void
	 */
	public function testPolygonImageMapPassesUa1()
	{
		$png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwADhQGAWjR9awAAAABJRU5ErkJggg==';
		$html = '<h1>Polygon image map</h1>'
			. '<p><img src="' . $png . '" alt="Floor plan" usemap="#rooms" width="200" height="200"></p>'
			. '<map name="rooms">'
			. '<area shape="poly" coords="100,20,180,150,20,150"        href="https://example.com/atrium" alt="Atrium">'
			. '<area shape="poly" coords="10,10,60,10,60,60,35,90,10,60" href="https://example.com/wing"   alt="West wing">'
			. '</map>';
		$mpdf = $this->makeMpdf();
		$pdf  = $this->getOutput($mpdf, $html);
		$this->assertVeraPdfCompliant($pdf, 'polygon image map');
	}

	/**
	 * Text with fi, ffi and ffl ligatures passes, each ligature carrying the /ActualText it stands for.
	 *
	 * @return void
	 */
	public function testDocumentWithLigaturesPassesUa1()
	{
		$html = '<h1>Ligature test</h1>'
			. '<p style="font-family: DejaVuSerif;">fine office difficulty</p>';

		$mpdf = $this->makeMpdf();
		$pdf  = $this->getOutput($mpdf, $html);
		$this->assertVeraPdfCompliant($pdf, 'document with ligatures');
	}

	/**
	 * Characters beyond U+FFFF pass, with a well-formed ToUnicode CMap for their font and for the
	 * default font beside it.
	 *
	 * @return void
	 */
	public function testDocumentWithAstralCodepointsPassesUa1()
	{
		$html = '<h1>Astral codepoints</h1>'
			. '<p style="font-family: aegean">Linear B &#65536; and Old Italic &#66304; glyphs.</p>';

		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$pdf  = $this->getOutput($mpdf, $html);
		$this->assertVeraPdfCompliant($pdf, 'document with astral codepoints');
	}

	/**
	 * An encrypted document passes, 'extract' being kept among its permissions (Matterhorn 07-001).
	 *
	 * @return void
	 */
	public function testEncryptedDocumentPassesUa1()
	{
		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$mpdf->SetProtection(['copy', 'print']);
		$pdf = $this->getOutput($mpdf, '<h1>Protected</h1><p>Encrypted PDF/UA-1 document.</p>');
		$this->assertVeraPdfCompliant($pdf, 'encrypted document');
	}

	/**
	 * An encrypted document's XMP stays readable through the Identity crypt filter, and passes.
	 *
	 * Compressed, as uncompressed RC4 content streams trip a veraPDF parsing fault; see
	 * testExample64ProtectedDocumentPassesUa1().
	 *
	 * @return void
	 */
	public function testEncryptedUaMetadataPassesUa1()
	{
		$mpdf = $this->makeMpdf(['PDFUAauto' => true, 'title' => 'Encrypted Metadata Document']);
		$mpdf->compress = true;
		$mpdf->SetProtection(['print']);
		$pdf = $this->getOutput($mpdf, '<h1>Protected</h1><p>Encrypted PDF/UA-1 metadata test.</p>');
		$this->assertVeraPdfCompliant($pdf, 'encrypted UA metadata');
	}

	/**
	 * A page imported from an untagged PDF as a page template passes, drawn as an artifact.
	 *
	 * @return void
	 */
	public function testFpdiTier1ImportPassesUa1()
	{
		$sourceFixture = $this->makeUntaggedSourceFixture();

		$mpdf = $this->makeMpdf(['enableImports' => true, 'PDFUAauto' => true]);
		$mpdf->setSourceFile($sourceFixture);
		$pageId = $mpdf->importPage(1);
		$mpdf->SetPageTemplate($pageId);
		$pdf = $this->getOutput($mpdf, '<h1>Imported Page</h1><p>Content over imported background.</p>');
		@unlink($sourceFixture);
		$this->assertVeraPdfCompliant($pdf, 'untagged FPDI import');
	}

	/**
	 * Writes an untagged PDF whose font is embedded, since an imported page keeps its source's font
	 * references and PDF/UA requires embedded fonts.
	 *
	 * @return string The file's path
	 */
	private function makeUntaggedSourceFixture()
	{
		$source = new \Mpdf\Mpdf(['mode' => 'utf-8', 'default_font' => 'DejaVuSansCondensed']);
		$source->WriteHTML('<p>Untagged source page generated for an untagged FPDI import.</p>');
		$path = tempnam(sys_get_temp_dir(), 'mpdf_ua_fpdi_source_') . '.pdf';
		file_put_contents($path, $source->Output('', 'S'));
		return $path;
	}

	/**
	 * A page imported from an untagged PDF and given an alt passes, tagged as a Figure with that /Alt.
	 *
	 * @return void
	 */
	public function testFpdiTier1ImportWithAuthorAltPassesUa1()
	{
		$sourceFixture = $this->makeUntaggedSourceFixture();

		$mpdf = $this->makeMpdf(['enableImports' => true]);
		$mpdf->setSourceFile($sourceFixture);
		$pageId = $mpdf->importPage(1);
		$mpdf->WriteHTML('<h1>Imported figure</h1>');
		$mpdf->useImportedPage($pageId, [
			'x'     => 15,
			'y'     => 40,
			'width' => 150,
			'alt'   => 'Untagged source page rendered as an accessible figure.',
		]);
		$pdf = $mpdf->Output(null, 'S');
		@unlink($sourceFixture);
		$this->assertVeraPdfCompliant($pdf, 'untagged FPDI import with an /Alt');
	}

	/**
	 * A page imported from a tagged PDF passes with its structure merged, bare MCIDs and content in a
	 * Form XObject included.
	 *
	 * @return void
	 */
	public function testFpdiTier2TaggedImportPassesUa1()
	{
		$sourceFixture = $this->makeTaggedSourceFixture();

		$mpdf = $this->makeMpdf(['enableImports' => true, 'PDFUAauto' => true]);
		$mpdf->setSourceFile($sourceFixture);
		$pageId = $mpdf->importPage(1);
		$mpdf->AddPage();
		$mpdf->useImportedPage($pageId);
		$pdf = $mpdf->Output(null, 'S');
		@unlink($sourceFixture);
		$this->assertVeraPdfCompliant($pdf, 'tagged FPDI import');
	}

	/**
	 * A tagged page holding a table with header cells and spans, a form field and a linked image
	 * passes once imported into a document that is not PDFUAauto.
	 *
	 * @return void
	 */
	public function testFpdiTaggedImportOfTableFormAndLinkPassesUa1()
	{
		$png = 'data:image/png;base64,'
			. 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8'
			. 'z8BQDwADhQGAWjR9awAAAABJRU5ErkJggg==';
		$sourceFixture = $this->makeTaggedSourceFixture(
			'<h1>Tagged source</h1>'
			. '<table border="1"><tr><th scope="col" colspan="2">Totals</th></tr>'
			. '<tr><th scope="row">Q1</th><td>10</td></tr></table>'
			. '<p>Name <input type="text" name="fname" title="Name"></p>'
			. '<p><a href="https://example.com"><img src="' . $png . '" alt="Example home" width="20" height="20"></a></p>',
			['useActiveForms' => true]
		);

		$mpdf = $this->makeMpdf(['enableImports' => true]);
		$mpdf->setSourceFile($sourceFixture);
		$pageId = $mpdf->importPage(1);
		$mpdf->AddPage();
		$mpdf->useImportedPage($pageId);
		$pdf = $mpdf->Output(null, 'S');
		@unlink($sourceFixture);
		$this->assertVeraPdfCompliant($pdf, 'tagged FPDI import of a table, a form field and a link');
	}

	/**
	 * Writes a tagged PDF/UA document to import.
	 *
	 * @param string $html
	 * @param array  $config
	 *
	 * @return string The file's path
	 */
	private function makeTaggedSourceFixture($html = '', $config = [])
	{
		if ($html === '') {
			$html = '<h1>Tagged source heading</h1>'
				. '<p>Tagged source body paragraph generated for a tagged FPDI import.</p>';
		}
		$source = $this->makeMpdf($config);
		$source->WriteHTML($html);
		$path = tempnam(sys_get_temp_dir(), 'mpdf_ua_fpdi_tagged_') . '.pdf';
		$source->Output($path, 'F');
		return $path;
	}

	/**
	 * Active form fields pass, each widget in a Form struct element.
	 *
	 * @return void
	 */
	public function testDocumentWithFormWidgetsPassesUa1()
	{
		$html = '<h1>Form test</h1>'
			. '<p>Please fill in the form below.</p>'
			. '<form>'
			. '<input type="text" name="fname" value="">'
			. '<input type="checkbox" name="agree" value="1">'
			. '<select name="choice">'
			. '<option value="a">Option A</option>'
			. '<option value="b">Option B</option>'
			. '</select>'
			. '</form>';

		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$mpdf->useActiveForms = true;
		$pdf  = $this->getOutput($mpdf, $html);
		$this->assertVeraPdfCompliant($pdf, 'document with form widgets');
	}

	/**
	 * A document of nested headings over several pages passes.
	 *
	 * @return void
	 */
	public function testMultiPageDocumentPassesUa1()
	{
		$html = '<h1>Chapter One</h1>'
			. '<p>First paragraph of chapter one with sufficient text to fill a section.</p>'
			. '<h2>Section 1.1</h2>'
			. '<p>Content of section 1.1.</p>'
			. '<h2>Section 1.2</h2>'
			. '<p>Content of section 1.2.</p>'
			. '<h1>Chapter Two</h1>'
			. '<p>First paragraph of chapter two.</p>'
			. '<h2>Section 2.1</h2>'
			. '<p>Content of section 2.1.</p>';

		$mpdf = $this->makeMpdf();
		$pdf  = $this->getOutput($mpdf, $html);
		$this->assertVeraPdfCompliant($pdf, 'multi-page document');
	}

	/**
	 * Pages of several pieces of marked content each pass, each page with its own ParentTree entry.
	 *
	 * @return void
	 */
	public function testMultiKeyParentTreePassesUa1()
	{
		$png = base64_encode(
			base64_decode(
				'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
			)
		);

		$html = '<h1>Report</h1>'
			. '<p>First paragraph on the first page with enough words to be a real block.</p>'
			. '<p>Second paragraph, also on the first page, contributing another MCID.</p>'
			. '<img src="data:image/png;base64,' . $png . '" alt="A decorative square swatch." style="width:20px;height:20px" />'
			. '<table border="1"><thead><tr><th scope="col">Name</th><th scope="col">Value</th></tr></thead>'
			. '<tbody><tr><td>Alpha</td><td>1</td></tr><tr><td>Beta</td><td>2</td></tr></tbody></table>'
			. '<pagebreak />'
			. '<h2>Appendix</h2>'
			. '<p>A paragraph on the second page, giving that page its own StructParents key.</p>'
			. '<p>Another second-page paragraph so the second key also holds several MCIDs.</p>';

		$mpdf = $this->makeMpdf();
		$pdf  = $this->getOutput($mpdf, $html);
		$this->assertVeraPdfCompliant($pdf, 'multi-key ParentTree document');
	}

	/**
	 * abbr passes, tagged Span with its title as /E.
	 *
	 * @return void
	 */
	public function testDocumentWithAbbreviationsPassesUa1()
	{
		$html = '<h1>Abbreviations</h1>'
			. '<p>The <abbr title="World Wide Web Consortium">W3C</abbr> develops web standards.</p>'
			. '<p>PDF stands for <abbr title="Portable Document Format">PDF</abbr>.</p>';

		$mpdf = $this->makeMpdf();
		$pdf  = $this->getOutput($mpdf, $html);
		$this->assertVeraPdfCompliant($pdf, 'document with abbreviations');
	}

	/**
	 * aria-labelledby and aria-describedby pass with the /Alt and /E they give.
	 *
	 * @return void
	 */
	public function testAriaNameResolutionPassesUa1()
	{
		$html = '<h1>ARIA naming</h1>'
			. '<p id="cap">The quarterly revenue caption</p>'
			. '<div aria-labelledby="cap">Region referenced by the caption above.</div>'
			. '<p id="desc">A longer description of the following section.</p>'
			. '<p aria-describedby="desc">Body paragraph with an associated description.</p>';

		$mpdf = $this->makeMpdf();
		$pdf  = $this->getOutput($mpdf, $html);
		$this->assertVeraPdfCompliant($pdf, 'ARIA accessible-name resolution');
	}

	/**
	 * An image with no alt passes when aria-labelledby names it.
	 *
	 * @return void
	 */
	public function testImageNoAltAriaLabelledbyPassesUa1()
	{
		$png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwADhQGAWjR9awAAAABJRU5ErkJggg==';
		$html = '<h1>ARIA-named image</h1>'
			. '<p id="figcap">Q3 revenue chart</p>'
			. '<img src="' . $png . '" aria-labelledby="figcap" width="20" height="20">';

		$mpdf = $this->makeMpdf();
		$pdf  = $this->getOutput($mpdf, $html);
		$this->assertVeraPdfCompliant($pdf, 'image with no alt named by aria-labelledby');
	}

	/**
	 * Text in a link, a span with a lang, an abbr and ruby passes, marked as that element's own content.
	 *
	 * @return void
	 */
	public function testInlineStructContentPassesUa1()
	{
		$html = '<h1>Inline structure</h1>'
			. '<p>see <a href="https://example.com">the report '
			. '<span lang="fr">rapport</span></a> today</p>'
			. '<p>The <abbr title="World Health Organization">WHO</abbr> reported it.</p>'
			. '<p>Read <ruby><rb>KANJI</rb><rt>kan</rt></ruby> and '
			. '<ruby>BASE<rt>note</rt></ruby> now.</p>';

		$mpdf = $this->makeMpdf();
		$pdf  = $this->getOutput($mpdf, $html);
		$this->assertVeraPdfCompliant($pdf, 'inline struct content (link/span/abbr/ruby)');
	}

	/**
	 * mpdf-examples example01_basic.php: headings, paragraphs, links, blocks, lists and a table.
	 *
	 * @return void
	 */
	public function testExample01BasicPassesUa1()
	{
		$html = $this->loadExampleFixture('example01_basic');
		$mpdf = $this->makeMpdf();
		$pdf  = $this->getOutput($mpdf, $html);
		$this->assertVeraPdfCompliant($pdf, 'example01_basic.php');
	}

	/**
	 * mpdf-examples example04_images.php, its images replaced by a PNG data: URI.
	 *
	 * @return void
	 */
	public function testExample04ImagesPassesUa1()
	{
		$html = $this->loadExampleFixture('example04_images');
		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$pdf  = $this->getOutput($mpdf, $html);
		$this->assertVeraPdfCompliant($pdf, 'example04_images.php');
	}

	/**
	 * mpdf-examples example05_tables.php: tables with header and footer rows, and a heading in a cell.
	 *
	 * @return void
	 */
	public function testExample05TablesPassesUa1()
	{
		$html = $this->loadExampleFixture('example05_tables');
		$mpdf = $this->makeMpdf();
		$pdf  = $this->getOutput($mpdf, $html);
		$this->assertVeraPdfCompliant($pdf, 'example05_tables.php');
	}

	/**
	 * mpdf-examples example06_tables_nested.php, without its background image.
	 *
	 * @return void
	 */
	public function testExample06TablesNestedPassesUa1()
	{
		$html = $this->loadExampleFixture('example06_tables_nested');
		$mpdf = $this->makeMpdf();
		$pdf  = $this->getOutput($mpdf, $html);
		$this->assertVeraPdfCompliant($pdf, 'example06_tables_nested.php');
	}

	/**
	 * mpdf-examples example07_tables_borders.php: collapsed and separate borders.
	 *
	 * @return void
	 */
	public function testExample07TablesBordersPassesUa1()
	{
		$html = $this->loadExampleFixture('example07_tables_borders');
		$mpdf = $this->makeMpdf();
		$pdf  = $this->getOutput($mpdf, $html);
		$this->assertVeraPdfCompliant($pdf, 'example07_tables_borders.php');
	}

	/**
	 * mpdf-examples example08_lists.php, without the Arabic-Indic list, whose xbriyaz font the tests
	 * do not have.
	 *
	 * @return void
	 */
	public function testExample08ListsPassesUa1()
	{
		$html = $this->loadExampleFixture('example08_lists');
		$mpdf = $this->makeMpdf();
		$pdf  = $this->getOutput($mpdf, $html);
		$this->assertVeraPdfCompliant($pdf, 'example08_lists.php');
	}

	/**
	 * mpdf-examples example10_floating_and_fixed_position_elements.php, with text floats in place of
	 * its WMF image; floated content is tagged, not an artifact.
	 *
	 * @return void
	 */
	public function testExample10FloatingAndFixedPassesUa1()
	{
		$html = $this->loadExampleFixture('example10_floating_and_fixed_position_elements');
		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$pdf  = $this->getOutput($mpdf, $html);
		$this->assertVeraPdfCompliant($pdf, 'example10_floating_and_fixed_position_elements.php');
	}

	/**
	 * mpdf-examples example12_paging_html.php: headers and footers set in the HTML, without the image
	 * in the header.
	 *
	 * @return void
	 */
	public function testExample12PagingHtmlPassesUa1()
	{
		$html = $this->loadExampleFixture('example12_paging_html');
		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$mpdf->mirrorMargins = true;
		$pdf  = $this->getOutput($mpdf, $html);
		$this->assertVeraPdfCompliant($pdf, 'example12_paging_html.php');
	}

	/**
	 * mpdf-examples example14_page_numbers_ToC_Index_Bookmarks.php: a table of contents and page numbers.
	 *
	 * @return void
	 */
	public function testExample14TocAndBookmarksPassesUa1()
	{
		$html = $this->loadExampleFixture('example14_page_numbers_ToC_Index_Bookmarks');
		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$mpdf->mirrorMargins = 1;
		$pdf  = $this->getOutput($mpdf, $html);
		$this->assertVeraPdfCompliant($pdf, 'example14_page_numbers_ToC_Index_Bookmarks.php');
	}

	/**
	 * mpdf-examples example16_headers_method_2.php: headers and footers from SetHTMLHeader() and
	 * SetHTMLFooter(), in text as the image is not available.
	 *
	 * @return void
	 */
	public function testExample16HeadersMethod2PassesUa1()
	{
		$html = $this->loadExampleFixture('example16_headers_method_2');

		$mpdf = $this->makeMpdf([
			'PDFUAauto'     => true,
			'margin_header' => 10,
			'margin_footer' => 10,
		]);
		$mpdf->mirrorMargins = 1;

		$header = '<table width="100%" style="border-bottom: 1px solid #000000; font-family: serif; font-size: 9pt;"><tr>'
			. '<td width="50%">Left header {PAGENO}</td>'
			. '<td width="50%" style="text-align: right;"><b>Right header</b></td>'
			. '</tr></table>';

		$headerEven = '<table width="100%" style="border-bottom: 1px solid #000000; font-family: serif; font-size: 9pt;"><tr>'
			. '<td width="50%"><b>Outer header</b></td>'
			. '<td width="50%" style="text-align: right;">Inner header {PAGENO}</td>'
			. '</tr></table>';

		$footer = '<div align="center">Footer text</div>';

		$mpdf->SetHTMLHeader($header);
		$mpdf->SetHTMLHeader($headerEven, 'E');
		$mpdf->SetHTMLFooter($footer);
		$mpdf->SetHTMLFooter($footer, 'E');

		$pdf = $this->getOutput($mpdf, $html);
		$this->assertVeraPdfCompliant($pdf, 'example16_headers_method_2.php');
	}

	/**
	 * mpdf-examples example22_columns.php: three columns.
	 *
	 * @return void
	 */
	public function testExample22ColumnsPassesUa1()
	{
		$html = $this->loadExampleFixture('example22_columns');

		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);

		$mpdf->SetColumns(3, 'J');
		$pdf = $this->getOutput($mpdf, $html);
		$this->assertVeraPdfCompliant($pdf, 'example22_columns.php');
	}

	/**
	 * mpdf-examples example26_RTL.php: Hebrew, Arabic and Farsi, without the parts in xbriyaz.
	 *
	 * @return void
	 */
	public function testExample26RtlPassesUa1()
	{
		$html = $this->loadExampleFixture('example26_RTL');
		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$pdf  = $this->getOutput($mpdf, $html);
		$this->assertVeraPdfCompliant($pdf, 'example26_RTL.php');
	}

	/**
	 * mpdf-examples example34_invoice_example.php, without its protection, which
	 * testExample64ProtectedDocumentPassesUa1() covers, or its watermark.
	 *
	 * @return void
	 */
	public function testExample34InvoicePassesUa1()
	{
		$html = $this->loadExampleFixture('example34_invoice_example');
		$mpdf = $this->makeMpdf([
			'PDFUAauto'     => true,
			'margin_left'   => 20,
			'margin_right'  => 15,
			'margin_top'    => 48,
			'margin_bottom' => 25,
			'margin_header' => 10,
			'margin_footer' => 10,
		]);
		$pdf = $this->getOutput($mpdf, $html);
		$this->assertVeraPdfCompliant($pdf, 'example34_invoice_example.php');
	}

	/**
	 * mpdf-examples example36_annotations_and_attached_files.php: sticky notes, without the attached
	 * file.
	 *
	 * @return void
	 */
	public function testExample36AnnotationsPassesUa1()
	{
		$html = $this->loadExampleFixture('example36_annotations_and_attached_files');
		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$mpdf->title2annots = true;
		$pdf  = $this->getOutput($mpdf, $html);
		$this->assertVeraPdfCompliant($pdf, 'example36_annotations_and_attached_files.php');
	}

	/**
	 * mpdf-examples example39_PDFA_compliance.php, as PDF/A and PDF/UA together.
	 *
	 * @return void
	 */
	public function testExample39PdfaCompliancePassesUa1()
	{
		$html = $this->loadExampleFixture('example39_PDFA_compliance');
		$mpdf = $this->makeMpdf([
			'PDFA'      => true,
			'PDFAauto'  => true,
			'PDFUAauto' => true,
		]);
		$pdf = $this->getOutput($mpdf, $html);
		$this->assertVeraPdfCompliant($pdf, 'example39_PDFA_compliance.php');
	}

	/**
	 * A document that is PDF/A-2b and PDF/UA-1 at once passes both, its pdfuaid schema declared as
	 * PDF/A requires.
	 *
	 * @return void
	 */
	public function testPdfA2bDocumentPassesUa1And2b()
	{
		$mpdf = $this->makeMpdf(['PDFA' => true, 'PDFAversion' => '2-B']);
		$pdf = $this->getOutput($mpdf, '<h1>Archived and accessible</h1><p>Both at once.</p>');

		$this->assertVeraPdfCompliant($pdf, 'PDF/A-2b and PDF/UA-1');
		$this->assertVeraPdfCompliant($pdf, 'PDF/A-2b and PDF/UA-1', '2b');
	}

	/**
	 * mpdf-examples example64_protected_document.php: SetProtection() keeps 'extract' and leaves the
	 * XMP unencrypted.
	 *
	 * @return void
	 */
	public function testExample64ProtectedDocumentPassesUa1()
	{
		$html = $this->loadExampleFixture('example64_protected_document');
		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		// veraPDF missegments uncompressed RC4 content streams and reports their text as untagged,
		// though the same document passes compressed and decrypts to the unencrypted stream
		$mpdf->compress = true;
		$mpdf->SetProtection([]);
		$pdf = $this->getOutput($mpdf, $html);
		$this->assertVeraPdfCompliant($pdf, 'example64_protected_document.php');
	}

	/**
	 * mpdf-examples example03_backgrounds_and_borders.php: backgrounds, gradients and rounded borders
	 * drawn as artifacts.
	 *
	 * @return void
	 */
	public function testExample03BackgroundsAndBordersPassesUa1()
	{
		$html = $this->loadExampleFixture('example03_backgrounds_and_borders');
		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$pdf  = $this->getOutput($mpdf, $html);
		$this->assertVeraPdfCompliant($pdf, 'example03_backgrounds_and_borders.php');
	}

	/**
	 * mpdf-examples example09_forms.php: every kind of active form field.
	 *
	 * @return void
	 */
	public function testExample09FormsPassesUa1()
	{
		$html = $this->loadExampleFixture('example09_forms');
		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$mpdf->useActiveForms = true;
		$pdf = $this->getOutput($mpdf, $html);
		$this->assertVeraPdfCompliant($pdf, 'example09_forms.php');
	}

	/**
	 * mpdf-examples example11_overflow_auto.php: a fixed-position block shrunk to fit, its content
	 * still tagged.
	 *
	 * @return void
	 */
	public function testExample11OverflowAutoPassesUa1()
	{
		$html = $this->loadExampleFixture('example11_overflow_auto');
		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$pdf  = $this->getOutput($mpdf, $html);
		$this->assertVeraPdfCompliant($pdf, 'example11_overflow_auto.php');
	}

	/**
	 * mpdf-examples example18_headers_method_4.php: headers set in <!--mpdf ... mpdf--> comments,
	 * with odd and even headers.
	 *
	 * @return void
	 */
	public function testExample18HeadersMethod4PassesUa1()
	{
		$html = $this->loadExampleFixture('example18_headers_method_4');
		$mpdf = $this->makeMpdf([
			'PDFUAauto'     => true,
			'margin_left'   => 32,
			'margin_right'  => 25,
			'margin_top'    => 47,
			'margin_bottom' => 47,
			'margin_header' => 10,
			'margin_footer' => 10,
		]);
		$mpdf->mirrorMargins = 1;
		$pdf = $this->getOutput($mpdf, $html);
		$this->assertVeraPdfCompliant($pdf, 'example18_headers_method_4.php');
	}

	/**
	 * mpdf-examples example19_page_sizes.php: pages of several sizes.
	 *
	 * @return void
	 */
	public function testExample19PageSizesPassesUa1()
	{
		$html = $this->loadExampleFixture('example19_page_sizes');
		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$pdf  = $this->getOutput($mpdf, $html);
		$this->assertVeraPdfCompliant($pdf, 'example19_page_sizes.php');
	}

	/**
	 * mpdf-examples example20_justify.php: justified text.
	 *
	 * @return void
	 */
	public function testExample20JustifyPassesUa1()
	{
		$html = $this->loadExampleFixture('example20_justify');
		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$pdf  = $this->getOutput($mpdf, $html);
		$this->assertVeraPdfCompliant($pdf, 'example20_justify.php');
	}

	/**
	 * mpdf-examples example21_hyphenation.php: hyphenation in four columns.
	 *
	 * @return void
	 */
	public function testExample21HyphenationPassesUa1()
	{
		$html = $this->loadExampleFixture('example21_hyphenation');
		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$mpdf->SetColumns(4, 'J');
		$pdf = $this->getOutput($mpdf, $html);
		$this->assertVeraPdfCompliant($pdf, 'example21_hyphenation.php');
	}

	/**
	 * mpdf-examples example23_orientation.php: changes between portrait and landscape.
	 *
	 * @return void
	 */
	public function testExample23OrientationPassesUa1()
	{
		$html = $this->loadExampleFixture('example23_orientation');
		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$mpdf->mirrorMargins = 1;
		$pdf = $this->getOutput($mpdf, $html);
		$this->assertVeraPdfCompliant($pdf, 'example23_orientation.php');
	}

	/**
	 * mpdf-examples example24_orientation_2.php: a table on a landscape page.
	 *
	 * @return void
	 */
	public function testExample24Orientation2PassesUa1()
	{
		$html = $this->loadExampleFixture('example24_orientation_2');
		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$mpdf->displayDefaultOrientation = true;
		$pdf = $this->getOutput($mpdf, $html);
		$this->assertVeraPdfCompliant($pdf, 'example24_orientation_2.php');
	}

	/**
	 * mpdf-examples example38_dot_tab.php: dot leaders, drawn as artifacts.
	 *
	 * @return void
	 */
	public function testExample38DotTabPassesUa1()
	{
		$html = $this->loadExampleFixture('example38_dot_tab');
		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$pdf  = $this->getOutput($mpdf, $html);
		$this->assertVeraPdfCompliant($pdf, 'example38_dot_tab.php');
	}

	/**
	 * mpdf-examples example02_CSS_styles.php, with the part of its stylesheet it uses inlined.
	 *
	 * @return void
	 */
	public function testExample02CssStylesPassesUa1()
	{
		$html = $this->loadExampleFixture('example02_CSS_styles');
		$mpdf = $this->makeMpdf();
		$pdf  = $this->getOutput($mpdf, $html);
		$this->assertVeraPdfCompliant($pdf, 'example02_CSS_styles.php');
	}

	/**
	 * mpdf-examples example35_watermarks.php: a text watermark, drawn as a Background artifact; the
	 * image watermark is left out.
	 *
	 * @return void
	 */
	public function testExample35WatermarksPassesUa1()
	{
		$html = $this->loadExampleFixture('example35_watermarks');
		$mpdf = $this->makeMpdf(['PDFUAauto' => true, 'watermarkAngle' => 135]);
		$mpdf->SetWatermarkText('DRAFT');
		$mpdf->watermark_font = 'DejaVuSansCondensed';
		$mpdf->showWatermarkText = true;
		$pdf = $this->getOutput($mpdf, $html);
		$this->assertVeraPdfCompliant($pdf, 'example35_watermarks.php');
	}

	/**
	 * mpdf-examples example37_barcodes.php: every barcode type, each tagged Figure.
	 *
	 * @return void
	 */
	public function testExample37BarcodesPassesUa1()
	{
		$html = $this->loadExampleFixture('example37_barcodes');
		$mpdf = $this->makeMpdf([
			'PDFUAauto'          => true,
			'margin_left'        => 20,
			'margin_right'       => 15,
			'margin_top'         => 25,
			'margin_bottom'      => 25,
			'showBarcodeNumbers' => false,
		]);
		$pdf = $this->getOutput($mpdf, $html);
		$this->assertVeraPdfCompliant($pdf, 'example37_barcodes.php');
	}

	/**
	 * mpdf-examples example66_custom_properties.php: custom properties from the config and
	 * AddCustomProperty().
	 *
	 * @return void
	 */
	public function testExample66CustomPropertiesPassesUa1()
	{
		$html = $this->loadExampleFixture('example66_custom_properties');
		$mpdf = $this->makeMpdf([
			'customProperties' => [
				'property1'         => 'value of property 1',
				'property2'         => 'value of property 2',
				'rewritten_property' => 'value to rewrite',
			],
		]);
		$mpdf->AddCustomProperty('rewritten_property', 'rewritten_value');
		$mpdf->AddCustomProperty('property3', 'value of property 3');
		$pdf = $this->getOutput($mpdf, $html);
		$this->assertVeraPdfCompliant($pdf, 'example66_custom_properties.php');
	}

	/**
	 * SVGs named by their own title and desc, and a decorative one, pass.
	 *
	 * @return void
	 */
	public function testDocumentWithSvgAccessibleMetadataPassesUa1()
	{
		$html = $this->loadExampleFixture('svg-accessible');
		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$pdf  = $this->getOutput($mpdf, $html);
		$this->assertVeraPdfCompliant($pdf, 'svg-accessible.html');
	}

	/**
	 * Form fields drawn without useActiveForms pass, drawn as artifacts.
	 *
	 * @return void
	 */
	public function testLegacyFormsUseActiveFormsFalsePassesUa1()
	{
		$html = $this->loadExampleFixture('legacy_forms_useactiveformsfalse');
		$mpdf = $this->makeMpdf([
			'PDFUAauto'      => true,
			'useActiveForms' => false,
		]);
		$pdf = $this->getOutput($mpdf, $html);
		$this->assertVeraPdfCompliant($pdf, 'legacy_forms_useactiveformsfalse.html');
	}

	/**
	 * Ruby with rb and rt, with rp, and with a bare text base passes.
	 *
	 * @return void
	 */
	public function testDocumentWithRubyAnnotationsPassesUa1()
	{
		$html = '<h1>Ruby annotations</h1>'
			. '<p>Base and annotation: <ruby><rb>kanji</rb><rt>furigana</rt></ruby>.</p>'
			. '<p>With parenthesis fallback: '
			. '<ruby><rb>base</rb><rp>(</rp><rt>anno</rt><rp>)</rp></ruby>.</p>'
			. '<p>Bare-text base: <ruby>kanji<rt>furigana</rt></ruby>.</p>';

		$mpdf = $this->makeMpdf();
		$pdf  = $this->getOutput($mpdf, $html);
		$this->assertVeraPdfCompliant($pdf, 'document with ruby annotations');
	}

	/**
	 * A barcode and an image in columns pass, tagged as they would be outside columns.
	 *
	 * @return void
	 */
	public function testColumnsWithGraphicsPassUa1()
	{
		$png = 'data:image/png;base64,'
			. 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8'
			. 'z8BQDwADhQGAWjR9awAAAABJRU5ErkJggg==';

		$html = '<h1>Columns with graphics</h1>'
			. '<columns column-count="2" column-gap="5" />'
			. '<p>First column paragraph to fill the left column with some text.</p>'
			. '<barcode code="9780954224608" type="EAN13" />'
			. '<p>Text after the barcode to continue the flow into the column.</p>'
			. '<img src="' . $png . '" width="40" height="40" alt="A descriptive image">'
			. '<p>More text to push content across both columns of the page.</p>'
			. '<columns column-count="1" />'
			. '<p>Back to a single column.</p>';

		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$pdf  = $this->getOutput($mpdf, $html);
		$this->assertVeraPdfCompliant($pdf, 'columns with barcode and image');
	}

	/**
	 * A figure holding an image and its caption passes, as does one given a name with aria-label.
	 *
	 * @return void
	 */
	public function testFigureWithCaptionPassesUa1()
	{
		$png = 'data:image/png;base64,'
			. 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8'
			. 'z8BQDwADhQGAWjR9awAAAABJRU5ErkJggg==';

		$html = '<h1>Figures</h1>'
			. '<figure><img src="' . $png . '" width="20" height="20" alt="A red pixel">'
			. '<figcaption>Figure 1. One pixel</figcaption></figure>'
			. '<figure aria-label="Sales by quarter"><p>Q1 10, Q2 20</p></figure>';

		$pdf = $this->getOutput($this->makeMpdf(), $html);
		$this->assertVeraPdfCompliant($pdf, 'figures with captions');
	}

	/**
	 * Reads an HTML fixture from tests/data/html/pdfua-examples/, skipping the test when it is missing.
	 *
	 * @param string $name The file name without .html
	 * @return string
	 */
	private function loadExampleFixture($name)
	{
		$path = __DIR__ . '/../../data/html/pdfua-examples/' . $name . '.html';
		if (!is_file($path)) {
			$this->markTestSkipped('Fixture not found: ' . $path);
		}
		return file_get_contents($path);
	}

	/**
	 * Asserts veraPDF finds the document compliant, listing the failed rules when it does not.
	 *
	 * @param string $pdfBytes
	 * @param string $label   What the document is, for the failure message
	 * @param string $flavour The veraPDF profile, ua1 unless another is named
	 * @return void
	 */
	private function assertVeraPdfCompliant($pdfBytes, $label, $flavour = 'ua1')
	{
		$tmpFile = tempnam(sys_get_temp_dir(), 'mpdf-ua1-');

		// Older veraPDF releases look at the extension
		$pdfFile = $tmpFile . '.pdf';
		rename($tmpFile, $pdfFile);

		file_put_contents($pdfFile, $pdfBytes);

		$result = null;
		try {
			$result = $this->runVeraPdf($pdfFile, $flavour);
		} finally {
			if (is_file($pdfFile)) {
				unlink($pdfFile);
			}
		}

		if (!$result['isCompliant']) {
			$errorSummary = implode("\n", $result['errors']);
			$this->fail(
				'veraPDF ' . $flavour . ' validation FAILED for "' . $label . "\".\n\n"
				. 'Failures (' . count($result['errors']) . "):\n" . $errorSummary
			);
		}

		$this->assertTrue(true);
	}

	/**
	 * Runs a veraPDF profile over a file.
	 *
	 * @param string $pdfPath
	 * @param string $flavour
	 * @return array ['isCompliant' => bool, 'errors' => string[]]
	 */
	private function runVeraPdf($pdfPath, $flavour)
	{
		// stderr is thrown away: left in a pipe nobody reads, veraPDF would block once it filled
		$cmd = escapeshellarg($this->veraPdfBin)
			. ' --flavour ' . escapeshellarg($flavour) . ' --format json '
			. escapeshellarg($pdfPath)
			. ' 2>/dev/null';

		$descriptors = [
			0 => ['pipe', 'r'],
			1 => ['pipe', 'w'],
		];

		$process = proc_open($cmd, $descriptors, $pipes);

		if (!is_resource($process)) {
			$this->fail('Failed to launch veraPDF process: ' . $cmd);
		}

		fclose($pipes[0]);

		$stdout = stream_get_contents($pipes[1]);
		fclose($pipes[1]);

		proc_close($process);

		return $this->parseVeraPdfJson((string) $stdout);
	}

	/**
	 * Reads veraPDF's JSON report, whose validationResult is an object up to 1.26 and a one-element
	 * array from 1.30.
	 *
	 * @param string $json
	 * @return array ['isCompliant' => bool, 'errors' => string[]]
	 */
	private function parseVeraPdfJson($json)
	{
		if ($json === '') {
			$this->fail('veraPDF produced no JSON output. Is VERAPDF_BIN correct?');
		}

		$data = json_decode($json, true);

		if (!is_array($data)) {
			$this->fail(
				'veraPDF output is not valid JSON. Raw output:' . "\n" . substr($json, 0, 2000)
			);
		}

		if (!isset($data['report'])
			|| !isset($data['report']['jobs'])
			|| !is_array($data['report']['jobs'])
			|| !isset($data['report']['jobs'][0])
			|| !isset($data['report']['jobs'][0]['validationResult'])
		) {
			$this->fail(
				'veraPDF JSON structure not recognised (expected report.jobs[0].validationResult). '
				. 'Raw output:' . "\n" . substr($json, 0, 2000)
			);
		}

		$validationResult = $data['report']['jobs'][0]['validationResult'];
		if (is_array($validationResult)
			&& isset($validationResult[0])
			&& is_array($validationResult[0])
			&& array_key_exists('compliant', $validationResult[0])) {
			$validationResult = $validationResult[0];
		}
		$isCompliant = !empty($validationResult['compliant']);

		$errors = [];

		if (!$isCompliant && isset($validationResult['details']['ruleSummaries'])) {
			foreach ($validationResult['details']['ruleSummaries'] as $rule) {
				if (isset($rule['failedChecks']) && (int) $rule['failedChecks'] > 0) {
					$spec        = isset($rule['specification']) ? $rule['specification'] : '';
					$clause      = isset($rule['clause']) ? $rule['clause'] : '';
					$testNum     = isset($rule['testNumber']) ? $rule['testNumber'] : '';
					$description = isset($rule['description']) ? $rule['description'] : '';
					$failed      = (int) $rule['failedChecks'];

					$errors[] = sprintf(
						'[%s §%s test %s] %s (%d failed check%s)',
						$spec,
						$clause,
						$testNum,
						$description,
						$failed,
						$failed === 1 ? '' : 's'
					);
				}
			}
		}

		return [
			'isCompliant' => $isCompliant,
			'errors'      => $errors,
		];
	}
}
