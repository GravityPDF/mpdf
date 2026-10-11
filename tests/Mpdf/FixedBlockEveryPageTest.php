<?php

namespace Mpdf;

/**
 * In paged media a position: fixed block is drawn on every page (CSS 2.1 section 9.3.1). In standard cssMode mPDF
 * draws it on each page from the one it appears on, placed by that page's size and margins.
 */
class FixedBlockEveryPageTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;

	const FIXED = '<div style="position: fixed; top: 5mm; left: 5mm">FIXEDX</div>';

	/**
	 * Where each page draws the text FIXEDX, as the operands of its Td, one array per page
	 *
	 * @param string $pdf
	 *
	 * @return string[][]
	 */
	private function placements($pdf)
	{
		$placements = [];
		foreach ($this->pageContents($pdf) as $stream) {
			preg_match_all('/([\d.]+ [\d.]+) Td\s+\(FIXEDX\) Tj/', $stream, $m);
			$placements[] = $m[1];
		}

		return $placements;
	}

	/**
	 * How many times each page draws FIXEDX
	 *
	 * @param string $pdf
	 *
	 * @return int[]
	 */
	private function counts($pdf)
	{
		return array_map('count', $this->placements($pdf));
	}

	/**
	 * A one-page document draws the block once
	 */
	public function testOnePage()
	{
		$this->assertSame([1], $this->counts($this->render(self::FIXED . '<p>Text</p>')));
	}

	/**
	 * A six-page document draws the block once on each page, in the same place
	 */
	public function testSixPages()
	{
		$placements = $this->placements($this->render(self::FIXED . $this->filler(160)));

		$this->assertCount(6, $placements);
		foreach ($placements as $page) {
			$this->assertSame($placements[0], $page);
		}
		$this->assertCount(1, $placements[0]);
	}

	/**
	 * A block after a forced page break is drawn from the page it appears on
	 */
	public function testFromThePageItAppearsOn()
	{
		$html = '<p>First</p><pagebreak />' . self::FIXED . $this->filler(40);

		$this->assertSame([0, 1, 1], $this->counts($this->render($html)));
	}

	/**
	 * Percentages are of the page area of the page it is drawn on, so it moves with the page's size
	 */
	public function testPercentagesOfEachPage()
	{
		$html = '<div style="position: fixed; top: 50%; left: 50%">FIXEDX</div><p>A4</p>'
			. '<pagebreak sheet-size="A5" /><p>A5</p>';
		$placements = $this->placements($this->render($html));
		list($a4x, $a4y) = explode(' ', $placements[0][0]);
		list($a5x, $a5y) = explode(' ', $placements[1][0]);

		// The page area is inside 15mm side margins and 16mm top and bottom ones
		$this->assertEqualsWithDelta((15 + 180 / 2) * Mpdf::SCALE, (float) $a4x, 0.01);
		$this->assertEqualsWithDelta((15 + 118 / 2) * Mpdf::SCALE, (float) $a5x, 0.01);
		// The top is 50% down the page area: 148.5mm on A4 and 105mm on A5, each measured up from its own foot
		$this->assertEqualsWithDelta((297 - 148.5 - (210 - 105)) * Mpdf::SCALE, $a4y - $a5y, 0.01);
	}

	/**
	 * Pages that HTML written afterwards adds, or AddPage() alone, carry it too
	 */
	public function testPagesAddedLater()
	{
		$mpdf = $this->mpdf();
		$mpdf->WriteHTML(self::FIXED . '<p>First</p>');
		$mpdf->WriteHTML('<pagebreak /><p>Second</p>');
		$mpdf->AddPage();

		$this->assertSame([1, 1, 1], $this->counts($this->output($mpdf)));
	}

	/**
	 * An absolutely positioned block stays on the page it appears on
	 */
	public function testAbsoluteStaysOnItsPage()
	{
		$html = '<div style="position: absolute; top: 5mm; left: 5mm">FIXEDX</div>' . $this->filler(40);

		$this->assertSame([1, 0], $this->counts($this->render($html)));
	}

	/**
	 * With a negative z-index, as a watermark, it goes under the content of every page
	 */
	public function testUnderTheContentOfEveryPage()
	{
		$html = '<div style="position: fixed; top: 5mm; left: 5mm; z-index: -1">FIXEDX</div>' . $this->filler(40);

		foreach ($this->pageContents($this->render($html)) as $stream) {
			$this->assertLessThan(strpos($stream, '(Filler) Tj'), strpos($stream, '(FIXEDX) Tj'));
		}
	}

	/**
	 * The legacy cssMode draws it on the page it appears on alone
	 */
	public function testLegacyDrawsItOnce()
	{
		$this->assertSame([1, 0], $this->counts($this->render(self::FIXED . $this->filler(40), ['cssMode' => CssMode::LEGACY])));
	}
}
