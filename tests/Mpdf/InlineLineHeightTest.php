<?php

namespace Mpdf;

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * line-height on an inline element gives the height of that element's box on the line, and the line box grows to hold
 * the tallest box on it, as line-stacking-strategy: inline-line-height describes. The block's own line height stays on
 * every line as its strut, so an inline line-height below it does not shrink the line.
 *
 * In cssMode legacy an inline element's line-height is ignored, as before.
 *
 * Each test reads the top of each line from where Cell() drew its text. Paragraphs have no margins and the text is
 * 11pt unless a document says otherwise.
 */
class InlineLineHeightTest extends TestCase
{

	use DrawnStyles;

	/**
	 * @dataProvider providerLinePositions
	 *
	 * @param string $html A document whose first line starts with "aa"
	 * @param float[] $offsets Pieces of text and how far below "aa" the top of each one's line is, in mm
	 */
	public function testLinePositionsInStandardMode($html, array $offsets)
	{
		$this->assertOffsets($offsets, $this->offsets($html, CssMode::STANDARD));
	}

	/**
	 * @return array[] Documents with a line-height on an inline element, where their lines are drawn in cssMode
	 *                 standard, and, where taking the line-height out of the style attributes of span and b elements
	 *                 does not give it, the document without it
	 */
	public function providerLinePositions()
	{
		$pt = 1 / Mpdf::SCALE;
		$image = '<img src="' . __DIR__ . '/../data/img/exif-orientation-none.jpg" style="height: 5mm; width: 5mm">';

		return [
			'a length' => ['<p>aa <span style="line-height: 30mm">qq</span> bb<br>zz</p>', ['zz' => 30.0]],
			'a number' => ['<p>aa <span style="line-height: 3">qq</span> bb<br>zz</p>', ['zz' => 33 * $pt]],
			'a percentage' => ['<p>aa <span style="line-height: 200%">qq</span> bb<br>zz</p>', ['zz' => 22 * $pt]],
			'an em length' => ['<p>aa <span style="line-height: 2em">qq</span> bb<br>zz</p>', ['zz' => 22 * $pt]],
			'from a stylesheet rule' => [
				'<style>.tall { line-height: 30mm }</style><p>aa <span class="tall">qq</span><br>zz</p>',
				['zz' => 30.0],
				'<p>aa <span>qq</span><br>zz</p>',
			],
			'in the font shorthand' => [
				'<p>aa <span style="font: 11pt/30mm serif">qq</span><br>zz</p>',
				['zz' => 30.0],
				'<p>aa <span style="font: 11pt serif">qq</span><br>zz</p>',
			],
			'with line-stacking-strategy: inline-line-height' => [
				'<p style="line-stacking-strategy: inline-line-height">aa <span style="line-height: 30mm">qq</span><br>zz</p>',
				['zz' => 30.0],
			],
			'taller than the block\'s line-height' => [
				'<p style="line-height: 10mm">aa <span style="line-height: 20mm">qq</span><br>zz</p>',
				['zz' => 20.0],
			],
			'shorter than the block\'s line-height' => [
				'<p style="line-height: 10mm">aa <span style="line-height: 2mm">qq</span><br>zz</p>',
				['zz' => 10.0],
			],
			'shorter, alone on its line' => [
				'<p style="line-height: 10mm"><span style="line-height: 2mm">aa</span><br>zz</p>',
				['zz' => 10.0],
			],
			'zero, alone on its line' => [
				'<p style="line-height: 10mm"><span style="line-height: 0">aa</span><br>zz</p>',
				['zz' => 10.0],
			],
			'inherited by an inline element inside' => [
				'<p>aa <span style="line-height: 30mm"><b>qq</b></span><br>zz</p>',
				['zz' => 30.0],
			],
			'overridden by an inline element inside' => [
				'<p><span style="line-height: 30mm">aa<br><b style="line-height: 10mm">qq</b><br>zz</span></p>',
				['qq' => 30.0, 'zz' => 40.0],
			],
			'a number inherited as a number' => [
				'<p>aa <span style="line-height: 2"><span style="font-size: 20pt">qq</span></span><br>zz</p>',
				['zz' => 40 * $pt],
			],
			'a length inherited as a length' => [
				'<p>aa <span style="line-height: 2em"><span style="font-size: 20pt">qq</span></span><br>zz</p>',
				['zz' => 22 * $pt],
			],
			'the tallest of mixed font sizes' => [
				'<p>aa <span style="font-size: 20pt">bb</span> <span style="line-height: 30mm">cc</span> <small>dd</small><br>zz</p>',
				['zz' => 30.0],
			],
			'shorter than its own font\'s normal line height' => [
				'<p>aa <span style="font-size: 30pt; line-height: 1">qq</span><br>zz</p>',
				['zz' => 30 * $pt],
			],
			'around an image' => [
				'<p>aa <span style="line-height: 30mm">' . $image . '</span><br>zz</p>',
				['zz' => 30.0],
			],
			'in a table cell' => [
				'<table><tr><td>aa <span style="line-height: 30mm">qq</span><br>zz</td></tr></table>',
				['zz' => 30.0],
			],
			'in a table cell with a smaller line-height' => [
				'<table><tr><td style="line-height: 5mm">aa <span style="line-height: 30mm">qq</span><br>zz</td></tr></table>',
				['zz' => 30.0],
			],
			'shorter than the table cell\'s line-height' => [
				'<table><tr><td style="line-height: 10mm"><span style="line-height: 2mm">aa</span><br>zz</td></tr></table>',
				['zz' => 10.0],
			],
			'in a nested table' => [
				'<table><tr><td><table><tr><td>aa <span style="line-height: 30mm">qq</span><br>zz</td></tr></table></td></tr></table>',
				['zz' => 30.0],
			],
			'in a list item' => [
				'<ul><li>aa <span style="line-height: 30mm">qq</span><br>zz</li></ul>',
				['zz' => 30.0],
			],
			'in a positioned block' => [
				'<div style="position: absolute; top: 100mm; left: 20mm; width: 100mm">aa <span style="line-height: 30mm">qq</span><br>zz</div>',
				['zz' => 30.0],
			],
			'after a forced page break' => [
				'<p>first</p><p style="page-break-before: always">aa <span style="line-height: 30mm">qq</span><br>zz</p>',
				['zz' => 30.0],
			],
		];
	}

