<?php

namespace Snapshots;

/**
 * A text field drops the linefeeds in its value, as a browser does, while a textarea keeps its lines
 *
 * @group snapshot
 */
class TextFieldLinefeedSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'text-field-linefeed';
	}

	/**
	 * Each case under a caption saying what it should show
	 */
	public function generatePdf()
	{
		$html = '<style>
				body { font-size: 10pt; }
				h1 { font-size: 13pt; margin: 0 0 2mm 0; }
				p.caption { font-size: 8pt; color: #666666; margin: 3mm 0 1mm 0; }
				p { margin: 0; }
				input, textarea { width: 70mm; font-family: courier; }
			</style>
			<h1>A linefeed in a text field\'s value</h1>

			<p class="caption">A text field whose value is written over two lines: "firstsecond", with no space between the words.</p>
			<p><input type="text" value="first
second"></p>

			<p class="caption">A search field with a linefeed written as "&amp;#10;": "onetwo", with no space.</p>
			<p><input type="search" value="one&#10;two"></p>

			<p class="caption">A password field whose value is "ab", then a linefeed, then "cd": four stars.</p>
			<p><input type="password" value="ab
cd"></p>

			<p class="caption">A textarea keeping its two lines, "first" above "second".</p>
			<p><textarea rows="2">first
second</textarea></p>';

		$this->mpdf = $this->createMpdf();

		$this->mpdf->WriteHTML($html);
	}

}
