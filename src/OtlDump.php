<?php

namespace Mpdf;

use Mpdf\Fonts\FontCache;
use Mpdf\Fonts\GlyphString;
use Mpdf\Fonts\Table\Anchor;
use Mpdf\Fonts\Table\LookupFlag;
use Mpdf\Fonts\Table\MarkArray;
use Mpdf\Fonts\Table\SequenceRule;
use Mpdf\Fonts\Table\ValueRecord;

/**
 * A readable report of the OpenType layout tables in a font, for working on OTL support.
 *
 * Extends the parser the renderer uses, rather than being a second copy of it. That is the whole
 * point: a debugging tool that parses independently is free to disagree with the thing it is meant
 * to explain, and is useless exactly when it is needed. What it overrides here is reporting - the
 * four table readers emit HTML as they go - not reading.
 */
class OtlDump extends TTFontFile
{

	/**
	 * Which report to build: 'summary' lists the scripts, languages and features the font offers,
	 * 'detail' reports one script and language system's lookups in full.
	 *
	 * @var string
	 */
	var $mode;

	/**
	 * The script and language whose lookups detail mode reports on.
	 *
	 * These used to be read as $this->mpdf->OTLscript and ->OTLlang. Mpdf declares neither, and it
	 * uses the Strict trait, so every read threw - which is why detail mode has never run. They are
	 * arguments now, because they are arguments.
	 */
	private $script;

	private $language;

	/**
	 * The detail report as it is built, shared by the writer at both levels so that either can hand
	 * over what has built up so far.
	 *
	 * @var string
	 */
	private $report = '';

	/**
	 * How much report to build up before handing it to WriteHTML, a quarter of what it will accept.
	 *
	 * @var int
	 */
	private $reportChunkBytes = 0;

	/**
	 * Where the GSUB report is: level 1, or a lookup nested at level 2 with what the rule that nested
	 * it filters and frames its rows by. @see reportGSUBlookups()
	 *
	 * @var array
	 */
	private $nesting = self::TOP_LEVEL;

	const TOP_LEVEL = ['level' => 1, 'coverage' => '', 'exB' => '', 'exL' => '', 'class0excl' => ''];

	/**
	 * What the summary report's links should carry to reach a detail report of the same font.
	 *
	 * The summary lists every script and language system a font offers and links each to its own
	 * detail report. Only the caller knows how it named the font it handed over, so it says here,
	 * and the link gets the script and language appended.
	 *
	 * @var array query terms, e.g. ['family' => 'freeserif', 'style' => '']
	 */
	public $detailReportQuery = [];

	/**
	 * What each of GSUB and GPOS had to say when it did not carry the script or language system asked
	 * for. Two entries means neither table did, which is a mistake in the tag rather than a font that
	 * only positions or only substitutes.
	 *
	 * @var string[]
	 */
	private $notOffered = [];

	/**
	 * GDEF's marks, keyed by hex without leading zeros, for deciding which glyphs the report draws on a
	 * dotted circle. The parser's GlyphClassMarks can only be searched, and a search for one glyph's hex
	 * finds it inside another's: 0030 inside 00300, D165 inside 1D165.
	 *
	 * @var true[]
	 */
	private $marks = [];

	private $mpdf;

	/**
	 * @param Mpdf      $mpdf           The document the report is written to
	 * @param FontCache $fontCache      Where a parsed font is kept
	 * @param string    $fontDescriptor Which of the font's three sets of vertical metrics to believe
	 */
	public function __construct(Mpdf $mpdf, FontCache $fontCache, $fontDescriptor = 'win')
	{
		parent::__construct($fontCache, $fontDescriptor);

		$this->mpdf = $mpdf;
	}

	/**
	 * @param string $mode     'summary' lists the scripts, languages and features a font offers;
	 *                         'detail' walks the lookups of one script and language
	 * @param string $script   OpenType script tag, e.g. 'deva'. Required by detail mode
	 * @param string $language OpenType language system tag, e.g. 'DFLT'. Required by detail mode
	 */
	public function getMetrics($file, $fontkey, $TTCfontID = 0, $debug = false, $BMPonly = false, $useOTL = 0, $mode = null, $script = '', $language = '')
	{
		if ($mode === 'detail' && (!$script || !$language)) {
			throw new \Mpdf\MpdfException('Dumping the lookups of a font in detail needs a script and a language system to dump');
		}

		$this->mode = $mode;
		$this->script = $script;
		$this->language = $language;
		$this->notOffered = [];
		$this->reportChunkBytes = max(1, (int) ((int) ini_get('pcre.backtrack_limit') / 4));

		parent::getMetrics($file, $fontkey, $TTCfontID, $debug, $BMPonly, $useOTL);

		$this->failIfNeitherTableOffers();
	}

	/**
	 * A font whose licence forbids embedding is not reported on, unless the caller overrides that.
	 */
	protected function restrictedFont()
	{
		global $overrideTTFFontRestriction;
		if (!$overrideTTFFontRestriction) {
			throw new \Mpdf\Exception\FontException('Font file ' . $this->filename . ' cannot be embedded due to copyright restrictions.');
		}

		parent::restrictedFont();
	}

	/**
	 * A font without GDEF still has GSUB and GPOS to report, so the dump says so and reads on. It has
	 * no marks either, so a dump reused across fonts does not keep the last font's.
	 */
	protected function missingGDEF()
	{
		$this->marks = [];
		$this->reportTableMissing('GDEF');
	}

	/**
	 * Nothing is cached. The cache the dump is handed can be the one the shaper reads the same font key
	 * back from.
	 */
	protected function cacheLayoutTables()
	{
	}

	/**
	 * Keeps the marks for every mode, before the summary-only report, since detail mode draws them too.
	 */
	protected function reportGlyphClasses(array $glyphByClass)
	{
		$this->marks = [];
		if (isset($glyphByClass[3])) {
			foreach ($glyphByClass[3] as $glyph) {
				$this->marks[ltrim($glyph, '0')] = true;
			}
		}

		if ($this->mode != 'summary') {
			return;
		}

		$this->mpdf->WriteHTML('<h1>GDEF table</h1>');
		$this->mpdf->WriteHTML('<h2>Glyph classes</h2>');

		$descriptions = [
			1 => 'Base glyph (single character, spacing glyph)',
			2 => 'Ligature glyph (multiple character, spacing glyph)',
			3 => 'Mark glyph (non-spacing combining glyph)',
			4 => 'Component glyph (part of single character, spacing glyph)',
		];

		foreach ($descriptions as $class => $description) {
			if (empty($glyphByClass[$class])) {
				continue;
			}

			$this->mpdf->WriteHTML('<h3>Glyph class ' . $class . '</h3>');
			$this->mpdf->WriteHTML('<h5>' . $description . '</h5>');
			$this->mpdf->WriteHTML($this->glyphList($glyphByClass[$class], $class === 3));
		}
	}

	protected function reportMarkAttachmentTypes(array $markAttachmentTypes)
	{
		if ($this->mode != 'summary') {
			return;
		}

		$this->mpdf->WriteHTML('<h1>Mark Attachment Types</h1>');
		foreach ($markAttachmentTypes as $class => $glyphs) {
			$this->mpdf->WriteHTML('<h3>Mark Attachment Type: ' . $class . '</h3>');
			$this->mpdf->WriteHTML($this->glyphList($glyphs, true));
		}
	}

	protected function reportMarkGlyphSets(array $markGlyphSets)
	{
		if ($this->mode != 'summary') {
			return;
		}

		$this->mpdf->WriteHTML('<h1>Mark Glyph Sets</h1>');
		foreach ($markGlyphSets as $set => $glyphs) {
			$this->mpdf->WriteHTML('<h3>Mark Glyph Set class: ' . $set . '</h3>');
			$this->mpdf->WriteHTML($this->glyphList($glyphs, true));
		}
	}

	/**
	 * @param string[] $glyphs As hex
	 * @param bool     $marks  Whether to draw each on a dotted circle, as a mark is
	 */
	private function glyphList(array $glyphs, $marks)
	{
		$html = '<div class="glyphs">';
		foreach ($glyphs as $g) {
			$html .= ($marks ? '&#x25cc;' : '') . '&#x' . $g . '; ';
		}

		return $html . '</div>';
	}

	/**
	 * A Sequence of no glyphs is legal - it is how a font deletes the glyph it covers - so the report
	 * shows it substituting nothing rather than passing over it. @see TTFontFile::multipleSubstitutes
	 */
	protected function multipleSubstitutes(array $sequence)
	{
		$substitute = [];
		foreach ($sequence as $sub) {
			$substitute[] = GlyphString::of($this->glyphToChar[$sub][0]);
		}

		return $substitute;
	}

	/**
	 * Every alternate, where the parser keeps only the first: which alternates an `aalt` offers is
	 * most of what makes one worth looking at. @see TTFontFile::alternateSubstitutes
	 */
	protected function alternateSubstitutes(array $alternateSet)
	{
		$substitute = [];
		for ($gl = 0; $gl < $alternateSet['GlyphCount']; $gl++) {
			$gid = $alternateSet['SubstituteGlyphID'][$gl];
			// A glyph the cmap does not reach has no character to report it by
			if (isset($this->glyphToChar[$gid][0])) {
				$substitute[] = GlyphString::of($this->glyphToChar[$gid][0]);
			}
		}

		return $substitute;
	}

	/**
	 * Report the GSUB lookups the script and language asked for, instead of building the shaper's
	 * derived tables from every script the font offers. @see TTFontFile::useGSUBlookups
	 *
	 * @return string Always empty: the report says nothing about the RTL Private Use Area mapping
	 */
	protected function useGSUBlookups(array $Lookup, array $gsub, array $GSLookup, $gsubOffset)
	{
		$this->reportGSUBlookups($Lookup, $this->lookupsInTableOrder($gsub, 'GSUB'), $this->script, self::TOP_LEVEL);

		return '';
	}

