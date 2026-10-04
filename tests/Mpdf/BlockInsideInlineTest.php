<?php

namespace Mpdf;

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * A block or table opened inside inline elements inherits their state, and the text after it is read in their state
 * again, until their end tags. A link around a block covers the block's text. Under legacy the block or table starts
 * from the enclosing block's state, and so does the text after it (#541, #543).
 */
class BlockInsideInlineTest extends TestCase
{

	use DrawnStyles;

	/**
	 * The inline element's style is green and bold; an enclosing block's is blue
	 */
	const CSS = '<style>.a { color: #0f0; font-weight: bold } .o { color: #00f }</style>';

	const GREEN_BOLD = ['0.000 1.000 0.000 rg', 'B'];

	const BLUE = ['0.000 0.000 1.000 rg', ''];

	const PLAIN = ['0.000 g', ''];

	const LINK = 'https://example.com/';

	/**
	 * Pushes the text after it to the foot of the first page, so that a kept block opened there runs onto the second
	 * and is laid out again there
	 */
	const FILLER = '<div style="height: 250mm"></div>';

	/**
	 * Each piece of text is drawn in the colour and weight given for the mode, on the page given for it
	 *
	 * @dataProvider documents
	 *
	 * @param string $mode
	 * @param string $html
	 * @param int[] $pages For some pieces of text, the page each is drawn on: a kept block starts on the first page,
	 *                     runs onto the second and is laid out again from its top
	 * @param array[] $expected For each piece of text, its colour operator and font style
	 */
	public function testTheTextIsDrawnInTheInlineElementsState($mode, $html, array $pages, array $expected)
	{
		$mpdf = $this->drawDocument(self::CSS . $html, ['cssMode' => $mode]);

		$drawn = $this->keyedByText($mpdf, array_map(null, $mpdf->drawnColours, $mpdf->drawnFontStyles));
		foreach ($expected as $text => $style) {
			$this->assertArrayHasKey($text, $drawn, sprintf('"%s" is not drawn', $text));
			$this->assertSame($style, $drawn[$text], sprintf('"%s" is drawn in the wrong style', $text));
		}

		$drawnPages = $this->keyedByText($mpdf, array_column($mpdf->drawnBoxes, 0));
		foreach ($pages as $text => $page) {
			$this->assertSame($page, $drawnPages[$text], sprintf('"%s" is drawn on the wrong page', $text));
		}
	}

