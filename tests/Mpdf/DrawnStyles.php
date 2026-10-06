<?php

namespace Mpdf;

/**
 * Writes a document through TextRecordingMpdf, to read the colour and style each piece of text is drawn in, or where
 * on the page it lies, or matches a selector against the elements a document leaves open
 */
trait DrawnStyles
{

	/**
	 * Writes a document in core-font mode, recording each piece of text Cell() draws with its colour and font style
	 *
	 * @param string $html
	 * @param array $config Merged over the core-font mode
	 *
	 * @return TextRecordingMpdf The document, written, with what Cell() drew recorded
	 */
	private function drawDocument($html, array $config = [])
	{
		$mpdf = new TextRecordingMpdf($config + ['mode' => 'c']);
		$mpdf->WriteHTML($html);

		return $mpdf;
	}

	/**
	 * The colour each piece of text in a document is drawn in, keyed by the text
	 *
	 * @param string $html
	 * @param array $config Merged over the core-font mode
	 *
	 * @return array<string, string> Each piece of text drawn, trimmed, and the operator that set its colour
	 */
	private function drawnColours($html, array $config = [])
	{
		$mpdf = $this->drawDocument($html, $config);

		return $this->keyedByText($mpdf, $mpdf->drawnColours);
	}

	/**
	 * Each piece of text named is drawn, in the colour given for it
	 *
	 * @param array<string, string> $expected Pieces of text and the operator that should set each one's colour
	 * @param array<string, string> $colours What was drawn, as drawnColours() gives it
	 */
	private function assertDrawnInColours(array $expected, array $colours)
	{
		foreach ($expected as $text => $colour) {
			$this->assertArrayHasKey($text, $colours, sprintf('"%s" is not drawn', $text));
			$this->assertSame($colour, $colours[$text], sprintf('"%s" is drawn in the wrong colour', $text));
		}
	}

	/**
	 * HTML in one of the places an element is styled in apart from the flow of a page
	 *
	 * @param string $context table cell, header, footer, positioned block, kept block (a page-break-inside: avoid
	 *                        block laid out again on the next page), forced page break (after one, inside a block), or
	 *                        anything else for the flow
	 * @param string $html
	 *
	 * @return string
	 */
	private function inContext($context, $html)
	{
		switch ($context) {
			case 'table cell':
				return '<table><tr><td>' . $html . '</td></tr></table>';

			case 'header':
				return '<htmlpageheader name="h">' . $html . '</htmlpageheader><sethtmlpageheader name="h" value="on" show-this-page="1" /><p>body</p>';

			case 'footer':
				return '<htmlpagefooter name="f">' . $html . '</htmlpagefooter><sethtmlpagefooter name="f" value="on" /><p>body</p>';

			case 'positioned block':
				return '<div style="position: absolute; top: 60mm; left: 20mm; width: 150mm;">' . $html . '</div>';

			case 'kept block':
				// The block starts on the first page, runs over it, and is put back and laid out again on the second
				return '<div style="height: 200mm">filler</div><div style="page-break-inside: avoid"><p>kept</p>' . $html
					. '<div style="height: 60mm"></div></div>';

			case 'forced page break':
				return '<div class="w"><p>before the break</p><pagebreak />' . $html . '</div>';

			default:
				return $html;
		}
	}

	/**
	 * Pieces of text put in a context by inContext() are drawn where it puts them, so that a context cannot quietly
	 * stop testing what it is named for: in the header, the footer or the positioned block, and on the second page
	 * after the forced page break or once the kept block has been put back and laid out again
	 *
	 * @param string $context As inContext() takes it
	 * @param TextRecordingMpdf $mpdf The document, written and closed
	 * @param string[] $texts The pieces of text
	 */
	private function assertDrawnInContext($context, TextRecordingMpdf $mpdf, array $texts)
	{
		$this->assertSame($context === 'kept block' ? 1 : 0, $mpdf->unwinds, 'How often a kept block is put back');

		$boxes = $this->keyedByText($mpdf, $mpdf->drawnBoxes);
		$drawnIn = $this->keyedByText($mpdf, $mpdf->drawnIn);
		$where = ['header' => 'header', 'footer' => 'footer', 'positioned block' => 'positioned'];
		foreach ($texts as $text) {
			$this->assertSame(isset($where[$context]) ? $where[$context] : '', $drawnIn[$text], $text);
			$this->assertSame(in_array($context, ['kept block', 'forced page break'], true) ? 2 : 1, $boxes[$text][0], $text);
		}
	}

