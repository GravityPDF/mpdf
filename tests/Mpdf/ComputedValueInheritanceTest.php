<?php

namespace Mpdf;

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Under the standard CSS mode a descendant inherits its parent's computed values: a relative font size is taken of the
 * parent's size, and a length given in em is handed on as the length it came to, not re-read against the
 * descendant's own font size. The same goes for inherit on a property that is not inherited. Under legacy an
 * inherited em length is read again against the descendant's size, and inherit is dropped
 */
class ComputedValueInheritanceTest extends TestCase
{

	use DrawnStyles;

	/**
	 * A font size relative to the parent's is taken of the parent's computed size, three deep, in every context
	 *
	 * @dataProvider fontSizeChains
	 *
	 * @param string $mode
	 * @param string $context
	 * @param string $html
	 * @param float[] $expected The size, in points, each piece of text is drawn at
	 */
	public function testARelativeFontSizeIsTakenOfTheParentsComputedSize($mode, $context, $html, array $expected)
	{
		$mpdf = $this->drawDocument($this->inContext($context, $html), ['cssMode' => $mode]);
		$mpdf->Close();

		$sizes = $this->keyedByText($mpdf, $mpdf->drawnFontSize);
		foreach ($expected as $text => $size) {
			$this->assertArrayHasKey($text, $sizes, $text . ' is drawn');
			$this->assertEqualsWithDelta($size, $sizes[$text], 0.001, $text);
		}
		$this->assertDrawnInContext($context, $mpdf, array_keys($expected));
	}

	/**
	 * Chains of em and % font sizes, three deep, through blocks, inline elements, a table, its row group and row, and a
	 * table nested in a cell, each in every context under both modes. Legacy does not read a row's or row group's
	 * size, and starts a table from the document's size
	 *
	 * @return array[]
	 */
	public function fontSizeChains()
	{
		$chains = [
			'blocks and inline elements' => [
				'<div style="font-size: 20pt"><div style="font-size: 1.5em"><p style="font-size: 50%">aa'
				. '<span style="font-size: 2em">bb<span style="font-size: 150%">cc</span></span></p></div></div>',
				['aa' => 15, 'bb' => 30, 'cc' => 45],
				['aa' => 15, 'bb' => 30, 'cc' => 45],
			],
			'a table in a block' => [
				'<div style="font-size: 20pt"><table style="font-size: 1.5em"><tr><td style="font-size: 50%">aa'
				. '<span style="font-size: 2em">bb</span></td></tr></table></div>',
				['aa' => 15, 'bb' => 30],
				['aa' => 8.25, 'bb' => 16.5],
			],
			'a row group and a row' => [
				'<table style="font-size: 20pt"><tbody style="font-size: 150%"><tr style="font-size: 50%">'
				. '<td style="font-size: 2em">aa<span style="font-size: 50%">bb</span></td></tr></tbody></table>',
				['aa' => 30, 'bb' => 15],
				['aa' => 40, 'bb' => 20],
			],
			'a table nested in a cell' => [
				'<table><tr><td style="font-size: 20pt"><table style="font-size: 150%"><tr>'
				. '<td style="font-size: 0.5em">aa<span style="font-size: 2em">bb</span></td></tr></table></td></tr></table>',
				['aa' => 15, 'bb' => 30],
				['aa' => 8.25, 'bb' => 16.5],
			],
		];

		$data = [];
		foreach (['flow', 'table cell', 'header', 'footer', 'positioned block', 'kept block', 'forced page break'] as $context) {
			foreach ($chains as $name => $chain) {
				// A table in a table cell is a nested table, whose own cases are above
				if ($context === 'table cell' && $name !== 'blocks and inline elements') {
					continue;
				}
				$data[CssMode::STANDARD . ': ' . $name . ' in ' . $context] = [CssMode::STANDARD, $context, $chain[0], $chain[1]];
				$data[CssMode::LEGACY . ': ' . $name . ' in ' . $context] = [CssMode::LEGACY, $context, $chain[0], $chain[2]];
			}
		}

		return $data;
	}