	/**
	 * A document is drawn as it is without the inline element's line-height: each document above in legacy mode, and
	 * near misses in both modes
	 *
	 * @dataProvider providerUnchanged
	 *
	 * @param string $html A document whose first line starts with "aa"
	 * @param string $mode
	 * @param string|null $without The document without the inline line-height, where withoutInlineLineHeight() does
	 *                             not give it
	 */
	public function testLinePositionsAreUnchanged($html, $mode, $without = null)
	{
		if ($without === null) {
			$without = $this->withoutInlineLineHeight($html);
		}
		$this->assertNotSame($html, $without);

		$this->assertOffsets($this->offsets($without, $mode), $this->offsets($html, $mode));
	}

	/**
	 * @return array[] Documents where an inline element's line-height changes nothing, and the mode
	 */
	public function providerUnchanged()
	{
		$cases = [];
		foreach ($this->providerLinePositions() as $name => $case) {
			$cases[$name . ', ' . CssMode::LEGACY] = [$case[0], CssMode::LEGACY, isset($case[2]) ? $case[2] : null];
		}

		$documents = [
			'the same as the block\'s' => '<p style="line-height: 3">aa <span style="line-height: 3">qq</span><br>zz</p>',
			'block-line-height, which fixes the line at the block\'s' => '<p style="line-stacking-strategy: block-line-height; line-height: 10mm">aa <span style="line-height: 30mm">qq</span><br>zz</p>',
			'max-height, which leaves out the leading of inline elements' => '<p style="line-stacking-strategy: max-height">aa <span style="line-height: 30mm">qq</span><br>zz</p>',
		];

		foreach ($documents as $name => $html) {
			foreach ([CssMode::STANDARD, CssMode::LEGACY] as $mode) {
				$cases[$name . ', ' . $mode] = [$html, $mode];
			}
		}

		return $cases;
	}

	/**
	 * Only the line holding the inline element grows
	 */
	public function testOnlyTheLineItIsOn()
	{
		$html = '<p>aa<br><span style="line-height: 30mm">qq</span><br>zz<br>yy</p>';
		$normal = $this->normalLineHeight();

		$this->assertOffsets(['qq' => $normal, 'zz' => $normal + 30, 'yy' => 2 * $normal + 30], $this->offsets($html, CssMode::STANDARD));
		$this->assertOffsets(['qq' => $normal, 'zz' => 2 * $normal, 'yy' => 3 * $normal], $this->offsets($html, CssMode::LEGACY));
	}

