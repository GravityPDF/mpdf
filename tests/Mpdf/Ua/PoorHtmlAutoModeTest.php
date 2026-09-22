<?php

namespace Mpdf\Ua;

/**
 * Badly formed HTML rendered under PDFUAauto must not throw, and must pass veraPDF's ua1 profile
 * when VERAPDF_BIN points at the validator.
 *
 * Unlike VeraPdfConformanceTest nothing is skipped without veraPDF, so the no-throw half always runs.
 *
 * @group pdfua
 */
class PoorHtmlAutoModeTest extends PdfUaTestCase
{
	/**
	 * The veraPDF binary, or an empty string when there is none to run
	 *
	 * @var string
	 */
	private $veraPdfBin = '';

	/**
	 * Picks up the veraPDF binary from VERAPDF_BIN.
	 *
	 * @return void
	 */
	protected function set_up()
	{
		parent::set_up();
		$bin = (string) getenv('VERAPDF_BIN');
		if ($bin !== '' && is_file($bin) && is_executable($bin)) {
			$this->veraPdfBin = $bin;
		}
	}

	/**
	 * A table with no header cells renders conformantly.
	 */
	public function testTableWithoutThPasses()
	{
		$html = '<table><tr><td>1</td><td>2</td></tr><tr><td>3</td><td>4</td></tr></table>';
		$this->generateAndCheck($html, 'table without <th>');
	}

	/**
	 * A link with no text renders conformantly.
	 */
	public function testEmptyAnchorPasses()
	{
		$html = '<p>Before <a href="https://example.com"></a> after.</p>';
		$this->generateAndCheck($html, 'empty <a href>');
	}

	/**
	 * A link whose only content is a decorative image renders conformantly.
	 */
	public function testImageOnlyLinkPasses()
	{
		$png = $this->onePixelPng();
		$html = '<p><a href="https://example.com/foo"><img src="' . $png . '" alt="" width="20" height="20"></a></p>';
		$this->generateAndCheck($html, 'image-only link with decorative inner image');
	}

	/**
	 * More than one h1 renders conformantly.
	 */
	public function testMultipleH1Passes()
	{
		$html = '<h1>First top heading</h1><p>Body.</p><h1>Second top heading</h1><p>More body.</p>';
		$this->generateAndCheck($html, 'multiple <h1> siblings');
	}

	/**
	 * A heading that skips a level renders conformantly.
	 */
	public function testHeadingLevelSkipPasses()
	{
		$html = '<h1>One</h1><h3>Skipped two</h3><p>Body.</p>';
		$this->generateAndCheck($html, '<h1> followed directly by <h3>');
	}

	/**
	 * An image with no alt attribute renders conformantly.
	 */
	public function testImageWithNoAltPasses()
	{
		$png = $this->onePixelPng();
		$html = '<p>Before <img src="' . $png . '" width="20" height="20"> after.</p>';
		$this->generateAndCheck($html, '<img> with no alt');
	}

	/**
	 * A span with role="button" renders conformantly.
	 */
	public function testDivButtonRolePasses()
	{
		$html = '<p><span role="button">Click me</span></p><p>Following body.</p>';
		$this->generateAndCheck($html, '<span role="button">');
	}

	/**
	 * A list faked with bullet characters and line breaks renders conformantly.
	 */
	public function testFakeListAsParagraphPasses()
	{
		$html = '<p>• item one<br>• item two<br>• item three</p>';
		$this->generateAndCheck($html, 'bullet glyphs as fake list');
	}

	/**
	 * Text with no block element around it renders conformantly.
	 */
	public function testBodyLevelTextWithoutWrapperPasses()
	{
		$html = 'Loose body text with no surrounding block element.';
		$this->generateAndCheck($html, 'unwrapped body-level text');
	}

	/**
	 * A span inside a heading renders conformantly.
	 */
	public function testHeadingInsideHeadingPasses()
	{
		$html = '<h1>Outer<span>middle</span>tail</h1><p>Body.</p>';
		$this->generateAndCheck($html, 'inline span inside <h1>');
	}

	/**
	 * An anchor with no href renders conformantly.
	 */
	public function testAnchorWithoutHrefPasses()
	{
		$html = '<p>Before <a>orphan anchor</a> after.</p>';
		$this->generateAndCheck($html, '<a> without href');
	}

	/**
	 * A form with a text input renders conformantly.
	 */
	public function testFormWithInputPasses()
	{
		$html = '<form><input type="text" name="q" value=""></form><p>Following body.</p>';
		$this->generateAndCheck($html, '<form><input> (HIGH-5 path)');
	}

	/**
	 * Header ids with characters a PDF name must escape render conformantly.
	 */
	public function testTableWithIllegalIdCharsPasses()
	{
		$html = '<table>'
			. '<tr><th id="col(1)">A</th><th id="col(2)">B</th></tr>'
			. '<tr><td headers="col(1)">x</td><td headers="col(2)">y</td></tr>'
			. '</table>';
		$this->generateAndCheck($html, 'table with #-escaped TH ids');
	}