	/**
	 * A spacing or indent given in em on an ancestor is handed on as the length it came to at the ancestor's size,
	 * so the descendant is drawn as if it had set that length itself. Under legacy the descendant reads the em again
	 * at its own size
	 *
	 * @dataProvider emLengths
	 *
	 * @param string $mode
	 * @param string $html With {A} for the ancestor's declaration and {D} for the descendant's
	 * @param string $declaration In em, on the ancestor, whose font size is 20pt
	 * @param string $computed The same length at 20pt, which the descendant should be drawn with
	 * @param string $reread The same length at the descendant's 10pt
	 */
	public function testAnEmLengthIsInheritedAsTheLengthItCameTo($mode, $html, $declaration, $computed, $reread)
	{
		// An indent moves where the line starts, and spacing makes it wider
		$measure = strpos($declaration, 'text-indent') === 0 ? 0 : 1;
		$inherited = $this->firstLine($mode, strtr($html, ['{A}' => $declaration, '{D}' => '']))[$measure];
		$expected = $this->firstLine($mode, strtr($html, ['{A}' => '', '{D}' => $mode === CssMode::STANDARD ? $computed : $reread]))[$measure];
		$none = $this->firstLine($mode, strtr($html, ['{A}' => '', '{D}' => '']))[$measure];

		$this->assertNotEquals($none, $expected, 'The length does not change how the line is drawn');
		$this->assertEqualsWithDelta($expected, $inherited, 0.002);
	}

	/**
	 * Letter spacing, word spacing and text indent in em, from a block, a list, a cell, a positioned block and a block
	 * laid out again after it is kept together, under both modes
	 *
	 * @return array[]
	 */
	public function emLengths()
	{
		$lengths = [
			'letter-spacing' => ['letter-spacing: 0.1em', 'letter-spacing: 2pt', 'letter-spacing: 1pt'],
			'word-spacing' => ['word-spacing: 1em', 'word-spacing: 20pt', 'word-spacing: 10pt'],
			// mPDF has always handed a block's text indent on as the length it came to
			'text-indent' => ['text-indent: 2em', 'text-indent: 40pt', 'text-indent: 40pt'],
		];
		$contexts = [
			'block to block' => '<div style="font-size: 20pt; {A}"><p style="font-size: 10pt; {D}">qq qq</p></div>',
			'block to inline' => '<p style="font-size: 20pt; {A}">zz <span style="font-size: 10pt; {D}">qq qq</span></p>',
			'inline to inline' => '<p>zz <span style="font-size: 20pt; {A}">yy <b style="font-size: 10pt; {D}">qq qq</b></span></p>',
			'list to item' => '<ul style="font-size: 20pt; {A}"><li style="font-size: 10pt; {D}">qq qq</li></ul>',
			'cell to block' => '<table><tr><td style="font-size: 20pt; {A}"><div style="font-size: 10pt; {D}">qq qq</div></td></tr></table>',
			'positioned block to block' => '<div style="position: absolute; top: 50mm; left: 20mm; width: 100mm; font-size: 20pt; {A}">'
				. '<p style="font-size: 10pt; {D}">qq qq</p></div>',
			'block to table cell' => '<div style="font-size: 20pt; {A}"><table><tr><td style="width: 100mm; font-size: 10pt; {D}">qq qq</td></tr></table></div>',
		];

		$data = [];
		foreach ([CssMode::STANDARD, CssMode::LEGACY] as $mode) {
			foreach ($contexts as $context => $html) {
				foreach ($lengths as $property => $values) {
					// mPDF lays out a block in a cell as the cell's own content, which is not indented, and an inline
					// element's text is indented by the block its line is in
					if ($property === 'text-indent' && (strpos($html, '<td') !== false || strpos($context, 'inline') !== false)) {
						continue;
					}
					// Legacy hands a table nothing from the block it is in
					if ($mode === CssMode::LEGACY && $context === 'block to table cell') {
						continue;
					}
					// Legacy's positioned block hands its content no word spacing
					if ($mode === CssMode::LEGACY && $context === 'positioned block to block' && $property === 'word-spacing') {
						continue;
					}
					$data[$mode . ': ' . $context . ': ' . $property] = array_merge([$mode, $html], $values);
				}
			}
		}

		return $data;
	}

