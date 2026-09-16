<?php

namespace Mpdf\Fonts;

use Mpdf\Cache;
use Mpdf\HtmlRecordingMpdf;
use Mpdf\OtlDump;

/**
 * What the dump does with a request it cannot fully answer.
 *
 * A font's two layout tables need not carry the same scripts and language systems, and the report of
 * one script is worth having even when only one table has anything to say about it.
 */
class OtlDumpTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	const FONT_DIR = __DIR__ . '/../../data/ttf';

	/**
	 * @var HtmlRecordingMpdf
	 */
	private $mpdf;

	/**
	 * A fresh recording mPDF per test, since the report is read back off the one the dump wrote to.
	 */
	public function set_up()
	{
		parent::set_up();

		$this->mpdf = new HtmlRecordingMpdf(['mode' => 'utf-8', 'tempDir' => __DIR__ . '/../tmp/mpdf/otldump']);
	}

	/**
	 * Noto Sans Mono lists the Catalan language system under latn in GSUB and not in GPOS.
	 */
	public function testAScriptOnlyOneTableCarriesIsStillReported()
	{
		$report = $this->dump('NotoSansMono-GDEF13-Subset', 'latn', 'CAT ');

		$this->assertContains(
			'<div class="notoffered">This font\'s GPOS script "latn" offers no language system "CAT". It has: DFLT</div>',
			$report
		);
		$this->assertStringContainsString('<bookmark level="0" content="GSUB features">', implode('', $report));
	}

	/**
	 * Manjari positions Latin and substitutes nothing for it, so GSUB has no latn script at all.
	 */
	public function testATableWithoutTheScriptSaysWhatItHasInstead()
	{
		$report = $this->dump('Manjari-Regular', 'latn', 'DFLT');

		$this->assertContains(
			'<div class="notoffered">This font\'s GSUB table offers no script "latn". It has: DFLT, mlm2, mlym</div>',
			$report
		);
		$this->assertStringContainsString('<bookmark level="0" content="GPOS features">', implode('', $report));
	}

	/**
	 * A script neither table carries is a mistake in the tag rather than a one-sided font, and the
	 * message names what both tables do carry.
	 */
	public function testAScriptNeitherTableCarriesFails()
	{
		try {
			$this->dump('Manjari-Regular', 'arab', 'DFLT');
			$this->fail('Dumping a script the font does not carry should have thrown');
		} catch (\Mpdf\MpdfException $e) {
			$this->assertSame(
				'This font\'s GSUB table offers no script "arab". It has: DFLT, mlm2, mlym' . "\n"
				. 'This font\'s GPOS table offers no script "arab". It has: DFLT, latn, mlm2, mlym',
				$e->getMessage()
			);
		}
	}

	/**
	 * The summary links each script and language system it lists to its own detail report, and the
	 * caller says how it named the font so that the link comes back to the same one.
	 */
	public function testTheSummaryLinksEachScriptToItsOwnReport()
	{
		$dump = $this->dumper();
		$dump->detailReportQuery = ['family' => 'manjari', 'style' => ''];
		$dump->getMetrics(self::FONT_DIR . '/Manjari-Regular.ttf', 'manjari', 0, false, false, 0xFF, 'summary');

		$this->assertStringContainsString(
			'<a href="font_dump_otl.php?family=manjari&amp;style=&amp;script=mlym&amp;lang=DFLT">',
			implode('', $this->mpdf->recordedHtml)
		);
	}

	/**
	 * WriteHTML refuses HTML longer than pcre.backtrack_limit, and one lookup of one script can
	 * report far more than that, so the report is handed over in pieces.
	 */
	public function testTheReportIsWrittenInPiecesWriteHtmlWillAccept()
	{
		$this->dump('NotoSans-Regular', 'latn', 'DFLT');

		$limit = (int) ini_get('pcre.backtrack_limit');
		$longest = 0;
		foreach ($this->mpdf->recordedHtml as $html) {
			$longest = max($longest, strlen($html));
		}

		$this->assertGreaterThan($limit, array_sum(array_map('strlen', $this->mpdf->recordedHtml)));
		$this->assertLessThan($limit, $longest);
	}

	/**
	 * GPOS Lookup Types 7 and 8 are the positioning counterparts of GSUB's 5 and 6: a rule matches a
	 * run of glyphs and hands named positions within it to other lookups. Until #90 the dump threw
	 * rather than reporting five of their six formats, which is exactly when you would want to look.
	 *
	 * Type 7 Format 1 lists the glyphs of each rule one by one. Noto Sans Gurmukhi UI's `dist` nudges
	 * the AU matra + addak ligature by a single unit where a TTA and an EE matra follow it -
	 * ContextualPositioningTest shapes the same rule and sees the same -1.
	 */
	public function testAGlyphListContextPositioningIsReported()
	{
		$report = implode('', $this->dump('NotoSansGurmukhiUI-GPOS71-Subset', 'guru', 'DFLT'));

		$this->assertStringContainsString('LookupType 7: Context positioning [Format 1]', $report);
		$this->assertStringContainsString('<div>Input #1: <span class="unchanged">&nbsp;&#x0A1F;&nbsp;</span></div>', $report);
		$this->assertStringContainsString('<div class="sequenceIndex">Substitution Position: 0</div>', $report);
		$this->assertStringContainsString('Xpl: -1;', $report);
	}

	/**
	 * Format 3 matches a Coverage table per position. The synthetic font #80 built for it covers A
	 * then B and shifts the second left by 400 units.
	 */
	public function testACoverageContextPositioningIsReported()
	{
		$report = implode('', $this->dump('NotoSans-GPOS73-Synthetic', 'latn', 'DFLT'));

		$this->assertStringContainsString('LookupType 7: Context positioning [Format 3]', $report);
		$this->assertStringContainsString('<div>Input #0: <span class="unchanged">&nbsp;&#x0041;&nbsp;</span></div>', $report);
		$this->assertStringContainsString('<div>Input #1: <span class="unchanged">&nbsp;&#x0042;&nbsp;</span></div>', $report);
		$this->assertStringContainsString('<div class="sequenceIndex">Substitution Position: 1</div>', $report);
		$this->assertStringContainsString('Xpl: -400;', $report);
	}

	/**
	 * Type 8 puts a backtrack and a lookahead around the input. Noto Sans Takri's `kern` pulls the I
	 * matra across the KA that follows it only where the anusvara closes the cluster.
	 */
	public function testAGlyphListChainedContextPositioningIsReported()
	{
		$report = implode('', $this->dump('NotoSansTakri-GPOS81-Subset', 'takr', 'DFLT'));

		$this->assertStringContainsString('LookupType 8: Chained Context positioning [Format 1]', $report);
		$this->assertStringContainsString('<div>Backtrack #0: <span class="unicode">M+E002</span></div>', $report);
		$this->assertStringContainsString('<div>Input #0: <span class="unchanged">&nbsp;&#x1168A;&nbsp;</span></div>', $report);
		$this->assertStringContainsString('Xpl: 107; Xadv: 107', $report);
	}

	/**
	 * Format 2 matches classes, with a class definition of its own for each of the three sequences.
	 * Noto Sans is the only font among the 103 installed that carries one: its `kern` pulls a
	 * combining mark towards a dotless i where a mark below or a closing bracket follows.
	 */
	public function testAClassBasedChainedContextPositioningIsReported()
	{
		$report = implode('', $this->dump('NotoSans-Regular', 'latn', 'DFLT'));

		$this->assertStringContainsString('LookupType 8: Chained Context positioning [Format 2]', $report);
		$this->assertStringContainsString('<div class="lookuptypesub">Format 2: Class-based Chaining Context Positioning</div>', $report);
		$this->assertStringContainsString('<div>Backtrack #0: <span class="unicode">U+0131</span></div>', $report);
		$this->assertStringContainsString('<div class="rule">Input Class: 1</div>', $report);
	}

	/**
	 * Each of the three sequences of a chained context has a Class Definition of its own, and so a
	 * class 0 of its own: every glyph that Class Definition leaves unnamed. Noto Sans Devanagari's
	 * `dist` names class 0 in the backtrack of lookup #21, which read as U+0000 for as long as only
	 * the input sequence was told what to exclude.
	 */
	public function testAChainedContextBacktrackNamingClassZeroIsReportedAsWhatItExcludes()
	{
		$report = implode('', $this->dump('NotoSans-Regular', 'dev2', 'DFLT'));

		$this->assertStringContainsString(
			'<div>Backtrack #0: <span class="unchanged">&nbsp;[NOT &#x25cc;&#x0901; &#x25cc;&#x0902;',
			$report
		);
		$this->assertStringNotContainsString('<div>Backtrack #0: <span class="unicode">U+0000</span></div>', $report);
	}

	/**
	 * The position a rule hands a nested lookup can name class 0 as well, and then what the nested
	 * lookup's rules are filtered against is the complement of the named classes rather than a list.
	 * NotoSans-GPOS72-Synthetic's second rule shifts a class 0 glyph by -250, which the report named
	 * the nested lookup for and then left out of it.
	 */
	public function testANestedLookupHandedAClassZeroPositionIsReportedWithItsRules()
	{
		$report = implode('', $this->dump('NotoSans-GPOS72-Synthetic', 'latn', 'DFLT'));

		$this->assertStringContainsString('<div>Input #1: <span class="unchanged">&nbsp;[NOT &#x0041; &#x0042;]&nbsp;</span></div>', $report);
		$this->assertStringContainsString('Xpl: -250;', $report);
	}

	/**
	 * GSUB Types 5 and 6 filter the same way. NotoSans-GSUBClassZero-Synthetic names class 0 at the
	 * substituted position of a Type 5 Format 2 rule and of a Type 6 Format 2 one, and the lookup
	 * both hand it replaces A as well as C. Only C is reported: A is in class 1, which is what tells
	 * class 0 apart from "anything".
	 */
	public function testAGsubNestedLookupHandedAClassZeroPositionReportsOnlyTheGlyphsThatPositionHolds()
	{
		$report = implode('', $this->dump('NotoSans-GSUBClassZero-Synthetic', 'latn', 'DFLT'));

		$this->assertSame(2, substr_count($report, '<span class="unicode">U+0043&nbsp;</span>'));
		$this->assertStringNotContainsString('<span class="unicode">U+0041&nbsp;</span>', $report);
	}

	/**
	 * A format the spec does not define is named rather than reported as nothing, which is the one
	 * thing the five throws #90 removed were right about.
	 */
	public function testAnUnknownContextPositioningFormatIsNamed()
	{
		foreach ([7 => 'reportGPOScontextPos', 8 => 'reportGPOSchainContextPos'] as $type => $method) {
			try {
				$this->callFormatReport($method);
				$this->fail(sprintf('An unknown GPOS Type %d format should have thrown', $type));
			} catch (\Mpdf\Exception\FontException $e) {
				$this->assertSame(sprintf('GPOS Lookup Type %d, Format "4" not supported.', $type), $e->getMessage());
			}
		}
	}

	/**
	 * A rule whose format lists its records as two parallel arrays reads as the same records as one
	 * that lists them as records.
	 */
	public function testARulesLookupRecordsReadTheSameInEitherShape()
	{
		$records = [
			['SequenceIndex' => 0, 'LookupListIndex' => 7],
			['SequenceIndex' => 2, 'LookupListIndex' => 3]
		];

		$this->assertSame($records, $this->substLookupRecords(['SubstCount' => 2, 'SubstLookupRecord' => $records]));

		$this->assertSame($records, $this->substLookupRecords([
			'SubstCount' => 2,
			'SequenceIndex' => [0, 2],
			'LookupListIndex' => [7, 3]
		]));
	}

	/**
	 * A rule that substitutes nothing names neither shape, and the count is all there is to read.
	 */
	public function testARuleWithoutLookupRecordsHasNone()
	{
		$this->assertSame([], $this->substLookupRecords(['SubstCount' => 0]));
	}

	/**
	 * The report names the classes a lookup's flags skip. Noto Sans Takri's GDEF defines mark glyph sets
	 * 0 and 1 and a mark attachment class 1, which is enough to name each of the five.
	 */
	public function testTheClassesALookupSkipsAreNamed()
	{
		$dump = $this->dumper();
		$dump->getMetrics(self::FONT_DIR . '/NotoSansTakri-GSUB53-Subset.ttf', 'takri', 0, false, false, 0xFF, 'summary');

		$this->assertSame('', $this->skippedClassNames($dump, 0, ''));
		$this->assertSame('Mark Glyphs ', $this->skippedClassNames($dump, 0x0008, ''));
		$this->assertSame('Marks outside Mark Glyph Set[1] ', $this->skippedClassNames($dump, 0x0010, 1));
		$this->assertSame('MarkAttachmentType[1] |Ligature Glyphs |Base Glyphs ', $this->skippedClassNames($dump, 0x0106, ''));

		try {
			$this->skippedClassNames($dump, 0x0010, 9);
			$this->fail('Naming a mark glyph set GDEF does not define should have thrown');
		} catch (\Mpdf\Exception\FontException $e) {
			$this->assertSame('Font "takri" uses mark filtering set 9, which GDEF does not define', $e->getMessage());
		}
	}

	/**
	 * The dump reports a lookup's rules and never matches them, so it has no pattern of its own to
	 * build: asked for one, it builds the parser's, capture groups and all.
	 */
	public function testTheDumpBuildsTheParsersMatchPatterns()
	{
		$dump = $this->dumper();
		$dump->getMetrics(self::FONT_DIR . '/NotoSansTakri-GSUB53-Subset.ttf', 'takri', 0, false, false, 0xFF, 'summary');

		$this->assertSame('(0061|0062)() (0063)', $dump->_makeGSUBinputMatch(['0061|0062', '0063'], '()'));
		$this->assertSame('(0063)() (0061)', $dump->_makeGSUBcontextInputMatch(['0061|0062', '0063'], '()', ['0063', '0061'], 0));
		$this->assertSame('(0062)() (0061)() ', $dump->_makeGSUBbacktrackMatch(['0061', '0062'], '()'));
		$this->assertSame('() (0061)() (0062)', $dump->_makeGSUBlookaheadMatch(['0061', '0062'], '()'));
		$this->assertSame('()', $dump->_getGSUBignoreString(0, ''));
	}

	private function skippedClassNames(OtlDump $dump, $flag, $markFilteringSet)
	{
		$reflected = new \ReflectionMethod(OtlDump::class, 'skippedClassNames');
		$reflected->setAccessible(true);

		return $reflected->invoke($dump, $flag, $markFilteringSet);
	}

	private function substLookupRecords(array $rule)
	{
		$reflected = new \ReflectionMethod(OtlDump::class, 'substLookupRecords');
		$reflected->setAccessible(true);

		return $reflected->invoke($this->dumper(), $rule);
	}

	/**
	 * The format dispatchers are private, and refusing a format no font can hold is the only thing
	 * this needs to reach one of them for.
	 */
	private function callFormatReport($method)
	{
		$reflected = new \ReflectionMethod(OtlDump::class, $method);
		$reflected->setAccessible(true);
		$reflected->invoke($this->dumper(), [], 0, 4, 'kern', 'latn');
	}

	/**
	 * @return string[] The HTML the report was written in
	 */
	private function dump($font, $script, $language)
	{
		$this->dumper()->getMetrics(
			self::FONT_DIR . '/' . $font . '.ttf',
			$font,
			0,
			false,
			false,
			0xFF,
			'detail',
			str_pad($script, 4, ' '),
			str_pad($language, 4, ' ')
		);

		return $this->mpdf->recordedHtml;
	}

	/**
	 * @return OtlDump
	 */
	private function dumper()
	{
		return new OtlDump($this->mpdf, new FontCache(new Cache(__DIR__ . '/../tmp/mpdf/otldump/cache')), 'win');
	}

}
