<?php

namespace Mpdf;

use Mpdf\Css\TextDecorations;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * The text-decoration longhands, the style, colour and thickness parts of the shorthand, and text-underline-offset and
 * text-underline-position, which mPDF reads under CssMode::STANDARD only (#607)
 *
 * @group decorations
 */
class TextDecorationLonghandsTest extends TestCase
{

	use DrawnStyles;
	use PageStreams;

	/**
	 * A line stroked across the text, as the page stream gives it: x and y where it starts, and x where it ends
	 */
	const LINE = '/(-?[\d.]+) (-?[\d.]+) m (-?[\d.]+) [\d.]+ l S/';

	/**
	 * text-decoration-line draws the lines it names, as the shorthand does, and only under standard
	 */
	public function testTheLineLonghandDrawsItsLines()
	{
		$html = '<p style="text-decoration-line: underline overline">qq</p>';

		$this->assertSame(['underline', 'overline'], array_keys($this->decorationsOf('qq', $html)));
		$this->assertSame([], $this->decorationsOf('qq', $html, CssMode::LEGACY));
	}

	/**
	 * The style, colour and thickness of the shorthand, in any order, are kept with the line they draw
	 */
	public function testTheShorthandSetsTheStyleColourAndThickness()
	{
		$underline = $this->decorationsOf('qq', '<p style="text-decoration: #f00 wavy underline 1mm">qq</p>')['underline'];

		$this->assertSame('wavy', $underline['style']);
		$this->assertSame('1.000 0.000 0.000 RG', $underline['color']);
		$this->assertEqualsWithDelta(1.0, $underline['thickness'], 0.0001);
	}

	/**
	 * Legacy mode draws the shorthand's lines as it always did: a plain line in the text colour
	 */
	public function testLegacyDrawsAPlainLineInTheTextColour()
	{
		$underline = $this->decorationsOf('qq', '<p style="text-decoration: underline wavy #f00 1mm">qq</p>', CssMode::LEGACY)['underline'];

		$this->assertArrayNotHasKey('style', $underline);
		$this->assertArrayNotHasKey('thickness', $underline);
		$this->assertSame('0.000 G', $underline['color']);
	}

	/**
	 * A later longhand overrides the shorthand's part, and the shorthand resets a longhand set before it
	 */
	public function testTheShorthandAndItsLonghandsCascade()
	{
		$later = $this->decorationsOf('qq', '<p style="text-decoration: underline wavy; text-decoration-style: dashed">qq</p>')['underline'];
		$this->assertSame('dashed', $later['style']);

		$reset = $this->decorationsOf('qq', '<p style="text-decoration-style: dashed; text-decoration: underline">qq</p>')['underline'];
		$this->assertArrayNotHasKey('style', $reset);

		$rule = $this->decorationsOf('qq', '<style>p { text-decoration-color: #00f }</style><p style="text-decoration: underline">qq</p>')['underline'];
		$this->assertSame('0.000 G', $rule['color'], 'The shorthand in the style attribute resets the colour to currentColor');
	}

	/**
	 * text-decoration-color on a nested span colours the span's own line, and the line a paragraph sets keeps the
	 * paragraph's decoration colour over a span in another colour
	 */
	public function testTheColourIsTheDecoratingElements()
	{
		$own = $this->decorationsOf('qq', '<p>aa <span style="text-decoration: underline; text-decoration-color: #00f">qq</span></p>');
		$this->assertSame('0.000 0.000 1.000 RG', $own['underline']['color']);

		$html = '<p style="text-decoration: underline; text-decoration-color: #f00">aa <span style="color: #00f; text-decoration-color: #0f0">qq</span></p>';
		$this->assertSame('1.000 0.000 0.000 RG', $this->decorationsOf('qq', $html)['underline']['color']);
	}

	/**
	 * A descendant's own decoration of the same kind is drawn as it says, not in the style of the one around it
	 */
	public function testAnOwnDecorationDoesNotKeepTheStyleItReplaces()
	{
		$html = '<p style="text-decoration: underline wavy 1mm">aa <span style="text-decoration: underline">qq</span></p>';
		$underline = $this->decorationsOf('qq', $html)['underline'];

		$this->assertArrayNotHasKey('style', $underline);
		$this->assertArrayNotHasKey('thickness', $underline);
		$this->assertSame('wavy', $this->decorationsOf('aa', $html)['underline']['style']);
	}

	/**
	 * Each style strokes the line in its own way, and solid as mPDF always has
	 *
	 * @dataProvider styles
	 *
	 * @param string $style
	 * @param string $pattern What the page stream draws after the line width is set
	 */
	public function testEachStyleIsStroked($style, $pattern)
	{
		$stream = $this->firstPage('<p style="text-decoration: underline ' . $style . '">qq</p>');

		$this->assertMatchesRegularExpression($pattern, $stream);
	}

	/**
	 * The five styles, and what each draws
	 *
	 * @return array[]
	 */
	public function styles()
	{
		$line = '[\d.]+ [\d.]+ m [\d.]+ [\d.]+ l S';

		return [
			'solid' => ['solid', '/ 0 j 0 J  ' . $line . '  0\.567 w/'],
			'double' => ['double', '/ 0 j 0 J  ' . $line . ' ' . $line . '  0\.567 w/'],
			'dotted' => ['dotted', '/ 0 j 0 J  1 J \[0 1\.100\] 0 d ' . $line . ' \[\] 0 d /'],
			'dashed' => ['dashed', '/ 0 j 0 J  \[1\.650 1\.100\] 0 d ' . $line . ' \[\] 0 d /'],
			'wavy' => ['wavy', '/ 0 j 0 J  1 j [\d.]+ [\d.]+ m( [\d.]+ [\d.]+ [\d.]+ [\d.]+ [\d.]+ [\d.]+ c)+ S /'],
		];
	}

	/**
	 * A double underline's second line lies two thicknesses below the first, and a double line-through's two lines lie
	 * either side of where the single line would be
	 */
	public function testADoubleLineIsTwoLinesAThicknessApart()
	{
		$under = $this->lines('<p style="text-decoration: underline double 1pt">qq</p>');
		$this->assertCount(2, $under);
		$this->assertEqualsWithDelta(2.0, $under[0][1] - $under[1][1], 0.002);

		$single = $this->lines('<p style="text-decoration: line-through 1pt">qq</p>');
		$through = $this->lines('<p style="text-decoration: line-through double 1pt">qq</p>');
		$this->assertEqualsWithDelta($single[0][1] + 1, $through[0][1], 0.002);
		$this->assertEqualsWithDelta($single[0][1] - 1, $through[1][1], 0.002);
	}

	/**
	 * A wavy line runs the width of the text in whole half waves, alternating either side of the line
	 */
	public function testAWavyLineSpansTheText()
	{
		$solid = $this->lines('<p style="text-decoration: underline">qqqq</p>')[0];
		$stream = $this->firstPage('<p style="text-decoration: underline wavy">qqqq</p>');

		preg_match('/1 j ([\d.]+) ([\d.]+) m(( [\d.]+){6} c)+ S/', $stream, $wave);
		preg_match_all('/([\d.]+) ([\d.]+) [\d.]+ [\d.]+ ([\d.]+) ([\d.]+) c/', $wave[0], $arches, PREG_SET_ORDER);

		$this->assertSame($solid[0], (float) $wave[1]);
		$this->assertEqualsWithDelta($solid[2], (float) end($arches)[3], 0.002);
		$this->assertLessThan((float) $wave[2], (float) $arches[0][2], 'The first arch dips below the line');
		$this->assertGreaterThan((float) $wave[2], (float) $arches[1][2], 'The second rises above it');
	}

	/**
	 * text-decoration-thickness sets the line width: a length, or a percentage of the font size; auto and from-font
	 * keep the font's own
	 */
	public function testTheThicknessSetsTheLineWidth()
	{
		$this->assertStringContainsString(' 2.000 w 0 j 0 J ', $this->firstPage('<p style="text-decoration: underline; text-decoration-thickness: 2pt">qq</p>'));
		$this->assertStringContainsString(' 2.200 w 0 j 0 J ', $this->firstPage('<p style="text-decoration: underline; text-decoration-thickness: 20%">qq</p>'));
		$this->assertStringContainsString(' 1.000 w 0 j 0 J ', $this->firstPage('<p style="text-decoration: line-through; text-decoration-thickness: 1pt">qq</p>'));
		$this->assertStringContainsString(' 1.000 w 0 j 0 J ', $this->firstPage('<p style="text-decoration: overline 1pt">qq</p>'));

		$font = $this->firstPage('<p style="text-decoration: underline">qq</p>');
		foreach (['auto', 'from-font'] as $keyword) {
			$this->assertSame($font, $this->firstPage('<p style="text-decoration: underline; text-decoration-thickness: ' . $keyword . '">qq</p>'), $keyword);
		}
	}

	/**
	 * text-underline-offset puts the top of the underline that far below the baseline, and moves no other line
	 */
	public function testTheOffsetIsFromTheBaseline()
	{
		$zero = $this->underlineCentre('<p style="text-decoration: underline 1pt; text-underline-offset: 0">gq</p>');
		$three = $this->underlineCentre('<p style="text-decoration: underline 1pt; text-underline-offset: 3pt">gq</p>');
		$em = $this->underlineCentre('<p style="text-decoration: underline 1pt; text-underline-offset: 0.5em">gq</p>');
		$baseline = $this->baseline('<p style="text-decoration: underline 1pt; text-underline-offset: 0">gq</p>');

		$this->assertEqualsWithDelta($baseline - 0.5, $zero, 0.002);
		$this->assertEqualsWithDelta($zero - 3, $three, 0.002);
		$this->assertEqualsWithDelta($zero - 5.5, $em, 0.002);

		$through = '<p style="text-decoration: line-through">gq</p>';
		$this->assertSame($this->lines($through), $this->lines('<p style="text-decoration: line-through; text-underline-offset: 3pt">gq</p>'));
	}

	/**
	 * text-underline-position: under puts the underline below the descenders of a font that has them, and an offset is
	 * taken from there
	 */
	public function testUnderPutsTheLineBelowTheDescenders()
	{
		$font = $this->underlineCentre('<p style="text-decoration: underline 1pt">gq</p>', 'utf-8');
		$under = $this->underlineCentre('<p style="text-decoration: underline 1pt; text-underline-position: under">gq</p>', 'utf-8');
		$offset = $this->underlineCentre('<p style="text-decoration: underline 1pt; text-underline-position: under; text-underline-offset: 1pt">gq</p>', 'utf-8');

		$this->assertLessThan($font, $under);
		$this->assertEqualsWithDelta($under - 1, $offset, 0.002);
	}

	/**
	 * text-underline-offset and text-underline-position are inherited, and read where the underline is set
	 */
	public function testTheOffsetIsInherited()
	{
		$own = $this->decorationsOf('qq', '<p><span style="text-decoration: underline; text-underline-offset: 2mm">qq</span></p>');
		$this->assertEqualsWithDelta(2.0, $own['underline']['offset'], 0.0001);

		$inline = $this->decorationsOf('qq', '<p style="text-underline-offset: 2mm">aa <span style="text-decoration: underline">qq</span></p>');
		$this->assertEqualsWithDelta(2.0, $inline['underline']['offset'], 0.0001);

		$block = $this->decorationsOf('qq', '<div style="text-underline-offset: 2mm; text-underline-position: under"><p style="text-decoration: underline">qq</p></div>');
		$this->assertEqualsWithDelta(2.0, $block['underline']['offset'], 0.0001);
		$this->assertTrue($block['underline']['under']);

		// An em is the font size of the element that declares it
		$em = $this->decorationsOf('qq', '<div style="font-size: 10pt; text-underline-offset: 1em"><p style="font-size: 20pt; text-decoration: underline">qq</p></div>');
		$this->assertEqualsWithDelta(10 / Mpdf::SCALE, $em['underline']['offset'], 0.001);

		$cell = $this->decorationsOf('qq', '<table style="text-underline-offset: 2mm"><tr><td style="text-decoration: underline">qq</td></tr></table>');
		$this->assertEqualsWithDelta(2.0, $cell['underline']['offset'], 0.0001);
	}

	/**
	 * The longhands take the CSS-wide keywords
	 */
	public function testTheLonghandsTakeTheCssWideKeywords()
	{
		$html = '<p style="text-decoration: underline wavy #f00; text-decoration-style: initial; text-decoration-color: initial">qq</p>';
		$underline = $this->decorationsOf('qq', $html)['underline'];
		$this->assertArrayNotHasKey('style', $underline);
		$this->assertSame('0.000 G', $underline['color']);

		$html = '<div style="text-decoration-color: #00f"><p style="text-decoration: underline; text-decoration-color: inherit">qq</p></div>';
		$this->assertSame('0.000 0.000 1.000 RG', $this->decorationsOf('qq', $html)['underline']['color']);
	}

	/**
	 * A legacy document is drawn as it was before the longhands were read
	 */
	public function testLegacyIgnoresTheLonghands()
	{
		$html = '<p style="text-decoration: underline; text-decoration-style: wavy; text-decoration-color: #f00; text-decoration-thickness: 2mm; text-underline-offset: 3mm">qq</p>';

		$this->assertSame(
			$this->firstPage('<p style="text-decoration: underline">qq</p>', 'c', CssMode::LEGACY),
			$this->firstPage($html, 'c', CssMode::LEGACY)
		);
	}

	/**
	 * stroke() falls back to a plain line for a style it does not know
	 */
	public function testAnUnknownStyleIsAPlainLine()
	{
		$this->assertSame('1.000 2.000 m 4.000 2.000 l S', TextDecorations::stroke('groove', 1, 2, 3, 1, TextDecorations::UNDER));
	}

	/**
	 * @param string $text A piece of text the document draws
	 * @param string $html
	 * @param string $mode
	 *
	 * @return array[] The decorations drawn over the text, keyed by name, as TextRecordingMpdf records them
	 */
	private function decorationsOf($text, $html, $mode = CssMode::STANDARD)
	{
		$mpdf = $this->drawDocument($html, ['cssMode' => $mode]);

		return $this->keyedByText($mpdf, $mpdf->drawnDecorations)[$text];
	}

	/**
	 * @param string $html
	 * @param string $mode mPDF's mode, such as c for the core fonts
	 * @param string $cssMode
	 *
	 * @return string The content stream of the document's first page
	 */
	private function firstPage($html, $mode = 'c', $cssMode = CssMode::STANDARD)
	{
		return $this->pages($this->render($html, ['mode' => $mode, 'cssMode' => $cssMode]))[0];
	}

	/**
	 * @param string $html
	 * @param string $mode
	 *
	 * @return float[][] Each straight line stroked on the first page: x and y where it starts, x where it ends, in PDF
	 *                   units
	 */
	private function lines($html, $mode = 'c')
	{
		preg_match_all(self::LINE, $this->firstPage($html, $mode), $matches, PREG_SET_ORDER);

		return array_map(function ($match) {
			return [(float) $match[1], (float) $match[2], (float) $match[3]];
		}, $matches);
	}

	/**
	 * @param string $html
	 * @param string $mode
	 *
	 * @return float Where the first line's centre runs, in PDF units up the page
	 */
	private function underlineCentre($html, $mode = 'c')
	{
		return $this->lines($html, $mode)[0][1];
	}

	/**
	 * @param string $html
	 *
	 * @return float Where the first text is set, in PDF units up the page
	 */
	private function baseline($html)
	{
		preg_match('/BT [\d.]+ ([\d.]+) Td/', $this->firstPage($html), $match);

		return (float) $match[1];
	}
}
