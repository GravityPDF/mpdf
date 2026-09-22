<?php

namespace Mpdf;

/**
 * The ToUnicode CMap of an embedded TrueType font maps every code the text draws with, not only those below <0100>
 */
class ToUnicodeTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;

	/**
	 * A subset maps each run of 256 codes it draws from, as a range of its own
	 */
	public function testSubsetMapsTheRowsItDraws()
	{
		$cmap = $this->cmap(['percentSubset' => 100]);

		$this->assertStringContainsString("2 beginbfrange\n<0000> <00FF> <0000>\n<0300> <03FF> <0300>\nendbfrange\n", $cmap);
	}

	/**
	 * A font carried whole maps every run of 256 codes but the surrogates, in blocks of at most 100 ranges
	 */
	public function testWholeFontMapsEveryRow()
	{
		$cmap = $this->cmap(['percentSubset' => -1]); // Below the 0% of the font a line of Greek uses

		$this->assertSame(248, preg_match_all('/^<([0-9A-F]{2})00> <\g{1}FF> <\g{1}00>$/m', $cmap));
		$this->assertSame(['100', '100', '48'], preg_match_all('/^(\d+) beginbfrange$/m', $cmap, $blocks) ? $blocks[1] : []);
		$this->assertStringContainsString("<D700> <D7FF> <D700>\n<E000> <E0FF> <E000>\n", $cmap);
	}

	/**
	 * The ToUnicode CMap of the font a line of Greek is drawn in
	 *
	 * @param mixed[] $config
	 *
	 * @return string
	 */
	private function cmap($config)
	{
		$pdf = $this->render('<p>Ελληνικά</p>', $config + ['mode' => '', 'default_font' => 'dejavusans']);

		$this->assertSame(1, preg_match('/\/CMapName \/Adobe-Identity-UCS def\n(.*?)endcmap/s', $pdf, $cmap));

		return $cmap[1];
	}

}
