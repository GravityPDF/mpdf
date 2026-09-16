<?php

namespace Mpdf\Fonts;

use Mpdf\Cache;
use Mpdf\Fonts\Table\LookupFlag;
use Mpdf\HtmlRecordingMpdf;
use Mpdf\OtlDump;
use Mpdf\TTFontFile;

/**
 * The GSUB walk the parser and the dump share, over rule sets no font in tests/data/ttf has.
 *
 * The lookups are built by hand, in the shape readGSUBrules() leaves them: lookup 0 is a class-based
 * chained context (Type 6 Format 2) whose two rules name first two backtrack positions, then one, and
 * hand position 0 to lookup 1, which replaces A with D.
 */
class GsubArrayTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * The second rule matches the one backtrack position it names, not the first rule's second as
	 * well (#170).
	 */
	public function testTheParserReadsEachClassRuleWithItsOwnPositions()
	{
		$parser = $this->withGdef(new TTFontFile($this->cache(), 'win'));

		$volt = $parser->_getGSUBarray($this->chainedClassRules(), [0 => 'ccmp'], 'latn');

		$this->assertSame([2, 1], [$volt[0]['nBacktrack'], $volt[1]['nBacktrack']]);
		$this->assertSame('(00043)() ', $volt[1]['matchback']);
	}

	/**
	 * So does the dump, which reports the second rule with the one backtrack position it names.
	 */
	public function testTheDumpReportsEachClassRuleWithItsOwnPositions()
	{
		$mpdf = new HtmlRecordingMpdf(['mode' => 'utf-8', 'tempDir' => __DIR__ . '/../tmp/mpdf/otldump']);
		$dump = $this->withGdef(new OtlDump($mpdf, $this->cache(), 'win'));

		$report = \Closure::bind(function (array $Lookup) {
			$this->reportGSUBlookups($Lookup, [0 => 'ccmp'], 'latn', OtlDump::TOP_LEVEL);
		}, $dump, OtlDump::class);
		$report($this->chainedClassRules());

		$rules = explode('<div class="rule">Rule: 1</div>', implode('', $mpdf->recordedHtml));

		$this->assertCount(2, $rules);
		$this->assertSame(2, substr_count($rules[0], 'Backtrack #'));
		$this->assertSame(1, substr_count($rules[1], 'Backtrack #'));
		$this->assertStringContainsString('<div>Backtrack #0: <span class="unicode">U+0043</span></div>', $rules[1]);
	}

	/**
	 * A Type 5 rule has no backtrack or lookahead, so where it belongs to an Arabic joining form the
	 * shaper gets none, not those of the chained rule read before it (#189).
	 */
	public function testAPlainContextRuleOfAnArabicFormHasNoBacktrackOrLookahead()
	{
		$parser = $this->withGdef(new TTFontFile($this->cache(), 'win'));

		$Lookup = $this->chainedClassRules();
		$Lookup[2] = [
			'Type' => 5,
			'Flag' => 0,
			'MarkFilteringSet' => '',
			'SubtableCount' => 1,
			'Subtable' => [[
				'Format' => 1,
				'SubRuleSetCount' => 1,
				'SubRuleSet' => [['FirstGlyph' => '00041', 'SubRule' => [
					['GlyphCount' => 1, 'SubstCount' => 1, 'SubstLookupRecord' => [['SequenceIndex' => 0, 'LookupListIndex' => 1]]],
				]]],
			]],
		];

		$volt = $parser->_getGSUBarray($Lookup, [0 => 'init', 2 => 'init'], 'arab');

		$this->assertSame(['match' => '00041', 'replace' => '00044', 'tag' => 'init', 'prel' => [], 'postl' => [], 'ignore' => '()'], end($volt));
	}

	private function chainedClassRules()
	{
		$rule = function (array $backtrack) {
			return [
				'InputGlyphCount' => 1,
				'BacktrackGlyphCount' => count($backtrack),
				'Backtrack' => $backtrack,
				'LookaheadGlyphCount' => 0,
				'SubstCount' => 1,
				'SubstLookupRecord' => [['SequenceIndex' => 0, 'LookupListIndex' => 1]],
			];
		};

		return [
			0 => [
				'Type' => 6,
				'Flag' => 0,
				'MarkFilteringSet' => '',
				'SubtableCount' => 1,
				'Subtable' => [[
					'Format' => 2,
					'InputClasses' => [1 => '00041'],
					'BacktrackClasses' => [1 => '00042', 2 => '00043'],
					'LookaheadClasses' => [],
					'ChainSubClassSet' => [1 => ['ChainSubClassRuleCnt' => 2, 'ChainSubClassRule' => [$rule([1, 2]), $rule([2])]]],
				]],
			],
			1 => [
				'Type' => 1,
				'Flag' => 0,
				'MarkFilteringSet' => '',
				'SubtableCount' => 1,
				'Subtable' => [['Format' => 1, 'subs' => [['Replace' => ['00041'], 'substitute' => ['00044']]]]],
			],
		];
	}

	/**
	 * A GDEF with no classes in it, which is all a lookup whose flag skips nothing asks of one
	 */
	private function withGdef(TTFontFile $ttf)
	{
		$ttf->GlyphClassMarks = '';

		$set = \Closure::bind(function () {
			$this->lookupFlag = new LookupFlag('synthetic', [
				'GlyphClassBases' => '',
				'GlyphClassMarks' => '',
				'GlyphClassLigatures' => '',
				'GlyphClassComponents' => '',
				'MarkGlyphSets' => [],
				'MarkAttachmentType' => [],
			]);
		}, $ttf, TTFontFile::class);
		$set();

		return $ttf;
	}

	private function cache()
	{
		return new FontCache(new Cache(__DIR__ . '/../tmp/mpdf/ttfontdata'));
	}

}