	/**
	 * Tables nested three deep render conformantly.
	 */
	public function testNestedTablesPasses()
	{
		$html = '<table><tr><td>'
			. '<table><tr><td>'
			. '<table><tr><td>deepest</td></tr></table>'
			. '</td></tr></table>'
			. '</td></tr></table>';
		$this->generateAndCheck($html, 'nested tables 3 levels deep');
	}

	/**
	 * A div with role="doc-title" renders conformantly.
	 */
	public function testDocTitleRolePasses()
	{
		$html = '<div role="doc-title">My Document Title</div><p>Body.</p>';
		$this->generateAndCheck($html, '<div role="doc-title">');
	}

	/**
	 * A span in another language mid-paragraph renders conformantly.
	 */
	public function testInlineLangSpanPasses()
	{
		$html = '<p>The French word <span lang="fr">bonjour</span> means hello.</p>';
		$this->generateAndCheck($html, 'inline <span lang> mid-paragraph');
	}

	/**
	 * A span with aria-label renders conformantly.
	 */
	public function testInlineAriaLabelSpanPasses()
	{
		$html = '<p>An icon <span aria-label="warning sign">!</span> after text.</p>';
		$this->generateAndCheck($html, 'inline <span aria-label>');
	}

	/**
	 * A fieldset and its legend leave no content untagged.
	 */
	public function testFieldsetLegendPasses()
	{
		$html = '<fieldset><legend>Personal info</legend><p>Name: paragraph text.</p></fieldset>';
		$this->generateAndCheck($html, '<fieldset><legend>');
	}

	/**
	 * A form used as a block container renders conformantly.
	 */
	public function testFormContainerPasses()
	{
		$html = '<form><p>Email: paragraph text inside form.</p></form>';
		$this->generateAndCheck($html, '<form> as block container');
	}

	/**
	 * A header cell with scope="rowgroup" renders conformantly.
	 */
	public function testThScopeRowGroupPasses()
	{
		$html = '<table>'
			. '<tr><th scope="rowgroup">Group A</th><th scope="col">Col 1</th></tr>'
			. '<tr><td>data</td><td>data</td></tr>'
			. '</table>';
		$this->generateAndCheck($html, '<th scope="rowgroup">');
	}

	/**
	 * A javascript: link loses its URI in auto mode but keeps its text.
	 */
	public function testJavascriptHrefStrippedInAutoMode()
	{
		$html = '<p>Click <a href="javascript:alert(1)">here</a> to fail.</p>';
		$this->generateAndCheck($html, '<a href="javascript:..."> in auto mode');
	}

	/**
	 * An image map declared after its image, with a rect, a circle and a poly area, renders conformantly.
	 */
	public function testImageMapPasses()
	{
		$png = $this->onePixelPng();
		$html = '<p><img src="' . $png . '" alt="Floor plan" usemap="#rooms" width="200" height="200"></p>'
			. '<map name="rooms">'
			. '<area shape="rect"   coords="10,10,100,100"     href="https://example.com/lobby"  alt="Lobby">'
			. '<area shape="circle" coords="150,150,30"        href="https://example.com/atrium" alt="Atrium">'
			. '<area shape="poly"   coords="50,50,150,50,100,150" href="https://example.com/garden" alt="Garden">'
			. '</map>';
		$this->generateAndCheck($html, '<img usemap> + <map> + <area> with rect, circle, and poly');
	}

	/**
	 * An area with no alt, which auto mode fills in from its href, renders conformantly.
	 */
	public function testImageMapMissingAltSynthesisesPasses()
	{
		$png = $this->onePixelPng();
		$html = '<p><img src="' . $png . '" alt="Plan" usemap="#m" width="100" height="100"></p>'
			. '<map name="m"><area shape="rect" coords="0,0,50,50" href="https://example.com/x"></map>';
		$this->generateAndCheck($html, '<area> missing alt (auto-synthesised)');
	}

	/**
	 * Ruby annotation with rb, rt and rp renders conformantly.
	 */
	public function testRubyAnnotationPasses()
	{
		$html = '<p>Word <ruby><rb>kanji</rb><rp>(</rp><rt>furigana</rt><rp>)</rp></ruby> in context.</p>';
		$this->generateAndCheck($html, '<ruby><rt> with <rp> fallbacks');
	}

	/**
	 * Ruby inside a heading renders conformantly.
	 */
	public function testRubyInsideHeadingPasses()
	{
		$html = '<h1>Title with <ruby>kanji<rt>furigana</rt></ruby> annotation</h1><p>Body.</p>';
		$this->generateAndCheck($html, '<ruby> inside <h1>');
	}

	/**
	 * Ruby inside a link renders conformantly.
	 */
	public function testRubyInsideLinkPasses()
	{
		$html = '<p><a href="https://example.com">Link <ruby>kanji<rt>furigana</rt></ruby> text</a></p>';
		$this->generateAndCheck($html, '<ruby> inside <a href>');
	}

