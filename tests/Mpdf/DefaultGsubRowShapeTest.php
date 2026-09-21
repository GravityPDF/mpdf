<?php

namespace Mpdf;

/**
 * A language system the parser cached nothing for gets the same row as one it did.
 *
 * loadGsubDerivedData() fills its row from the file the parser wrote for the script and language
 * system in hand, and where there is none it writes a row of empties instead. The two were written
 * out separately, and the default had named 'rphf' twice and 'half' not at all, so a font reached one
 * way carried six keys where the other carried seven.
 *
 * useGSUBlookups() writes a file only where the classification found something, which is what makes
 * both branches reachable in one document: FreeSerif's dev2 has one, and the two subsets here have
 * none.
 *
 * Of the five class tables the row carries, Shaper\Indic reads rphf, pref, blwf and pstf and never
 * half - the half forms are masked positionally, on every glyph before the base - so the key the typo
 * displaced is the one key nothing reads. That is why this asserts the shape: there is no drawn run
 * that tells the two apart.
 */
class DefaultGsubRowShapeTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	public function testAScriptTheFontCachedNothingForGetsTheSameRowAsOneItDid()
	{
		$rows = [];
		$sawCached = false;
		$sawDefaulted = false;
		foreach ($this->gsubRows() as $key => $row) {
			$rows[$key] = $this->sortedKeys($row);
			// A defaulted row is all empties, where a cached one carries what the parser classified
			if (array_filter($row)) {
				$sawCached = true;
			} else {
				$sawDefaulted = true;
			}
		}

		$this->assertTrue($sawCached, 'the run must reach a language system the parser wrote a file for');
		$this->assertTrue($sawDefaulted, 'and one it wrote none for, or there are not two shapes to compare');

		$expected = ['blwf', 'finals', 'half', 'pref', 'pstf', 'rphf', 'rtlSUB'];
		foreach ($rows as $key => $keys) {
			$this->assertSame($expected, $keys, $key . ' carries a different row');
		}
	}

	/**
	 * @return array[] the per-script rows a Devanagari and a Gurmukhi run built, keyed as Otl keys them
	 */
	private function gsubRows()
	{
		$mpdf = new Mpdf([
			'mode' => 'utf-8',
			'fontDir' => [__DIR__ . '/../data/ttf'],
			'fontdata' => [
				'gurmukhisubset' => ['R' => 'NotoSansGurmukhiUI-GPOS71-Subset.ttf', 'useOTL' => 0xFF],
				'devanagarisubset' => ['R' => 'NotoSansDevanagari-ContextualLocl-Subset.ttf', 'useOTL' => 0xFF],
			],
		]);
		$mpdf->WriteHTML('<p style="font-family:gurmukhisubset">&#x0A17;&#x0A41;&#x0A30;</p>');
		$mpdf->WriteHTML('<p style="font-family:devanagarisubset">&#x0926;&#x0947;&#x0935;</p>');
		$mpdf->WriteHTML('<p style="font-family:freeserif">&#x0926;&#x0947;&#x0935;&#x0928;</p>');

		$otl = new \ReflectionProperty('Mpdf\Mpdf', 'otl');
		$otl->setAccessible(true);

		$rows = [];
		foreach ((array) $otl->getValue($mpdf)->GSUBdata as $key => $row) {
			// The font also keys its lookup coverage in here, under its name alone
			if (strpos($key, '.GSUB.') !== false) {
				$rows[$key] = $row;
			}
		}

		return $rows;
	}

	private function sortedKeys($row)
	{
		$keys = array_keys($row);
		sort($keys);

		return $keys;
	}

}
