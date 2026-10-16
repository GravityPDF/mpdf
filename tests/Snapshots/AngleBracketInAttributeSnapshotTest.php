<?php

namespace Snapshots;

/**
 * A "<" or ">" inside a quoted attribute value is part of the value and does not end the tag, and a quote that does
 * not follow "=" is an ordinary character. Drawn with autoScriptToLang on, where such a value used to leave the whole
 * page blank.
 *
 * @group snapshot
 */
class AngleBracketInAttributeSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'angle-bracket-in-attribute';
	}

	/**
	 * The configuration the document is drawn with
	 *
	 * @return array
	 */
	protected function config()
	{
		return ['autoScriptToLang' => true, 'autoLangToFont' => true];
	}

	/**
	 * What the heading says of the configuration
	 *
	 * @return string
	 */
	protected function configName()
	{
		return 'autoScriptToLang on';
	}

	/**
	 * The cases in sections by heading, each a caption saying what should be drawn and the HTML that draws it. The
	 * Hebrew word is left out where no font is picked to draw it
	 *
	 * @return array
	 */
	protected function sections()
	{
		$config = $this->config();
		$hebrew = empty($config['autoLangToFont']) ? ['', ''] : [', the Hebrew word too', ' שלום'];

		return [
			'In a style' => [
				['A ">" in a double-quoted font name: the next line is blue.', '<p style="font-family: \'a>b\', serif; color: blue">Blue text</p>'],
				['A ">" in a single-quoted style: the next line is red.', '<p style=\'font-family: "a>b", serif; color: red\'>Red text</p>'],
				['A "<" and a ">" in a style on a span: "red" in the next line is red and bold, the rest black.', '<p>Before <span style="font-family: \'a<b>c\'; color: red; font-weight: bold">red</span> after</p>'],
				['A ">" in the style of a table cell: one cell with a yellow background, reading "Cell".', '<table><tr><td style="background: yellow; font-family: \'a>b\'; padding: 1mm">Cell</td></tr></table>'],
			],
			'In other attributes' => [
				['A ">" in a title before the style: the next line is blue' . $hebrew[0] . '.', '<p title="1 > 0" style="color: blue">Blue text' . $hebrew[1] . '</p>'],
				['A "<" in a title before the style: the next line is green.', '<p title="a<b" style="color: green">Green text</p>'],
				['A ">" and a "<" in the value of a text field: both boxes read "a > b < c".', '<p><input type="text" size="16" value="a > b < c"> and, escaped, <input type="text" size="16" value="a &gt; b &lt; c"></p>'],
				['An unknown tag with a ">" in its attributes is dropped whole: the next line reads "before inside after" and nothing else.', '<p>before <custom data-x="a>b" data-y=\'c>d\'>inside</custom> after</p>'],
			],
			'Quotes that do not open a value' => [
				['An unquoted title with an apostrophe: the next line is blue, and the line after it is drawn.', '<p title=don\'t style="color: blue">Blue text</p><p>The line after</p>'],
				['A stray quote after an unquoted value: "bold" is bold, "italic" italic and "underlined" underlined.', '<p><b class=x">bold</b>, <i>italic</i> and <u>"underlined"</u></p>'],
			],
		];
	}

	/**
	 * Each case under a caption saying what it should show, in sections, then a closing line showing the document goes
	 * on after them
	 */
	public function generatePdf()
	{
		$html = '<style>
			body { font-size: 10pt; }
			h1 { font-size: 13pt; margin: 0 0 2mm 0; }
			h2 { font-size: 11pt; margin: 5mm 0 0 0; }
			p.caption { font-size: 8pt; color: #666666; margin: 3mm 0 1mm 0; }
			p { margin: 0; }
		</style>
		<h1>Angle brackets and quotes inside attribute values, ' . $this->configName() . '</h1>';

		foreach ($this->sections() as $heading => $cases) {
			$html .= '<h2>' . $heading . '</h2>';
			foreach ($cases as $case) {
				$html .= '<p class="caption">' . $case[0] . '</p>' . $case[1];
			}
		}

		$html .= '<p class="caption">The document goes on after them: this caption and the next line are drawn.</p><p>The end</p>';

		$this->mpdf = $this->createMpdf($this->config());

		$this->mpdf->WriteHTML($html);
	}

}