	/**
	 * A percentage in vertical-align is of the inline element's own line-height, so 50% of 10mm raises it as 5mm does.
	 * In legacy mode it is of the block's line-height
	 */
	public function testVerticalAlignPercentageIsOfTheOwnLineHeight()
	{
		$percentage = '<p>aa <span style="line-height: 10mm; vertical-align: 50%">qq</span><br>zz</p>';
		$length = '<p>aa <span style="line-height: 10mm; vertical-align: 5mm">qq</span><br>zz</p>';
		$ofTheBlock = '<p>aa <span style="vertical-align: ' . (0.5 * $this->normalLineHeight()) . 'mm">qq</span><br>zz</p>';

		$this->assertOffsets($this->offsets($length, CssMode::STANDARD), $this->offsets($percentage, CssMode::STANDARD));
		$this->assertOffsets($this->offsets($ofTheBlock, CssMode::LEGACY), $this->offsets($percentage, CssMode::LEGACY));
	}

	/**
	 * A span with line-height: normal in a block with a smaller line-height makes its line as high as the font's
	 * normal line height. In legacy mode the line keeps the block's line-height
	 */
	public function testNormal()
	{
		$html = '<p style="line-height: 0.5">aa <span style="line-height: normal">qq</span><br>zz</p>';

		$this->assertEqualsWithDelta($this->normalLineHeight(), $this->offsets($html, CssMode::STANDARD)['zz'], 0.001);
		$this->assertOffsets(
			$this->offsets('<p style="line-height: 0.5">aa <span>qq</span><br>zz</p>', CssMode::LEGACY),
			$this->offsets($html, CssMode::LEGACY)
		);
	}

	/**
	 * A table row grows with a tall inline element in one of its cells, so what follows the table moves down by as
	 * much as the line did. In legacy mode it does not
	 */
	public function testTableRowHeight()
	{
		$tall = '<table><tr><td>aa <span style="line-height: 30mm">qq</span><br>zz</td><td>cell</td></tr></table><p>after</p>';
		$plain = '<table><tr><td>aa <span>qq</span><br>zz</td><td>cell</td></tr></table><p>after</p>';

		$standardTall = $this->offsets($tall, CssMode::STANDARD);
		$standardPlain = $this->offsets($plain, CssMode::STANDARD);
		$this->assertEqualsWithDelta($standardTall['zz'] - $standardPlain['zz'], $standardTall['after'] - $standardPlain['after'], 0.001);
		$this->assertGreaterThan(20, $standardTall['after'] - $standardPlain['after']);

		$this->assertOffsets($this->offsets($plain, CssMode::LEGACY), $this->offsets($tall, CssMode::LEGACY));
	}

	/**
	 * A line-height on a paragraph in a table cell, which mPDF lays out as inline content, gives that paragraph's lines
	 * their height in standard mode. Legacy mode ignores it
	 */
	public function testParagraphInATableCell()
	{
		$html = '<table><tr><td><p style="line-height: 10mm">aa<br>zz</p></td></tr></table>';

		$this->assertEqualsWithDelta(10.0, $this->offsets($html, CssMode::STANDARD)['zz'], 0.001);
		$this->assertOffsets(
			$this->offsets('<table><tr><td><p>aa<br>zz</p></td></tr></table>', CssMode::LEGACY),
			$this->offsets($html, CssMode::LEGACY)
		);
	}

	/**
	 * A block inside an inline element with a line-height keeps its own line-height
	 */
	public function testBlockInsideAnInlineElement()
	{
		$html = '<span style="line-height: 30mm">aa<div style="line-height: 5mm">qq<br>zz</div></span>';

		$this->assertOffsets(['qq' => 30.0, 'zz' => 35.0], $this->offsets($html, CssMode::STANDARD));
	}

