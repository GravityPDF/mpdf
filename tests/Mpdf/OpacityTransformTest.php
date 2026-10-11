<?php

namespace Mpdf;

/**
 * Opacity and transform on blocks and inline elements: a block is drawn as a group, a form XObject painted with its
 * opacity and transform, and a run of an inline element's text with its opacity
 */
class OpacityTransformTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;

	/**
	 * A block with opacity is drawn as one transparency group, its background and text inside it, painted at its
	 * opacity: the background does not show through the text
	 */
	public function testBlockOpacityDrawsOneGroup()
	{
		$pdf = $this->render('<div style="opacity: .3; background: #f00">Faded</div>');
		$page = $this->pageContents($pdf)[0];
		$groups = $this->groups($pdf);

		$this->assertCount(1, $groups);
		$this->assertSame(1, preg_match('#q (/GS\d+) gs\n/(FO\d+) Do\nQ#', $page, $paint));
		$this->assertSame('0.3', $this->alphas($pdf)[$paint[1]]);
		$this->assertStringNotContainsString('(Faded)', $page);

		$group = $groups[$paint[2]];
		$this->assertStringContainsString('/Group', $group['dictionary']);
		$this->assertStringContainsString('1.000 0.000 0.000 rg', $group['stream']);
		$this->assertLessThan(strpos($group['stream'], '(Faded)'), strpos($group['stream'], 're f'));
	}

	/**
	 * The opacity of a block inside another is applied inside the outer group, so the two multiply
	 */
	public function testNestedOpacityMultiplies()
	{
		$pdf = $this->render('<div style="opacity: .5">Outer<div style="opacity: .5">Inner</div></div>');
		$groups = $this->groups($pdf);
		$page = $this->pageContents($pdf)[0];

		$this->assertCount(2, $groups);
		$this->assertSame(1, preg_match('#/(FO\d+) Do#', $page, $outer));
		$this->assertSame(1, preg_match('#q (/GS\d+) gs\n/(FO\d+) Do\nQ#', $groups[$outer[1]]['stream'], $inner));
		$this->assertSame('0.5', $this->alphas($pdf)[$inner[1]]);
		$this->assertStringContainsString('(Inner)', $groups[$inner[2]]['stream']);
		$this->assertStringNotContainsString('(Inner)', $groups[$outer[1]]['stream']);
	}

	/**
	 * A block a page break splits is drawn as a group on each page it reaches, the part of its background on that
	 * page in it
	 */
	public function testBlockAcrossPageBreakHasAGroupOnEachPage()
	{
		$pdf = $this->render('<div style="opacity: .4; background: #0c0">' . $this->filler(40) . '</div><p>After</p>');
		$pages = $this->pageContents($pdf);
		$groups = $this->groups($pdf);

		$this->assertCount(2, $pages);
		$this->assertCount(2, $groups);
		foreach ($pages as $page) {
			$this->assertSame(1, preg_match('#q /GS\d+ gs\n/(FO\d+) Do\nQ#', $page, $paint));
			$this->assertStringContainsString('0.000 0.800 0.000 rg', $groups[$paint[1]]['stream']);
			$this->assertStringNotContainsString('(Filler)', $page);
		}
		$this->assertStringContainsString('(After)', $pages[1]);
	}

	/**
	 * A block kept on one page with page-break-inside: avoid is drawn once, in one group, on the page it moves to
	 */
	public function testKeptBlockIsGroupedWhereItLands()
	{
		$pdf = $this->render($this->filler(25) . '<div style="opacity: .4; page-break-inside: avoid">' . $this->filler(8) . '</div>');
		$pages = $this->pageContents($pdf);

		$this->assertCount(2, $pages);
		$this->assertCount(1, $this->groups($pdf));
		$this->assertStringNotContainsString(' Do', $pages[0]);
		$this->assertSame(1, preg_match_all('# Do#', $pages[1]));
	}

	/**
	 * A run of an inline element's text is drawn at its opacity, that of an element inside it multiplied in, and the
	 * text after it opaque again
	 */
	public function testInlineOpacity()
	{
		$pdf = $this->render('<p>A <span style="opacity: .5">B <b style="opacity: .5">C</b></span> D</p>');
		$page = $this->pageContents($pdf)[0];
		$alphas = $this->alphas($pdf);

		$this->assertCount(0, $this->groups($pdf));
		$this->assertSame(1, preg_match('#(/GS\d+) gs\n[^\n]*\(B \)#', $page, $b));
		$this->assertSame(1, preg_match('#(/GS\d+) gs\n[^\n]*\(C\)#', $page, $c));
		$this->assertSame(1, preg_match('#\(C\) Tj ET Q\n(/GS\d+) gs\n#', $page, $after));
		$this->assertSame('0.5', $alphas[$b[1]]);
		$this->assertSame('0.25', $alphas[$c[1]]);
		$this->assertSame('1', $alphas[$after[1]]);
	}

	/**
	 * Inline opacity reaches text in a table cell too
	 */
	public function testInlineOpacityInATableCell()
	{
		$pdf = $this->render('<table><tr><td><span style="opacity: .3">Cell</span></td></tr></table>');

		$this->assertSame(1, preg_match('#(/GS\d+) gs\n[^\n]*\(Cell\)#', $this->pageContents($pdf)[0], $cell));
		$this->assertSame('0.3', $this->alphas($pdf)[$cell[1]]);
	}

	/**
	 * A block's transform wraps the group it is drawn in, in PDF user space: points, with y up the page
	 */
	public function testBlockTransform()
	{
		$pdf = $this->render('<div style="transform: translate(10mm, 20mm)">Moved</div>');
		$page = $this->pageContents($pdf)[0];

		$this->assertSame(1, preg_match('#q 1\.0000 0\.0000 0\.0000 1\.0000 28\.3465 -56\.6929 cm\n/(FO\d+) Do\nQ#', $page, $paint));
		$this->assertStringContainsString('(Moved)', $this->groups($pdf)[$paint[1]]['stream']);
	}

	/**
	 * A transform is about the centre of the block's border box unless transform-origin moves it: here the top left
	 * corner of the block, which stays where it is
	 */
	public function testTransformOrigin()
	{
		$mpdf = $this->mpdf();
		$mpdf->WriteHTML('<div style="transform: scale(2); transform-origin: left top; margin: 0; border: 0">Big</div>');
		$x = $mpdf->lMargin * Mpdf::SCALE;
		$y = ($mpdf->h - $mpdf->tMargin) * Mpdf::SCALE;
		$pdf = $this->output($mpdf);

		$this->assertSame(1, preg_match('#q 2\.0000 0\.0000 0\.0000 2\.0000 (-?[\d.]+) (-?[\d.]+) cm#', $this->pageContents($pdf)[0], $cm));
		$this->assertEqualsWithDelta(-$x, (float) $cm[1], 0.001);
		$this->assertEqualsWithDelta(-$y, (float) $cm[2], 0.001);
	}

	/**
	 * A transform CSS would reject draws the block as it is
	 */
	public function testRejectedTransformIsIgnored()
	{
		$pdf = $this->render('<div style="transform: wobble(2)">Still</div>');

		$this->assertStringNotContainsString(' cm', $this->pageContents($pdf)[0]);
		$this->assertStringContainsString('(Still)', $this->groups($pdf)['FO1']['stream']);
	}

	/**
	 * A link in a rotated block covers the box around where its corners are turned to: a quarter turn swaps its width
	 * and height
	 */
	public function testLinkInRotatedBlock()
	{
		$html = '<div style="width: 60mm; %s"><a href="https://example.com">A link of some length</a></div>';
		$plain = $this->linkRect($this->render(sprintf($html, '')));
		$turned = $this->linkRect($this->render(sprintf($html, 'transform: rotate(90deg)')));

		$this->assertEqualsWithDelta($plain[2] - $plain[0], $turned[1] - $turned[3], 0.01);
		$this->assertEqualsWithDelta($plain[1] - $plain[3], $turned[2] - $turned[0], 0.01);
	}

	/**
	 * PDF/A-1 forbids transparency: a block and an inline element with opacity are drawn opaque, the block's
	 * transform kept
	 */
	public function testPdfA1DrawsOpaque()
	{
		$mpdf = $this->pdfA('1-B');
		$mpdf->WriteHTML('<div style="opacity: .3; transform: rotate(10deg)">Block <span style="opacity: .3">span</span></div>');
		$pdf = $this->output($mpdf);

		$this->assertSame([], array_diff($this->alphas($pdf), ['1']));
		$this->assertStringNotContainsString('/Transparency', $pdf);
		$this->assertStringContainsString(' cm', $this->pageContents($pdf)[0]);
	}

	/**
	 * A PDF/A-1 document that is not to fix itself reports the opacity it could not keep
	 */
	public function testPdfA1ReportsOpacity()
	{
		$mpdf = $this->mpdf(['mode' => '', 'PDFA' => true, 'PDFAauto' => false, 'PDFAversion' => '1-B']);
		$mpdf->WriteHTML('<div style="opacity: .3">Block</div><p><span style="opacity: .3">span</span></p>');

		$this->assertSame(
			['Opacity must be 100% (Opacity changed to 100%)'],
			array_values(array_unique($mpdf->PDFAXwarnings))
		);
	}

	/**
	 * Under cssMode legacy opacity and transform are left alone on blocks and inline elements, as before
	 */
	public function testLegacyModeIgnoresThem()
	{
		$pdf = $this->render(
			'<div style="opacity: .3; transform: rotate(10deg)">Block <span style="opacity: .3">span</span></div>',
			['cssMode' => CssMode::LEGACY]
		);

		$this->assertCount(0, $this->groups($pdf));
		$this->assertSame([], array_diff($this->alphas($pdf), ['1']));
		$this->assertStringNotContainsString(' cm', $this->pageContents($pdf)[0]);
	}

	/**
	 * The groups the document draws, by the name the pages paint them with
	 *
	 * @param string $pdf
	 *
	 * @return array[] Each with the form's dictionary and its content stream
	 */
	private function groups($pdf)
	{
		preg_match_all('#/(FO\d+) (\d+) 0 R#', $pdf, $refs, PREG_SET_ORDER);

		$groups = [];
		foreach ($refs as $ref) {
			$object = $this->object($pdf, $ref[2]);
			preg_match('/^(.*?)stream\n(.*?)\nendstream/s', $object, $parts);
			$groups[$ref[1]] = ['dictionary' => $parts[1], 'stream' => $parts[2]];
		}

		return $groups;
	}

	/**
	 * The fill alpha of each graphics state, by the name the pages set it with
	 *
	 * @param string $pdf
	 *
	 * @return string[]
	 */
	private function alphas($pdf)
	{
		preg_match_all('#/(GS\d+) (\d+) 0 R#', $pdf, $refs, PREG_SET_ORDER);

		$alphas = [];
		foreach ($refs as $ref) {
			if (preg_match('#/ca ([\d.]+)#', $this->object($pdf, $ref[2]), $ca)) {
				$alphas['/' . $ref[1]] = $ca[1];
			}
		}

		return $alphas;
	}

	/**
	 * The /Rect of the document's one link
	 *
	 * @param string $pdf
	 *
	 * @return float[]
	 */
	private function linkRect($pdf)
	{
		$this->assertSame(1, preg_match('#/Subtype /Link /Rect \[([\d. -]+)\]#', $pdf, $rect));

		return array_map('floatval', explode(' ', $rect[1]));
	}
}