	/**
	 * The documents under both modes. Each has the text xx before the block, qq (and rr) in it, yy after it inside the
	 * inline element and zz after the inline element
	 *
	 * @return array[]
	 */
	public function documents()
	{
		$g = self::GREEN_BOLD;
		$p = self::PLAIN;
		$b = self::BLUE;

		// The block and the text after it in the inline element's state; the text after the inline element not
		$inherits = ['xx' => $g, 'qq' => $g, 'yy' => $g, 'zz' => $p];
		// Legacy: the block and the text after it in the enclosing block's state
		$loses = ['xx' => $g, 'qq' => $p, 'yy' => $p, 'zz' => $p];
		// Legacy in a cell: the block inherits, but the inline element's end tag restores nothing
		$sticks = ['xx' => $g, 'qq' => $g, 'yy' => $g, 'zz' => $g];

		$documents = [
			'a block in a span' => [
				'<span class="a">xx<div>qq</div>yy</span>zz',
				$inherits,
				$loses,
			],
			'a block in a span in a block' => [
				'<div class="o"><span class="a">xx<div>qq</div>yy</span>zz</div>',
				['xx' => $g, 'qq' => $g, 'yy' => $g, 'zz' => $b],
				['xx' => $g, 'qq' => $b, 'yy' => $b, 'zz' => $b],
			],
			'a block in nested inline elements' => [
				'<b><i>xx<div>qq</div>yy</i>ww</b>zz',
				[
					'xx' => ['0.000 g', 'BI'],
					'qq' => ['0.000 g', 'BI'],
					'yy' => ['0.000 g', 'BI'],
					'ww' => ['0.000 g', 'B'],
					'zz' => $p,
				],
				['xx' => ['0.000 g', 'BI'], 'qq' => $p, 'yy' => $p, 'ww' => $p, 'zz' => $p],
			],
			'blocks in a row in a span' => [
				'<span class="a">xx<div>qq</div><div>rr</div>yy</span>zz',
				$inherits + ['rr' => $g],
				$loses + ['rr' => $p],
			],
			'text between blocks in a span' => [
				'<span class="a">xx<div>qq</div>ww<div>rr</div>yy</span>zz',
				$inherits + ['ww' => $g, 'rr' => $g],
				$loses + ['ww' => $p, 'rr' => $p],
			],
			'a block inside the block in a span' => [
				'<span class="a">xx<div><p>qq</p>rr</div>yy</span>zz',
				$inherits + ['rr' => $g],
				$loses + ['rr' => $p],
			],
			'the block sets its own colour' => [
				'<span class="a">xx<div class="o">qq</div>yy</span>zz',
				array_replace($inherits, ['qq' => ['0.000 0.000 1.000 rg', 'B']]),
				array_replace($loses, ['qq' => $b]),
			],
			'a list in a span' => [
				'<span class="a">xx<ul><li>qq</li></ul>yy</span>zz',
				$inherits,
				$loses,
			],
			'a block in a span in a list item' => [
				'<ul><li class="o"><span class="a">xx<div>qq</div>yy</span>zz</li></ul>',
				['xx' => $g, 'qq' => $g, 'yy' => $g, 'zz' => $b],
				['xx' => $g, 'qq' => $b, 'yy' => $b, 'zz' => $b],
			],
			'text before the span on the line the block breaks' => [
				'<div>ww <span class="a">xx<div>qq</div>yy</span> zz</div>',
				$inherits + ['ww' => $p],
				// Legacy draws the line in the span's colour
				$loses + ['ww' => ['0.000 1.000 0.000 rg', '']],
			],
			'the span ends before the block' => [
				'<span class="a">xx</span><div>qq</div>yy',
				['xx' => $g, 'qq' => $p, 'yy' => $p],
				['xx' => $g, 'qq' => $p, 'yy' => $p],
			],
			'a block in a span in a cell' => [
				$this->inContext('table cell', '<span class="a">xx<div>qq</div>yy</span>zz'),
				$inherits,
				$sticks,
			],
			'a block in a span in a nested cell' => [
				$this->inContext('table cell', $this->inContext('table cell', '<span class="a">xx<div>qq</div>yy</span>zz')),
				$inherits,
				$sticks,
			],
			'a block in a span in a header' => [
				$this->inContext('header', '<span class="a">xx<div>qq</div>yy</span>zz'),
				$inherits,
				$loses,
			],
			'a block in a span in a footer' => [
				$this->inContext('footer', '<span class="a">xx<div>qq</div>yy</span>zz'),
				$inherits,
				$loses,
			],
			'a block in a span in a positioned block' => [
				$this->inContext('positioned block', '<span class="a">xx<div>qq</div>yy</span>zz'),
				$inherits,
				$loses,
			],
			'a kept block in a span that is laid out again on the next page' => [
				self::FILLER . '<span class="a">xx<div style="page-break-inside: avoid"><p>qq</p><p>rr</p></div>yy</span>zz',
				$inherits + ['rr' => $g],
				$loses + ['rr' => $p],
				['xx' => 1, 'qq' => 2],
			],
			'a block in a span in a kept block that is laid out again on the next page' => [
				self::FILLER . '<div style="page-break-inside: avoid"><p>ww</p><span class="a">xx<div>qq</div>yy</span>zz</div>',
				$inherits,
				$loses,
				['ww' => 2, 'qq' => 2],
			],
			'a forced page break inside the block in a span' => [
				'<span class="a">xx<div><p>qq</p><pagebreak /><p>rr</p>ww</div>yy</span>zz',
				$inherits + ['rr' => $g, 'ww' => $g],
				$loses + ['rr' => $p, 'ww' => $p],
				['qq' => 1, 'rr' => 2],
			],
			'the block in a span breaks the page before it' => [
				'<span class="a">xx<div style="page-break-before: always">qq</div>yy</span>zz',
				$inherits,
				$loses,
				['xx' => 1, 'qq' => 2],
			],
			'a table in a span' => [
				'<span class="a">xx<table><tr><td>qq</td></tr></table>yy</span>zz',
				$inherits,
				$loses,
			],
			'a table in a span in a block' => [
				'<div class="o"><span class="a">xx<table><tr><td>qq</td></tr></table>yy</span>zz</div>',
				['xx' => $g, 'qq' => $g, 'yy' => $g, 'zz' => $b],
				// Legacy starts the table from the document's defaults
				['xx' => $g, 'qq' => $p, 'yy' => $b, 'zz' => $b],
			],
			'a table in a span in a cell' => [
				$this->inContext('table cell', '<span class="a">xx<table><tr><td>qq</td></tr></table>yy</span>zz'),
				$inherits,
				// Legacy: the nested table takes the cell's colour but not its weight
				['xx' => $g, 'qq' => ['0.000 1.000 0.000 rg', ''], 'yy' => $g, 'zz' => $g],
			],
			'a table in a span in a kept block that is laid out again on the next page' => [
				self::FILLER . '<div style="page-break-inside: avoid"><p>ww</p><span class="a">xx<table><tr><td>qq</td></tr></table>yy</span>zz</div>',
				$inherits,
				$loses,
				['ww' => 2, 'qq' => 2],
			],
			'the table in a span breaks the page before it' => [
				'<span class="a">xx<table style="page-break-before: always"><tr><td>qq</td></tr></table>yy</span>zz',
				$inherits,
				$loses,
				['xx' => 1, 'qq' => 2],
			],
			'the block in a span breaks the page after it' => [
				'<span class="a">xx<div style="page-break-after: always">qq</div>yy</span>zz',
				$inherits,
				$loses,
				['qq' => 1, 'yy' => 2],
			],
		];

		$rows = [];
		foreach ($documents as $name => $document) {
			$rows[$name] = [$document[0], isset($document[3]) ? $document[3] : [], $document[1], $document[2]];
		}

		return $this->bothModes($rows);
	}

