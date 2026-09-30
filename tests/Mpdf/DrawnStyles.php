<?php

namespace Mpdf;

/**
 * Writes a document through TextRecordingMpdf, to read the colour and style each piece of text is drawn in, or
 * matches a selector against the elements a document leaves open
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
}
