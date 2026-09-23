<?php

namespace Mpdf;

/**
 * Where a visibility cannot be written as optional content, PDFAauto and PDFXauto draw print-only content and leave
 * out screen-only content, and hidden content where optional content is not allowed. What is left out keeps its space.
 */
class AutoVisibilityTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;

	/**
	 * A block and a span with each visibility are in the page content or not, and the text around them is
	 *
	 * @dataProvider autoVisibilities
	 */
	public function testVisibilityUnderAuto($config, $visibility, $drawn)
	{
		$mpdf = $this->document($config);
		$mpdf->WriteHTML(
			'<p>Before</p><div style="visibility: ' . $visibility . '; border: 1px solid #000; background: #ccc">Block</div>'
			. '<p>Line <span style="visibility: ' . $visibility . '">Span</span> After</p>'
		);
		$pages = implode('', $this->contents($this->output($mpdf)));

		$this->assertTrue($this->drawn($pages, 'Before'));
		$this->assertTrue($this->drawn($pages, 'After'));
		$this->assertSame($drawn, $this->drawn($pages, 'Block'));
		$this->assertSame($drawn, $this->drawn($pages, 'Span'));
		$this->assertStringNotContainsString('___DROPPED___', $pages);
	}

	/**
	 * PDF/A-1b, PDF/A-2b and PDF/X-1a under auto, each visibility, and whether its content is in the page: PDF/A-2
	 * draws hidden content in a hidden optional content group
	 *
	 * @return mixed[][]
	 */
	public function autoVisibilities()
	{
		$configs = [
			'PDF/A-1b' => [['PDFA' => true, 'PDFAauto' => true, 'PDFAversion' => '1-B'], false],
			'PDF/A-2b' => [['PDFA' => true, 'PDFAauto' => true, 'PDFAversion' => '2-B'], true],
			'PDF/X-1a' => [['PDFX' => true, 'PDFXauto' => true], false],
		];

		$cases = [];
		foreach ($configs as $name => $config) {
			list($config, $hiddenDrawn) = $config;
			$cases[$name . ', printonly'] = [$config, 'printonly', true];
			$cases[$name . ', screenonly'] = [$config, 'screenonly', false];
			$cases[$name . ', hidden'] = [$config, 'hidden', $hiddenDrawn];
		}

		return $cases;
	}

	/**
	 * Without auto, a visibility that cannot be written as optional content is a warning, and the document is not
	 * written
	 *
	 * @dataProvider refusedVisibilities
	 */
	public function testRefusedWithoutAuto($config, $visibility)
	{
		$mpdf = $this->document($config);
		$mpdf->WriteHTML('<div style="visibility: ' . $visibility . '">Block</div>');

		$this->assertContains('Cannot set visibility to ' . $visibility . ' when using PDFA or PDFX', $mpdf->PDFAXwarnings);

		$this->expectException(MpdfException::class);
		$this->expectExceptionMessage('PDFA/PDFX warnings generated');
		$mpdf->Output('', 'S');
	}

	/**
	 * The visibilities PDF/A-1b, PDF/A-2b and PDF/X-1a cannot write as optional content
	 *
	 * @return mixed[][]
	 */
	public function refusedVisibilities()
	{
		$pdfa1 = ['PDFA' => true, 'PDFAversion' => '1-B'];
		$pdfa2 = ['PDFA' => true, 'PDFAversion' => '2-B'];
		$pdfx = ['PDFX' => true];

		return [
			'PDF/A-1b, printonly' => [$pdfa1, 'printonly'],
			'PDF/A-1b, screenonly' => [$pdfa1, 'screenonly'],
			'PDF/A-1b, hidden' => [$pdfa1, 'hidden'],
			'PDF/A-2b, printonly' => [$pdfa2, 'printonly'],
			'PDF/A-2b, screenonly' => [$pdfa2, 'screenonly'],
			'PDF/X-1a, printonly' => [$pdfx, 'printonly'],
			'PDF/X-1a, screenonly' => [$pdfx, 'screenonly'],
			'PDF/X-1a, hidden' => [$pdfx, 'hidden'],
		];
	}

	/**
	 * A link, form field, annotation, bookmark and index entry register when their block or span is drawn, and not
	 * when it is left out
	 *
	 * @dataProvider registeringElements
	 */
	public function testLeftOutContentRegistersNothing($tag, $visibility, $registered)
	{
		$mpdf = $this->document(['PDFA' => true, 'PDFAauto' => true, 'PDFAversion' => '1-B', 'useActiveForms' => true]);
		$mpdf->WriteHTML(
			'<p>Before</p><' . $tag . ' style="visibility: ' . $visibility . '">Content <a href="https://example.com">link</a>'
			. ' <input type="text" name="field" value="Value" /> <annotation content="Note" /> <bookmark content="Mark" />'
			. ' <indexentry content="Entry" /></' . $tag . '><p>After</p>'
		);
		$pdf = $this->output($mpdf);

		$this->assertCount($registered, isset($mpdf->PageLinks[1]) ? $mpdf->PageLinks[1] : []);
		$this->assertCount($registered, isset($mpdf->PageAnnots[1]) ? $mpdf->PageAnnots[1] : []);
		$this->assertCount($registered, $mpdf->BMoutlines);
		$this->assertCount($registered, $mpdf->Reference);
		$this->assertSame($registered, substr_count($pdf, '/Subtype /Widget'));
		$this->assertSame($registered, substr_count($pdf, '/URI (https://example.com)'));
	}

	/**
	 * A block and a span, drawn and left out, with how many of each thing they register
	 *
	 * @return mixed[][]
	 */
	public function registeringElements()
	{
		return [
			'visible block' => ['div', 'visible', 1],
			'screen-only block' => ['div', 'screenonly', 0],
			'visible span' => ['span', 'visible', 1],
			'screen-only span' => ['span', 'screenonly', 0],
		];
	}

	/**
	 * Content left out across page breaks draws on none of its pages, and what follows is where it would be had the
	 * content been drawn
	 */
	public function testLeftOutContentAcrossPageBreaks()
	{
		$html = '<p>Before</p><div style="visibility: %s; border: 1px solid #000">'
			. str_repeat('<p>Screen</p>', 60) . '</div><p>After</p>';

		$mpdf = $this->document(['PDFA' => true, 'PDFAauto' => true, 'PDFAversion' => '1-B']);
		$mpdf->WriteHTML(sprintf($html, 'screenonly'));
		$pages = $this->contents($this->output($mpdf));

		$visible = $this->document(['PDFA' => true, 'PDFAauto' => true, 'PDFAversion' => '1-B']);
		$visible->WriteHTML(sprintf($html, 'visible'));
		$visiblePages = $this->contents($this->output($visible));

		$this->assertGreaterThan(2, count($pages));
		$this->assertCount(count($visiblePages), $pages);
		foreach ($pages as $page) {
			$this->assertFalse($this->drawn($page, 'Screen'));
		}
		$this->assertSame($this->position(end($visiblePages), 'After'), $this->position(end($pages), 'After'));
	}

	/**
	 * A line drawn after content left out has the colour and width set for it, although the content left out set
	 * the same ones last
	 */
	public function testDrawingResumesWithTheStateSet()
	{
		$mpdf = $this->document(['PDFA' => true, 'PDFAauto' => true, 'PDFAversion' => '1-B']);
		$mpdf->AddPage();
		$mpdf->SetDrawColor(255, 0, 0);
		$mpdf->SetLineWidth(1);
		$mpdf->SetVisibility('hidden');
		$mpdf->SetDrawColor(0, 0, 255);
		$mpdf->SetLineWidth(2);
		$mpdf->Line(10, 10, 50, 10);
		$mpdf->SetVisibility('visible');
		$mpdf->SetDrawColor(0, 0, 255);
		$mpdf->SetLineWidth(2);
		$mpdf->Line(10, 20, 50, 20);
		$page = $this->contents($this->output($mpdf))[0];

		$before = substr($page, 0, strrpos($page, ' m '));
		preg_match_all('/([\d.]+ [\d.]+ [\d.]+) RG/', $before, $colours);
		preg_match_all('/([\d.]+) w/', $before, $widths);

		$this->assertSame(1, substr_count($page, ' l S'));
		$this->assertSame('0.000 0.000 1.000', end($colours[1]));
		$this->assertSame(sprintf('%.3F', 2 * Mpdf::SCALE), end($widths[1]));
	}

	/**
	 * An uncompressed document with the given configuration, in the default fonts that PDF/A and PDF/X embed
	 *
	 * @param mixed[] $config
	 *
	 * @return \Mpdf\Mpdf
	 */
	private function document($config)
	{
		return $this->mpdf($config + ['mode' => 'utf-8']);
	}

	/**
	 * The content stream of each page, in order. Other streams, such as an embedded font's, are left out.
	 *
	 * @param string $pdf
	 *
	 * @return string[]
	 */
	private function contents($pdf)
	{
		$contents = [];
		foreach ($this->pageObjects($pdf) as $number) {
			preg_match('#/Contents (\d+) 0 R#', $this->object($pdf, $number), $ref);
			preg_match('/\n' . $ref[1] . ' 0 obj\s*<<\/Length \d+>>\s*stream\n(.*?)\nendstream/s', $pdf, $stream);
			$contents[] = $stream[1];
		}

		return $contents;
	}

	/**
	 * Whether text is drawn in a content stream, where mPDF writes it as UTF-16BE
	 *
	 * @param string $stream
	 * @param string $text
	 *
	 * @return bool
	 */
	private function drawn($stream, $text)
	{
		return strpos($stream, $this->utf16($text)) !== false;
	}

	/**
	 * Where text is drawn in a content stream: the operands of the Td that places it
	 *
	 * @param string $stream
	 * @param string $text
	 *
	 * @return string
	 */
	private function position($stream, $text)
	{
		$this->assertSame(1, preg_match('/([\d.]+ [\d.]+) Td\s+\(' . preg_quote($this->utf16($text), '/') . '\)/', $stream, $match));

		return $match[1];
	}

	/**
	 * Text as mPDF writes it in a content stream: UTF-16BE
	 *
	 * @param string $text
	 *
	 * @return string
	 */
	private function utf16($text)
	{
		return mb_convert_encoding($text, 'UTF-16BE', 'UTF-8');
	}

}
