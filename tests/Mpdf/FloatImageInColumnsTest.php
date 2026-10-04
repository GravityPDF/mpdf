<?php

namespace Mpdf;

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * A floated image in a column wraps the column's text around it, as it does outside columns, and balancing the
 * columns keeps it with the lines beside it.
 *
 * Positions are read back from the uncompressed page stream, where each line is drawn with one Td at its baseline
 * and the image with one cm, after balancing has moved them. The text is in a core font, so a line's width can be
 * measured with the same metrics that set it.
 */
class FloatImageInColumnsTest extends TestCase
{

	use PageStreams;

	/**
	 * The height of an A4 page, in points
	 */
	const PAGE_HEIGHT = 841.89;

	/**
	 * A little more than a line's height, in points
	 */
	const LINE = 15;

	/**
	 * Measures the width of a line of text
	 *
	 * @var \Mpdf\Mpdf
	 */
	private $ruler;

	/**
	 * A document to measure lines with, in the font and size the documents here are set in
	 */
	protected function set_up()
	{
		parent::set_up();

		$this->ruler = $this->mpdf();
		$this->ruler->SetFont('ctimes', '', 11);
	}

	/**
	 * Wherever balancing splits the columns, the lines level with the image in its column are set clear of it, and
	 * every other line, including those in the other column level with the image, has the column's full width
	 *
	 * @dataProvider layoutProvider
	 *
	 * @param string $side The side the image floats to, L or R
	 * @param string $dir The document's direction
	 * @param int $lead The words ahead of the image's paragraph
	 * @param string $columns The markup that starts the columns
	 * @param string $between Markup between the lead and the image's paragraph
	 * @param array $config
	 */
	public function testLinesAreNarrowedOnlyBesideTheImage($side, $dir, $lead, $columns = '<columns column-count="2" />', $between = '', $config = [])
	{
		$mpdf = $this->mpdf($config);
		if ($dir === 'rtl') {
			$mpdf->SetDirectionality('rtl');
		}
		$mpdf->WriteHTML($columns . '<p>' . str_repeat('alpha ', $lead) . '</p>' . $between
			. '<p><img src="' . $this->pngImage() . '" style="float: ' . ($side === 'L' ? 'left' : 'right') . '; width: 30mm; height: 30mm">'
			. str_repeat('beta ', 150) . '</p><p>' . str_repeat('gamma ', 150) . '</p>');
		$pages = $this->pageContents($this->output($mpdf));

		$image = $this->image($pages[0]);
		$lines = $this->lines($pages[0]);
		$column = $this->columnOf($mpdf, $image['left'] + 1, $image['right'] - 1);
		$this->assertNotNull($column, 'The image should lie within a column');

		$beside = 0;
		$levelInOtherColumn = 0;
		foreach ($lines as $line) {
			$lineColumn = $this->columnOf($mpdf, $line['x0'], $line['x1']);
			$this->assertNotNull($lineColumn, 'Each line should lie within a column: ' . $line['text']);

			// A line is set beside the image when the top of its box is, which is some way above its baseline
			$level = $line['baseline'] >= $image['top'] + self::LINE && $line['baseline'] <= $image['bottom'];
			$edge = !$level && $line['baseline'] > $image['top'] - self::LINE && $line['baseline'] < $image['bottom'] + self::LINE;
			if ($lineColumn === $column && $level) {
				$beside++;
				if ($side === 'L') {
					$this->assertGreaterThanOrEqual($image['right'] - 0.01, $line['x0'], 'A line beside the image should start clear of it: ' . $line['text']);
				} else {
					$this->assertLessThanOrEqual($image['left'] + 0.01, $line['x1'], 'A line beside the image should end clear of it: ' . $line['text']);
				}
				continue;
			}

			if ($lineColumn === $column && $edge) {
				continue;
			}

			if ($lineColumn !== $column && $level) {
				$levelInOtherColumn++;
			}

			if ($dir === 'rtl') {
				$this->assertEqualsWithDelta($mpdf->ColR[$lineColumn] * Mpdf::SCALE, $line['x1'], 0.05, 'A line away from the image should end at its column\'s edge: ' . $line['text']);
			} else {
				$this->assertEqualsWithDelta($mpdf->ColL[$lineColumn] * Mpdf::SCALE, $line['x0'], 0.05, 'A line away from the image should start at its column\'s edge: ' . $line['text']);
			}
		}

		$this->assertGreaterThanOrEqual(4, $beside, 'The lines beside the image should have gone with it into its column');
		if ($between === '' && empty($config['keepColumns'])) {
			$this->assertGreaterThan(0, $levelInOtherColumn, 'The other column should have lines level with the image');
		}
	}