	/**
	 * Report the substitution rules of a list of GSUB lookups, walked by the parser's _getGSUBarray().
	 *
	 * @param array  $Lookup    The GSUB lookup list, with subtable offsets already made absolute
	 * @param array  $lul       The lookups to report, as lookup index => the feature tag that asked
	 *                          for it
	 * @param string $scripttag The script the report is being written for
	 * @param array  $nesting   TOP_LEVEL for the report itself. For a lookup nested inside a context
	 *                          rule, level 2, whose part goes into the rule's rather than being
	 *                          written, with: the glyphs the nesting position can hold ('coverage',
	 *                          empty where it names class 0), so that only the rules that could fire
	 *                          there are reported; the example text before and after it ('exB',
	 *                          'exL'); and every glyph some class of the nesting rule's Class
	 *                          Definition names ('class0excl'), so that an empty coverage reads as
	 *                          class 0
	 */
	private function reportGSUBlookups(array $Lookup, $lul, $scripttag, array $nesting)
	{
		// Level 1 writes the report, level 2 adds its part to the rule that nested the lookup. Both
		// append to one buffer so that a nested lookup's thousands of rows can be handed over as they
		// are built, rather than arriving at level 1 as one string too long to write.
		if ($nesting['level'] == 1) {
			$this->report = '<bookmark level="0" content="GSUB features">';
		}

		$outer = $this->nesting;
		$this->nesting = $nesting;

		try {
			$this->_getGSUBarray($Lookup, $lul, $scripttag);
		} finally {
			$this->nesting = $outer;
		}

		if ($nesting['level'] == 1 && $this->report !== '') {
			$this->mpdf->WriteHTML($this->report);
			$this->report = '';
		}
	}

	protected function reportGSUBlookupStart(array $Lookup, $i, $tag)
	{
		$level = $this->nesting['level'];

		$this->report .= '<div class="level' . $level . '">';
		$this->report .= '<h5 class="level' . $level . '">';
		if ($level == 1) {
			$this->report .= '<bookmark level="1" content="' . $tag . ' [#' . $i . ']">';
		}
		$this->report .= 'Lookup #' . $i . ' [tag: <span style="color:#000066;">' . $tag . '</span>]</h5>';

		$ignore = $this->skippedClassNames($Lookup[$i]['Flag'], $Lookup[$i]['MarkFilteringSet']);
		if ($ignore) {
			$this->report .= '<div class="ignore">Ignoring: ' . $ignore . '</div> ';
		}
	}

	protected function reportGSUBlookupEnd()
	{
		$this->report .= '</div>';
		$this->flushReport($this->report);
	}

	protected function reportGSUBsubtable($c)
	{
		$this->report .= '<div class="subtable">Subtable #' . $c;
		if ($this->nesting['level'] == 1) {
			$this->report .= '<bookmark level="2" content="Subtable #' . $c . '">';
		}
		$this->report .= '</div>';
	}

	protected function reportGSUBlookupType($type, $format)
	{
		$types = [
			1 => 'LookupType 1: Single Substitution Subtable',
			2 => 'LookupType 2: Multiple Substitution Subtable',
			3 => 'LookupType 3: Alternate Forms',
			4 => 'LookupType 4: Ligature Substitution Subtable',
			5 => 'LookupType 5: Contextual Substitution Subtable',
			6 => 'LookupType 6: Chaining Contextual Substitution Subtable',
			8 => 'LookupType 8: Reverse Chaining Contextual Single Substitution Subtable',
		];

		$formats = [
			5 => [
				1 => 'Format 1: Context Substitution',
				2 => 'Format 2: Class-based Context Glyph Substitution',
				3 => 'Format 3: Coverage-based Context Glyph Substitution  ',
			],
			6 => [
				1 => 'Format 1: Simple Chaining Context Glyph Substitution  ',
				2 => 'Format 2: Class-based Chaining Context Glyph Substitution  ',
				3 => 'Format 3: Coverage-based Chaining Context Glyph Substitution  ',
			],
		];

		$this->report .= '<div class="lookuptype">' . $types[$type] . '</div>';

		if (isset($formats[$type][$format])) {
			$this->report .= '<div class="lookuptypesub">' . $formats[$type][$format] . '</div>';
		}
	}

	protected function reportGSUBinputClass($inputClass)
	{
		$this->report .= '<div class="rule">Input Class: ' . $inputClass . '</div>';
	}

	/**
	 * @return array Always empty: the substitutions are reported, one row each
	 */
	protected function gsubSubstitutions(array $Lookup, $i, $c, $tag)
	{
		$type = $Lookup[$i]['Type'];

		foreach ($Lookup[$i]['Subtable'][$c]['subs'] as $sub) {
			if ($type == 4) {
				$this->reportSubstitution($sub['Replace'], [$sub['substitute'][0]]);
			} elseif ($type == 2) {
				$this->reportSubstitution([$sub['Replace'][0]], $sub['substitute']);
			} else {
				// An Alternate substitution's first alternate is the substitution; the rest follow it
				$this->reportSubstitution([$sub['Replace'][0]], [$sub['substitute'][0]], $type == 3 ? array_slice($sub['substitute'], 1) : []);
			}
		}

		return [];
	}

	/**
	 * @return array Always empty: the rule is reported, along with every lookup it nests
	 */
	protected function gsubContextRule(array $Lookup, $i, $c, $tag, $scripttag, $ignore, array $rule)
	{
		if ($rule['index'] !== null) {
			$this->report .= '<div class="rule">' . ($Lookup[$i]['Subtable'][$c]['Format'] == 1 ? 'SubRule: ' : 'Rule: ') . $rule['index'] . '</div>';
		}

		list($class0excl, $bclass0excl, $lclass0excl) = $rule['class0excl'];

		$this->reportGSUBrule($Lookup, $rule['records'], $rule['backtrack'], $rule['input'], $rule['lookahead'], $class0excl, $bclass0excl, $lclass0excl, $tag, $scripttag);

		return [];
	}

	/**
	 * @return array Always empty: the substitutions are reported, one row each, without the context
	 */
	protected function gsubReverseChainRule(array $Lookup, $i, $c, $tag, $scripttag, $ignore, array $backtrackGlyphs, array $lookaheadGlyphs)
	{
		foreach ($Lookup[$i]['Subtable'][$c]['subs'] as $luss) {
			$this->reportSubstitution([$luss['Replace'][0]], [$luss['substitute'][0]], [], false);
		}

		return [];
	}

	/**
	 * The dump reads each class-based rule's positions fresh, where the parser does not (#170).
	 */
	protected function keepsEarlierRulePositions()
	{
		return false;
	}

	/**
	 * One substitution, as a row of the report.
	 *
	 * At level 2 only the substitutions the nesting position can hold are reported, each shown inside
	 * the example text of the rule that nested it. The position is tested before anything is
	 * formatted: a nested lookup is walked again for every rule that nests it, and most of its rows
	 * are not for that position.
	 *
	 * @param string[] $input       The glyphs replaced, as hex; the first is tested against the position
	 * @param string[] $output      What replaces them
	 * @param string[] $alternates  Any further alternates, each shown after the substitution
	 * @param bool     $withExample Whether to show the nesting rule's example text around it
	 */
	private function reportSubstitution(array $input, array $output, array $alternates = [], $withExample = true)
	{
		$nested = $this->nesting['level'] == 2;

		if ($nested && !$this->positionHolds($this->nesting['coverage'], $this->nesting['class0excl'], $input[0])) {
			return;
		}

		$exB = $withExample && $nested ? $this->nesting['exB'] : '';
		$exL = $withExample && $nested ? $this->nesting['exL'] : '';

		$this->flushReport($this->report);

		$this->report .= '<div class="substitution">';
		$this->report .= '<span class="unicode">' . $this->formatUniArr($input) . '&nbsp;</span> ';
		$this->report .= $exB . '<span class="unchanged">&nbsp;' . $this->formatEntityArr($input) . '</span>' . $exL;
		$this->report .= '&nbsp; &raquo; &raquo; &nbsp;';
		$this->report .= $exB . '<span class="changed">&nbsp;' . $this->formatEntityArr($output) . '</span>' . $exL;
		$this->report .= '&nbsp; <span class="unicode">' . $this->formatUniArr($output) . '</span> ';
		foreach ($alternates as $alt => $glyph) {
			$this->report .= '&nbsp; | &nbsp; ALT #' . ($alt + 1) . ' &nbsp; ';
			$this->report .= '<span class="changed">&nbsp;' . $this->formatEntity($glyph) . '</span>';
			$this->report .= '&nbsp; <span class="unicode">' . $this->formatUni($glyph) . '</span> ';
		}
		$this->report .= '</div>';
	}

	/**
	 * What a lookup's flags say to skip, in words, for the report to show above its rules.
	 *
	 * The parser's _getGSUBignoreString() projects the same LookupFlag::skipped() answer into a
	 * pattern of every glyph skipped; a list of every mark in the font is no use to a reader, so the
	 * classes are named instead. A font whose MarkFilteringSet GDEF never defined still fails here
	 * rather than being reported as if it were well formed.
	 *
	 * @param int $flag             The lookup's flags
	 * @param int $MarkFilteringSet The mark glyph set the flags name, where they name one
	 *
	 * @return string The classes skipped, named and "|"-separated, or "" where the flags skip nothing
	 */
	private function skippedClassNames($flag, $MarkFilteringSet)
	{
		$this->lookupFlag->checkMarkFilteringSet($flag, $MarkFilteringSet);

		$names = [
			LookupFlag::MARKS => 'Mark Glyphs ',
			LookupFlag::MARKS_OUTSIDE_FILTERING_SET => 'Marks outside Mark Glyph Set[' . $MarkFilteringSet . '] ',
			LookupFlag::MARKS_OUTSIDE_ATTACHMENT_CLASS => 'MarkAttachmentType[' . LookupFlag::attachmentClass($flag) . '] ',
			LookupFlag::LIGATURES => 'Ligature Glyphs ',
			LookupFlag::BASES => 'Base Glyphs ',
		];

		$skipped = [];
		foreach (LookupFlag::skipped($flag) as $class) {
			$skipped[] = $names[$class];
		}

		return implode('|', $skipped);
	}

	/**
	 * Summary mode stops at the scripts and languages a font offers, which is the whole of what that
	 * page reports. Detail mode goes on to walk the lookups of the one script it was asked for.
	 */
	protected function wantsLookups()
	{
		return $this->mode !== 'summary';
	}

	/**
	 * @param string $tag The layout table about to be reported, 'GSUB' or 'GPOS'
	 */
	protected function reportTableRead($tag)
	{
		$this->mpdf->WriteHTML('<h1>' . $tag . ' Tables</h1>');
	}

	/**
	 * @param string $tag The layout table this font does not have, 'GSUB' or 'GPOS'
	 */
	protected function reportTableMissing($tag)
	{
		$this->mpdf->WriteHTML('<div>' . $tag . ' table not defined</div>');
	}