	/**
	 * A font size in em or % on the block is taken from the inline element's size
	 */
	public function testARelativeFontSizeIsTakenFromTheInlineElement()
	{
		$html = '<span style="font-size: 20pt">xx<div style="font-size: 50%">qq</div><div style="font-size: 1.5em">rr</div>yy</span>zz';

		foreach ([CssMode::STANDARD => [20.0, 10.0, 30.0, 20.0, 11.0], CssMode::LEGACY => [20.0, 5.5, 16.5, 11.0, 11.0]] as $mode => $sizes) {
			$mpdf = $this->drawDocument($html, ['cssMode' => $mode]);

			$this->assertSame(
				array_combine(['xx', 'qq', 'rr', 'yy', 'zz'], $sizes),
				array_map(function ($size) {
					return round($size, 6);
				}, $this->keyedByText($mpdf, $mpdf->drawnFontSize)),
				$mode
			);
		}
	}

	/**
	 * bolder on the block steps from the inline element's weight, and currentColor as its colour is the inline
	 * element's colour. Under legacy the colour is the enclosing block's, and bolder is not read
	 */
	public function testRelativeWeightAndCurrentColorAreTakenFromTheInlineElement()
	{
		$html = '<span style="font-weight: 100; color: #0f0">xx<div style="font-weight: bolder; color: currentColor">qq</div>yy</span>';

		$expected = [
			CssMode::STANDARD => ['0.000 1.000 0.000 rg', ''],
			CssMode::LEGACY => ['0.000 g', ''],
		];
		foreach ($expected as $mode => $style) {
			$mpdf = $this->drawDocument($html, ['cssMode' => $mode]);
			$drawn = $this->keyedByText($mpdf, array_map(null, $mpdf->drawnColours, $mpdf->drawnFontStyles));

			$this->assertSame($style, $drawn['qq'], $mode);
		}
	}

	/**
	 * The text in a block inside a link, and after it in the link, is drawn in the link's colour and underlined, and the
	 * link covers it
	 *
	 * @dataProvider links
	 *
	 * @param string $mode
	 * @param string $html
	 * @param string[][] $expected The text drawn as the link, in its colour, underlined and covered by it, and the text
	 *                             drawn as the rest
	 */
	public function testALinkAroundABlockCoversItsText($mode, $html, array $expected)
	{
		list($linked, $unlinked) = $expected;
		$mpdf = $this->drawDocument($html, ['cssMode' => $mode]);

		$colours = $this->keyedByText($mpdf, $mpdf->drawnColours);
		$textVars = $this->keyedByText($mpdf, $mpdf->drawnTextVars);
		$boxes = $this->keyedByText($mpdf, $mpdf->drawnBoxes);

		foreach ($linked as $text) {
			$this->assertSame('0.000 0.000 1.000 rg', $colours[$text], $text . ' is in the link\'s colour');
			$this->assertNotSame(0, $textVars[$text] & Css\TextVars::FD_UNDERLINE, $text . ' is underlined');
			$this->assertTrue($this->isLinked($mpdf, $boxes[$text]), $text . ' is covered by the link');
		}

		foreach ($unlinked as $text) {
			$this->assertSame('0.000 g', $colours[$text], $text . ' is not in the link\'s colour');
			$this->assertSame(0, $textVars[$text] & Css\TextVars::FD_UNDERLINE, $text . ' is not underlined');
			$this->assertFalse($this->isLinked($mpdf, $boxes[$text]), $text . ' is not covered by the link');
		}
	}