	/**
	 * The combinations of side, direction and column breaking, with the lead long enough to put the image near
	 * the top, the middle and the bottom of the first column, and past it
	 *
	 * @return array[]
	 */
	public function layoutProvider()
	{
		$cases = [];
		foreach ([40, 120, 200, 260, 300, 340, 420] as $lead) {
			$cases['left, ltr, balanced, lead ' . $lead] = ['L', 'ltr', $lead];
			$cases['right, rtl, balanced, lead ' . $lead] = ['R', 'rtl', $lead];
			$cases['right, ltr, balanced, lead ' . $lead] = ['R', 'ltr', $lead];
			$cases['left, ltr, vAlign J with no stretch, lead ' . $lead] = ['L', 'ltr', $lead, '<columns column-count="2" vAlign="J" />', '', ['max_colH_correction' => 1]];
			$cases['left, ltr, kept columns, lead ' . $lead] = ['L', 'ltr', $lead, '<columns column-count="2" />', '', ['keepColumns' => true]];
		}
		$cases['left, ltr, after a column break'] = ['L', 'ltr', 40, '<columns column-count="2" />', '<columnbreak />'];
		$cases['right, rtl, after a column break'] = ['R', 'rtl', 40, '<columns column-count="2" />', '<columnbreak />'];

		return $cases;
	}

	/**
	 * An image too tall for any column is set in its line as it was before floats were allowed in columns
	 */
	public function testAnImageTallerThanAColumnIsSetInline()
	{
		$mpdf = $this->mpdf();
		$mpdf->WriteHTML('<columns column-count="2" /><p>' . str_repeat('alpha ', 100)
			. '<img src="' . $this->pngImage() . '" style="float: left; width: 30mm; height: 280mm">' . str_repeat('beta ', 100) . '</p>');
		$pages = $this->pageContents($this->output($mpdf));

		$drawn = 0;
		foreach ($pages as $page) {
			$drawn += $this->images($page);
			foreach ($this->lines($page) as $line) {
				$column = $this->columnOf($mpdf, $line['x0'], $line['x1']);
				$this->assertNotNull($column, 'Each line should lie within a column: ' . $line['text']);
				$this->assertEqualsWithDelta($mpdf->ColL[$column] * Mpdf::SCALE, $line['x0'], 0.05, 'No line should be narrowed: ' . $line['text']);
			}
		}
		$this->assertSame(1, $drawn);
	}

	/**
	 * The bottom of a column, which balancing measures the columns by, is taken down to the bottom of a floated
	 * image's margin where that is lower than the column's last line
	 */
	public function testTheColumnReachesDownToTheImage()
	{
		$mpdf = $this->mpdf();
		$mpdf->WriteHTML('<columns column-count="2" /><p><img src="' . $this->pngImage() . '" style="float: left; width: 30mm; height: 30mm; margin-bottom: 10mm">One line</p>');

		$drawn = array_values(array_filter($mpdf->columnbuffer, static function ($entry) {
			return preg_match('/\/I\d+ Do/', $entry['s']) === 1;
		}));
		$this->assertCount(1, $drawn, 'The image should wait in the column buffer');
		$this->assertEqualsWithDelta($drawn[0]['y'] + 40, $mpdf->ColDetails[0]['bottom_margin'], 0.01);

		$mpdf->cleanup();
	}

	/**
	 * The one image on a page, in points from the top left
	 *
	 * @param string $stream
	 *
	 * @return float[]
	 */
	private function image($stream)
	{
		$this->assertSame(1, preg_match_all('/q ([\d.]+) 0 0 ([\d.]+) ([\d.]+) ([\d.]+) cm \/I\d+ Do Q/', $stream, $m, PREG_SET_ORDER), 'The page should draw one image');
		list(, $w, $h, $x, $y) = $m[0];
		return ['left' => (float) $x, 'right' => $x + $w, 'top' => self::PAGE_HEIGHT - $y - $h, 'bottom' => self::PAGE_HEIGHT - $y];
	}

	/**
	 * The lines of text on a page, each with its horizontal extent and its baseline in points from the top left
	 *
	 * @param string $stream
	 *
	 * @return array[]
	 */
	private function lines($stream)
	{
		preg_match_all('/BT ([\d.]+) ([\d.]+) Td\s+\((.*?)\) Tj ET/', $stream, $m, PREG_SET_ORDER);

		$lines = [];
		foreach ($m as $line) {
			$lines[] = [
				'x0' => (float) $line[1],
				'x1' => $line[1] + $this->ruler->GetStringWidth($line[3]) * Mpdf::SCALE,
				'baseline' => self::PAGE_HEIGHT - $line[2],
				'text' => $line[3],
			];
		}

		return $lines;
	}

	/**
	 * The column a horizontal extent lies within, or null
	 *
	 * @param \Mpdf\Mpdf $mpdf
	 * @param float $x0
	 * @param float $x1
	 *
	 * @return int|null
	 */
	private function columnOf(Mpdf $mpdf, $x0, $x1)
	{
		foreach ($mpdf->ColL as $i => $left) {
			if ($x0 >= $left * Mpdf::SCALE - 0.05 && $x1 <= $mpdf->ColR[$i] * Mpdf::SCALE + 0.05) {
				return $i;
			}
		}

		return null;
	}
}