	/**
	 * Everything a document draws, for comparing two documents that should draw the same: the content stream of
	 * each page, and each piece of text with its colour, font size, font style and box. The sizes and boxes are
	 * rounded, as a table keeps its font size in millimetres, which comes back a few units in the last place off
	 *
	 * @param string $html
	 * @param array $config Merged over the core-font mode
	 *
	 * @return array
	 */
	private function drawnPages($html, array $config = [])
	{
		$mpdf = $this->drawDocument($html, $config);
		$mpdf->SetCompression(false);

		preg_match_all('/\d+ 0 obj\s*<<\/Length \d+>>\s*stream\n(.*?)\nendstream/s', $mpdf->Output('', 'S'), $streams);

		$round = function ($number) {
			return round($number, 6);
		};

		return [
			$streams[1],
			$mpdf->drawnText,
			$mpdf->drawnColours,
			array_map($round, $mpdf->drawnFontSize),
			$mpdf->drawnFontStyles,
			array_map(function ($box) use ($round) {
				return array_map($round, $box);
			}, $mpdf->drawnBoxes),
		];
	}

	/**
	 * Splits cases into one for each CSS mode
	 *
	 * @param array[] $rows Each case's arguments, ending with what it expects in the standard mode and in the legacy mode
	 *
	 * @return array[] Each case's arguments, led by the mode and ending with what it expects in that mode
	 */
	private function bothModes(array $rows)
	{
		$cases = [];
		foreach ($rows as $name => $row) {
			$legacy = array_pop($row);
			$standard = array_pop($row);
			$cases[$name . ' (standard)'] = array_merge([CssMode::STANDARD], $row, [$standard]);
			$cases[$name . ' (legacy)'] = array_merge([CssMode::LEGACY], $row, [$legacy]);
		}

		return $cases;
	}

	/**
	 * Each CSS mode, for a test that expects the same of both
	 *
	 * @return array[] Each mode, keyed by its name
	 */
	public function modes()
	{
		return [CssMode::STANDARD => [CssMode::STANDARD], CssMode::LEGACY => [CssMode::LEGACY]];
	}

	/**
	 * Keys what was recorded of each piece of text a document drew by the text itself, so a test can look a piece up
	 * by what it says
	 *
	 * @param TextRecordingMpdf $mpdf A document, written
	 * @param array $values One value for each piece of text drawn, in the order they were drawn
	 *
	 * @return array Each value, keyed by its piece of text, trimmed
	 */
	private function keyedByText(TextRecordingMpdf $mpdf, array $values)
	{
		return array_combine(array_map('trim', $mpdf->drawnText), $values);
	}

	/**
	 * Writes HTML without closing what it leaves open, and matches a selector against the element open last. This
	 * reaches elements that are never drawn, such as those inside one hidden with display: none
	 *
	 * @param string $html
	 * @param string $selector
	 *
	 * @return bool Whether the selector matches the innermost open element
	 */
	private function matchesLastOpenElement($html, $selector)
	{
		$mpdf = new Mpdf(['mode' => 'c']);
		$mpdf->WriteHTML($html, HTMLParserMode::DEFAULT_MODE, true, false);

		$compiler = new Css\SelectorCompiler($mpdf);
		$matcher = new Css\SelectorMatcher();

		return $matcher->matches($compiler->compile($selector), $mpdf->getOpenElements());
	}

	/**
	 * Justified paragraphs of text
	 *
	 * @param int $paragraphs
	 *
	 * @return string
	 */
	private function story($paragraphs)
	{
		return str_repeat('<p>' . str_repeat('Lorem ipsum dolor sit amet, consectetur adipiscing elit. ', 8) . '</p>', $paragraphs);
	}

	/**
	 * The narrowest left edge and widest right edge of the boxes under each key, rounded to a tenth of a millimetre
	 *
	 * @param array[] $boxes Each a key, such as the page, then a left and right edge, as TextRecordingMpdf records them
	 *
	 * @return array[] By key, in the order the keys first appear
	 */
	private function spans(array $boxes)
	{
		$spans = [];
		foreach ($boxes as $box) {
			list($key, $left, $right) = $box;
			$spans[$key] = isset($spans[$key]) ? [min($spans[$key][0], $left), max($spans[$key][1], $right)] : [$left, $right];
		}

		return array_map(static function ($span) {
			return [round($span[0], 1), round($span[1], 1)];
		}, $spans);
	}

	/**
	 * The top of the first line drawn on each page, rounded to a tenth of a millimetre
	 *
	 * @param array[] $boxes As TextRecordingMpdf records them
	 *
	 * @return float[] By page number, in the order the pages were drawn
	 */
	private function firstLineTops(array $boxes)
	{
		$tops = [];
		foreach ($boxes as $box) {
			if (!isset($tops[$box[0]])) {
				$tops[$box[0]] = round($box[3], 1);
			}
		}

		return $tops;
	}
}
