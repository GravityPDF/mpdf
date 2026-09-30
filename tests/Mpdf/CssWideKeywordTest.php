<?php

namespace Mpdf;

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * The CSS-wide keywords in the cases GravityPDF/mpdf#546 measured, in the shorthands, on a cell's row and table, on
 * the elements the built-in defaults style, and through a border's parts and a chain of elements. The standard CSS
 * mode resolves them as a browser does. The legacy mode reads them as it always has.
 *
 * The cases in shorthands() and the ones after it compare two documents: one with the keyword, and one with the
 * declarations it should draw as written in its place.
 */
class CssWideKeywordTest extends TestCase
{

	use DrawnStyles;

	const RED = '1.000 0.000 0.000 rg';
	const LIME = '0.000 1.000 0.000 rg';
	const DEFAULT_BLACK = '0.000 g';

	/**
	 * The text is drawn in the colour, size and style each mode leaves it in
	 *
	 * @dataProvider measuredCases
	 *
	 * @param string $mode
	 * @param string $css
	 * @param string $html
	 * @param array $expected The colour, size and style the text is drawn in
	 */
	public function testTheMeasuredCasesDrawAsABrowserDoesInTheStandardMode($mode, $css, $html, array $expected)
	{
		$mpdf = $this->drawDocument('<style>' . $css . '</style>' . $html, ['cssMode' => $mode]);

		$this->assertSame($expected, [$mpdf->drawnColours[0], round($mpdf->drawnFontSize[0], 6), $mpdf->drawnFontStyles[0]]);
	}

	/**
	 * The cases of GravityPDF/mpdf#546, with what the standard mode draws, as a browser does, and what the legacy mode
	 * draws, as measured
	 *
	 * @return array[]
	 */
	public function measuredCases()
	{
		$block = '<div class="a"><p class="b">text</p></div>';
		$rows = [
			'color: inherit' => ['.a { color: #0f0; } p { color: red; } p.b { color: inherit; }', $block, [self::LIME, 11.0, ''], [self::DEFAULT_BLACK, 11.0, '']],
			'color: unset' => ['.a { color: #0f0; } p { color: red; } p.b { color: unset; }', $block, [self::LIME, 11.0, ''], [self::DEFAULT_BLACK, 11.0, '']],
			'color: revert' => ['.a { color: #0f0; } p { color: red; } p.b { color: revert; }', $block, [self::LIME, 11.0, ''], [self::RED, 11.0, '']],
			'font-size: inherit' => ['.a { font-size: 20pt; } p { font-size: 8pt; } p.b { font-size: inherit; }', $block, [self::DEFAULT_BLACK, 20.0, ''], [self::DEFAULT_BLACK, 11.0, '']],
			'font-weight: inherit' => ['.a { font-weight: bold; } p { font-weight: normal; } p.b { font-weight: inherit; }', $block, [self::DEFAULT_BLACK, 11.0, 'B'], [self::DEFAULT_BLACK, 11.0, '']],
			'color: inherit on a cell' => [
				'table { color: #0f0; } td { color: red; } td.b { color: inherit; }',
				'<table><tr><td class="b">text</td></tr></table>',
				[self::LIME, 11.0, ''],
				[self::DEFAULT_BLACK, 11.0, ''],
			],
		];

		return $this->bothModes($rows);
	}

	/**
	 * border: inherit on a paragraph in a bordered block draws a second border in the standard mode, and none in the
	 * legacy mode
	 *
	 * @dataProvider modes
	 *
	 * @param string $mode
	 * @param int $sides How many border sides are drawn in red
	 */
	public function testBorderInheritDrawsTheParentsBorder($mode, $sides)
	{
		$pages = $this->drawnPages('<style>div { border: 1px solid red; } p { border: inherit; }</style><div><p>text</p></div>', ['cssMode' => $mode]);

		$this->assertSame($sides, substr_count($pages[0][0], '1.000 0.000 0.000 RG'));
	}

	/**
	 * The initial colour of a border is currentColor, so in the standard mode initial draws a side in the element's
	 * colour
	 */
	public function testTheInitialBorderColourIsTheElementsColour()
	{
		$css = '.a { border: 1mm solid #00ff00; } .a p { color: #ff0000; border: 1mm solid #0000ff; } .a .k { {V} }';
		$html = '<div class="a"><p class="k">subject</p><p>near miss</p></div>';

		$this->assertSame(
			$this->drawnPages('<style>' . str_replace('{V}', 'border-top-color: #ff0000', $css) . '</style>' . $html),
			$this->drawnPages('<style>' . str_replace('{V}', 'border-top-color: initial', $css) . '</style>' . $html)
		);
	}