	/**
	 * The scripts a table speaks for, each language system under them linked to its detail page, and
	 * the feature tags that language system asks for.
	 */
	protected function reportScriptList($tag, array $features)
	{
		// The summary page is this list; the detail page reports the lookups of one entry in it
		if ($this->wantsLookups()) {
			return;
		}

		$this->mpdf->WriteHTML('<h3>' . $tag . ' Scripts &amp; Languages</h3>');
		$this->mpdf->WriteHTML('<div class="glyphs">');

		$html = '';
		if (count($features)) {
			foreach ($features as $script => $languages) {
				$html .= '<h5>' . $script . '</h5>';
				foreach ($languages as $language => $tags) {
					$html .= '<div><a href="' . $this->detailLink($script, $language) . '">' . $language . '</a></b>: ';
					foreach ($tags as $featureTag => $lookupListIndices) {
						$html .= $featureTag . ' ';
					}
					$html .= '</div>';
				}
			}
		} else {
			$html .= '<div>No entries in ' . $tag . ' table.</div>';
		}

		$this->mpdf->WriteHTML($html);
		$this->mpdf->WriteHTML('</div>');
	}

	/**
	 * Every lookup the script and language system asked for, in the order the table lists them.
	 *
	 * A feature names the lookups it wants, but the order they run in is the Lookup table's rather
	 * than the feature list's, so they are keyed by lookup index and sorted. The tag is kept against
	 * each because the report names the feature that asked for it.
	 *
	 * @return array LookupListIndex => the feature tag that asked for it, in run order
	 */
	private function lookupsInTableOrder(array $features, $table)
	{
		$lul = [];
		foreach ($this->langSys($features, $table) as $tag => $lookupListIndices) {
			foreach ($lookupListIndices as $lookupListIndex) {
				$lul[$lookupListIndex] = $tag;
			}
		}

		ksort($lul);

		return $lul;
	}

	/**
	 * Report the GPOS lookups the script and language asked for, instead of caching their coverage.
	 *
	 * The parser's version of this walks every subtable to work out which glyph each one could match
	 * on, and writes that out for the shaper. The dump has no shaper to feed, so it goes the other
	 * way: the lookups this one script and language system actually run, in the order they run in,
	 * each written out rule by rule.
	 */
	protected function useGPOSlookups(array $Lookup, $gposOffset, array $features)
	{
		$this->_getGPOSarray(
			$this->absoluteSubtables($Lookup, $gposOffset),
			$this->lookupsInTableOrder($features, 'GPOS'),
			$this->script
		);
	}