	/**
	 * inherit on a length that is not inherited takes the length the parent's em came to, not the em read again at
	 * the element's own size. Legacy does not read inherit on padding at all
	 *
	 * @dataProvider modes
	 *
	 * @param string $mode
	 */
	public function testInheritTakesTheLengthAnEmCameToOnTheParent($mode)
	{
		$html = '<div style="font-size: 20pt; padding-left: {A}"><p style="font-size: 10pt; padding-left: {D}">qq qq</p></div>';

		// Where the line starts
		$inherited = $this->firstLine($mode, strtr($html, ['{A}' => '1em', '{D}' => 'inherit']))[0];
		$computed = $this->firstLine($mode, strtr($html, ['{A}' => '1em', '{D}' => '20pt']))[0];
		$dropped = $this->firstLine($mode, strtr($html, ['{A}' => '1em', '{D}' => '0']))[0];

		$this->assertNotEquals($computed, $dropped);
		$this->assertEquals($mode === CssMode::STANDARD ? $computed : $dropped, $inherited);
	}

	/**
	 * The content of a positioned block draws line-height: normal as a paragraph outside one does. Legacy draws it at
	 * the fixed 1.33 the block's channel handed on
	 *
	 * @dataProvider modes
	 *
	 * @param string $mode
	 */
	public function testThePositionedBlocksContentDrawsANormalLineHeight($mode)
	{
		$positioned = $this->lineGap($mode, '<div style="position: absolute; top: 50mm; left: 20mm; width: 100mm"><p>qq qq<br>ww ww</p></div>');
		$flow = $this->lineGap($mode, '<p>qq qq<br>ww ww</p>');
		$fixed = $this->lineGap($mode, '<p style="line-height: 1.33">qq qq<br>ww ww</p>');

		$this->assertNotEquals($flow, $fixed, 'normal is not drawn at 1.33 outside a positioned block');
		$this->assertEquals($mode === CssMode::STANDARD ? $flow : $fixed, $positioned);
	}

	/**
	 * A block ends the paragraph an inline element's bidirectional embedding is in. The embedding is opened again for
	 * the inline element's text after the block, which legacy draws outside it
	 *
	 * @dataProvider modes
	 *
	 * @param string $mode
	 */
	public function testABidiEmbeddingIsOpenedAgainAfterABlock($mode)
	{
		$hebrew = html_entity_decode('&#x5d0;&#x5d1;', ENT_QUOTES, 'UTF-8');
		$mpdf = new TextRecordingMpdf(['mode' => 'utf-8', 'default_font' => 'dejavusans', 'cssMode' => $mode]);
		$mpdf->WriteHTML('<div><span dir="rtl">' . $hebrew . ' cd<div>qq</div>' . $hebrew . ' ef</span> zz</div>');
		$mpdf->Close();

		$drawn = array_map('trim', $mpdf->drawnText);
		$reversed = html_entity_decode('&#x5d1;&#x5d0;', ENT_QUOTES, 'UTF-8');
		$this->assertContains('cd ' . $reversed, $drawn, 'Before the block');
		$this->assertContains($mode === CssMode::STANDARD ? 'ef ' . $reversed : $reversed . ' ef', $drawn, 'After the block');
	}

	/**
	 * The text before a block opened in an inline element is drawn in its own style, not the inline element's: neither
	 * italic nor shadowed. The inline element's text before, in and after the block is. Legacy draws that text in the
	 * shadow of the inline element, and the block and the text after it in neither
	 *
	 * @dataProvider modes
	 *
	 * @param string $mode
	 */
	public function testTheLineBeforeABlockIsDrawnInItsOwnState($mode)
	{
		$mpdf = $this->drawDocument('<div>ww <span style="font-style: italic; text-shadow: 1px 1px #f00">xx<div>qq</div>yy</span> zz</div>', ['cssMode' => $mode]);
		$mpdf->Close();

		$styles = $this->keyedByText($mpdf, $mpdf->drawnFontStyles);
		$shadowed = array_map(function ($shadow) {
			return $shadow !== '';
		}, $this->keyedByText($mpdf, $mpdf->drawnShadows));

		if ($mode === CssMode::STANDARD) {
			$this->assertSame(['ww' => '', 'xx' => 'I', 'qq' => 'I', 'yy' => 'I', 'zz' => ''], $styles);
			$this->assertSame(['ww' => false, 'xx' => true, 'qq' => true, 'yy' => true, 'zz' => false], $shadowed);
		} else {
			$this->assertSame(['ww' => '', 'xx' => 'I', 'qq' => '', 'yy' => '', 'zz' => ''], $styles);
			$this->assertSame(['ww' => true, 'xx' => true, 'qq' => false, 'yy' => false, 'zz' => false], $shadowed);
		}
	}