	/**
	 * @return array[] Each CSS mode, with how many red border sides the case above draws in it
	 */
	public function modes()
	{
		return [CssMode::STANDARD => [CssMode::STANDARD, 8], CssMode::LEGACY => [CssMode::LEGACY, 4]];
	}

	/**
	 * The keyword draws as the declarations it resolves to
	 *
	 * @dataProvider shorthands
	 * @dataProvider cellsRowsAndTables
	 * @dataProvider builtInDefaults
	 * @dataProvider borderParts
	 * @dataProvider chains
	 *
	 * @param string $mode
	 * @param string $css With {V} for the subject's declarations
	 * @param string $html
	 * @param string $declarations The subject's declarations, with the keyword
	 * @param string $resolved What they should draw as
	 */
	public function testTheKeywordDrawsAsWhatItResolvesTo($mode, $css, $html, $declarations, $resolved)
	{
		$this->assertSame(
			$this->drawnPages('<style>' . str_replace('{V}', $resolved, $css) . '</style>' . $html, ['cssMode' => $mode]),
			$this->drawnPages('<style>' . str_replace('{V}', $declarations, $css) . '</style>' . $html, ['cssMode' => $mode])
		);
	}

	/**
	 * Each shorthand with each keyword. In the standard mode the keyword reaches each of its longhands. In the legacy
	 * mode a border or background shorthand draws as its initial value, except with revert-layer, which it drops, and
	 * font and list-style drop every keyword. border-color reads inherit as transparent and the other keywords as a
	 * colour it cannot read. A legacy column given as one value holds for every keyword
	 *
	 * @return array[]
	 */
	public function shorthands()
	{
		$block = '<div class="a"><p class="k">subject</p><p>near miss</p></div>';
		$list = '<ul class="a"><li class="k">subject</li><li>near miss</li></ul>';

		$shorthands = [
			'font' => [
				'.a { font: italic bold 20pt courier; } .a p { font: 8pt helvetica; } .a .k { {V} }',
				$block,
				['inherit' => 'font: italic bold 20pt courier', 'initial' => 'font: 11pt ctimes', 'unset' => 'font: italic bold 20pt courier', 'revert' => 'font: italic bold 20pt courier'],
				'',
			],
			'background' => [
				'.a { background: #00ff00; } .a p { background: #0000ff; } .a .k { {V} }',
				$block,
				['inherit' => 'background: #00ff00', 'initial' => 'background: none', 'unset' => 'background: none', 'revert' => 'background: none'],
				['inherit' => 'background: none', 'initial' => 'background: none', 'unset' => 'background: none', 'revert' => 'background: none', 'revert-layer' => ''],
			],
			'margin' => [
				'.a { margin: 7mm; } .a p { margin: 2mm; } .a .k { {V} }',
				$block,
				['inherit' => 'margin: 7mm', 'initial' => 'margin: 0', 'unset' => 'margin: 0', 'revert' => 'margin: 1.12em 0'],
				'margin: 0',
			],
			'border-width' => [
				'.a { border: 2mm solid #00ff00; } .a p { border: 0.5mm solid #0000ff; } .a .k { {V} }',
				$block,
				['inherit' => 'border-width: 2mm', 'initial' => 'border-width: medium', 'unset' => 'border-width: medium', 'revert' => 'border-width: medium'],
				'border-width: 0',
			],
			'border-style' => [
				'.a { border: 1mm dotted #00ff00; } .a p { border: 1mm dashed #0000ff; } .a .k { {V} }',
				$block,
				['inherit' => 'border-style: dotted', 'initial' => 'border-style: none', 'unset' => 'border-style: none', 'revert' => 'border-style: none'],
				'border-style: none',
			],
			'border-color' => [
				'.a { border: 1mm solid #00ff00; } .a p { border: 1mm solid #0000ff; } .a .k { {V} }',
				$block,
				['inherit' => 'border-color: #00ff00', 'initial' => 'border-color: #000000', 'unset' => 'border-color: #000000', 'revert' => 'border-color: #000000'],
				['inherit' => 'border-color: transparent', 'initial' => 'border-color: unreadable', 'unset' => 'border-color: unreadable', 'revert' => 'border-color: unreadable', 'revert-layer' => 'border-color: unreadable'],
			],
			'list-style' => [
				'.a { list-style: square inside; } .a li { list-style: circle outside; } .a .k { {V} }',
				$list,
				['inherit' => 'list-style: square inside', 'initial' => 'list-style: disc outside', 'unset' => 'list-style: square inside', 'revert' => 'list-style: square inside'],
				'',
			],
		];

		$rows = [];
		foreach ($shorthands as $shorthand => $case) {
			list($css, $html, $standard, $legacy) = $case;
			$standard['revert-layer'] = $standard['revert'];
			foreach (['inherit', 'initial', 'unset', 'revert', 'revert-layer'] as $keyword) {
				$declaration = $shorthand . ': ' . $keyword;
				$rows[$declaration] = [$css, $html, $declaration, $standard[$keyword], is_array($legacy) ? $legacy[$keyword] : $legacy];
			}
		}

		return $this->bothModes($rows);
	}