	/**
	 * Report the positioning rules of a list of GPOS lookups.
	 *
	 * @param array  $Lookup     The GPOS lookup list, with subtable offsets already made absolute
	 * @param array  $lul        The lookups to report, as lookup index => the feature tag that asked
	 *                           for it
	 * @param string $scripttag  The script the report is being written for
	 * @param int    $level      1 for the report itself; 2 for a lookup nested inside a context rule,
	 *                           whose part is returned to the rule rather than written
	 * @param string $lcoverage  At level 2, the glyphs the nesting position can hold, so that only the
	 *                           rules that could fire there are reported. Empty where it names class 0
	 * @param string $exB        At level 2, the example text that precedes the nested position
	 * @param string $exL        At level 2, the example text that follows it
	 * @param string $class0excl At level 2, every glyph in some class of the nesting rule's Class
	 *                           Definition, so that an empty $lcoverage reads as class 0
	 *
	 * @return string At level 2, the rules; at level 1, the empty string, the report having been
	 *                written as it was built
	 */
	function _getGPOSarray(array $Lookup, $lul, $scripttag, $level = 1, $lcoverage = '', $exB = '', $exL = '', $class0excl = '')
	{
		// Process (3) LookupList for specific Script-LangSys
		// Level 1 writes the report, level 2 returns its part of it to the rule that nested the
		// lookup. Both append to one buffer so that a nested lookup's thousands of rows can be handed
		// over as they are built, rather than arriving at level 1 as one string too long to write.
		if ($level == 1) {
			$this->report = '';
		}
		$html = &$this->report;
		if ($level == 1) {
			$html .= '<bookmark level="0" content="GPOS features">';
		}
		foreach ($lul as $luli => $tag) {
			$html .= '<div class="level' . $level . '">';
			$html .= '<h5 class="level' . $level . '">';
			if ($level == 1) {
				$html .= '<bookmark level="1" content="' . $tag . ' [#' . $luli . ']">';
			}
			$html .= 'Lookup #' . $luli . ' [tag: <span style="color:#000066;">' . $tag . '</span>]</h5>';
			$ignore = $this->skippedClassNames($Lookup[$luli]['Flag'], $Lookup[$luli]['MarkFilteringSet']);
			if ($ignore) {
				$html .= '<div class="ignore">Ignoring: ' . $ignore . '</div> ';
			}

			$Type = $Lookup[$luli]['Type'];
			$Flag = $Lookup[$luli]['Flag'];
			if (($Flag & 0x0001) == 1) {
				$dir = 'RTL';
			} else {
				$dir = 'LTR';
			}

			for ($c = 0; $c < $Lookup[$luli]['SubtableCount']; $c++) {
				$html .= '<div class="subtable">Subtable #' . $c;
				if ($level == 1) {
					$html .= '<bookmark level="2" content="Subtable #' . $c . '">';
				}
				$html .= '</div>';

				// Lets start
				$subtable_offset = $Lookup[$luli]['Subtables'][$c];
				$this->reader->seek($subtable_offset);
				$PosFormat = $this->reader->readUInt16();

				// LookupType 1: Single adjustment 	Adjust position of a single glyph (e.g. SmallCaps/Sups/Subs)
				if ($Lookup[$luli]['Type'] == 1) {
					$html .= '<div class="lookuptype">LookupType 1: Single adjustment [Format ' . $PosFormat . ']</div>';
					// Format 1:
					if ($PosFormat == 1) {
						$Coverage = $subtable_offset + $this->reader->readUInt16();
						$ValueFormat = $this->reader->readUInt16();
						$Value = $this->valueRecord($ValueFormat);

						$this->reader->seek($Coverage);
						$glyphs = $this->coverageHex(); // Array of Hex Glyphs
						for ($g = 0; $g < count($glyphs); $g++) {
							if ($level == 2 && !$this->positionHolds($lcoverage, $class0excl, $glyphs[$g])) {
								continue;
							}

							$this->flushReport($html);
							$html .= '<div class="substitution">';
							$html .= '<span class="unicode">' . $this->formatUni($glyphs[$g]) . '&nbsp;</span> ';
							if ($level == 2 && $exB) {
								$html .= $exB;
							}
							$html .= '<span class="unchanged">&nbsp;' . $this->formatEntity($glyphs[$g]) . '</span>';
							if ($level == 2 && $exL) {
								$html .= $exL;
							}
							$html .= '&nbsp; &raquo; &raquo; &nbsp;';
							if ($level == 2 && $exB) {
								$html .= $exB;
							}
							$html .= '<span class="changed" style="font-feature-settings:\'' . $tag . '\' 1;">&nbsp;' . $this->formatEntity($glyphs[$g]) . '</span>';
							if ($level == 2 && $exL) {
								$html .= $exL;
							}
							$html .= ' <span class="unicode">';
							if ($Value['XPlacement']) {
								$html .= ' Xpl: ' . $Value['XPlacement'] . ';';
							}
							if ($Value['YPlacement']) {
								$html .= ' YPl: ' . $Value['YPlacement'] . ';';
							}
							if ($Value['XAdvance']) {
								$html .= ' Xadv: ' . $Value['XAdvance'];
							}
							$html .= '</span>';
							$html .= '</div>';
						}
					}
					// Format 2:
					else {
						if ($PosFormat == 2) {
							$Coverage = $subtable_offset + $this->reader->readUInt16();
							$ValueFormat = $this->reader->readUInt16();
							$ValueCount = $this->reader->readUInt16();
							$Values = [];
							for ($v = 0; $v < $ValueCount; $v++) {
								$Values[] = $this->valueRecord($ValueFormat);
							}

							$this->reader->seek($Coverage);
							$glyphs = $this->coverageHex(); // Array of Hex Glyphs

							for ($g = 0; $g < count($glyphs); $g++) {
								if ($level == 2 && !$this->positionHolds($lcoverage, $class0excl, $glyphs[$g])) {
									continue;
								}
								$Value = $Values[$g];

								$this->flushReport($html);
								$html .= '<div class="substitution">';
								$html .= '<span class="unicode">' . $this->formatUni($glyphs[$g]) . '&nbsp;</span> ';
								if ($level == 2 && $exB) {
									$html .= $exB;
								}
								$html .= '<span class="unchanged">&nbsp;' . $this->formatEntity($glyphs[$g]) . '</span>';
								if ($level == 2 && $exL) {
									$html .= $exL;
								}
								$html .= '&nbsp; &raquo; &raquo; &nbsp;';
								if ($level == 2 && $exB) {
									$html .= $exB;
								}
								$html .= '<span class="changed" style="font-feature-settings:\'' . $tag . '\' 1;">&nbsp;' . $this->formatEntity($glyphs[$g]) . '</span>';
								if ($level == 2 && $exL) {
									$html .= $exL;
								}
								$html .= ' <span class="unicode">';
								if ($Value['XPlacement']) {
									$html .= ' Xpl: ' . $Value['XPlacement'] . ';';
								}
								if ($Value['YPlacement']) {
									$html .= ' YPl: ' . $Value['YPlacement'] . ';';
								}
								if ($Value['XAdvance']) {
									$html .= ' Xadv: ' . $Value['XAdvance'];
								}
								$html .= '</span>';
								$html .= '</div>';
							}
						}
					}
				}
				// LookupType 2: Pair adjustment 	Adjust position of a pair of glyphs (Kerning)
				else {
					if ($Lookup[$luli]['Type'] == 2) {
						$html .= '<div class="lookuptype">LookupType 2: Pair adjustment e.g. Kerning [Format ' . $PosFormat . ']</div>';
						$Coverage = $subtable_offset + $this->reader->readUInt16();
						$ValueFormat1 = $this->reader->readUInt16();
						$ValueFormat2 = $this->reader->readUInt16();
						// Format 1:
						if ($PosFormat == 1) {
							$PairSetCount = $this->reader->readUInt16();
							$PairSetOffset = [];
							for ($p = 0; $p < $PairSetCount; $p++) {
								$PairSetOffset[] = $subtable_offset + $this->reader->readUInt16();
							}
							$this->reader->seek($Coverage);
							$glyphs = $this->coverageHex(); // Array of Hex Glyphs
							for ($p = 0; $p < $PairSetCount; $p++) {
								if ($level == 2 && !$this->positionHolds($lcoverage, $class0excl, $glyphs[$p])) {
									continue;
								}
								$this->reader->seek($PairSetOffset[$p]);
								// First Glyph = $glyphs[$p]
// Takes too long e.g. Calibri font - just list kerning pairs with this:
								$html .= '<div class="glyphs">';
								$html .= '<span class="unchanged">&nbsp;' . $this->formatEntity($glyphs[$p]) . ' </span>';

								//PairSet table
								$PairValueCount = $this->reader->readUInt16();
								for ($pv = 0; $pv < $PairValueCount; $pv++) {
									//PairValueRecord
									$gid = $this->reader->readUInt16();
									$SecondGlyph = GlyphString::of($this->glyphToChar[$gid][0]);
									$Value1 = $this->valueRecord($ValueFormat1);
									$Value2 = $this->valueRecord($ValueFormat2);

									// If RTL pairs, GPOS declares a XPlacement e.g. -180 for an XAdvance of -180 to take
									// account of direction. mPDF does not need the XPlacement adjustment
									if ($dir == 'RTL' && $Value1['XPlacement']) {
										$Value1['XPlacement'] -= $Value1['XAdvance'];
									}

									if ($ValueFormat2) {
										// If RTL pairs, GPOS declares a XPlacement e.g. -180 for an XAdvance of -180 to take
										// account of direction. mPDF does not need the XPlacement adjustment
										if ($dir == 'RTL' && $Value2['XPlacement'] && $Value2['XAdvance']) {
											$Value2['XPlacement'] -= $Value2['XAdvance'];
										}
									}

									$html .= ' ' . $this->formatEntity($SecondGlyph) . ' ';

									/*
									  $html .= '<div class="substitution">';
									  $html .= '<span class="unicode">'.$this->formatUni($glyphs[$p]).'&nbsp;</span> ';
									  if ($level==2 && $exB) { $html .= $exB; }
									  $html .= '<span class="unchanged">&nbsp;'.$this->formatEntity($glyphs[$p]).$this->formatEntity($SecondGlyph).'</span>';
									  if ($level==2 && $exL) { $html .= $exL; }
									  $html .= '&nbsp; &raquo; &raquo; &nbsp;';
									  if ($level==2 && $exB) { $html .= $exB; }
									  $html .= '<span class="changed" style="font-feature-settings:\''.$tag.'\' 1;">&nbsp;'.$this->formatEntity($glyphs[$p]).$this->formatEntity($SecondGlyph).'</span>';
									  if ($level==2 && $exL) { $html .= $exL; }
									  $html .= ' <span class="unicode">';
									  if ($Value1['XPlacement']) { $html .= ' Xpl[1]: '.$Value1['XPlacement'].';'; }
									  if ($Value1['YPlacement']) { $html .= ' YPl[1]: '.$Value1['YPlacement'].';'; }
									  if ($Value1['XAdvance']) { $html .= ' Xadv[1]: '.$Value1['XAdvance']; }
									  if ($Value2['XPlacement']) { $html .= ' Xpl[2]: '.$Value2['XPlacement'].';'; }
									  if ($Value2['YPlacement']) { $html .= ' YPl[2]: '.$Value2['YPlacement'].';'; }
									  if ($Value2['XAdvance']) { $html .= ' Xadv[2]: '.$Value2['XAdvance']; }
									  $html .= '</span>';
									  $html .= '</div>';
									 */
								}
								$html .= '</div>';
							}
						}
						// Format 2:
						else {
							if ($PosFormat == 2) {
								$ClassDef1 = $subtable_offset + $this->reader->readUInt16();
								$ClassDef2 = $subtable_offset + $this->reader->readUInt16();
								$Class1Count = $this->reader->readUInt16();
								$Class2Count = $this->reader->readUInt16();

								$sizeOfPair = ValueRecord::size($ValueFormat1) + ValueRecord::size($ValueFormat2);
								$sizeOfValueRecords = $Class1Count * $Class2Count * $sizeOfPair;

								// NB Class1Count includes Class 0 even though it is not defined by $ClassDef1
								// i.e. Class1Count = 5; Class1 will contain array(indices 1-4);
								$Class1 = $this->_getClassDefinitionTable($ClassDef1);
								$Class2 = $this->_getClassDefinitionTable($ClassDef2);

								$this->reader->seek($subtable_offset + 16);

								for ($i = 0; $i < $Class1Count; $i++) {
									for ($j = 0; $j < $Class2Count; $j++) {
										$Value1 = $this->valueRecord($ValueFormat1);
										$Value2 = $this->valueRecord($ValueFormat2);

										// If RTL pairs, GPOS declares a XPlacement e.g. -180 for an XAdvance of -180
										// of direction. mPDF does not need the XPlacement adjustment
										if ($dir == 'RTL' && $Value1['XPlacement'] && $Value1['XAdvance']) {
											$Value1['XPlacement'] -= $Value1['XAdvance'];
										}
										if ($ValueFormat2) {
											if ($dir == 'RTL' && $Value2['XPlacement'] && $Value2['XAdvance']) {
												$Value2['XPlacement'] -= $Value2['XAdvance'];
											}
										}

										// Class1Count counts class 0, which ClassDef1 does not define, and a font may
										// leave any other class empty too. Otl guards both the same way; this copy
										// indexed straight in and killed the dump on the first font with a gap.
										if (!isset($Class1[$i]) || !isset($Class2[$j])) {
											continue;
										}

										for ($c1 = 0; $c1 < count($Class1[$i]); $c1++) {
											$FirstGlyph = $Class1[$i][$c1];
											if ($level == 2 && !$this->positionHolds($lcoverage, $class0excl, $FirstGlyph)) {
												continue;
											}

											for ($c2 = 0; $c2 < count($Class2[$j]); $c2++) {
												$SecondGlyph = $Class2[$j][$c2];

												if (!$Value1['XPlacement'] && !$Value1['YPlacement'] && !$Value1['XAdvance'] && !$Value2['XPlacement'] && !$Value2['YPlacement'] && !$Value2['XAdvance']) {
													continue;
												}

												$this->flushReport($html);
												$html .= '<div class="substitution">';
												$html .= '<span class="unicode">' . $this->formatUni($FirstGlyph) . '&nbsp;</span> ';
												if ($level == 2 && $exB) {
													$html .= $exB;
												}
												$html .= '<span class="unchanged">&nbsp;' . $this->formatEntity($FirstGlyph) . $this->formatEntity($SecondGlyph) . '</span>';
												if ($level == 2 && $exL) {
													$html .= $exL;
												}
												$html .= '&nbsp; &raquo; &raquo; &nbsp;';
												if ($level == 2 && $exB) {
													$html .= $exB;
												}
												$html .= '<span class="changed" style="font-feature-settings:\'' . $tag . '\' 1;">&nbsp;' . $this->formatEntity($FirstGlyph) . $this->formatEntity($SecondGlyph) . '</span>';
												if ($level == 2 && $exL) {
													$html .= $exL;
												}
												$html .= ' <span class="unicode">';
												if ($Value1['XPlacement']) {
													$html .= ' Xpl[1]: ' . $Value1['XPlacement'] . ';';
												}
												if ($Value1['YPlacement']) {
													$html .= ' YPl[1]: ' . $Value1['YPlacement'] . ';';
												}
												if ($Value1['XAdvance']) {
													$html .= ' Xadv[1]: ' . $Value1['XAdvance'];
												}
												if ($Value2['XPlacement']) {
													$html .= ' Xpl[2]: ' . $Value2['XPlacement'] . ';';
												}
												if ($Value2['YPlacement']) {
													$html .= ' YPl[2]: ' . $Value2['YPlacement'] . ';';
												}
												if ($Value2['XAdvance']) {
													$html .= ' Xadv[2]: ' . $Value2['XAdvance'];
												}
												$html .= '</span>';
												$html .= '</div>';
											}
										}
									}
								}
							}
						}
					}
					// LookupType 3: Cursive attachment 	Attach cursive glyphs
					else {
						if ($Lookup[$luli]['Type'] == 3) {
							$html .= '<div class="lookuptype">LookupType 3: Cursive attachment </div>';
							$Coverage = $subtable_offset + $this->reader->readUInt16();
							$EntryExitCount = $this->reader->readUInt16();
							$EntryAnchors = [];
							$ExitAnchors = [];
							for ($i = 0; $i < $EntryExitCount; $i++) {
								$EntryAnchors[$i] = $this->reader->readUInt16();
								$ExitAnchors[$i] = $this->reader->readUInt16();
							}

							$this->reader->seek($Coverage);
							$Glyphs = $this->coverageHex();
							for ($i = 0; $i < $EntryExitCount; $i++) {
								// Need default XAdvance for glyph
								$pdfWidth = $this->mpdf->_getCharWidth($this->mpdf->fonts[$this->fontkey]['cw'], hexdec($Glyphs[$i]));
								$EntryAnchor = $EntryAnchors[$i];
								$ExitAnchor = $ExitAnchors[$i];
								$html .= '<div class="glyphs">';
								$html .= '<span class="unchanged">' . $this->formatEntity($Glyphs[$i]) . ' </span> ';
								$html .= '<span class="unicode"> ' . $this->formatUni($Glyphs[$i]) . ' => ';

								if ($EntryAnchor != 0) {
									$EntryAnchor += $subtable_offset;
									list($x, $y) = Anchor::coordinates($this->reader, $EntryAnchor);
									if ($dir == 'RTL') {
										if (round($pdfWidth) == round($x * 1000 / $this->unitsPerEm)) {
											$x = 0;
										} else {
											$x = $x - ($pdfWidth * $this->unitsPerEm / 1000);
										}
									}
									$html .= " Entry X: " . $x . " Y: " . $y . "; ";
								}
								if ($ExitAnchor != 0) {
									$ExitAnchor += $subtable_offset;
									list($x, $y) = Anchor::coordinates($this->reader, $ExitAnchor);
									if ($dir == 'LTR') {
										if (round($pdfWidth) == round($x * 1000 / $this->unitsPerEm)) {
											$x = 0;
										} else {
											$x = $x - ($pdfWidth * $this->unitsPerEm / 1000);
										}
									}
									$html .= " Exit X: " . $x . " Y: " . $y . "; ";
								}

								$html .= '</span></div>';
							}
						}
						// LookupType 4: MarkToBase attachment 	Attach a combining mark to a base glyph
						else {
							if ($Lookup[$luli]['Type'] == 4) {
								$html .= '<div class="lookuptype">LookupType 4: MarkToBase attachment </div>';
								$MarkCoverage = $subtable_offset + $this->reader->readUInt16();
								$BaseCoverage = $subtable_offset + $this->reader->readUInt16();

								$this->reader->seek($MarkCoverage);
								$MarkGlyphs = $this->coverageHex();

								$this->reader->seek($BaseCoverage);
								$BaseGlyphs = $this->coverageHex();

								$firstMark = '';
								$html .= '<div class="glyphs">Marks: ';
								for ($i = 0; $i < count($MarkGlyphs); $i++) {
									if ($level == 2 && !$this->positionHolds($lcoverage, $class0excl, $MarkGlyphs[$i])) {
										continue;
									} else {
										if (!$firstMark) {
											$firstMark = $MarkGlyphs[$i];
										}
									}
									$html .= ' ' . $this->formatEntity($MarkGlyphs[$i]) . ' ';
								}
								$html .= '</div>';
								if (!$firstMark) {
									return;
								}

								$html .= '<div class="glyphs">Bases: ';
								for ($j = 0; $j < count($BaseGlyphs); $j++) {
									$html .= ' ' . $this->formatEntity($BaseGlyphs[$j]) . ' ';
								}
								$html .= '</div>';

								// Example
								$html .= '<div class="glyphs" style="font-feature-settings:\'' . $tag . '\' 1;">Example(s): ';
								for ($j = 0; $j < min(count($BaseGlyphs), 20); $j++) {
									$html .= ' ' . $this->formatEntity($BaseGlyphs[$j]) . $this->formatEntity($firstMark, true) . ' &nbsp; ';
								}
								$html .= '</div>';
							}
							// LookupType 5: MarkToLigature attachment 	Attach a combining mark to a ligature
							else {
								if ($Lookup[$luli]['Type'] == 5) {
									$html .= '<div class="lookuptype">LookupType 5: MarkToLigature attachment </div>';
									$MarkCoverage = $subtable_offset + $this->reader->readUInt16();
									//$MarkCoverage is already set in $lcoverage 00065|00073 etc
									$LigatureCoverage = $subtable_offset + $this->reader->readUInt16();
									$ClassCount = $this->reader->readUInt16(); // Number of classes defined for marks = Number of mark glyphs in the MarkCoverage table
									$MarkArray = $subtable_offset + $this->reader->readUInt16(); // Offset to MarkArray table
									$LigatureArray = $subtable_offset + $this->reader->readUInt16(); // Offset to LigatureArray table

									$this->reader->seek($MarkCoverage);
									$MarkGlyphs = $this->coverageHex();
									$this->reader->seek($LigatureCoverage);
									$LigatureGlyphs = $this->coverageHex();

									$firstMark = '';
									$html .= '<div class="glyphs">Marks: <span class="unchanged">';
									$MarkRecord = [];
									for ($i = 0; $i < count($MarkGlyphs); $i++) {
										if ($level == 2 && !$this->positionHolds($lcoverage, $class0excl, $MarkGlyphs[$i])) {
											continue;
										} else {
											if (!$firstMark) {
												$firstMark = $MarkGlyphs[$i];
											}
										}
										// Get the relevant MarkRecord
										$MarkRecord[$i] = MarkArray::record($this->reader, $MarkArray, $i);
										//Mark Class is = $MarkRecord[$i]['Class']
										$html .= ' ' . $this->formatEntity($MarkGlyphs[$i]) . ' ';
									}
									$html .= '</span></div>';
									if (!$firstMark) {
										return;
									}

									$this->reader->seek($LigatureArray);
									$LigatureCount = $this->reader->readUInt16();
									$LigatureAttach = [];
									$html .= '<div class="glyphs">Ligatures: <span class="unchanged">';
									for ($j = 0; $j < count($LigatureGlyphs); $j++) {
										// Get the relevant LigatureRecord
										$LigatureAttach[$j] = $LigatureArray + $this->reader->readUInt16();
										$html .= ' ' . $this->formatEntity($LigatureGlyphs[$j]) . ' ';
									}
									$html .= '</span></div>';

									/*
									  for ($i=0;$i<count($MarkGlyphs);$i++) {
									  $html .= '<div class="glyphs">';
									  $html .= '<span class="unchanged">'.$this->formatEntity($MarkGlyphs[$i]).'</span>';

									  for ($j=0;$j<count($LigatureGlyphs);$j++) {
									  $this->reader->seek($LigatureAttach[$j]);
									  $ComponentCount = $this->reader->readUInt16();
									  $html .= '<span class="unchanged">'.$this->formatEntity($LigatureGlyphs[$j]).'</span>';
									  $offsets = array();
									  for ($comp=0;$comp<$ComponentCount;$comp++) {
									  // ComponentRecords
									  for ($class=0;$class<$ClassCount;$class++) {
									  $offset = $this->reader->readUInt16();
									  if ($offset!= 0 && $class == $MarkRecord[$i]['Class']) {

									  $html .= ' ['.$comp.'] ';

									  }
									  }
									  }
									  }
									  $html .= '</span></div>';
									  }
									 */
								}
								// LookupType 6: MarkToMark attachment 	Attach a combining mark to another mark
								else {
									if ($Lookup[$luli]['Type'] == 6) {
										$html .= '<div class="lookuptype">LookupType 6: MarkToMark attachment </div>';
										$Mark1Coverage = $subtable_offset + $this->reader->readUInt16(); // Combining Mark
										//$Mark1Coverage is already set in $LuCoverage 0065|0073 etc
										$Mark2Coverage = $subtable_offset + $this->reader->readUInt16(); // Base Mark
										$ClassCount = $this->reader->readUInt16(); // Number of classes defined for marks = No. of Combining mark1 glyphs in the MarkCoverage table
										$this->reader->seek($Mark1Coverage);
										$Mark1Glyphs = $this->coverageHex();
										$this->reader->seek($Mark2Coverage);
										$Mark2Glyphs = $this->coverageHex();

										$firstMark = '';
										$html .= '<div class="glyphs">Marks: <span class="unchanged">';
										for ($i = 0; $i < count($Mark1Glyphs); $i++) {
											if ($level == 2 && !$this->positionHolds($lcoverage, $class0excl, $Mark1Glyphs[$i])) {
												continue;
											} else {
												if (!$firstMark) {
													$firstMark = $Mark1Glyphs[$i];
												}
											}
											$html .= ' ' . $this->formatEntity($Mark1Glyphs[$i]) . ' ';
										}
										$html .= '</span></div>';

										if ($firstMark) {
											$html .= '<div class="glyphs">Bases: <span class="unchanged">';
											for ($j = 0; $j < count($Mark2Glyphs); $j++) {
												$html .= ' ' . $this->formatEntity($Mark2Glyphs[$j]) . ' ';
											}
											$html .= '</span></div>';

											// Example
											$html .= '<div class="glyphs" style="font-feature-settings:\'' . $tag . '\' 1;">Example(s): <span class="changed">';
											for ($j = 0; $j < min(count($Mark2Glyphs), 20); $j++) {
												$html .= ' ' . $this->formatEntity($Mark2Glyphs[$j]) . $this->formatEntity($firstMark, true) . ' &nbsp; ';
											}
											$html .= '</span></div>';
										}
									} else {
										if ($Lookup[$luli]['Type'] == 7) {
											$html .= '<div class="lookuptype">LookupType 7: Context positioning [Format ' . $PosFormat . ']</div>';
											$this->reportGPOScontextPos($Lookup, $subtable_offset, $PosFormat, $tag, $scripttag);
										} elseif ($Lookup[$luli]['Type'] == 8) {
											$html .= '<div class="lookuptype">LookupType 8: Chained Context positioning [Format ' . $PosFormat . ']</div>';
											$this->reportGPOSchainContextPos($Lookup, $subtable_offset, $PosFormat, $tag, $scripttag);
										}
									}
								}
							}
						}
					}
				}
			}
			$html .= '</div>';
			$this->flushReport($html);
		}
		if ($level == 1 && $html !== '') {
			$this->mpdf->WriteHTML($html);
			$html = '';
		}

		return '';
	}

