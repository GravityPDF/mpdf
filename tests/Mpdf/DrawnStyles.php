<?php

namespace Mpdf;

/**
 * Writes a document through TextRecordingMpdf, to read the colour and size each piece of text is drawn in
 */
trait DrawnStyles
{

	/**
	 * Writes a document in core-font mode, recording each piece of text Cell() draws
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
