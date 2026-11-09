<?php

namespace Snapshots;

/**
 * Bytes that are not UTF-8 are drawn as question marks, and the text around them is kept
 *
 * @group snapshot
 */
class InvalidUtf8SnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'invalid-utf8';
	}

	/**
	 * Draws each kind of bad bytes under a caption, in HTML and through the text calls
	 *
	 * @return void
	 */
	public function generatePdf()
	{
		$this->mpdf = new \Mpdf\Mpdf();

		$this->mpdf->WriteHTML(
			'<h2>Invalid UTF-8</h2>'
			. '<p>Each line below should read "before", then question marks, then "after".</p>'
			. "<p>A character missing its last byte: before \xE2\x82 after</p>"
			. "<p>A stray continuation byte: before \x80 after</p>"
			. "<p>A lead byte past the end of Unicode: before \xF5 after</p>"
			. "<p>A valid character is kept: caf\xC3\xA9 before \xE2 after</p>"
			. "<p>This line should end in a question mark, as the HTML is cut off: \xE2"
		);

		$this->mpdf->MultiCell(0, 8, "Text drawn with MultiCell: before \xE2 after");
		$this->mpdf->WriteCell(0, 8, "Text drawn with WriteCell: before \xE2 after");
	}

}