	/**
	 * LookupType 7: Context positioning - position one or more glyphs in context.
	 *
	 * The GPOS counterpart of GSUB's Type 5, and read the same way: a rule matches a run of glyphs
	 * and then hands named positions within it to other lookups, which do the positioning. All three
	 * formats are reported by walking every rule and reporting the nested lookup under the context it
	 * fires in - which is what makes the report worth reading, because the lookup on its own says
	 * nothing about when it applies.
	 *
	 * @see https://learn.microsoft.com/en-us/typography/opentype/spec/gpos#contextual-positioning-subtables
	 *
	 * Every one of these appends to $this->report rather than returning a string, because a nested
	 * lookup reported at level 2 writes into that same buffer itself: a caller that built its own
	 * string and appended it afterwards would put the rule and the lookup it runs in the wrong order.
	 */
	private function reportGPOScontextPos(array $Lookup, $subtable_offset, $PosFormat, $tag, $scripttag)
	{
		if ($PosFormat == 1) {
			$this->reportGPOScontextPosFormat1($Lookup, $subtable_offset, $tag, $scripttag);
		} elseif ($PosFormat == 2) {
			$this->reportGPOScontextPosFormat2($Lookup, $subtable_offset, $tag, $scripttag);
		} elseif ($PosFormat == 3) {
			$this->reportGPOScontextPosFormat3($Lookup, $subtable_offset, $tag, $scripttag);
		} else {
			throw new \Mpdf\Exception\FontException(sprintf('GPOS Lookup Type 7, Format "%s" not supported.', $PosFormat));
		}
	}

