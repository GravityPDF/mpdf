<?php

namespace Snapshots;

/**
 * A quoted attribute value holding " name=value", or an unquoted one holding an apostrophe, is read whole. Text fields
 * show the values they were given, and selectors matching whole values colour their elements green
 *
 * @group snapshot
 */
class AttributeValueSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'attribute-value';
	}

	/**
	 * The cases in sections by heading, each a caption saying what should be drawn and the HTML that draws it
	 *
	 * @return array
	 */
	private function sections()
	{
		return [
			'In a text field' => [
				['A " name=value" in the value: the box reads "Set x=1 first".', '<p><input type="text" size="30" value="Set x=1 first"></p>'],
				['A " src=" in the value: the box reads "see src=a.png now".', '<p><input type="text" size="30" value="see src=a.png now"></p>'],
				['A single-quoted value after "=" in the value: the box reads "a=\'b\' c".', '<p><input type="text" size="30" value="a=\'b\' c"></p>'],
			],
			'Matched by a selector' => [
				['A title holding " name=value": the next line is green.', '<p title="Set x=1 first">Green text</p>'],
				['A data attribute holding two of them: the next line is green.', '<p data-size="width=10 height=20">Green text</p>'],
				['An unquoted title with an apostrophe: the next line is green.', '<p title=don\'t>Green text</p>'],
				['A link whose title holds " href=", then the same link without it: both links are green and underlined.', '<p><a title="see href=x now" href="https://example.com/a">With the title</a> and <a href="https://example.com/a">without it</a></p>'],
			],
		];
	}

	/**
	 * Each case under a caption saying what it should show, in sections
	 */
	public function generatePdf()
	{
		$html = '<style>
			body { font-size: 10pt; }
			h1 { font-size: 13pt; margin: 0 0 2mm 0; }
			h2 { font-size: 11pt; margin: 5mm 0 0 0; }
			p.caption { font-size: 8pt; color: #666666; margin: 3mm 0 1mm 0; }
			p { margin: 0; }
			p[title="Set x=1 first"], p[data-size="width=10 height=20"], p[title="don\'t"], a[href="https://example.com/a"] { color: green; }
		</style>
		<h1>Attribute values holding "name=value"</h1>';

		foreach ($this->sections() as $heading => $cases) {
			$html .= '<h2>' . $heading . '</h2>';
			foreach ($cases as $case) {
				$html .= '<p class="caption">' . $case[0] . '</p>' . $case[1];
			}
		}

		$this->mpdf = $this->createMpdf();

		$this->mpdf->WriteHTML($html);
	}

}