	/**
	 * In justified text only the line holding the inline element grows, and the lines are still spread to the full
	 * width
	 */
	public function testJustifiedText()
	{
		$words = implode(' ', array_fill(0, 12, 'lorem ipsum'));
		$html = '<p style="text-align: justify">' . $words . ' <span style="line-height: 20mm">qq</span> ' . $words . '</p>';

		$mpdf = $this->draw($html, CssMode::STANDARD);
		$standard = $this->lineTops($mpdf);
		$legacy = $this->lineTops($this->draw($html, CssMode::LEGACY));

		$this->assertGreaterThan(2, count($standard));
		$this->assertCount(count($legacy), $standard);
		$normal = $this->normalLineHeight();
		$qqLine = array_search(round($this->keyedByText($mpdf, $mpdf->drawnY)['qq'], 3), $standard, true);
		$this->assertGreaterThan(0, $qqLine);
		for ($i = 1; $i < count($standard); $i++) {
			$this->assertEqualsWithDelta($normal, $legacy[$i] - $legacy[$i - 1], 0.001);
			$this->assertEqualsWithDelta($i - 1 === $qqLine ? 20 : $normal, $standard[$i] - $standard[$i - 1], 0.001, 'Line ' . $i);
		}

		$right = [];
		foreach ($mpdf->drawnBoxes as $box) {
			$top = (string) round($box[3], 3);
			$right[$top] = isset($right[$top]) ? max($right[$top], $box[2]) : $box[2];
		}
		array_pop($right); // The last line of justified text is not spread
		$this->assertEqualsWithDelta(max($right), min($right), 0.01);
	}

	/**
	 * A block kept together with page-break-inside: avoid is laid out again on the next page, with the same line
	 * heights
	 */
	public function testBlockThatUnwinds()
	{
		$html = '<div style="height: 250mm"></div>'
			. '<div style="page-break-inside: avoid">aa <span style="line-height: 30mm">qq</span><br>zz<br>yy</div>';

		$mpdf = $this->draw($html, CssMode::STANDARD);
		$pages = $this->keyedByText($mpdf, array_column($mpdf->drawnBoxes, 0));

		$this->assertSame(2, $pages['aa']);
		$this->assertSame(2, $pages['yy']);
		$this->assertOffsets(['zz' => 30.0, 'yy' => 30 + $this->normalLineHeight()], $this->offsetsOf($mpdf));
	}

	/**
	 * Each pair of offsets is equal
	 *
	 * @param float[] $expected
	 * @param float[] $actual
	 */
	private function assertOffsets(array $expected, array $actual)
	{
		foreach ($expected as $text => $offset) {
			$this->assertArrayHasKey($text, $actual, sprintf('"%s" is not drawn', $text));
			$this->assertEqualsWithDelta($offset, $actual[$text], 0.001, sprintf('"%s" is drawn at the wrong height', $text));
		}
	}

	/**
	 * @param string $html
	 * @param string $mode A CssMode value
	 *
	 * @return TextRecordingMpdf The document, written, with paragraphs that have no margins
	 */
	private function draw($html, $mode)
	{
		$mpdf = new TextRecordingMpdf(['cssMode' => $mode]);
		$mpdf->WriteHTML('<style>p { margin: 0 }</style>' . $html);

		return $mpdf;
	}

	/**
	 * @param string $html A document whose first line starts with "aa"
	 * @param string $mode A CssMode value
	 *
	 * @return float[] Each piece of text drawn, trimmed, and how far the top of its line is below the top of the line
	 *                 "aa" is on, in mm. A piece laid out twice, as a block that unwinds is, counts where it was drawn
	 *                 last
	 */
	private function offsets($html, $mode)
	{
		return $this->offsetsOf($this->draw($html, $mode));
	}

	/**
	 * @param TextRecordingMpdf $mpdf A document, written, whose first line starts with "aa"
	 *
	 * @return float[] As offsets() gives them
	 */
	private function offsetsOf(TextRecordingMpdf $mpdf)
	{
		$tops = $this->keyedByText($mpdf, $mpdf->drawnY);

		return array_map(function ($top) use ($tops) {
			return $top - $tops['aa'];
		}, $tops);
	}

	/**
	 * @param TextRecordingMpdf $mpdf A document, written
	 *
	 * @return float[] The top of each line drawn, rounded, in order
	 */
	private function lineTops(TextRecordingMpdf $mpdf)
	{
		return array_values(array_unique(array_map(function ($top) {
			return round($top, 3);
		}, $mpdf->drawnY)));
	}

	/**
	 * @param string $html
	 *
	 * @return string The document with the line-height taken out of the style of each span and b element
	 */
	private function withoutInlineLineHeight($html)
	{
		return preg_replace('/(<(?:span|b)\b[^>]*?)line-height:\s*[^;"]+;?\s*/', '$1', $html);
	}

	/**
	 * @return float The normal line height of the 11pt text, in mm
	 */
	private function normalLineHeight()
	{
		static $height = null;
		if ($height === null) {
			$height = $this->offsets('<p>aa<br>zz</p>', CssMode::STANDARD)['zz'];
		}

		return $height;
	}

}