	/**
	 * Format 1: the rules list the glyphs they match one by one.
	 *
	 * Rules are grouped into a PosRuleSet per first glyph, and which set is which is given by the
	 * position of that glyph in the subtable's Coverage table.
	 */
	private function reportGPOScontextPosFormat1(array $Lookup, $subtable_offset, $tag, $scripttag)
	{
		$this->report .= '<div class="lookuptypesub">Format 1: Context Positioning</div>';

		$CoverageTableOffset = $subtable_offset + $this->reader->readUInt16();
		$PosRuleSetCount = $this->reader->readUInt16();
		$PosRuleSetOffset = [];
		for ($s = 0; $s < $PosRuleSetCount; $s++) {
			$PosRuleSetOffset[$s] = $this->reader->readUInt16();
		}

		$this->reader->seek($CoverageTableOffset);
		$CoverageGlyphs = $this->coverageHex();

		for ($s = 0; $s < $PosRuleSetCount; $s++) {
			// A PosRuleSet offset of 0 means no context begins with that glyph
			if (!$PosRuleSetOffset[$s]) {
				continue;
			}

			$this->report .= '<div class="rule">Pos Rule Set: ' . $s . '</div>';

			$PosRuleSet = $subtable_offset + $PosRuleSetOffset[$s];
			$this->reader->seek($PosRuleSet);
			$PosRuleCount = $this->reader->readUInt16();
			$PosRule = [];
			for ($b = 0; $b < $PosRuleCount; $b++) {
				$PosRule[$b] = $PosRuleSet + $this->reader->readUInt16();
			}

			for ($b = 0; $b < $PosRuleCount; $b++) {
				$this->report .= '<div class="rule">PosRule: ' . $b . '</div>';
				$this->reader->seek($PosRule[$b]);
				list($inputGlyphIDs, $PosCount) = SequenceRule::plain($this->reader);

				// Position 0 is the glyph the Coverage table selected this rule set by, so the rule
				// itself lists one fewer than it counts
				$inputGlyphs = array_merge(
					[isset($CoverageGlyphs[$s]) ? $CoverageGlyphs[$s] : ''],
					$this->glyphNames($inputGlyphIDs)
				);

				$records = SequenceRule::lookupRecords($this->reader, $PosCount);

				$this->reportGPOSrule($Lookup, $records, [], $inputGlyphs, [], '', '', '', $tag, $scripttag);
			}
		}
	}

	/**
	 * Format 2: the rules match classes of glyphs rather than glyphs.
	 *
	 * The rule set array is indexed by the class of the first input glyph, so the loop index over it
	 * is that class.
	 */
	private function reportGPOScontextPosFormat2(array $Lookup, $subtable_offset, $tag, $scripttag)
	{
		$this->report .= '<div class="lookuptypesub">Format 2: Class-based Context Positioning</div>';

		$this->reader->readUInt16(); // coverageOffset, which class 0 stands in for below
		$InputClassDefOffset = $subtable_offset + $this->reader->readUInt16();
		$PosClassSetCnt = $this->reader->readUInt16();
		$PosClassSetOffset = [];
		for ($b = 0; $b < $PosClassSetCnt; $b++) {
			$PosClassSetOffset[$b] = $this->reader->readUInt16();
		}

		$InputClasses = $this->_getClasses($InputClassDefOffset);

		// Class 0 is every glyph that is in none of the other classes, so it is reported as what it
		// excludes rather than as a list
		$class0excl = implode('|', $InputClasses);

		for ($s = 0; $s < $PosClassSetCnt; $s++) {
			// A PosClassSet offset of 0 means no context begins with a glyph of that class
			if (!$PosClassSetOffset[$s]) {
				continue;
			}

			$this->report .= '<div class="rule">Input Class: ' . $s . '</div>';

			$PosClassSet = $subtable_offset + $PosClassSetOffset[$s];
			$this->reader->seek($PosClassSet);
			$PosClassRuleCnt = $this->reader->readUInt16();
			$PosClassRule = [];
			for ($b = 0; $b < $PosClassRuleCnt; $b++) {
				$PosClassRule[$b] = $PosClassSet + $this->reader->readUInt16();
			}

			for ($b = 0; $b < $PosClassRuleCnt; $b++) {
				$this->report .= '<div class="rule">Rule: ' . $b . '</div>';
				$this->reader->seek($PosClassRule[$b]);
				list($inputClassIndices, $PosCount) = SequenceRule::plain($this->reader);

				$inputGlyphs = array_merge(
					[$this->classGlyphs($InputClasses, $s)],
					$this->classNames($InputClasses, $inputClassIndices)
				);

				$records = SequenceRule::lookupRecords($this->reader, $PosCount);

				$this->reportGPOSrule($Lookup, $records, [], $inputGlyphs, [], $class0excl, '', '', $tag, $scripttag);
			}
		}
	}

	/**
	 * Format 3: one Coverage table per input position, and one rule.
	 *
	 * Unlike Type 8 Format 3, the count of positionings precedes the Coverage table offsets.
	 */
	private function reportGPOScontextPosFormat3(array $Lookup, $subtable_offset, $tag, $scripttag)
	{
		$this->report .= '<div class="lookuptypesub">Format 3: Coverage-based Context Positioning</div>';

		$InputGlyphCount = $this->reader->readUInt16();
		$PosCount = $this->reader->readUInt16();
		$inputOffsets = SequenceRule::coverageOffsets($this->reader, $subtable_offset, $InputGlyphCount);
		$records = SequenceRule::lookupRecords($this->reader, $PosCount);

		$this->reportGPOSrule($Lookup, $records, [], $this->coverageGlyphs($inputOffsets), [], '', '', '', $tag, $scripttag);
	}

	/**
	 * LookupType 8: Chained context positioning - Type 7 with a backtrack and a lookahead sequence
	 * either side of the input.
	 *
	 * @see https://learn.microsoft.com/en-us/typography/opentype/spec/gpos#chained-contexts-positioning-subtable
	 */
	private function reportGPOSchainContextPos(array $Lookup, $subtable_offset, $PosFormat, $tag, $scripttag)
	{
		if ($PosFormat == 1) {
			$this->reportGPOSchainContextPosFormat1($Lookup, $subtable_offset, $tag, $scripttag);
		} elseif ($PosFormat == 2) {
			$this->reportGPOSchainContextPosFormat2($Lookup, $subtable_offset, $tag, $scripttag);
		} elseif ($PosFormat == 3) {
			$this->reportGPOSchainContextPosFormat3($Lookup, $subtable_offset, $tag, $scripttag);
		} else {
			throw new \Mpdf\Exception\FontException(sprintf('GPOS Lookup Type 8, Format "%s" not supported.', $PosFormat));
		}
	}

	/**
	 * Format 1: the rules list the glyphs of all three sequences one by one. @see reportGPOScontextPosFormat1
	 */
	private function reportGPOSchainContextPosFormat1(array $Lookup, $subtable_offset, $tag, $scripttag)
	{
		$this->report .= '<div class="lookuptypesub">Format 1: Simple Chaining Context Positioning</div>';

		$CoverageTableOffset = $subtable_offset + $this->reader->readUInt16();
		$ChainPosRuleSetCount = $this->reader->readUInt16();
		$ChainPosRuleSetOffset = [];
		for ($s = 0; $s < $ChainPosRuleSetCount; $s++) {
			$ChainPosRuleSetOffset[$s] = $this->reader->readUInt16();
		}

		$this->reader->seek($CoverageTableOffset);
		$CoverageGlyphs = $this->coverageHex();

		for ($s = 0; $s < $ChainPosRuleSetCount; $s++) {
			if (!$ChainPosRuleSetOffset[$s]) {
				continue;
			}

			$this->report .= '<div class="rule">Chain Pos Rule Set: ' . $s . '</div>';

			$ChainPosRuleSet = $subtable_offset + $ChainPosRuleSetOffset[$s];
			$this->reader->seek($ChainPosRuleSet);
			$ChainPosRuleCount = $this->reader->readUInt16();
			$ChainPosRule = [];
			for ($b = 0; $b < $ChainPosRuleCount; $b++) {
				$ChainPosRule[$b] = $ChainPosRuleSet + $this->reader->readUInt16();
			}

			for ($b = 0; $b < $ChainPosRuleCount; $b++) {
				$this->report .= '<div class="rule">ChainPosRule: ' . $b . '</div>';
				$this->reader->seek($ChainPosRule[$b]);

				list($backtrackGlyphIDs, $inputGlyphIDs, $lookaheadGlyphIDs) = SequenceRule::chained($this->reader);

				$backtrackGlyphs = $this->glyphNames($backtrackGlyphIDs);
				$inputGlyphs = array_merge(
					[isset($CoverageGlyphs[$s]) ? $CoverageGlyphs[$s] : ''],
					$this->glyphNames($inputGlyphIDs)
				);
				$lookaheadGlyphs = $this->glyphNames($lookaheadGlyphIDs);

				$records = SequenceRule::lookupRecords($this->reader, $this->reader->readUInt16());

				$this->reportGPOSrule($Lookup, $records, $backtrackGlyphs, $inputGlyphs, $lookaheadGlyphs, '', '', '', $tag, $scripttag);
			}
		}
	}

	/**
	 * Format 2: the rules match classes, with a class definition of its own for each of the three
	 * sequences. @see reportGPOScontextPosFormat2
	 */
	private function reportGPOSchainContextPosFormat2(array $Lookup, $subtable_offset, $tag, $scripttag)
	{
		$this->report .= '<div class="lookuptypesub">Format 2: Class-based Chaining Context Positioning</div>';

		$this->reader->readUInt16(); // coverageOffset, which class 0 stands in for below
		$BacktrackClassDefOffset = $subtable_offset + $this->reader->readUInt16();
		$InputClassDefOffset = $subtable_offset + $this->reader->readUInt16();
		$LookaheadClassDefOffset = $subtable_offset + $this->reader->readUInt16();
		$ChainPosClassSetCnt = $this->reader->readUInt16();
		$ChainPosClassSetOffset = [];
		for ($b = 0; $b < $ChainPosClassSetCnt; $b++) {
			$ChainPosClassSetOffset[$b] = $this->reader->readUInt16();
		}

		$BacktrackClasses = $this->_getClasses($BacktrackClassDefOffset);
		$InputClasses = $this->_getClasses($InputClassDefOffset);
		$LookaheadClasses = $this->_getClasses($LookaheadClassDefOffset);

		// Class 0 is every glyph in none of the other classes of its own ClassDef, and a chained
		// context has three of them, so each sequence is told what its own class 0 excludes
		$class0excl = implode('|', $InputClasses);
		$bclass0excl = implode('|', $BacktrackClasses);
		$lclass0excl = implode('|', $LookaheadClasses);

		for ($s = 0; $s < $ChainPosClassSetCnt; $s++) {
			if (!$ChainPosClassSetOffset[$s]) {
				continue;
			}

			$this->report .= '<div class="rule">Input Class: ' . $s . '</div>';

			$ChainPosClassSet = $subtable_offset + $ChainPosClassSetOffset[$s];
			$this->reader->seek($ChainPosClassSet);
			$ChainPosClassRuleCnt = $this->reader->readUInt16();
			$ChainPosClassRule = [];
			for ($b = 0; $b < $ChainPosClassRuleCnt; $b++) {
				$ChainPosClassRule[$b] = $ChainPosClassSet + $this->reader->readUInt16();
			}

			for ($b = 0; $b < $ChainPosClassRuleCnt; $b++) {
				$this->report .= '<div class="rule">Rule: ' . $b . '</div>';
				$this->reader->seek($ChainPosClassRule[$b]);

				list($backtrackClassIndices, $inputClassIndices, $lookaheadClassIndices) = SequenceRule::chained($this->reader);

				$backtrackGlyphs = $this->classNames($BacktrackClasses, $backtrackClassIndices);
				$inputGlyphs = array_merge(
					[$this->classGlyphs($InputClasses, $s)],
					$this->classNames($InputClasses, $inputClassIndices)
				);
				$lookaheadGlyphs = $this->classNames($LookaheadClasses, $lookaheadClassIndices);

				$records = SequenceRule::lookupRecords($this->reader, $this->reader->readUInt16());

				$this->reportGPOSrule($Lookup, $records, $backtrackGlyphs, $inputGlyphs, $lookaheadGlyphs, $class0excl, $bclass0excl, $lclass0excl, $tag, $scripttag);
			}
		}
	}

