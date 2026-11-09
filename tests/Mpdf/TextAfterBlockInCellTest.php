<?php

namespace Mpdf;

/**
 * The line break a table cell saves for text that follows a block must leave the text its own OTL run, and a chunk
 * that falls back to the kern table must not turn it on for the text after it
 */
class TextAfterBlockInCellTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;

	/**
	 * The operators that draw text in the first page of a document, without their positions
	 *
	 * @param string $html
	 *
	 * @return string[]
	 */
	private function textOperators($html)
	{
		$pages = $this->pageContents($this->render($html, [
			'mode' => '',
			'useKerning' => true,
			'autoLangToFont' => false,
			'autoScriptToLang' => false,
			'shrink_tables_to_fit' => 1,
		]));
		preg_match_all('/BT [\d.\-]+ [\d.\-]+ Td(.*?)ET/s', $pages[0], $matches);

		return $matches[1];
	}

	/**
	 * A cell of the given content in the given font
	 *
	 * @param string $font
	 * @param string $content
	 * @param string $attr
	 *
	 * @return string
	 */
	private function cell($font, $content, $attr)
	{
		return '<table ' . $attr . ' style="width:100%;font-kerning:normal;font-family:' . $font . '"><tr><td>' . $content . '</td></tr></table>';
	}

	/**
	 * Shaped text in each script that shows what the run carries
	 *
	 * @return array
	 */
	public function samples()
	{
		$arabic = '&#x628;&#x650;&#x633;&#x652;&#x645;&#x650; &#x627;&#x644;&#x644;&#x651;&#x64E;&#x647;&#x650;';

		return [
			'kerning, kern table and GPOS' => ['dejavusans', 'AVAVA To Wa', ''],
			'Latin marks' => ['dejavusans', 'x&#x323;y&#x301;q&#x323;', ''],
			'Arabic marks, rtl' => ['dejavusans', $arabic, 'dir="rtl"'],
			'Arabic glyphs with no Unicode of their own, ltr' => ['xbriyaz', $arabic, ''],
			'Arabic glyphs with no Unicode of their own, rtl' => ['xbriyaz', $arabic, 'dir="rtl"'],
			'Thai marks' => ['garuda', '&#xE17;&#xE35;&#xE48;&#xE19;&#xE35;&#xE48; &#xE19;&#xE49;&#xE33;', ''],
			'Devanagari' => ['freeserif', '&#x915;&#x94D;&#x937;&#x93F; &#x930;&#x94D;&#x915;&#x93F; &#x926;&#x941;', ''],
		];
	}

	/**
	 * The text after the block draws as the same text in a cell of its own
	 *
	 * @dataProvider samples
	 *
	 * @param string $font
	 * @param string $text
	 * @param string $attr
	 */
	public function testTextAfterABlockKeepsItsRun($font, $text, $attr)
	{
		$alone = $this->textOperators($this->cell($font, $text, $attr));
		$afterBlock = $this->textOperators($this->cell($font, '<div>Blk</div>' . $text, $attr));

		$this->assertCount(1, $alone);
		$this->assertCount(2, $afterBlock);
		$this->assertSame($alone[0], $afterBlock[1]);
	}

	/**
	 * Neither text with no kerning of its own nor the line break after a block turns the kern table on for the text
	 * after it, on top of that text's GPOS
	 *
	 * @dataProvider kerningFallbacks
	 *
	 * @param string $earlier
	 * @param string $kerned
	 */
	public function testKerningFallbackStaysWithItsOwnText($earlier, $kerned)
	{
		$alone = $this->textOperators(sprintf($kerned, ''));
		$afterText = $this->textOperators(sprintf($kerned, $earlier));

		$this->assertSame(end($alone), end($afterText));
	}

	/**
	 * Text that takes the kern table, and a document where text with GPOS kerning follows it
	 *
	 * @return array
	 */
	public function kerningFallbacks()
	{
		return [
			'paragraph, no table' => ['Blk ', '<p style="font-family:dejavusans;font-kerning:normal">%s<span>AVAVA To Wa</span></p>'],
			'table cell, a br between' => ['Blk<br>', '<table style="font-kerning:normal;font-family:dejavusans"><tr><td>%s<span>AVAVA To Wa</span></td></tr></table>'],
			'table cell, a block before' => ['<div>Blk</div>', '<table style="font-kerning:normal;font-family:dejavusans"><tr><td>%sAVAVA To Wa<span> AVAVA To Wa</span></td></tr></table>'],
		];
	}
}
