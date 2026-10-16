<?php

namespace Mpdf;

class ColumnBackgroundAlphaTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{
	use PageStreams;

	/**
	 * Each translucent fill in a page stream, as [graphics state name, colour, left edge of its first rectangle, how
	 * many rectangles its one path is made of]
	 *
	 * @param string $page
	 *
	 * @return array[]
	 */
	private function translucentFills($page)
	{
		$number = '[\d.\-]+';
		$rect = "$number $number $number re ";
		preg_match_all("#q /(GS\\d+) gs ([\\d. ]+ (?:rg|k)) (($number) $rect(?:$number $rect)*)f Q#", $page, $matches, PREG_SET_ORDER);

		$fills = [];
		foreach ($matches as $m) {
			$fills[] = [$m[1], $m[2], $m[4], substr_count($m[3], ' re ')];
		}

		return $fills;
	}

	/**
	 * How many rectangles a page stream fills outside the translucent fills
	 *
	 * @param string $page
	 *
	 * @return int
	 */
	private function opaqueFills($page)
	{
		return preg_match_all('# re f\s#', preg_replace('#q /GS\d+ gs .*? f Q#', '', $page));
	}

	/**
	 * The fill opacity of each ExtGState the document defines, by name
	 *
	 * @param string $pdf
	 *
	 * @return string[]
	 */
	private function fillOpacities($pdf)
	{
		preg_match_all('#/(GS\d+) (\d+) 0 R#', $pdf, $refs, PREG_SET_ORDER);

		$opacities = [];
		foreach ($refs as $ref) {
			if (preg_match('#/Type /ExtGState[^>]*/ca ([\d.]+)#', $this->object($pdf, $ref[2]), $m)) {
				$opacities[$ref[1]] = $m[1];
			}
		}

		return $opacities;
	}

	/**
	 * Asserts that no two translucent fills of one colour on a page start in the same column, where they would
	 * overlap or meet
	 *
	 * @param array[] $fills
	 */
	private function assertOneFillPerColumn($fills)
	{
		$columns = [];
		foreach ($fills as $fill) {
			$columns[] = $fill[1] . ' at ' . $fill[2];
		}

		$this->assertSame(array_unique($columns), $columns);
	}

	/**
	 * A two-column section holding $body
	 *
	 * @param string $body
	 * @param string $valign
	 *
	 * @return string
	 */
	private function columns($body, $valign = '')
	{
		return '<columns column-count="2" vAlign="' . $valign . '" />' . $body . '<columns column-count="0" />';
	}

	/**
	 * $count paragraphs of a few lines each
	 *
	 * @param int $count
	 *
	 * @return string
	 */
	private function paragraphs($count)
	{
		return str_repeat('<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.</p>', $count);
	}

	/**
	 * A block's rgba() background in columns is painted with its alpha, as it is outside them
	 */
	public function testABlockBackgroundInColumnsKeepsItsAlpha()
	{
		$pdf = $this->render($this->columns('<div style="background-color: rgba(255, 255, 255, 0.8)"><p>aaa</p></div>'));
		$page = $this->pages($pdf)[0];

		$fills = $this->translucentFills($page);
		$opacities = $this->fillOpacities($pdf);

		$this->assertNotEmpty($fills);
		$this->assertSame(0, $this->opaqueFills($page));
		foreach ($fills as $fill) {
			$this->assertSame(['1.000 1.000 1.000 rg', '0.8'], [$fill[1], $opacities[$fill[0]]]);
		}
	}

	/**
	 * cmyka() carries its alpha in a different place from rgba()
	 */
	public function testACmykaBlockBackgroundInColumnsKeepsItsAlpha()
	{
		$pdf = $this->render($this->columns('<div style="background-color: cmyka(0, 50, 100, 0, 0.4)"><p>aaa</p></div>'));

		$fills = $this->translucentFills($this->pages($pdf)[0]);
		$opacities = $this->fillOpacities($pdf);

		$this->assertNotEmpty($fills);
		foreach ($fills as $fill) {
			$this->assertSame(['0.000 0.500 1.000 0.000 k', '0.4'], [$fill[1], $opacities[$fill[0]]]);
		}
	}

	/**
	 * The background is laid down a line at a time, but each column's part of it is painted as one shape: fills
	 * meeting at an edge would paint it twice and show a darker band there
	 *
	 * @param string $valign
	 *
	 * @dataProvider verticalAlignments
	 */
	public function testEachColumnOfATranslucentBlockIsPaintedOnce($valign)
	{
		$pages = $this->pages($this->render($this->columns('<div style="background-color: rgba(255, 255, 255, 0.5); padding: 3mm">' . $this->paragraphs(60) . '</div>', $valign)));

		$this->assertGreaterThan(2, count($pages));

		// The last page's columns are balanced, which moves lines between them
		foreach ($pages as $page) {
			$fills = $this->translucentFills($page);
			$this->assertOneFillPerColumn($fills);
			$this->assertGreaterThan(10, $fills[0][3]);
		}
	}

	/**
	 * Columns that are balanced when they end and columns that are stretched to the same height
	 *
	 * @return array[]
	 */
	public static function verticalAlignments()
	{
		return [
			'top' => [''],
			'justify' => ['J'],
		];
	}