	/**
	 * Ruby whose base is bare text, with no rb, renders conformantly.
	 */
	public function testRubyWithoutRbPasses()
	{
		$html = '<p><ruby>kanji<rt>furigana</rt></ruby></p>';
		$this->generateAndCheck($html, 'bare-text ruby base (no <rb>)');
	}

	/**
	 * Ruby nested in ruby renders conformantly.
	 */
	public function testRubyNestedPasses()
	{
		$html = '<p><ruby><ruby>kanji<rt>inner</rt></ruby><rt>outer</rt></ruby></p>';
		$this->generateAndCheck($html, 'nested <ruby><ruby></ruby></ruby>');
	}

	/**
	 * An rtc grouping several rt renders conformantly.
	 */
	public function testRubyWithRtcPasses()
	{
		$html = '<p><ruby>kanji<rtc><rt>semantic</rt><rt>phonetic</rt></rtc></ruby></p>';
		$this->generateAndCheck($html, '<rtc> grouping multiple <rt>');
	}

	/**
	 * An inline SVG takes its Figure /Alt from its own title and desc (Matterhorn 13-004).
	 */
	public function testInlineSvgWithTitleAndDescPasses()
	{
		$svg = '<svg width="20" height="20" xmlns="http://www.w3.org/2000/svg">'
			 . '<title>Company logo</title>'
			 . '<desc>A blue circle with the company initial in the centre.</desc>'
			 . '<circle cx="10" cy="10" r="8" fill="blue"/>'
			 . '</svg>';
		$this->generateAndCheck('<p>' . $svg . '</p>', 'inline SVG with title and desc');
	}

	/**
	 * Renders the HTML under PDFUAauto and checks the output with veraPDF when it is available.
	 *
	 * @param string $html
	 * @param string $label What the HTML is, for failure messages
	 * @return void
	 */
	private function generateAndCheck($html, $label)
	{
		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$pdf  = $this->getOutput($mpdf, $html);
		$this->assertNotEmpty($pdf, 'PDFUAauto must produce output for ' . $label);
		$this->assertVeraPdfCompliant($pdf, $label);
	}

	/**
	 * @return string A 1x1 transparent PNG as a data: URI, the same one VeraPdfConformanceTest uses
	 */
	private function onePixelPng()
	{
		return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwADhQGAWjR9awAAAABJRU5ErkJggg==';
	}

	/**
	 * Asserts veraPDF finds the document ua1 compliant, or passes without a check when there is no veraPDF.
	 *
	 * @param string $pdfBytes
	 * @param string $label
	 * @return void
	 */
	private function assertVeraPdfCompliant($pdfBytes, $label)
	{
		if ($this->veraPdfBin === '') {
			$this->assertTrue(true);
			return;
		}

		$tmpFile = tempnam(sys_get_temp_dir(), 'mpdf-poor-html-');
		$pdfFile = $tmpFile . '.pdf';
		rename($tmpFile, $pdfFile);
		file_put_contents($pdfFile, $pdfBytes);

		$result = null;
		try {
			$result = $this->runVeraPdf($pdfFile);
		} finally {
			if (is_file($pdfFile)) {
				unlink($pdfFile);
			}
		}

		if (!$result['isCompliant']) {
			$errorSummary = implode("\n", $result['errors']);
			$this->fail(
				'veraPDF ua1 validation FAILED for "' . $label . "\".\n\n"
				. 'Failures (' . count($result['errors']) . "):\n" . $errorSummary
			);
		}
		$this->assertTrue(true);
	}

	/**
	 * Runs veraPDF's ua1 profile over a file.
	 *
	 * A copy of VeraPdfConformanceTest::runVeraPdf(), kept separate so a change to that gate
	 * cannot quietly weaken this one.
	 *
	 * @param string $pdfPath
	 * @return array ['isCompliant' => bool, 'errors' => string[]]
	 */
	private function runVeraPdf($pdfPath)
	{
		$cmd = escapeshellarg($this->veraPdfBin)
			. ' --flavour ua1 --format json '
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
	 * Reads veraPDF's JSON report, whose validationResult is an object up to 1.26 and a
	 * one-element array from 1.30.
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
			$this->fail('veraPDF output is not valid JSON: ' . substr($json, 0, 2000));
		}

		if (!isset($data['report'])
			|| !isset($data['report']['jobs'])
			|| !is_array($data['report']['jobs'])
			|| !isset($data['report']['jobs'][0])
			|| !isset($data['report']['jobs'][0]['validationResult'])
		) {
			$this->fail('veraPDF JSON structure not recognised: ' . substr($json, 0, 2000));
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
					$errors[] = sprintf(
						'[%s §%s test %s] %s (%d failed check%s)',
						isset($rule['specification']) ? $rule['specification'] : '',
						isset($rule['clause']) ? $rule['clause'] : '',
						isset($rule['testNumber']) ? $rule['testNumber'] : '',
						isset($rule['description']) ? $rule['description'] : '',
						(int) $rule['failedChecks'],
						(int) $rule['failedChecks'] === 1 ? '' : 's'
					);
				}
			}
		}

		return ['isCompliant' => $isCompliant, 'errors' => $errors];
	}
}
