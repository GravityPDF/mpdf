<?php

namespace Mpdf;

use Mpdf\Fonts\FontCache;
use Mpdf\Unicode\Ucdn;

/**
 * What a ligature or a multiple substitution does to the ligature and mark records held by position.
 *
 * GSUBsubstitute() keeps assocLigs (a ligature's position => how many components it has) and
 * assocMarks (a mark's position => the ligature and component it belongs to) for GPOS to attach
 * marks to the right component. Taking glyphs out of the run or putting them in moves everything
 * after, so every record after the change has to be renumbered with it. The renumbering walks the
 * records rather than every position to the end of the run, which made each substitution cost the
 * length of the paragraph; these pin the positions it arrives at, far along a long run included.
 */
class LigatureBookkeepingTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * Long enough that a renumbering which stopped short of the end would leave records behind
	 */
	const RUN = 400;

	/**
	 * A sentence of each script with conjuncts, marks and ligatures in it
	 */
	const DEVANAGARI = 'हिन्दी विश्व की एक प्रमुख भाषा है और भारत की राजभाषा है। क्षत्रिय श्रृंखला द्विज र्क्ष्म्य।';

	const TELUGU = 'తెలుగు భారతదేశంలో ఆంధ్రప్రదేశ్, తెలంగాణ రాష్ట్రాల అధికార భాష. స్త్రీ క్ష్మ ర్క్క ప్రాచీన సాహిత్యం.';

	/**
	 * @var \Mpdf\Mpdf
	 */
	private $mpdf;

	/**
	 * Tear down the document, which holds the font the shaper reads.
	 */
	protected function tear_down()
	{
		if ($this->mpdf) {
			$this->mpdf->cleanup();
			$this->mpdf = null;
		}

		parent::tear_down();
	}

	/**
	 * A ligature of three components with glyphs between them, the second component a ligature of
	 * two already, so the new one has four. The glyphs between stay, in order, after the ligature;
	 * every record after it moves back by the components removed before it, one at the far end of
	 * the run as much as one just after the ligature. A record on a component that was removed goes,
	 * and one before the ligature stays where it is.
	 */
	public function testALigatureRenumbersEveryRecordAfterIt()
	{
		$otl = $this->otl('dejavusans');
		$otl->OTLdata = $this->baseGlyphs(self::RUN);
		$otl->assocLigs = [5 => 2, 12 => 2, 350 => 2];
		$otl->assocMarks = [
			6 => ['compID' => 1, 'ligPos' => 5],
			13 => ['compID' => 0, 'ligPos' => 13],
			351 => ['compID' => 1, 'ligPos' => 350],
			399 => ['compID' => 0, 'ligPos' => 350],
		];

		$this->assertSame(1, $otl->GSUBsubstitute(10, 0xFB01, 4, [10, 12, 15]));

		$this->assertSame(
			array_merge(range(0x1000, 0x1009), [0xFB01, 0x100B, 0x100D, 0x100E], range(0x1010, 0x1000 + self::RUN - 1)),
			array_column($otl->OTLdata, 'uni')
		);
		$this->assertAssociations(
			[5 => 2, 10 => 4, 348 => 2],
			[
				6 => ['compID' => 1, 'ligPos' => 5],
				12 => ['compID' => 0, 'ligPos' => 12],
				349 => ['compID' => 1, 'ligPos' => 348],
				397 => ['compID' => 0, 'ligPos' => 348],
			],
			$otl
		);
	}

	/**
	 * One glyph written as three moves every record after it on by two, and a mark's ligature with
	 * it. A record before the substitution stays where it is.
	 */
	public function testAMultipleSubstitutionRenumbersEveryRecordAfterIt()
	{
		$otl = $this->otl('dejavusans');
		$otl->OTLdata = $this->baseGlyphs(self::RUN);
		$otl->assocLigs = [5 => 2, 300 => 2];
		$otl->assocMarks = [
			6 => ['compID' => 1, 'ligPos' => 5],
			301 => ['compID' => 1, 'ligPos' => 300],
			399 => ['compID' => 0, 'ligPos' => 300],
		];

		$this->assertSame(3, $otl->GSUBsubstitute(10, [0x41, 0x42, 0x43], 2));

		$this->assertCount(self::RUN + 2, $otl->OTLdata);
		$this->assertAssociations(
			[5 => 2, 302 => 2],
			[
				6 => ['compID' => 1, 'ligPos' => 5],
				303 => ['compID' => 1, 'ligPos' => 302],
				401 => ['compID' => 0, 'ligPos' => 302],
			],
			$otl
		);
	}

	/**
	 * A glyph substituted by nothing moves every record after it back by one.
	 */
	public function testADeletionRenumbersEveryRecordAfterIt()
	{
		$otl = $this->otl('dejavusans');
		$otl->OTLdata = $this->baseGlyphs(self::RUN);
		$otl->assocLigs = [5 => 2, 300 => 2];
		$otl->assocMarks = [
			6 => ['compID' => 1, 'ligPos' => 5],
			301 => ['compID' => 1, 'ligPos' => 300],
			398 => ['compID' => 0, 'ligPos' => 300],
		];

		$this->assertSame(0, $otl->GSUBsubstitute(10, [], 2));

		$this->assertCount(self::RUN - 1, $otl->OTLdata);
		$this->assertAssociations(
			[5 => 2, 299 => 2],
			[
				6 => ['compID' => 1, 'ligPos' => 5],
				300 => ['compID' => 1, 'ligPos' => 299],
				397 => ['compID' => 0, 'ligPos' => 299],
			],
			$otl
		);
	}

	/**
	 * @return array[] A font and a sentence in a script it shapes with ligatures and marks
	 */
	public function dataParagraphs()
	{
		return [
			'Devanagari in FreeSerif' => ['freeserif', self::DEVANAGARI],
			'Telugu in Pothana2000' => ['pothana2000', self::TELUGU],
		];
	}

	/**
	 * A paragraph of one sentence written thirty times comes out as that sentence shaped on its own,
	 * thirty times over with a space between: the same glyphs, the same groups and the same
	 * positioning at the end of the paragraph as at the start.
	 *
	 * @dataProvider dataParagraphs
	 */
	public function testALongParagraphIsShapedAsItsSentencesAre($font, $sentence)
	{
		$otl = $this->otl($font);
		$once = $this->shaped($otl, $sentence);

		$expected = [];
		for ($i = 0; $i < 30; $i++) {
			if ($i) {
				$expected[] = [0x20, 'S', null];
			}
			$expected = array_merge($expected, $once);
		}

		$this->assertSame($expected, $this->shaped($otl, implode(' ', array_fill(0, 30, $sentence))));
	}

	/**
	 * @param string $font The font the document starts in, which is the one the shaper reads
	 *
	 * @return \Mpdf\Otl A shaper of its own for that font, reading the document's font cache
	 */
	private function otl($font)
	{
		$tempDir = sys_get_temp_dir() . '/mpdf-ligature-bookkeeping-test';

		$this->mpdf = new Mpdf(['mode' => 'utf-8', 'tempDir' => $tempDir, 'default_font' => $font]);

		return new Otl($this->mpdf, new FontCache(new Cache($tempDir . '/mpdf/ttfontdata')));
	}

	/**
	 * @param int $length
	 *
	 * @return array[] A run of distinct base glyphs, U+1000 onwards, as analyseCharacters() leaves them
	 */
	private function baseGlyphs($length)
	{
		$run = [];
		for ($i = 0; $i < $length; $i++) {
			$run[] = [
				'uni' => 0x1000 + $i,
				'hex' => sprintf('%05X', 0x1000 + $i),
				'bidi_type' => Ucdn::BIDI_CLASS_L,
				'general_category' => Ucdn::UNICODE_GENERAL_CATEGORY_OTHER_LETTER,
				'group' => 'C',
			];
		}

		return $run;
	}

	/**
	 * @return array[] Each glyph of the shaped text as its code, its group and its positioning
	 */
	private function shaped(Otl $otl, $text)
	{
		$otl->applyOTL($text, 0xFF);

		$glyphs = [];
		foreach ($otl->OTLdata['char_data'] as $i => $char) {
			$glyphs[] = [
				$char['uni'],
				$otl->OTLdata['group'][$i],
				isset($otl->OTLdata['GPOSinfo'][$i]) ? $otl->OTLdata['GPOSinfo'][$i] : null,
			];
		}

		return $glyphs;
	}

	/**
	 * The records compared by position, whatever order they were written in.
	 */
	private function assertAssociations(array $ligs, array $marks, Otl $otl)
	{
		$actualLigs = $otl->assocLigs;
		$actualMarks = $otl->assocMarks;
		ksort($actualLigs);
		ksort($actualMarks);

		$this->assertSame($ligs, $actualLigs, 'assocLigs');
		$this->assertSame($marks, $actualMarks, 'assocMarks');
	}

}
