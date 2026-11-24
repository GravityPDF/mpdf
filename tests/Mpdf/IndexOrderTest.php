<?php

namespace Mpdf;

/**
 * Index entries that collate the same are listed in the order they were made, whatever PHP sorts with
 */
class IndexOrderTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;

	/**
	 * Twelve capitalised terms, then the same twelve in lower case: pairs that collate the same, made far enough
	 * apart for the sort PHP used before 8.0 to turn some of them round
	 */
	public function testEntriesThatCollateTheSameKeepTheirOrder()
	{
		$html = '';
		for ($i = 1; $i <= 12; $i++) {
			$html .= sprintf('<p>Item %1$d</p><indexentry content="Term%1$02d" />', $i);
		}
		for ($i = 1; $i <= 12; $i++) {
			$html .= sprintf('<p>Item %1$d again</p><indexentry content="term%1$02d" />', $i);
		}

		$streams = $this->pages($this->render($html . '<pagebreak /><indexinsert />'));
		preg_match_all('/\(((?:T|t)erm\d+)/', end($streams), $listed);

		$expected = [];
		for ($i = 1; $i <= 12; $i++) {
			$expected[] = sprintf('Term%02d', $i);
			$expected[] = sprintf('term%02d', $i);
		}
		$this->assertSame($expected, $listed[1]);
	}

	/**
	 * Without a collation locale, lower-cased entries sort byte by byte, so accented words come after "zebra"
	 */
	public function testEntriesSortByteWiseWithoutACollationLocale()
	{
		$this->assertSame(
			['apple', 'Banana', 'eagle', 'Zebra', 'Éclair', 'église'],
			$this->sortedEntries('')
		);
	}

	/**
	 * With a collation locale and ext-intl, accented words sort among the others
	 */
	public function testEntriesSortByTheCollationLocale()
	{
		if (!class_exists('Collator')) {
			$this->markTestSkipped('ext-intl is not loaded');
		}

		$originalLocale = setlocale(LC_COLLATE, 0);
		$locale = setlocale(LC_COLLATE, ['en_US.UTF-8', 'en_US.utf8', 'en_US']);
		setlocale(LC_COLLATE, $originalLocale);
		if (!$locale) {
			$this->markTestSkipped('No en_US locale is installed');
		}

		$this->assertSame(
			['apple', 'Banana', 'eagle', 'Éclair', 'église', 'Zebra'],
			$this->sortedEntries($locale)
		);
		$this->assertSame($originalLocale, setlocale(LC_COLLATE, 0));
	}

	/**
	 * Index entry names for a set of mixed-case and accented words, sorted in $locale
	 */
	private function sortedEntries($locale)
	{
		$mpdf = $this->mpdf();
		$html = '';
		foreach (['Zebra', 'église', 'apple', 'Éclair', 'Banana', 'eagle'] as $word) {
			$html .= '<p>' . $word . '</p><indexentry content="' . $word . '" />';
		}
		$mpdf->WriteHTML($html);

		$indexHtml = new \ReflectionMethod($mpdf, 'indexHtml');
		if (PHP_VERSION_ID < 80100) {
			$indexHtml->setAccessible(true);
		}
		preg_match_all('/mpdf_index_entry">(.*?)\x{a0}/u', $indexHtml->invoke($mpdf, 0, false, $locale, ''), $listed);
		$mpdf->cleanup();

		return $listed[1];
	}
}