	/**
	 * inherit on a cell takes a property that is not inherited from its row, not its table, and an inherited one
	 * from its row where the row sets it, and otherwise from its table. A row takes it from its row group, or from
	 * none where the HTML leaves the tbody out
	 *
	 * @return array[]
	 */
	public function cellsRowsAndTables()
	{
		$row = '<table class="t"><tr class="r"><td class="k">subject</td><td>near miss</td></tr></table>';
		$rows = '<table class="t"><tbody class="g"><tr class="k"><td>subject</td></tr><tr><td>near miss</td></tr></tbody></table>';
		$implied = '<table class="t"><tr class="k"><td>subject</td></tr><tr><td>near miss</td></tr></table>';

		return $this->bothModes([
			'a cell\'s background from its row' => [
				'.t { background-color: #ffff00; } .r { background-color: #00ff00; } td { background-color: #0000ff; } td.k { {V} }',
				$row,
				'background-color: inherit',
				'background-color: #00ff00',
				'background-color: currentcolor',
			],
			'a cell\'s border from its row, which has none' => [
				'.t { border: 1mm solid #00ff00; } td { border: 0.5mm dashed #0000ff; } td.k { {V} }',
				$row,
				'border: inherit',
				'border: none',
				'border: none',
			],
			'a cell\'s padding from its row, which has none' => [
				'.t { padding: 5mm; } td { padding: 2mm; } td.k { {V} }',
				$row,
				'padding: inherit',
				'padding: 0',
				'padding: 0',
			],
			'a cell\'s colour from its table' => [
				'.t { color: #008000; } td { color: #ff0000; } td.k { {V} }',
				$row,
				'color: inherit',
				'color: #008000',
				'color: currentcolor',
			],
			'a cell\'s colour from its row, over its table\'s' => [
				'.t { color: #0000ff; } .r { color: #008000; } td { color: #ff0000; } td.k { {V} }',
				$row,
				'color: inherit',
				'color: #008000',
				'color: currentcolor',
			],
			'a cell\'s font size from its table' => [
				'.t { font-size: 16pt; } td { font-size: 8pt; } td.k { {V} }',
				$row,
				'font-size: inherit',
				'font-size: 16pt',
				'font-size: auto',
			],
			'a row\'s background from its row group' => [
				'.t { background-color: #ffff00; } .g { background-color: #00ff00; } tr { background-color: #0000ff; } tr.k { {V} }',
				$rows,
				'background-color: inherit',
				'background-color: #00ff00',
				'background-color: currentcolor',
			],
			'a row\'s background in a tbody the HTML leaves out' => [
				'.t { background-color: #ffff00; } tr { background-color: #0000ff; } tr.k { {V} }',
				$implied,
				'background-color: inherit',
				'background-color: transparent',
				'background-color: currentcolor',
			],
		]);
	}

	/**
	 * revert gives an element the value the built-in defaults and the default stylesheet give it, over any author
	 * rule, and a presentational attribute counts as an author rule. With none, it is unset
	 *
	 * @return array[]
	 */
	public function builtInDefaults()
	{
		return $this->bothModes([
			'a list\'s margin' => [
				'ul { margin: 5mm; } ul.k { {V} }',
				'<ul class="k"><li>subject</li></ul><ul><li>near miss</li></ul>',
				'margin: revert',
				'margin: 0.83em 0',
				'margin: 0',
			],
			'a nested list\'s margin, from the default stylesheet' => [
				'ul { margin: 5mm; } ul.k { {V} }',
				'<ul><li>outer<ul class="k"><li>subject</li></ul></li></ul>',
				'margin: revert',
				'margin: 0',
				'margin: 0',
			],
			'a heading\'s size' => [
				'h1 { font-size: 9pt; } h1.k { {V} }',
				'<h1 class="k">subject</h1><h1>near miss</h1>',
				'font-size: revert',
				'font-size: 2em',
				'font-size: auto',
			],
			'a header cell\'s weight' => [
				'th { font-weight: normal; } th.k { {V} }',
				'<table><tr><th class="k">subject</th><th>near miss</th></tr></table>',
				'font-weight: revert',
				'font-weight: bold',
				'font-weight: normal',
			],
			'a cell\'s padding, over its table\'s cellpadding' => [
				'td { padding: 5mm; } td.k { {V} }',
				'<table cellpadding="4mm"><tr><td class="k">subject</td><td>near miss</td></tr></table>',
				'padding: revert',
				'padding: 0.1em',
				'padding: 0',
			],
			'a paragraph\'s colour, which has no default' => [
				'div { color: #008000; } p { color: #ff0000; } p.k { {V} }',
				'<div><p class="k">subject</p><p>near miss</p></div>',
				'color: revert',
				'color: #008000',
				'',
			],
		]);
	}

