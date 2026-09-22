<?php

namespace Mpdf;

/**
 * The Ascent, Descent and Leading a fontdata entry sets replace the ones read from the font, for layout and in the
 * FontDescriptor, without being written into the metrics cache the next document reads
 */
class FontdataMetricOverrideTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;

	/** In points */
	const FONT_SIZE = 11;

	/** What DejaVu Sans itself declares */
	private static $fontMetrics = ['Ascent' => 928, 'Descent' => -236, 'Leading' => 0];

	private static $overrides = ['Ascent' => 1500, 'Descent' => -900, 'Leading' => 400];

	/** A test's documents share it, so the second one reads the cache the first one wrote */
	private $tempDir;

	/**
	 * Give each test a cache of its own
	 */
	protected function set_up()
	{
		parent::set_up();

		$this->tempDir = sys_get_temp_dir() . '/mpdf-fontdata-metric-override-' . uniqid('', true);
	}

	/**
	 * Remove the cache the documents wrote
	 */
	protected function tear_down()
	{
		/* The directories a Cache makes, innermost first, then the tempDir itself */
		foreach ([$this->tempDir . '/mpdf/ttfontdata', $this->tempDir . '/mpdf', $this->tempDir] as $directory) {
			if (!is_dir($directory)) {
				continue;
			}

			foreach (glob($directory . '/*') as $entry) {
				if (is_file($entry)) {
					unlink($entry);
				}
			}

			rmdir($directory);
		}

		parent::tear_down();
	}

	/**
	 * The registered font carries each override, and the font's own value for any metric left alone
	 *
	 * @dataProvider overrideProvider
	 *
	 * @param array $overrides
	 */
	public function testOverridesReplaceTheFontMetrics(array $overrides)
	{
		$mpdf = $this->document($overrides);

		$this->assertSame(array_merge(self::$fontMetrics, $overrides), $this->metrics($mpdf->fonts['dejavusans']['desc']));
	}

	/**
	 * Each override alone, and all three together
	 *
	 * @return array
	 */
	public function overrideProvider()
	{
		return [
			'Ascent' => [['Ascent' => 1500]],
			'Descent' => [['Descent' => -900]],
			'Leading' => [['Leading' => 400]],
			'all three' => [self::$overrides],
		];
	}

	/**
	 * A normal line is adjustFontDescLineheight times Ascent - Descent + Leading, with the Leading added again as a
	 * line gap, so the lines of a paragraph move apart by exactly what the overrides imply
	 */
	public function testOverridesSetTheLineHeight()
	{
		$plain = $this->document([]);
		$overridden = $this->document(self::$overrides);

		$this->assertEqualsWithDelta($this->pitch(self::$fontMetrics, $plain), $this->linePitch($plain), 0.002);
		$this->assertEqualsWithDelta($this->pitch(self::$overrides, $overridden), $this->linePitch($overridden), 0.002);
	}

	/**
	 * The embedded font describes itself with the overridden metrics, as it did before they were lost
	 */
	public function testOverridesReachTheFontDescriptor()
	{
		$pdf = $this->output($this->document(self::$overrides));

		$this->assertMatchesRegularExpression('/\/FontDescriptor\s+\/FontName \/\w+\+DejaVuSans\s+[^>]*\/Ascent 1500\s+\/Descent -900\s+\/Leading 400\s/', $pdf);
	}

	/**
	 * A document without overrides that reads the cache a document with them wrote gets the font's own metrics
	 */
	public function testOverridesStayOutOfTheCache()
	{
		$this->output($this->document(self::$overrides));

		$cached = json_decode(file_get_contents($this->tempDir . '/mpdf/ttfontdata/dejavusans.mtx.json'), true);
		$this->assertSame(self::$fontMetrics, $this->metrics($cached['desc']));

		$plain = $this->document([]);
		$this->assertSame(self::$fontMetrics, $this->metrics($plain->fonts['dejavusans']['desc']));
	}

	/**
	 * A document in DejaVu Sans, with $overrides on its fontdata entry, and three lines written
	 *
	 * @param array $overrides
	 *
	 * @return Mpdf
	 */
	private function document(array $overrides)
	{
		$mpdf = new Mpdf([
			'mode' => 'utf-8',
			'tempDir' => $this->tempDir,
			'default_font' => 'dejavusans',
			'default_font_size' => self::FONT_SIZE,
			'fontdata' => ['dejavusans' => ['R' => 'DejaVuSans.ttf'] + $overrides],
		]);
		$mpdf->compress = false;
		$mpdf->WriteHTML('<p>Line one<br>Line two<br>Line three</p>');

		return $mpdf;
	}

	/**
	 * @param array $desc
	 *
	 * @return array Ascent, Descent and Leading from a font's desc, in that order
	 */
	private function metrics(array $desc)
	{
		return [
			'Ascent' => $desc['Ascent'],
			'Descent' => $desc['Descent'],
			'Leading' => $desc['Leading'],
		];
	}

	/**
	 * @param array $metrics
	 * @param Mpdf $mpdf
	 *
	 * @return float The distance between baselines, in points, that a normal line height gives these metrics
	 */
	private function pitch(array $metrics, Mpdf $mpdf)
	{
		$lineHeight = $mpdf->adjustFontDescLineheight * ($metrics['Ascent'] - $metrics['Descent'] + $metrics['Leading']) / 1000;

		return ($lineHeight + $metrics['Leading'] / 1000) * self::FONT_SIZE;
	}

	/**
	 * @param Mpdf $mpdf
	 *
	 * @return float The distance between baselines, in points, of the three lines drawn, which must be even
	 */
	private function linePitch(Mpdf $mpdf)
	{
		preg_match_all('/BT [\d.]+ ([\d.]+) Td/', $this->output($mpdf), $matches);
		$baselines = array_map('floatval', $matches[1]);

		$this->assertCount(3, $baselines);
		$this->assertEqualsWithDelta($baselines[0] - $baselines[1], $baselines[1] - $baselines[2], 0.002);

		return $baselines[0] - $baselines[1];
	}

}