	/**
	 * Format 3: one Coverage table per position of all three sequences, and one rule.
	 */
	private function reportGPOSchainContextPosFormat3(array $Lookup, $subtable_offset, $tag, $scripttag)
	{
		$this->report .= '<div class="lookuptypesub">Format 3: Coverage-based Chaining Context Positioning</div>';

		$backtrackOffsets = SequenceRule::coverageOffsets($this->reader, $subtable_offset, $this->reader->readUInt16());
		$inputOffsets = SequenceRule::coverageOffsets($this->reader, $subtable_offset, $this->reader->readUInt16());
		$lookaheadOffsets = SequenceRule::coverageOffsets($this->reader, $subtable_offset, $this->reader->readUInt16());
		$records = SequenceRule::lookupRecords($this->reader, $this->reader->readUInt16());

		$this->reportGPOSrule(
			$Lookup,
			$records,
			$this->coverageGlyphs($backtrackOffsets),
			$this->coverageGlyphs($inputOffsets),
			$this->coverageGlyphs($lookaheadOffsets),
			'',
			'',
			'',
			$tag,
			$scripttag
		);
	}

	/**
	 * The CONTEXT block of one rule: the sequences it matches, a line per position.
	 *
	 * Every contextual and chaining format of both tables renders this, and there were seven copies
	 * of it - six written for GSUB, and the seventh added for GPOS by #90 to the shape the six
	 * already had.
	 *
	 * A plain context has no backtrack or lookahead and passes empty arrays for them. A rule that
	 * names glyphs rather than classes passes no exclusions, and every position then reads as the
	 * glyphs it holds.
	 *
	 * Backtrack is held in glyph sequence order, which runs away from the input rather than towards
	 * it, so it is reported last position first - the order the text reads in.
	 *
	 * @param string $class0excl  Every glyph in some class of the input sequence's Class Definition,
	 *                            so that a position naming class 0 reads as what it excludes rather
	 *                            than as a list. Empty where the rule names glyphs.
	 * @param string $bclass0excl Likewise for the backtrack sequence, $lclass0excl for lookahead
	 *
	 * @return array [$exampleB, $exampleI, $exampleL]: one fragment per position of each sequence,
	 *               which is what the nested lookups are then shown against
	 */
	private function reportContext(array $backtrackGlyphs, array $inputGlyphs, array $lookaheadGlyphs, $class0excl = '', $bclass0excl = '', $lclass0excl = '')
	{
		$exampleB = [];
		$exampleI = [];
		$exampleL = [];

		$this->report .= '<div class="context">CONTEXT: ';

		for ($ff = count($backtrackGlyphs) - 1; $ff >= 0; $ff--) {
			if ($bclass0excl !== '' && !$backtrackGlyphs[$ff]) {
				$this->report .= '<div>Backtrack #' . $ff . ': <span class="unchanged">&nbsp;[NOT ' . $this->formatEntityStr($bclass0excl) . ']&nbsp;</span></div>';
				$exampleB[] = '[NOT ' . $this->formatEntityFirst($bclass0excl) . ']';
			} else {
				$this->report .= '<div>Backtrack #' . $ff . ': <span class="unicode">' . $this->formatUniStr($backtrackGlyphs[$ff]) . '</span></div>';
				$exampleB[] = $this->formatEntityFirst($backtrackGlyphs[$ff]);
			}
		}

		for ($ff = 0; $ff < count($inputGlyphs); $ff++) {
			if ($class0excl !== '' && !$inputGlyphs[$ff]) {
				$this->report .= '<div>Input #' . $ff . ': <span class="unchanged">&nbsp;[NOT ' . $this->formatEntityStr($class0excl) . ']&nbsp;</span></div>';
				$exampleI[] = '[NOT ' . $this->formatEntityFirst($class0excl) . ']';
			} else {
				$this->report .= '<div>Input #' . $ff . ': <span class="unchanged">&nbsp;' . $this->formatEntityStr($inputGlyphs[$ff]) . '&nbsp;</span></div>';
				$exampleI[] = $this->formatEntityFirst($inputGlyphs[$ff]);
			}
		}

		for ($ff = 0; $ff < count($lookaheadGlyphs); $ff++) {
			if ($lclass0excl !== '' && !$lookaheadGlyphs[$ff]) {
				$this->report .= '<div>Lookahead #' . $ff . ': <span class="unchanged">&nbsp;[NOT ' . $this->formatEntityStr($lclass0excl) . ']&nbsp;</span></div>';
				$exampleL[] = '[NOT ' . $this->formatEntityFirst($lclass0excl) . ']';
			} else {
				$this->report .= '<div>Lookahead #' . $ff . ': <span class="unicode">' . $this->formatUniStr($lookaheadGlyphs[$ff]) . '</span></div>';
				$exampleL[] = $this->formatEntityFirst($lookaheadGlyphs[$ff]);
			}
		}

		$this->report .= '</div>';

		return [$exampleB, $exampleI, $exampleL];
	}

	/**
	 * The example a nested lookup's own output is rendered between: everything the rule matches
	 * before the position it is handed, and everything it matches after it.
	 *
	 * The zero-width joiner separates a fragment from that output, so it trails every fragment
	 * before the position and leads every fragment after it.
	 *
	 * @param array $exampleB One fragment per backtrack position, as reportContext() returned them;
	 *                        likewise $exampleI for the input sequence and $exampleL for lookahead
	 * @param int   $seqIndex The input position the nested lookup is handed
	 *
	 * @return array [$exB, $exL]
	 */
	private function contextExample(array $exampleB, array $exampleI, array $exampleL, $seqIndex)
	{
		$exB = '';
		$exL = '';

		if (count($exampleB)) {
			$exB .= '<span class="backtrack">' . implode('&#x200d;', $exampleB) . '</span>';
		}

		if ($seqIndex > 0) {
			$exB .= '<span class="inputother">' . implode('&#x200d;', array_slice($exampleI, 0, $seqIndex)) . '&#x200d;</span>';
		}

		if (count($exampleI) > ($seqIndex + 1)) {
			$exL .= '<span class="inputother">&#x200d;' . implode('&#x200d;', array_slice($exampleI, $seqIndex + 1)) . '</span>';
		}

		if (count($exampleL)) {
			$exL .= '<span class="lookahead">' . implode('&#x200d;', $exampleL) . '</span>';
		}

		return [$exB, $exL];
	}

	/**
	 * Whether the position a nested lookup was handed can hold the glyph one of its rules reads.
	 *
	 * A position naming class 0 holds every glyph its Class Definition leaves unnamed, and the dump
	 * has no list of those - _getClasses() only walks the pairs the table names. So the test there is
	 * against the complement of $class0excl, the same set reportContext() renders the position as.
	 *
	 * @param string $coverage   The glyphs that position holds, empty where it names class 0
	 * @param string $class0excl Every glyph in some class of that sequence's Class Definition, or
	 *                           empty where the rule names glyphs rather than classes
	 */
	private function positionHolds($coverage, $class0excl, $glyph)
	{
		if ($coverage === '' && $class0excl !== '') {
			return strpos($class0excl, $glyph) === false;
		}

		return strpos($coverage, $glyph) !== false;
	}

	/**
	 * One context rule: the sequences it matches, and every lookup it hands a position within them.
	 *
	 * @param array  $PosLookupRecord Each a SequenceIndex and a LookupListIndex, already read: where
	 *                                they sit relative to a rule's own sequences differs by format
	 * @param array  $backtrackGlyphs In glyph sequence order, so reported last first
	 * @param string $class0excl      Every glyph that is in some class of the input sequence's Class
	 *                                Definition, for a class-based rule, so that class 0 reads as what
	 *                                it excludes. Empty for a glyph list, as are the other two.
	 * @param string $bclass0excl     Likewise for the backtrack sequence, $lclass0excl for lookahead
	 */
	private function reportGPOSrule(array $Lookup, array $PosLookupRecord, array $backtrackGlyphs, array $inputGlyphs, array $lookaheadGlyphs, $class0excl, $bclass0excl, $lclass0excl, $tag, $scripttag)
	{
		list($exampleB, $exampleI, $exampleL) = $this->reportContext($backtrackGlyphs, $inputGlyphs, $lookaheadGlyphs, $class0excl, $bclass0excl, $lclass0excl);

		foreach ($PosLookupRecord as $record) {
			$seqIndex = $record['SequenceIndex'];

			list($exB, $exL) = $this->contextExample($exampleB, $exampleI, $exampleL, $seqIndex);

			$this->report .= '<div class="sequenceIndex">Substitution Position: ' . $seqIndex . '</div>';

			$this->_getGPOSarray($Lookup, [$record['LookupListIndex'] => $tag], $scripttag, 2, $inputGlyphs[$seqIndex], $exB, $exL, $class0excl);
		}
	}

	/**
	 * One context rule: the sequences it matches, and every lookup it hands a position within them.
	 *
	 * @param array $SubstLookupRecord Each a SequenceIndex and a LookupListIndex
	 *
	 * @see reportGPOSrule() for the rest of the parameters, which are the same ones
	 */
	private function reportGSUBrule(array $Lookup, array $SubstLookupRecord, array $backtrackGlyphs, array $inputGlyphs, array $lookaheadGlyphs, $class0excl, $bclass0excl, $lclass0excl, $tag, $scripttag)
	{
		list($exampleB, $exampleI, $exampleL) = $this->reportContext($backtrackGlyphs, $inputGlyphs, $lookaheadGlyphs, $class0excl, $bclass0excl, $lclass0excl);

		foreach ($SubstLookupRecord as $record) {
			$seqIndex = $record['SequenceIndex'];

			list($exB, $exL) = $this->contextExample($exampleB, $exampleI, $exampleL, $seqIndex);

			$this->report .= '<div class="sequenceIndex">Substitution Position: ' . $seqIndex . '</div>';

			// The position's own glyphs, e.g. 00636|00645|00656, are what level 2 filters its rules on
			$this->reportGSUBlookups($Lookup, [$record['LookupListIndex'] => $tag], $scripttag, ['level' => 2, 'coverage' => $inputGlyphs[$seqIndex], 'exB' => $exB, 'exL' => $exL, 'class0excl' => $class0excl]);
		}
	}

