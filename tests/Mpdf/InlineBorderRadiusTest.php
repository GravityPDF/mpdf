<?php

namespace Mpdf;

/**
 * border-radius on inline elements, in standard mode: the background and border of each piece of the element's text
 * follow the curve, with only the first piece's left corners and the last piece's right corners rounded, as with
 * box-decoration-break: slice. Legacy mode draws them square, as before.
 */
class InlineBorderRadiusTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;

	/**
	 * Each rounded background filled on a page: which of its corners are rounded, keyed TL/BL/BR/TR, and its bounding
	 * box in millimetres from the top left of an A4 page
	 *
	 * @param string $stream
	 *
	 * @return array[] Keyed corners and box
	 */
	private function roundedFills($stream)
	{
		preg_match_all('/([-\d.]+ [-\d.]+ m ((?:(?:[-\d.]+ ){2}l |(?:[-\d.]+ ){6}c )+))h f /', $stream, $matches, PREG_SET_ORDER);

		$fills = [];
		foreach ($matches as $match) {
			// RoundedBox::path() runs from the top left anticlockwise: an arc for each rounded corner, then a line
			$groups = explode(' l ', ' ' . $match[2]);
			$corners = [];
			foreach (['TL', 'BL', 'BR', 'TR'] as $i => $corner) {
				$corners[$corner] = substr_count($groups[$i], ' c') > 0;
			}
			$fills[] = ['corners' => $corners, 'box' => $this->bounds($match[1])];
		}

		return $fills;
	}

	/**
	 * The bounding box of a path, in millimetres from the top left of an A4 page
	 *
	 * @param string $path
	 *
	 * @return float[]
	 */
	private function bounds($path)
	{
		$numbers = array_map('floatval', preg_split('/\s+/', trim(preg_replace('/[mlch]/', ' ', $path))));
		$xs = $ys = [];
		for ($i = 0; $i + 1 < count($numbers); $i += 2) {
			$xs[] = $numbers[$i] / Mpdf::SCALE;
			$ys[] = 297 - $numbers[$i + 1] / Mpdf::SCALE;
		}

		return [round(min($xs), 2), round(min($ys), 2), round(max($xs), 2), round(max($ys), 2)];
	}

	/**
	 * Which corners of each rounded background are rounded, in the order they are drawn
	 *
	 * @param string $stream
	 *
	 * @return array[]
	 */
	private function roundedCorners($stream)
	{
		return array_column($this->roundedFills($stream), 'corners');
	}

	/**
	 * A span's background follows its rounded corners
	 */
	public function testBackgroundIsRounded()
	{
		$page = $this->pages($this->render('<p>Before <span style="background: #0f0; border-radius: 2mm">rounded</span> after</p>'))[0];

		$this->assertSame([['TL' => true, 'BL' => true, 'BR' => true, 'TR' => true]], $this->roundedCorners($page));
	}

	/**
	 * Legacy mode draws the background square, as before
	 */
	public function testLegacyModeDrawsItSquare()
	{
		$page = $this->pages($this->render('<p>Before <span style="background: #0f0; border: 1mm solid #00f; border-radius: 2mm">rounded</span> after</p>', ['cssMode' => 'legacy']))[0];

		$this->assertSame([], $this->roundedFills($page));
		$this->assertDoesNotMatchRegularExpression('/(?:[-\d.]+ ){6}c /', $page);
	}

	/**
	 * A span that wraps rounds the left corners of its first piece and the right corners of its last; a piece in
	 * between is square on both sides
	 */
	public function testWrappedSpanRoundsOnlyItsEnds()
	{
		$page = $this->pages($this->render('<div style="width: 50mm"><span style="background: #0f0; border-radius: 2mm">one two three four five six seven eight nine ten eleven twelve thirteen</span></div>'))[0];

		$this->assertSame(
			[
				['TL' => true, 'BL' => true, 'BR' => false, 'TR' => false],
				['TL' => false, 'BL' => false, 'BR' => true, 'TR' => true],
			],
			$this->roundedCorners($page)
		);
		// The middle line's piece is drawn as a plain rectangle
		$this->assertMatchesRegularExpression('/re f /', $page);
	}

	/**
	 * An element inside the span, such as a bold word, does not start a box of its own: the span's first piece is
	 * rounded on the left and its last on the right, and the bold word between them on neither
	 */
	public function testNestedElementJoinsTheBox()
	{
		$page = $this->pages($this->render('<p><span style="background: #0f0; border-radius: 2mm">a <b>bold</b> word</span></p>'))[0];

		$this->assertSame(
			[
				['TL' => true, 'BL' => true, 'BR' => false, 'TR' => false],
				['TL' => false, 'BL' => false, 'BR' => true, 'TR' => true],
			],
			$this->roundedCorners($page)
		);
	}

	/**
	 * An element inside the span with a border of its own draws a box of its own, which the span's radius does not
	 * round
	 */
	public function testNestedBorderedElementIsSquare()
	{
		$page = $this->pages($this->render('<p><span style="background: #0f0; border-radius: 2mm">a <span style="border: 0.5mm solid #f00">boxed</span> word</span></p>'))[0];

		$this->assertSame(
			[
				['TL' => true, 'BL' => true, 'BR' => true, 'TR' => true],
				['TL' => true, 'BL' => true, 'BR' => true, 'TR' => true],
			],
			$this->roundedCorners($page)
		);
	}

	/**
	 * Two neighbouring spans rounded alike are two boxes, each rounded at both ends
	 */
	public function testNeighbouringSpansAreRoundedApart()
	{
		$page = $this->pages($this->render('<p><span style="background: #0f0; border-radius: 2mm">first</span><span style="background: #0f0; border-radius: 2mm">second</span></p>'))[0];

		$all = ['TL' => true, 'BL' => true, 'BR' => true, 'TR' => true];
		$this->assertSame([$all, $all], $this->roundedCorners($page));
	}

	/**
	 * A percentage is taken of the piece's own box: 50% of a box wider than it is tall gives each corner a vertical
	 * radius of half the height and a horizontal one of half the width
	 */
	public function testPercentageIsOfThePiece()
	{
		$page = $this->pages($this->render('<p><span style="background: #0f0; border-radius: 50%">ellipse</span></p>'))[0];

		list($fill) = $this->roundedFills($page);
		list($x0, $y0, $x1, $y1) = $fill['box'];
		// The path starts on the top edge, past the top left corner's horizontal radius
		preg_match('/([-\d.]+) [-\d.]+ m (?:(?:[-\d.]+ ){6}c )+[-\d.]+ ([-\d.]+) l /', $page, $start);
		$this->assertEqualsWithDelta($x0 + ($x1 - $x0) / 2, $start[1] / Mpdf::SCALE, 0.02);
		$this->assertEqualsWithDelta($y0 + ($y1 - $y0) / 2, 297 - $start[2] / Mpdf::SCALE, 0.02);
	}

	/**
	 * A solid border follows the curve: each side is filled between the border and padding edges, clipped to its
	 * part of the box, instead of stroked as a straight line
	 */
	public function testSolidBorderFollowsTheCurve()
	{
		$page = $this->pages($this->render('<p><span style="border: 1mm solid #0000ff; border-radius: 2mm">rounded</span></p>'))[0];

		$this->assertSame(4, preg_match_all('/W n 0\.000 0\.000 1\.000 rg (?:[-\d.]+ [-\d.]+ [ml] |(?:[-\d.]+ ){6}c |h )+f\* Q/', $page));
		$this->assertStringNotContainsString('0.000 0.000 1.000 RG', $page);
	}

	/**
	 * A dotted border is stroked in round dots along the middle of the border, through the curve
	 */
	public function testDottedBorderIsStrokedAlongTheCurve()
	{
		$page = $this->pages($this->render('<p><span style="border: 1mm dotted #0000ff; border-radius: 2mm">rounded</span></p>'))[0];

		$this->assertSame(4, preg_match_all('/q 0\.000 0\.000 1\.000 RG 2\.835 w 1 J 1 j \[0 5\.669\] 0 d (?:[-\d.]+ [-\d.]+ [ml] |(?:[-\d.]+ ){6}c )+S Q/', $page));
	}

	/**
	 * A groove keeps its two tones on a rounded span
	 */
	public function testGrooveIsTwoToned()
	{
		$page = $this->pages($this->render('<p><span style="border: 1mm groove #3366cc; border-radius: 2mm">rounded</span></p>'))[0];

		// Two rings on each of the four sides
		$this->assertSame(8, preg_match_all('/rg (?:[-\d.]+ [-\d.]+ [ml] |(?:[-\d.]+ ){6}c |h )+f\* /', $page));
	}

	/**
	 * The outline of a rounded span follows its corners
	 */
	public function testOutlineFollowsTheCorners()
	{
		$page = $this->pages($this->render('<p><span style="border-radius: 2mm; outline: 0.5mm solid #ff0000">rounded</span></p>'))[0];

		$this->assertMatchesRegularExpression('/1\.000 0\.000 0\.000 rg [-\d.]+ [-\d.]+ m (?:[-\d.]+ ){6}c /', $page);
	}
}