	/**
	 * A keyword in a border side's width, style or colour resolves that part of the side, and a part declared after a
	 * border keyword still applies over it
	 *
	 * @return array[]
	 */
	public function borderParts()
	{
		$block = '<div class="a"><p class="k">subject</p><p>near miss</p></div>';
		$css = '.a { border: 1mm solid #00ff00; } .a p { border: 0.5mm dashed #0000ff; } .a .k { {V} }';

		return $this->bothModes([
			'one side\'s colour' => [$css, $block, 'border-top-color: inherit', 'border-top-color: #00ff00', 'border-top-color: transparent'],
			'one side\'s width' => [$css, $block, 'border-left-width: inherit', 'border-left-width: 1mm', 'border-left-width: 0'],
			'one side\'s style' => [$css, $block, 'border-bottom-style: initial', 'border-bottom-style: none', 'border-bottom-style: none'],
			'one side' => [$css, $block, 'border-right: initial', 'border-right: none', 'border-right: none'],
			'a part after the keyword' => [
				$css,
				$block,
				'border: inherit; border-top-color: #ff0000',
				'border: 1mm solid #00ff00; border-top-color: #ff0000',
				'border: none; border-top-color: #ff0000',
			],
		]);
	}

	/**
	 * inherit reaches through an element that inherits the value itself, and at the top of the document takes the
	 * value body has, and on body the value html has. A block takes an inherited value mPDF does not hand it from its
	 * parent
	 *
	 * @return array[]
	 */
	public function chains()
	{
		return $this->bothModes([
			'a background through a block' => [
				'.a { background-color: #00ff00; } .a div { background-color: inherit; } .a p { background-color: #0000ff; } .a .k { {V} }',
				'<div class="a"><div><p class="k">subject</p><p>near miss</p></div></div>',
				'background-color: inherit',
				'background-color: #00ff00',
				'background-color: currentcolor',
			],
			'a colour from body' => [
				'body { color: #008000; } p { color: #ff0000; } p.k { {V} }',
				'<p class="k">subject</p><p>near miss</p>',
				'color: inherit',
				'color: #008000',
				'color: currentcolor',
			],
			'a background from body' => [
				'body { background-color: #ffff00; } p { background-color: #0000ff; } p.k { {V} }',
				'<p class="k">subject</p><p>near miss</p>',
				'background-color: inherit',
				'background-color: #ffff00',
				'background-color: currentcolor',
			],
			'a nested list\'s markers, through the item it is in' => [
				'.a { list-style: square; } .a ul { {V} }',
				'<ul class="a"><li>outer<ul><li>subject</li></ul></li></ul>',
				'list-style: inherit',
				'list-style: square',
				'',
			],
			'a text shadow, which mPDF does not pass from block to block' => [
				'.a { text-shadow: 0.5mm 0.5mm #ff0000; } .a p { {V} }',
				'<div class="a"><p>subject</p></div>',
				'text-shadow: inherit',
				'text-shadow: 0.5mm 0.5mm #ff0000',
				'',
			],
			'a colour from html, on body' => [
				'html { color: #008000; } body { color: #ff0000; } body { {V} }',
				'<p>subject</p>',
				'color: inherit',
				'color: #008000',
				'color: currentcolor',
			],
			'a font size from html, on body' => [
				'html { font-size: 14pt; } body { font-size: 8pt; } body { {V} }',
				'<p>subject</p>',
				'font-size: unset',
				'font-size: 14pt',
				'font-size: auto',
			],
			'a font size from body' => [
				'body { font-size: 14pt; } p { font-size: 8pt; } p.k { {V} }',
				'<p class="k">subject</p><p>near miss</p>',
				'font-size: inherit',
				'font-size: 14pt',
				'font-size: auto',
			],
		]);
	}
}
