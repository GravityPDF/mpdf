<?php

namespace Mpdf;

/**
 * Writes a document through TextRecordingMpdf, to read the colour and style each piece of text is drawn in
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
}
