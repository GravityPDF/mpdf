<?php

namespace Mpdf;

/**
 * A tag written inside a quoted attribute value is part of the value: removing comments and scripts, and rewriting
 * tags before the document is read, leave it and the text after it alone
 */
class TagInAttributeValueTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use DrawnStyles;

	/**
	 * Each CSS mode, keeping headings and captions with their tables
	 *
	 * @return array[]
	 */
	public static function modes()
	{
		return [
			'standard' => [['cssMode' => CssMode::STANDARD, 'use_kwt' => true]],
			'legacy' => [['cssMode' => CssMode::LEGACY, 'use_kwt' => true]],
		];
	}

	/**
	 * A comment opener or a script tag in a title, before a real comment or script
	 *
	 * @return array[]
	 */
	public static function hidingTitles()
	{
		$cases = [
			'a comment opener' => ['<p title="a <!-- b">Before</p><p>Middle</p><!-- real --><p>After</p>'],
			'a script tag' => ['<p title="see <script> tag">Before</p><p>Middle</p><script>x()</script><p>After</p>'],
		];

		$data = [];
		foreach (self::modes() as $mode => $config) {
			foreach ($cases as $case => $html) {
				$data[$case . ', ' . $mode] = [$html[0], $config[0]];
			}
		}

		return $data;
	}

	/**
	 * The text after the title is drawn, and the comment and script are not
	 *
	 * @dataProvider hidingTitles
	 *
	 * @param string $html
	 * @param array $config
	 */
	public function testTheTextAfterTheTitleIsDrawn($html, array $config)
	{
		$mpdf = $this->drawDocument($html, $config);

		$this->assertSame(['Before', 'Middle', 'After'], array_map('trim', $mpdf->drawnText));
	}

	/**
	 * The values of a text field holding a comment, or a tag that is rewritten before the document is read where it is
	 * a real one
	 *
	 * @return array[]
	 */
	public static function fieldValues()
	{
		$values = [
			'a comment' => 'a <!-- b --> c',
			'mPDF comment markers' => '<!--mpdf b mpdf-->',
			'a script' => '<script>x()</script>',
			'br' => 'a<br>b',
			'br, closed' => 'a<br/>b',
			'br, closed with a space' => 'a<br /> b',
			'hr' => 'a<hr>b',
			'wbr' => 'x<wbr>y',
			'dottab' => 'a <dottab> b',
			'symbol runs' => '</tts><tts>',
			'a substituted no-break space' => '<tta>160</tta>',
			'an empty row' => '<tr></tr>',
			'an empty thead' => '<thead> </thead>',
			'an empty tfoot' => '<tfoot></tfoot>',
			'an empty table' => '<table></table>',
			'a space before a cell end' => 'a </td>',
			'an empty textarea' => '<textarea></textarea>',
			'a table with a caption' => '<table><caption>c</caption></table>',
			'a heading before a table' => '<h2>h</h2><table',
			'spaces around list items' => '</li> <li> </ul> </li>',
			'spaces around a list' => 'a <ul> b',
		];

		$data = [];
		foreach (self::modes() as $mode => $config) {
			foreach ($values as $case => $value) {
				$data[$case . ', ' . $mode] = [$value, $config[0]];
			}
		}

		return $data;
	}

	/**
	 * The field shows its value as it was written, and the text after it is drawn
	 *
	 * @dataProvider fieldValues
	 *
	 * @param string $value
	 * @param array $config
	 */
	public function testAFieldValueKeepsTheTagWrittenInIt($value, array $config)
	{
		$mpdf = $this->drawDocument('<p><input type="text" size="40" value="' . $value . '"></p><p>After</p>', $config);

		$this->assertSame([$value, 'After'], array_map('trim', $mpdf->drawnText));
	}

}
