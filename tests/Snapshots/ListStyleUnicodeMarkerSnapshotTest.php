<?php

namespace Snapshots;

/**
 * A U+XXXX marker set by the list-style shorthand draws that character before each item, as the list-style-type
 * longhand does, rather than numbering the list.
 *
 * @group snapshot
 */
class ListStyleUnicodeMarkerSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'list-style-unicode-marker';
	}

	/**
	 * The list styles, each with a caption saying which marker its items should have
	 *
	 * @return array
	 */
	protected function cases()
	{
		return [
			['list-style-type: U+2022', 'list-style-type: U+2022 (the longhand, for comparison): a bullet before each item.'],
			['list-style: U+2022', 'list-style: U+2022: a bullet before each item, the same as above.'],
			['list-style: u+25ba', 'list-style: u+25ba: a right-pointing triangle before each item.'],
			['list-style: U+2013 inside', 'list-style: U+2013 inside: an en dash before each item, inside the item\'s box.'],
		];
	}

	/**
	 * A two-item list for each list style, under a caption saying which marker it should have
	 */
	public function generatePdf()
	{
		$html = '<style>
			body { font-size: 10pt; }
			h1 { font-size: 13pt; margin: 0 0 2mm 0; }
			p { margin: 4mm 0 1mm 0; }
			ul { margin: 0; border: 0.2mm solid #999999; }
		</style>
		<h1>list-style with a U+ marker</h1>';

		foreach ($this->cases() as $case) {
			$html .= '<p>' . $case[1] . '</p><ul style="' . $case[0] . '"><li>One</li><li>Two</li></ul>';
		}

		$this->mpdf = $this->createMpdf();

		$this->mpdf->WriteHTML($html);
	}

}