	/**
	 * In columns a block takes its parent's background, which the parent has already painted there. Painting it again
	 * would double its opacity
	 */
	public function testANestedBlockDoesNotPaintItsParentsBackgroundAgain()
	{
		$page = $this->pages($this->render($this->columns('<div style="background-color: rgba(255, 255, 255, 0.5); padding: 3mm"><div style="margin: 0 5mm">' . $this->paragraphs(6) . '</div>Closing line</div>')))[0];

		$fills = $this->translucentFills($page);

		$this->assertCount(2, $fills);
		$this->assertOneFillPerColumn($fills);
		$this->assertSame(0, $this->opaqueFills($page));
	}

	/**
	 * A nested block with a translucent background of its own paints it over its parent's
	 */
	public function testANestedBlockPaintsItsOwnTranslucentBackground()
	{
		$pdf = $this->render($this->columns('<div style="background-color: rgba(255, 255, 255, 0.5)"><div style="background-color: rgba(0, 0, 255, 0.3); margin: 0 5mm">' . $this->paragraphs(2) . '</div>Closing line</div>'));

		$fills = $this->translucentFills($this->pages($pdf)[0]);
		$opacities = $this->fillOpacities($pdf);

		$this->assertOneFillPerColumn($fills);
		$this->assertSame(['1.000 1.000 1.000 rg', '0.5'], [$fills[0][1], $opacities[$fills[0][0]]]);
		$this->assertSame(['0.000 0.000 1.000 rg', '0.3'], [$fills[1][1], $opacities[$fills[1][0]]]);
	}

	/**
	 * An opaque background in columns is drawn as it was, a fill per line with no graphics state of its own
	 */
	public function testAnOpaqueBackgroundInColumnsIsUnchanged()
	{
		$page = $this->pages($this->render($this->columns('<div style="background-color: #ffffff">' . $this->paragraphs(2) . '</div>')))[0];

		$this->assertSame([], $this->translucentFills($page));
		$this->assertGreaterThan(1, $this->opaqueFills($page));
	}

	/**
	 * Both CSS modes, since the bgcolorarray warnings came in each
	 *
	 * @return array[]
	 */
	public static function cssModes()
	{
		return [
			'standard' => [CssMode::STANDARD],
			'legacy' => [CssMode::LEGACY],
		];
	}

	/**
	 * A translucent block inside a block with a border but no background, in columns, renders without a warning and
	 * still paints its own translucent background
	 *
	 * @dataProvider cssModes
	 *
	 * @param string $cssMode
	 */
	public function testTranslucentBlockInBorderedBlockInColumnsRendersSilently($cssMode)
	{
		$html = $this->columns('<div style="border: 1px solid black"><div style="background: rgba(255, 0, 0, 0.5)">Text</div></div>');
		$pdf = $this->assertDrawsSilently(function (Mpdf $mpdf) use ($html) {
			$mpdf->WriteHTML($html);
		}, ['cssMode' => $cssMode]);

		$this->assertMatchesRegularExpression('#/GS\d+ gs 1\.000 0\.000 0\.000 rg#', $pdf, 'The translucent background should be painted');
	}

	/**
	 * A block in columns whose background sets no colour takes its parent's, and renders without a warning when the
	 * parent has none either
	 *
	 * @dataProvider cssModes
	 *
	 * @param string $cssMode
	 */
	public function testTransparentBlockInColumnsRendersSilently($cssMode)
	{
		$html = $this->columns('<div><div style="background-color: transparent">Text</div></div>');
		$this->assertDrawsSilently(function (Mpdf $mpdf) use ($html) {
			$mpdf->WriteHTML($html);
		}, ['cssMode' => $cssMode]);
	}

	/**
	 * A table in a block that has a border but no background, inside columns, renders without a warning: the block's
	 * fill is put back after each row only when the block has one
	 *
	 * @dataProvider cssModes
	 *
	 * @param string $cssMode
	 */
	public function testTableInBorderedBlockInColumnsRendersSilently($cssMode)
	{
		$html = $this->columns('<div style="border: 1px solid black"><table><tr><td>Cell</td></tr></table></div>');
		$this->assertDrawsSilently(function (Mpdf $mpdf) use ($html) {
			$mpdf->WriteHTML($html);
		}, ['cssMode' => $cssMode]);
	}

	/**
	 * A table header that repeats at the top of the next column, in a bordered block without a background, renders
	 * without a warning too
	 *
	 * @dataProvider cssModes
	 *
	 * @param string $cssMode
	 */
	public function testRepeatedHeaderInBorderedBlockInColumnsRendersSilently($cssMode)
	{
		$html = $this->columns('<div style="border: 1px solid black"><table><thead><tr><th>Head</th></tr></thead><tbody>' . str_repeat('<tr><td>Cell</td></tr>', 8) . '</tbody></table></div>');
		$pdf = $this->assertDrawsSilently(function (Mpdf $mpdf) use ($html) {
			$mpdf->WriteHTML($html);
		}, ['cssMode' => $cssMode, 'format' => [100, 60]]);

		$this->assertGreaterThan(1, substr_count($pdf, '(Head)'), 'The header should repeat in the next column');
	}
}