	/**
	 * The text before a block opened in a spaced inline element is drawn at its own letter and word spacing, as it is
	 * where the inline element holds no block. Legacy draws it at the inline element's
	 *
	 * @dataProvider modes
	 *
	 * @param string $mode
	 */
	public function testTheLineBeforeABlockIsDrawnAtItsOwnSpacing($mode)
	{
		$boxes = [];
		foreach (['<div>' => '</div>', '' => ''] as $open => $close) {
			$mpdf = $this->drawDocument('<div>ww w <span style="letter-spacing: 3mm; word-spacing: 2mm">xx' . $open . 'qq' . $close . 'yy</span> zz</div>', ['cssMode' => $mode]);
			$mpdf->Close();
			$boxes[] = $this->keyedByText($mpdf, $mpdf->drawnBoxes)['ww w'];
		}

		if ($mode === CssMode::STANDARD) {
			$this->assertSame($boxes[1], $boxes[0]);
		} else {
			$this->assertNotSame($boxes[1], $boxes[0]);
		}
	}

	/**
	 * A table opened in an inline element takes its style, and the inline element's text after the table is drawn in
	 * it again. Legacy draws the table and the text after it in neither, and the text before the span in its colour
	 *
	 * @dataProvider modes
	 *
	 * @param string $mode
	 */
	public function testTheTextAfterATableInAnInlineElementIsDrawnInItsStyle($mode)
	{
		$mpdf = $this->drawDocument('<div>ww <span style="font-style: italic; color: #0f0">xx<table><tr><td>qq</td></tr></table>yy</span> zz</div>', ['cssMode' => $mode]);
		$mpdf->Close();

		$styles = $this->keyedByText($mpdf, $mpdf->drawnFontStyles);
		$colours = $this->keyedByText($mpdf, $mpdf->drawnColours);
		$green = '0.000 1.000 0.000 rg';
		$black = '0.000 g';

		if ($mode === CssMode::STANDARD) {
			$this->assertSame(['ww' => '', 'xx' => 'I', 'qq' => 'I', 'yy' => 'I', 'zz' => ''], $styles);
			$this->assertSame(['ww' => $black, 'xx' => $green, 'qq' => $green, 'yy' => $green, 'zz' => $black], $colours);
		} else {
			$this->assertSame(['ww' => '', 'xx' => 'I', 'qq' => '', 'yy' => '', 'zz' => ''], $styles);
			$this->assertSame(['ww' => $green, 'xx' => $green, 'qq' => $black, 'yy' => $black, 'zz' => $black], $colours);
		}
	}

	/**
	 * How the first line of a document, "qq qq", is drawn: where it starts and how wide it is
	 *
	 * @param string $mode
	 * @param string $html
	 *
	 * @return float[]
	 */
	private function firstLine($mode, $html)
	{
		$mpdf = $this->drawDocument($html, ['cssMode' => $mode]);
		$mpdf->Close();

		$boxes = $this->keyedByText($mpdf, $mpdf->drawnBoxes);
		$this->assertArrayHasKey('qq qq', $boxes);

		return [round($boxes['qq qq'][1], 3), round($boxes['qq qq'][2] - $boxes['qq qq'][1], 3)];
	}

	/**
	 * @param string $mode
	 * @param string $html Two lines, "qq qq" and "ww ww"
	 *
	 * @return float The distance between the tops of the two lines
	 */
	private function lineGap($mode, $html)
	{
		$mpdf = $this->drawDocument($html, ['cssMode' => $mode]);
		$mpdf->Close();

		$tops = $this->keyedByText($mpdf, $mpdf->drawnY);

		return round($tops['ww ww'] - $tops['qq qq'], 3);
	}
}