	/**
	 * Links around blocks in each context, under both modes. Under legacy only the text before the block is the link's
	 *
	 * @return array[]
	 */
	public function links()
	{
		$a = '<a href="' . self::LINK . '">';

		// Each document, the text linked and not linked under standard, then the same under legacy, where the block and
		// the text after it are not the link's. Legacy is left out where it draws the text before the link, or after it
		// in a cell, in the link's colour
		$documents = [
			'a block in a link' => [
				$a . 'xx<div>qq</div>yy</a>zz',
				['xx', 'qq', 'yy'],
				['zz'],
				[['xx'], ['qq', 'yy', 'zz']],
			],
			'a block inside the block in a link' => [
				$a . 'xx<div><p>qq</p>rr</div>yy</a>zz',
				['xx', 'qq', 'rr', 'yy'],
				['zz'],
				[['xx'], ['qq', 'rr', 'yy', 'zz']],
			],
			'a link in a block' => [
				'<div>ww ' . $a . 'xx<div>qq</div>yy</a> zz</div>',
				['xx', 'qq', 'yy'],
				['ww', 'zz'],
			],
			'a block in a link in a cell' => [
				$this->inContext('table cell', $a . 'xx<div>qq</div>yy</a>zz'),
				['xx', 'qq', 'yy'],
				['zz'],
			],
			'a kept block in a link that is laid out again on the next page' => [
				self::FILLER . $a . 'xx<div style="page-break-inside: avoid"><p>qq</p><p>rr</p></div>yy</a>zz',
				['xx', 'qq', 'rr', 'yy'],
				['zz'],
				[['xx'], ['qq', 'rr', 'yy', 'zz']],
			],
			'a forced page break inside the block in a link' => [
				$a . 'xx<div><p>qq</p><pagebreak /><p>rr</p></div>yy</a>zz',
				['xx', 'qq', 'rr', 'yy'],
				['zz'],
				[['xx'], ['qq', 'rr', 'yy', 'zz']],
			],
		];

		$rows = [];
		foreach ($documents as $name => $document) {
			$rows[$name] = [$document[0], [$document[1], $document[2]], isset($document[3]) ? $document[3] : null];
		}

		return array_filter($this->bothModes($rows), function ($case) {
			return $case[2] !== null;
		});
	}

	/**
	 * Under standard the link's rectangles are one for each line of its text: before, in and after the block, on the
	 * page each is drawn on
	 */
	public function testTheLinkHasARectangleForEachLineOfItsText()
	{
		$mpdf = $this->drawDocument('<a href="' . self::LINK . '">xx<div><p>qq</p><pagebreak /><p>rr</p></div>yy</a>zz');

		$this->assertCount(2, $mpdf->PageLinks[1]);
		$this->assertCount(2, $mpdf->PageLinks[2]);
		foreach ($mpdf->PageLinks as $links) {
			foreach ($links as $link) {
				$this->assertSame(self::LINK, $link[4]);
			}
		}
	}

	/**
	 * @param TextRecordingMpdf $mpdf A document, written
	 * @param array $box A piece of text's page, left and right edges and top, as TextRecordingMpdf records them
	 *
	 * @return bool Whether a link to self::LINK on the text's page spans the text's width and its line
	 */
	private function isLinked(TextRecordingMpdf $mpdf, array $box)
	{
		list($page, $left, $right, $top) = $box;
		if (!isset($mpdf->PageLinks[$page])) {
			return false;
		}

		foreach ($mpdf->PageLinks[$page] as $link) {
			$linkLeft = $link[0] / Mpdf::SCALE;
			$linkRight = ($link[0] + $link[2]) / Mpdf::SCALE;
			$linkTop = ($mpdf->hPt - $link[1]) / Mpdf::SCALE;
			$linkBottom = $linkTop + $link[3] / Mpdf::SCALE;

			if ($link[4] === self::LINK && $linkLeft <= $left + 0.01 && $linkRight >= $right - 0.01
				&& $linkTop <= $top + 1 && $linkBottom >= $top + 2) {
				return true;
			}
		}

		return false;
	}
}
