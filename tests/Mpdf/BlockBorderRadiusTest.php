<?php

namespace Mpdf;

/**
 * A percentage border-radius on a block is of the block's own border box: a horizontal radius of its width, a vertical
 * one of its height. The radii are read from the path the block's background is painted along.
 */
class BlockBorderRadiusTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;

	/**
	 * The corners of the background path, anticlockwise from the top left, as path() runs them
	 */
	const CORNERS = ['TL', 'BL', 'BR', 'TR'];

	/**
	 * The pages of a document holding one block styled with the given CSS, 40 mm wide unless the CSS says otherwise
	 *
	 * @param string $css
	 * @param string $content
	 * @param array $config
	 *
	 * @return string[]
	 */
	private function pagesOf($css, $content = '&nbsp;', $config = [])
	{
		return $this->pages($this->render('<style>div { width: 40mm; background: #ccc; ' . $css . ' }</style><div>' . $content . '</div>', $config));
	}

	/**
	 * The path the block's background is painted along: the first path on the page that follows a zero line width
	 *
	 * @param string $page
	 *
	 * @return string
	 */
	private function backgroundPath($page)
	{
		$this->assertSame(1, preg_match('/q 0 w ((?:[-\d.]+ [-\d.]+ [ml] |(?:[-\d.]+ ){6}c )+)/', $page, $m), 'a background path is painted');

		return $m[1];
	}

	/**
	 * The background path of a block styled with the given CSS, on the first page
	 *
	 * @param string $css
	 * @param array $config
	 *
	 * @return string
	 */
	private function firstPath($css, $config = [])
	{
		return $this->backgroundPath($this->pagesOf($css, '&nbsp;', $config)[0]);
	}

	/**
	 * The radius of each rounded corner of a path, keyed TL/TR/BR/BL as [horizontal, vertical] in millimetres. A corner
	 * is a run of curves; its radii are how far the run travels along each axis. Square corners are left out.
	 *
	 * @param string $path
	 *
	 * @return array
	 */
	private function radii($path)
	{
		preg_match_all('/((?:[-\d.]+ )+)([mlc]) /', $path, $ops, PREG_SET_ORDER);

		$radii = [];
		$corner = 0;
		$point = null;
		$arcStart = null;
		foreach ($ops as $op) {
			$numbers = array_map('floatval', explode(' ', trim($op[1])));
			if ($op[2] === 'c') {
				if ($arcStart === null) {
					$arcStart = $point;
				}
			} elseif ($arcStart !== null) {
				$radii[self::CORNERS[$corner]] = [abs($point[0] - $arcStart[0]) / Mpdf::SCALE, abs($point[1] - $arcStart[1]) / Mpdf::SCALE];
				$arcStart = null;
			}
			if ($op[2] === 'l') {
				$corner++;
			}
			$point = array_slice($numbers, -2);
		}

		return $radii;
	}

	/**
	 * The height of the box a path runs round, in millimetres
	 *
	 * @param string $path
	 *
	 * @return float
	 */
	private function heightOf($path)
	{
		preg_match_all('/[-\d.]+ ([-\d.]+) [ml] /', $path, $m);
		$ys = array_map('floatval', $m[1]);

		return (max($ys) - min($ys)) / Mpdf::SCALE;
	}

	/**
	 * Every corner of the path has the given radii
	 *
	 * @param float[] $expected [horizontal, vertical] in millimetres
	 * @param string $path
	 */
	private function assertEveryCorner($expected, $path)
	{
		$radii = $this->radii($path);

		$this->assertSame(self::CORNERS, array_keys($radii), 'four rounded corners');
		foreach ($radii as $corner => $radius) {
			$this->assertEqualsWithDelta($expected, $radius, 0.01, $corner);
		}
	}

	/**
	 * 50% on a box wider than it is tall is an ellipse, not a pill
	 */
	public function testHalfOfABoxThatIsNotSquareIsAnEllipse()
	{
		$path = $this->firstPath('height: 12mm; border-radius: 50%');

		$this->assertEqualsWithDelta(12, $this->heightOf($path), 0.01);
		$this->assertEveryCorner([20, 6], $path);
	}

	/**
	 * A smaller percentage keeps the proportions of the box
	 */
	public function testATenthIsATenthOfEachSide()
	{
		$this->assertEveryCorner([4, 1.2], $this->firstPath('height: 12mm; border-radius: 10%'));
	}

	/**
	 * On a square box the two radii come out the same, a circle
	 */
	public function testHalfOfASquareIsACircle()
	{
		$this->assertEveryCorner([20, 20], $this->firstPath('height: 40mm; border-radius: 50%'));
	}

	/**
	 * The slash form gives the horizontal radii before it and the vertical after, each of its own side
	 */
	public function testTheSlashFormResolvesEachHalfAgainstItsOwnAxis()
	{
		$this->assertEveryCorner([20, 1.2], $this->firstPath('height: 12mm; border-radius: 50% / 10%'));
	}

	/**
	 * A corner longhand with two values rounds that corner alone, horizontal of the width and vertical of the height
	 */
	public function testACornerLonghandWithTwoPercentagesRoundsThatCornerAlone()
	{
		$radii = $this->radii($this->firstPath('height: 12mm; border-top-left-radius: 50% 10%'));

		$this->assertSame(['TL'], array_keys($radii));
		$this->assertEqualsWithDelta([20, 1.2], $radii['TL'], 0.01);
	}

	/**
	 * A length is unchanged by the block's size
	 */
	public function testALengthIsNotOfTheBox()
	{
		$this->assertEveryCorner([3, 3], $this->firstPath('height: 12mm; border-radius: 3mm'));
	}

	/**
	 * The misread value is corrected in the legacy CSS mode too
	 */
	public function testTheLegacyModeResolvesAgainstTheBoxToo()
	{
		$this->assertEveryCorner([20, 6], $this->firstPath('height: 12mm; border-radius: 50%', ['cssMode' => CssMode::LEGACY]));
	}

	/**
	 * Each part of a block split across pages is its own box: the first keeps its top corners, the last its bottom
	 * ones, and a vertical percentage is of the part's height
	 */
	public function testAPartOfABlockSplitAcrossPagesResolvesAgainstItsOwnHeight()
	{
		$pages = $this->pagesOf('border-radius: 50%', $this->filler(40));

		$this->assertCount(2, $pages);

		$first = $this->backgroundPath($pages[0]);
		$firstRadii = $this->radii($first);
		$this->assertSame(['TL', 'TR'], array_keys($firstRadii), 'the first part keeps its top corners');
		$this->assertEqualsWithDelta([20, $this->heightOf($first) / 2], $firstRadii['TL'], 0.01);

		$last = $this->backgroundPath($pages[1]);
		$lastRadii = $this->radii($last);
		$this->assertSame(['BL', 'BR'], array_keys($lastRadii), 'the last part keeps its bottom corners');
		$this->assertEqualsWithDelta([20, $this->heightOf($last) / 2], $lastRadii['BL'], 0.01);

		$this->assertNotEqualsWithDelta($this->heightOf($first), $this->heightOf($last), 1, 'the parts differ in height');
	}
}