	/**
	 * @return string[] One "hex|hex|hex" string of the alternatives each Coverage table holds
	 */
	private function coverageGlyphs(array $offsets)
	{
		$glyphs = [];
		foreach ($offsets as $b => $offset) {
			$this->reader->seek($offset);
			$glyphs[$b] = implode('|', $this->coverageHex());
		}

		return $glyphs;
	}

	/**
	 * What SequenceRule read as glyph ids, as the report names glyphs.
	 *
	 * @param int[] $glyphIDs In glyph sequence order
	 *
	 * @return string[] One hex character per position
	 */
	private function glyphNames(array $glyphIDs)
	{
		$names = [];
		foreach ($glyphIDs as $glyphID) {
			$names[] = $this->glyphHex($glyphID);
		}

		return $names;
	}

	/**
	 * What SequenceRule read as class numbers, as the report names the glyphs of a class.
	 *
	 * @param array $classes      class => "hex|hex|hex", as _getClasses returns it
	 * @param int[] $classIndices The class each position names, in glyph sequence order
	 *
	 * @return string[] One "hex|hex|hex" string per position, empty where the class is 0 or unnamed
	 */
	private function classNames(array $classes, array $classIndices)
	{
		$names = [];
		foreach ($classIndices as $class) {
			$names[] = $this->classGlyphs($classes, $class);
		}

		return $names;
	}

	/**
	 * @return string The character a glyph id stands for, as hex, or '' where the cmap does not reach it
	 */
	private function glyphHex($glyphID)
	{
		return isset($this->glyphToChar[$glyphID][0]) ? GlyphString::of($this->glyphToChar[$glyphID][0]) : '';
	}

	/**
	 * Hand over the report so far if it has built up more than WriteHTML will take.
	 *
	 * AdjustHTML refuses HTML longer than pcre.backtrack_limit, and one lookup can report tens of
	 * thousands of rules - a Latin font's GPOS kern lookup runs to ten megabytes on its own, so
	 * writing per lookup is not enough. Call this only where the report is between rows, so that
	 * every piece is whole elements; the enclosing div stays open across the calls, which the rest
	 * of the report does too - the summary opens a div in one call and closes it in another.
	 *
	 * @param string $html The report so far, emptied if it was handed over
	 */
	private function flushReport(&$html)
	{
		if (strlen($html) < $this->reportChunkBytes) {
			return;
		}

		$this->mpdf->WriteHTML($html);
		$html = '';
	}

	/**
	 * A link from the summary report to the detail report of one script and language system.
	 *
	 * These named font_dump_OTL.php, a spelling the file has never had, so following one 404s on any
	 * case-sensitive server; and they carried the script and language alone, losing the font the
	 * summary was of, so the detail report came back for whatever font the tool defaults to.
	 *
	 * @return string An href, with its ampersands escaped for HTML
	 */
	private function detailLink($script, $language)
	{
		$query = $this->detailReportQuery;
		$query['script'] = trim($script);
		$query['lang'] = trim($language);

		return 'font_dump_otl.php?' . htmlspecialchars(http_build_query($query), ENT_QUOTES);
	}

	/**
	 * The features one script and language system offers in GSUB or GPOS.
	 *
	 * A table with nothing for the script, or nothing for that language system within it, is
	 * reported and skipped rather than fatal. Fonts routinely substitute for a script without
	 * positioning it, or list a language system in one table only - 96 of the 245 script and
	 * language systems in the shipped fonts are in one table and not the other - and the half the
	 * reader asked for is in the other table. Only a script or language system that neither table
	 * carries is a mistake in the tag, and failIfNeitherTableOffers raises that once both have been
	 * asked. Before either, a missing script read straight through to a null a few lines later.
	 *
	 * @return array feature tag => list of lookup list indexes, empty if this table offers none
	 */
	private function langSys($features, $table)
	{
		if (!isset($features[$this->script])) {
			return $this->noteNotOffered(sprintf(
				'This font\'s %s table offers no script "%s". It has: %s',
				$table,
				trim($this->script),
				$features ? implode(', ', array_map('trim', array_keys($features))) : 'none'
			));
		}

		if (!isset($features[$this->script][$this->language])) {
			return $this->noteNotOffered(sprintf(
				'This font\'s %s script "%s" offers no language system "%s". It has: %s',
				$table,
				trim($this->script),
				trim($this->language),
				implode(', ', array_map('trim', array_keys($features[$this->script])))
			));
		}

		return $features[$this->script][$this->language];
	}

	/**
	 * Record, and show in the report, that one table has nothing for the script and language asked.
	 *
	 * @return array Always empty, so that the caller reports no lookups for this table
	 */
	private function noteNotOffered($message)
	{
		$this->notOffered[] = $message;
		$this->mpdf->WriteHTML('<div class="notoffered">' . $message . '</div>');

		return [];
	}

	/**
	 * Fail when neither GSUB nor GPOS carries the script and language system detail mode was asked
	 * for, naming what each table does carry. Called once both have been asked.
	 */
	private function failIfNeitherTableOffers()
	{
		if ($this->mode === 'detail' && count($this->notOffered) === 2) {
			throw new \Mpdf\MpdfException(implode("\n", $this->notOffered));
		}
	}

	/**
	 * A value record with all three of the fields mPDF uses present, zero where the format leaves
	 * one out.
	 *
	 * The shaper tells an absent field from a zero one, so ValueRecord::read() leaves it absent. The
	 * report reads all six of a pair unconditionally to decide what to print, and asking for absent
	 * keys raised tens of thousands of warnings on a single font.
	 */
	private function valueRecord($ValueFormat)
	{
		return array_merge(['XPlacement' => 0, 'YPlacement' => 0, 'XAdvance' => 0], ValueRecord::read($this->reader, $ValueFormat));
	}

	/**
	 * @param string $char A character as hex
	 *
	 * @return string It as "U+0041", or "M+E000" where it is in a Private Use Area and so stands for
	 *                a glyph the font reached only through a substitution
	 */
	function formatUni($char)
	{
		$x = preg_replace('/^[0]*/', '', $char);
		$x = str_pad($x, 4, '0', STR_PAD_LEFT);
		$d = hexdec($x);
		if (($d > 57343 && $d < 63744) || ($d > 122879 && $d < 126977)) {
			$id = 'M';
		} // E000 - F8FF, 1E000-1F000
		else {
			$id = 'U';
		}

		return $id . '+' . $x;
	}

	/**
	 * @param string $glyph As hex, with or without leading zeros
	 */
	private function isMark($glyph)
	{
		return isset($this->marks[ltrim($glyph, '0')]);
	}

	/**
	 * @param string $char         A character as hex
	 * @param bool   $allowjoining Whether a mark may be shown on its own. A mark otherwise gets a
	 *                             dotted circle to sit on, so that it renders where a base would be.
	 *
	 * @return string It as an HTML entity
	 */
	function formatEntity($char, $allowjoining = false)
	{
		$char = preg_replace('/^[0]/', '', $char);
		$x = '&#x' . $char . ';';
		if (!$allowjoining && $this->isMark($char)) {
			$x = '&#x25cc;' . $x;
		}

		return $x;
	}

	/**
	 * @param array $arr Characters as hex
	 *
	 * @return string Them as comma-separated "U+0041" codes
	 */
	function formatUniArr($arr)
	{
		$s = [];
		foreach ($arr as $c) {
			$x = preg_replace('/^[0]*/', '', $c);
			$d = hexdec($x);
			if (($d > 57343 && $d < 63744) || ($d > 122879 && $d < 126977)) {
				$id = 'M';
			} // E000 - F8FF, 1E000-1F000
			else {
				$id = 'U';
			}
			$s[] = $id . '+' . str_pad($x, 4, '0', STR_PAD_LEFT);
		}

		return implode(', ', $s);
	}

	/**
	 * @param array $arr Characters as hex
	 *
	 * @return string Them as space-separated HTML entities, marks on dotted circles
	 */
	function formatEntityArr($arr)
	{
		return implode(' ', array_map([$this, 'formatEntity'], $arr));
	}

	/**
	 * @param array $arr The characters of one class, as hex
	 *
	 * @return string Them as comma-separated "U+0041" codes
	 */
	function formatClassArr($arr)
	{
		$s = [];
		foreach ($arr as $c) {
			$x = preg_replace('/^[0]*/', '', $c);
			$d = hexdec($x);
			if (($d > 57343 && $d < 63744) || ($d > 122879 && $d < 126977)) {
				$id = 'M';
			} // E000 - F8FF, 1E000-1F000
			else {
				$id = 'U';
			}
			$s[] = $id . '+' . str_pad($x, 4, '0', STR_PAD_LEFT);
		}

		return implode(', ', $s);
	}

	/**
	 * @param string $str A pipe-joined run of characters as hex, as the class and coverage readers
	 *                    hand them back
	 *
	 * @return string Them as comma-separated "U+0041" codes
	 */
	function formatUniStr($str)
	{
		$s = [];
		$arr = explode('|', $str);
		foreach ($arr as $c) {
			$x = preg_replace('/^[0]*/', '', $c);
			$d = hexdec($x);
			if (($d > 57343 && $d < 63744) || ($d > 122879 && $d < 126977)) {
				$id = 'M';
			} // E000 - F8FF, 1E000-1F000
			else {
				$id = 'U';
			}
			$s[] = $id . '+' . str_pad($x, 4, '0', STR_PAD_LEFT);
		}

		return implode(', ', $s);
	}

	/**
	 * @param string $str A pipe-joined run of characters as hex
	 *
	 * @return string Them as space-separated HTML entities, marks on dotted circles
	 */
	function formatEntityStr($str)
	{
		return $this->formatEntityArr(explode('|', $str));
	}

	/**
	 * @param string $str A pipe-joined run of characters as hex
	 *
	 * @return string The first of them as an HTML entity. A position that can hold any of a set is
	 *                shown as one of them, so that the example reads as a word.
	 */
	function formatEntityFirst($str)
	{
		$arr = explode('|', $str);

		return $this->formatEntity($arr[0]);
	}

}
