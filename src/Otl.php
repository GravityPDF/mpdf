<?php

namespace Mpdf;

use Mpdf\Strict;

use Mpdf\Css\TextVars;
use Mpdf\Fonts\BlobReader;
use Mpdf\Fonts\GlyphString;
use Mpdf\Fonts\Table\Anchor;
use Mpdf\Fonts\Table\ClassDef;
use Mpdf\Fonts\Table\Coverage;
use Mpdf\Fonts\Table\LookupFlag;
use Mpdf\Fonts\Table\MarkArray;
use Mpdf\Fonts\Table\SequenceRule;
use Mpdf\Fonts\Table\ValueRecord;
use Mpdf\Fonts\FontCache;

use Mpdf\Shaper\Arabic;
use Mpdf\Shaper\Indic;
use Mpdf\Shaper\LineBreaking;
use Mpdf\Shaper\Myanmar;
use Mpdf\Shaper\OtlData;
use Mpdf\Shaper\OtlTags;
use Mpdf\Shaper\Sea;

use Mpdf\Utils\UtfString;

class Otl
{

	use Strict;

	const _OTL_OLD_SPEC_COMPAT_1 = true;

	private $mpdf;

	private $fontCache;

	var $GSUBdata;

	var $GPOSdata;

	var $GSUBfont;

	var $fontkey;

	/**
	 * The table the reader below is currently pointed at: 'GSUB' or 'GPOS'.
	 *
	 * Both tables number their offsets from their own start, so the same number means two different
	 * places depending on which is being applied. Everything that caches by offset has to say which
	 * table it meant - see $LuDataCache.
	 *
	 * @var string
	 */
	private $otlTable;

	/**
	 * One reader per table, keyed by tag, each over that table's cached bytes
	 *
	 * @var BlobReader[]
	 */
	private $readers = [];

	/**
	 * Whichever of $readers the current phase is reading
	 *
	 * @var BlobReader
	 */
	private $reader;

	/**
	 * $LuDataCache's first-level key for the current font and table, as "fontkey/GSUB".
	 *
	 * Composed once per phase rather than per lookup, which keeps the cache exactly as deep as it was
	 * before the two tables had to be told apart. The four memoised readers are called once per glyph
	 * per rule, so depth there is worth not adding.
	 *
	 * @var string
	 */
	private $otlCacheKey;

	var $glyphIDtoUni;

	var $MarkAttachmentType;

	var $MarkGlyphSets;

	var $GlyphClassMarks;

	var $GlyphClassLigatures;

	var $GlyphClassBases;

	var $GlyphClassComponents;

	/**
	 * Which glyphs a lookup skips, for the current font
	 *
	 * @var LookupFlag
	 */
	private $lookupFlag;

	/**
	 * GlyphClassMarks for the current font, as GlyphString::set() gives it
	 *
	 * @var true[]
	 */
	private $marks;

	/**
	 * $lookupFlag for every font laid out so far, by font key: the sets it builds from GDEF are kept
	 * with it, and a document sets one font for run after run
	 *
	 * @var LookupFlag[]
	 */
	private $lookupFlags = [];

	var $Ignores;

	var $LuCoverage;

	var $OTLdata;

	var $assocLigs;

	var $assocMarks;

	var $shaper;

	var $restrictToSyllable;

	var $lbdicts; // Line-breaking dictionaries

	/**
	 * Memoised Coverage and ClassDef tables, for the life of the document.
	 *
	 * Keyed ["fontkey/GSUB"][reader][offset]. Both parts of that are load-bearing. The reader: a font
	 * may point both a PairPos ClassDef and a chained-context InputClassDef at one table, and
	 * _getClassDefinitionTable returns class => list of unicodes where _getClasses returns
	 * class => map of unicode => 1, so sharing by offset alone would hand one of them a shape it
	 * cannot index. The table: offsets are relative to their own table, so GSUB offset 0x100 and GPOS
	 * offset 0x100 are two different places that would otherwise share a key.
	 */
	var $LuDataCache;

	var $Entry;

	var $Exit;

	var $GDEFdata;

	var $GPOSLookups;

	var $GSLuCoverage;

	var $GSUBLookups;

	var $schOTLdata;

	var $debugOTL = false;

	/**
	 * @param Mpdf      $mpdf      The document whose current font is being laid out
	 * @param FontCache $fontCache Where the parsed font and its lookup coverage are kept
	 */
	public function __construct(Mpdf $mpdf, FontCache $fontCache)
	{
		$this->mpdf = $mpdf;
		$this->fontCache = $fontCache;

		$this->lbdicts = [];
		$this->LuDataCache = [];
	}

	/**
	 * Lay a string out with the current font's own tables.
	 *
	 * The eleven phases below, in order: read GDEF, work out what script each run of the text is in,
	 * pick a shaper and a script and language for each, substitute with GSUB, shape, read GPOS,
	 * position with it, resolve cursive attachment, and put the runs back together. A string in more
	 * than one script is cut into subchunks and each is taken through on its own, because the script
	 * decides the shaper.
	 *
	 * @param string $str    The text, as UTF-8
	 * @param int    $useOTL Which script groups the document asked to be laid out this way, as a mask.
	 *                       The low byte says whether the font's own tables are read at all.
	 *
	 * @return string The text as the font substituted it, with the positioning left on $this->OTLdata
	 *                for the drawing code to read
	 */
	function applyOTL($str, $useOTL)
	{
		$this->OTLdata = [];
		if (trim($str) == '') {
			return $str;
		}
		if (!$useOTL) {
			return $str;
		}

		// Whether any script at all is to be laid out by the font's own tables. It cannot change
		// between the phases below or between subchunks, so it is asked here and not in each.
		$applyTables = (bool) ($useOTL & 0xFF);

		// 1. Load GDEF data
		$this->loadGdefData();

		// 2. Prepare string as HEX string and Analyse character properties
		list($OTLdata, $scriptblocks) = $this->analyseCharacters($str);
		$subchunk = count($scriptblocks) - 1;

		/* PROCESS EACH SUBCHUNK WITH DIFFERENT SCRIPTS */
		for ($sch = 0; $sch <= $subchunk; $sch++) {
			$this->OTLdata = $OTLdata[$sch];
			$scriptblock = $scriptblocks[$sch];

			// 3. Get Appropriate Scripts, and Shaper engine from analysing text and list of available scripts/langsys in font
			$this->shaper = $this->selectShaper($scriptblock);
			list($GSUBscriptTag, $GSUBlangsys, $GPOSscriptTag, $GPOSlangsys, $is_old_spec)
				= $this->selectScriptAndLanguage($scriptblock, $useOTL);

			$this->shaper = $this->shaperForScriptTag($this->shaper, $scriptblock, $GSUBscriptTag);

			// A run the font offers no script for still goes to its shaper, which reorders it with
			// nothing to substitute. Every script with a shaper is one useOTL opens with 0x80, and a
			// document that has not opened it gets nothing.
			$shapeWithoutTables = $this->shaper && ($useOTL & 0x80);

			if (!$GSUBscriptTag && !$GSUBlangsys && !$GPOSscriptTag && !$GPOSlangsys && !$shapeWithoutTables) {
				$this->removeJoinControls(false);
				$this->schOTLdata[$sch] = $this->OTLdata;
				$this->OTLdata = [];
				continue;
			}

			$GSUBFeatures = $this->features('GSUB', $GSUBscriptTag, $GSUBlangsys);
			$GPOSFeatures = $this->features('GPOS', $GPOSscriptTag, $GPOSlangsys);

			$this->assocLigs = []; // Ligatures[$posarr lpos] => nc
			$this->assocMarks = [];  // assocMarks[$posarr mpos] => array(compID, ligPos)

			if ($this->debugOTL) {
				echo OtlDump::shapingStep($this->OTLdata, 'BEGIN', '-', '-', '-', '-', -1, '-', 0);
			}

			$this->markWordBoundaries($scriptblock);

			$useGSUBtags = $applyTables
				? $this->applyGSUB($GSUBscriptTag, $GSUBlangsys, $GSUBFeatures, $scriptblock, $is_old_spec)
				: '';

			$this->insertWordBoundaries($scriptblock);

			// Shapers - INDIC & ARABIC & KHMER & SINHALA  & MYANMAR - Remove ZWJ and ZWNJ
			if ($this->shaper == 'I' || $this->shaper == 'S' || $this->shaper == 'A' || $this->shaper == 'K' || $this->shaper == 'M') {
				$this->removeJoinControls(true);
			}

			if ($applyTables) {
				$this->applyGPOS($GPOSscriptTag, $GPOSlangsys, $GPOSFeatures, $scriptblock, $is_old_spec, $useGSUBtags);
			}

			if ($this->debugOTL) {
				echo OtlDump::shapingStep($this->OTLdata, 'END', '-', '-', '-', '-', 0, '-', 0);
				exit;
			}

			$this->schOTLdata[$sch] = $this->OTLdata;
			$this->OTLdata = [];
		} // END foreach subchunk
		// 11. Re-assemble and return text string
		return $this->reassemble($subchunk);
	}

	/**
	 * Phase 1: the GDEF table, which says which glyphs are marks, ligatures, bases and components.
	 *
	 * Everything downstream asks these rather than the font: the Ignore flags on a lookup are stated
	 * in terms of them, and so is the group each character is put in. Held per font on the object,
	 * because a document sets the same font for line after line.
	 */
	private function loadGdefData()
	{
		$this->fontkey = $this->mpdf->CurrentFont['fontkey'];
		$this->glyphIDtoUni = $this->mpdf->CurrentFont['glyphIDtoUni'];

		if (!isset($this->GDEFdata[$this->fontkey])) {
			$font = $this->loadRequiredLayoutData($this->fontkey . '.GDEFdata.json');

			$this->GDEFdata[$this->fontkey] = [
				'MarkAttachmentType' => $font['MarkAttachmentType'],
				'MarkGlyphSets' => $font['MarkGlyphSets'],
				'GlyphClassMarks' => $font['GlyphClassMarks'],
				'GlyphClassLigatures' => $font['GlyphClassLigatures'],
				'GlyphClassComponents' => $font['GlyphClassComponents'],
				'GlyphClassBases' => $font['GlyphClassBases'],
			];
		}

		$gdef = $this->GDEFdata[$this->fontkey];
		$this->MarkAttachmentType = $gdef['MarkAttachmentType'];
		$this->MarkGlyphSets = $gdef['MarkGlyphSets'];
		$this->GlyphClassMarks = $gdef['GlyphClassMarks'];
		$this->GlyphClassLigatures = $gdef['GlyphClassLigatures'];
		$this->GlyphClassComponents = $gdef['GlyphClassComponents'];
		$this->GlyphClassBases = $gdef['GlyphClassBases'];

		if (!isset($this->lookupFlags[$this->fontkey])) {
			$this->lookupFlags[$this->fontkey] = new LookupFlag($this->fontkey, $gdef);
		}

		$this->lookupFlag = $this->lookupFlags[$this->fontkey];
		$this->marks = $this->lookupFlag->marks();
	}

	/**
	 * @param string $hex A glyph, as GlyphString::of() writes it
	 *
	 * @return bool Whether GDEF classes it as a mark
	 */
	private function isMark($hex)
	{
		return isset($this->marks[$hex]);
	}

	/**
	 * Phase 2: what each character of the run is, and where the run changes script.
	 *
	 * A run can hold more than one script and each is shaped by different rules, so it is cut into
	 * subchunks at every change and each is shaped on its own. ScriptRuns::split() makes the cut.
	 *
	 * @return array [$OTLdata, $scriptblocks]: the characters of each subchunk, and which script
	 *               each subchunk is
	 */
	private function analyseCharacters($str)
	{
		$earr = $this->mpdf->UTF8StringToArray($str, false);

		$runs = ScriptRuns::split($earr);

		$scriptblocks = [];
		$OTLdata = [];

		foreach ($runs as $subchunk => $run) {
			$scriptblocks[$subchunk] = $run['script'];

			foreach ($run['characters'] as $charctr => $character) {
				$char = $character['uni'];
				$ucd_record = $character['record'];

				$OTLdata[$subchunk][$charctr]['general_category'] = $ucd_record[0];
				$OTLdata[$subchunk][$charctr]['bidi_type'] = $ucd_record[2];

				//$OTLdata[$subchunk][$charctr]['combining_class'] = $ucd_record[1];
				//$OTLdata[$subchunk][$charctr]['bidi_type'] = $ucd_record[2];
				//$OTLdata[$subchunk][$charctr]['mirrored'] = $ucd_record[3];
				//$OTLdata[$subchunk][$charctr]['east_asian_width'] = $ucd_record[4];
				//$OTLdata[$subchunk][$charctr]['normalization_check'] = $ucd_record[5];
				//$OTLdata[$subchunk][$charctr]['script'] = $ucd_record[6];

				$charasstr = GlyphString::of($char);

				if ($this->isMark($charasstr)) {
					$OTLdata[$subchunk][$charctr]['group'] = 'M';
				} elseif ($char == 32 || $char == 12288) { // 12288 = 0x3000 = CJK space
					$OTLdata[$subchunk][$charctr]['group'] = 'S';
				} else {
					$OTLdata[$subchunk][$charctr]['group'] = 'C';
				}

				$OTLdata[$subchunk][$charctr]['uni'] = $char;
				$OTLdata[$subchunk][$charctr]['hex'] = $charasstr;
			}
		}

		return [$OTLdata, $scriptblocks];
	}

	/**
	 * Take out the zero-width joiner and non-joiner.
	 *
	 * Both are instructions to the shaper - join these two where you would not, keep these two apart
	 * where you would join them - rather than characters to draw, so once the shaper has read them
	 * they come out. A font that offers nothing for this script never reads them, and they come out
	 * just the same.
	 *
	 * @param bool $shaped Whether a shaper has run. If it has, the ligature and mark bookkeeping is
	 *                     tracking positions in this run and has to be shifted along with it.
	 */
	private function removeJoinControls($shaped)
	{
		for ($i = 0; $i < count($this->OTLdata); $i++) {
			if ($this->OTLdata[$i]['uni'] == 8204 || $this->OTLdata[$i]['uni'] == 8205) {
				array_splice($this->OTLdata, $i, 1);
				if ($shaped) {
					$this->_updateLigatureMarks($i, -1);
				}
			}
		}
	}

	/**
	 * Mark where a word could end, for the scripts that do not write spaces.
	 *
	 * Khmer, Thai and Lao run their words together, and Tibetan separates syllables rather than
	 * words, so there is nothing in the text for the line breaker to break at. A dictionary of the
	 * language is walked over the run instead, and every place a word could end is marked. The marks
	 * are put in before shaping so that the shaper sees the text as written, and turned into real
	 * zero-width spaces afterwards by insertWordBoundaries().
	 */
	private function markWordBoundaries($scriptblock)
	{
		// Both set $this->OTLdata[$i]['wordend'] = true at every possible end of a word
		if ($this->usesWordBoundaryDictionary()) {
			$dict = $this->lineBreakDictionary();
			if ($dict !== null) {
				LineBreaking::southEastAsian($this->OTLdata, $dict, $this->marks);
			}
		} elseif ($this->usesTibetanWordBoundaries($scriptblock)) {
			LineBreaking::tibetan($this->OTLdata);
		}
	}

	/**
	 * Whether this run is one of the scripts a dictionary is walked over - Khmer, Thai and Lao, which
	 * write their words without spaces between them.
	 */
	private function usesWordBoundaryDictionary()
	{
		return $this->mpdf->useDictionaryLBR
			&& ($this->shaper == 'K' || $this->shaper == 'T' || $this->shaper == 'L');
	}

	/**
	 * Whether this run is Tibetan, which separates syllables rather than words and so needs its own
	 * rules rather than a dictionary.
	 */
	private function usesTibetanWordBoundaries($scriptblock)
	{
		return $this->mpdf->useTibetanLBR && $scriptblock == Ucdn::SCRIPT_TIBETAN;
	}

	/**
	 * Turn the word boundaries marked before shaping into zero-width spaces.
	 *
	 * Asks the same two questions as markWordBoundaries(), which is what set them.
	 */
	private function insertWordBoundaries($scriptblock)
	{
		if ($this->usesWordBoundaryDictionary() || $this->usesTibetanWordBoundaries($scriptblock)) {
			// Set up properties to insert a U+200B character
			$newinfo = [];
			//$newinfo[0] = array('general_category' => 1, 'bidi_type' => 14, 'group' => 'S', 'uni' => 0x200B, 'hex' => '0200B');
			$newinfo[0] = [
			'general_category' => Ucdn::UNICODE_GENERAL_CATEGORY_FORMAT,
			'bidi_type' => Ucdn::BIDI_CLASS_BN,
			'group' => 'S', 'uni' => 0x200B, 'hex' => '0200B'];
			// Then insert U+200B at (after) all word end boundaries
			for ($i = count($this->OTLdata) - 1; $i > 0; $i--) {
				// Make sure after GSUB that wordend has not been moved - check next char is not in the same syllable
				if (isset($this->OTLdata[$i]['wordend']) && $this->OTLdata[$i]['wordend'] &&
				isset($this->OTLdata[$i + 1]['uni']) && (!isset($this->OTLdata[$i + 1]['syllable']) || !isset($this->OTLdata[$i + 1]['syllable']) || $this->OTLdata[$i + 1]['syllable'] != $this->OTLdata[$i]['syllable'])) {
					array_splice($this->OTLdata, $i + 1, 0, $newinfo);
					$this->_updateLigatureMarks($i, 1);
				} elseif ($this->OTLdata[$i]['uni'] == 0x2e) { // Word end if Full-stop.
					array_splice($this->OTLdata, $i + 1, 0, $newinfo);
					$this->_updateLigatureMarks($i, 1);
				}
			}
		}
	}

	/**
	 * Phases 4 and 5: substitution.
	 *
	 * Loads what this font's GSUB table says for this script and language, then hands the run to the
	 * shaper the script needs. Every shaper ends by applying the presentation features - together in
	 * Lookup List order, or a feature at a time where a later one is meant to read the glyphs an
	 * earlier one made - and what each does first is put the characters into the order those lookups
	 * expect to find them in.
	 *
	 * @return string The feature tags the generic path settled on, which the positioning below reads
	 *                to tell an OpenType small-caps run from one drawn with synthesised capitals.
	 *                Empty from the shapers that do not reach that decision, and from a font with
	 *                nothing to substitute.
	 */
	private function applyGSUB($GSUBscriptTag, $GSUBlangsys, $GSUBFeatures, $scriptblock, $is_old_spec)
	{
		if (!$GSUBFeatures && !$this->shaper) {
			return '';
		}

		$this->loadGsubDerivedData($GSUBscriptTag, $GSUBlangsys);

		// A run with nothing to substitute still goes to its shaper, and its font need not have a
		// GSUB table at all
		if ($GSUBFeatures) {
			$this->loadGsubLookups();
		}

		// 5. GSUB - Shaper
		if ($this->shaper == 'A') {
			$this->shapeArabic($GSUBscriptTag, $GSUBlangsys, $GSUBFeatures, $scriptblock);
			return '';
		}

		if ($this->shaper == 'I' || $this->shaper == 'K' || $this->shaper == 'S') {
			$this->shapeIndic($GSUBscriptTag, $GSUBlangsys, $GSUBFeatures, $scriptblock, $is_old_spec);
			return '';
		}

		if ($this->shaper == 'M') {
			$this->shapeMyanmar($GSUBscriptTag, $GSUBlangsys, $GSUBFeatures);
			return '';
		}

		if ($this->shaper == 'E') {
			$this->shapeSouthEastAsian($GSUBscriptTag, $GSUBlangsys, $GSUBFeatures, $scriptblock);
			return '';
		}

		// Everything else, Thai and Lao and Myanmar v1 and Tibetan among them, is laid out by the
		// font's own features in the order it lists them
		return $this->shapeGeneric($GSUBscriptTag, $GSUBlangsys, $GSUBFeatures, $scriptblock);
	}

	/**
	 * What this font's GSUB table says, for this script and language system.
	 *
	 * Cached for the life of the document because a document sets the same font for line after line:
	 * the derived tables the shapers work from, which the parser builds per script and language, and
	 * which are empty where it built none.
	 */
	private function loadGsubDerivedData($GSUBscriptTag, $GSUBlangsys)
	{
		$this->GSUBfont = $this->fontkey . '.GSUB.' . $GSUBscriptTag . '.' . $GSUBlangsys;

		if (!isset($this->GSUBdata[$this->GSUBfont])) {
			$font = $this->fontCache->jsonLoadIfPresent($this->GSUBfont . '.json');
			if (null !== $font) {
				$this->GSUBdata[$this->GSUBfont]['rtlSUB'] = $font['rtlSUB'];
				$this->GSUBdata[$this->GSUBfont]['finals'] = $font['finals'];
				if ($this->shaper == 'I') {
					$this->GSUBdata[$this->GSUBfont]['rphf'] = $font['rphf'];
					$this->GSUBdata[$this->GSUBfont]['half'] = $font['half'];
					$this->GSUBdata[$this->GSUBfont]['pref'] = $font['pref'];
					$this->GSUBdata[$this->GSUBfont]['blwf'] = $font['blwf'];
					$this->GSUBdata[$this->GSUBfont]['pstf'] = $font['pstf'];
				}
			} else {
				$this->GSUBdata[$this->GSUBfont] = ['rtlSUB' => [], 'rphf' => [], 'rphf' => [],
					'pref' => [], 'blwf' => [], 'pstf' => [], 'finals' => ''
				];
			}
		}
	}

	/**
	 * The coverage of every lookup, which is how a lookup is passed over without being read, and the
	 * lookup list itself.
	 */
	private function loadGsubLookups()
	{
		$this->readTable('GSUB');

		if (!isset($this->GSUBdata[$this->fontkey])) {
			$this->GSUBdata[$this->fontkey]['GSLuCoverage'] = $this->loadRequiredLayoutData($this->fontkey . '.GSUBdata.json');
		}

		$this->GSLuCoverage = $this->GSUBdata[$this->fontkey]['GSLuCoverage'];

		$this->GSUBLookups = $this->mpdf->CurrentFont['GSUBLookups'];
	}

	/**
	 * Shaper A: Arabic, Syriac, N'Ko and Mandaic.
	 *
	 * These scripts join: a letter is written differently depending on whether a letter that joins to
	 * it stands either side of it. The font states the four forms as the features isol, fina, medi
	 * and init - Syriac adds fin2, fin3 and med2 - but which of them a letter takes follows from the
	 * joining classes rather than from a lookup, because the rule is the script's and not the font's.
	 * Kashida points, where a word may be stretched to justify a line, are set once the joining is
	 * known and before anything else is substituted.
	 */
	private function shapeArabic($GSUBscriptTag, $GSUBlangsys, $GSUBFeatures, $scriptblock)
	{
		// The form every character calls for, the Syriac Alaph's three included, is resolved off the
		// characters as written and carried on the run from here, because a glyph the substitutions below
		// leave in a character's place is no character to read it from
		Arabic::resolveJoining($this->OTLdata, $this->GlyphClassMarks, $GSUBscriptTag);

		// a. Apply initial GSUB Lookups (in order specified in lookup list but only selecting from certain tags)
		$tags = 'locl ccmp';
		$omittags = '';
		$usetags = $tags;
		if (!empty($this->mpdf->OTLtags)) {
			$usetags = $this->_applyTagSettings($tags, $GSUBFeatures, $omittags, true);
		}
		$this->_applyGSUBrules($usetags, $GSUBscriptTag, $GSUBlangsys);

		// b. Apply context-specific forms GSUB Lookups (initial, isolated, medial, final)
		// Arab and Syriac are the only scripts requiring the special joining - which takes the place of
		// isol fina medi init rules in GSUB (+ fin2 fin3 med2 in Syriac syrc)
		$tags = 'isol fina fin2 fin3 medi med2 init';
		$omittags = '';
		$usetags = $tags;
		if (!empty($this->mpdf->OTLtags)) {
			$usetags = $this->_applyTagSettings($tags, $GSUBFeatures, $omittags, true);
		}

		$multiple = Arabic::shape($this->OTLdata, $this->GSUBdata[$this->GSUBfont]['rtlSUB'], $usetags);

		// A form the font states as more than one glyph goes in through the same Multiple Substitution
		// path GSUB uses, which carries the ligature and mark bookkeeping over the run getting longer.
		// From the back, so each position is still the one the shaper named when it is reached.
		foreach (array_reverse($multiple, true) as $pos => $glyphs) {
			$this->GSUBsubstitute($pos, $glyphs, 2);
		}

		// c. Set Kashida points (after joining occurred - medi, fina, init) but before other substitutions
		//if ($scriptblock == Ucdn::SCRIPT_ARABIC ) {
		for ($i = 0; $i < count($this->OTLdata); $i++) {
			// Put the kashida marker on the character BEFORE which is inserted the kashida
			// Kashida marker is inverse of priority i.e. Priority 1 => 7, Priority 7 => 1.
			// Priority 1   User-inserted Kashida 0640 = Tatweel
			// The user entered a Kashida in a position
			// Position: Before the user-inserted kashida
			if ($this->OTLdata[$i]['uni'] == 0x0640) {
				$this->OTLdata[$i]['GPOSinfo']['kashida'] = 8; // Put before the next character
			} // Priority 2   Seen (0633)  FEB3, FEB4; Sad (0635)  FEBB, FEBC
			// Initial or medial form
			// Connecting to the next character
			// Position: After the character
			elseif ($this->OTLdata[$i]['uni'] == 0xFEB3 || $this->OTLdata[$i]['uni'] == 0xFEB4 || $this->OTLdata[$i]['uni'] == 0xFEBB || $this->OTLdata[$i]['uni'] == 0xFEBC) {
				$checkpos = $i + 1;
				while (isset($this->OTLdata[$checkpos]) && $this->isMark($this->OTLdata[$checkpos]['hex'])) {
					$checkpos++;
				}
				if (isset($this->OTLdata[$checkpos])) {
					$this->OTLdata[$checkpos]['GPOSinfo']['kashida'] = 7; // Put after marks on next character
				}
			} // Priority 3   Taa Marbutah (0629) FE94; Haa (062D) FEA2; Dal (062F) FEAA
			// Final form
			// Connecting to previous character
			// Position: Before the character
			elseif ($this->OTLdata[$i]['uni'] == 0xFE94 || $this->OTLdata[$i]['uni'] == 0xFEA2 || $this->OTLdata[$i]['uni'] == 0xFEAA) {
				$this->OTLdata[$i]['GPOSinfo']['kashida'] = 6;
			} // Priority 4   Alef (0627) FE8E; Tah (0637) FEC2; Lam (0644) FEDE; Kaf (0643)  FEDA; Gaf (06AF) FB93
			// Final form
			// Connecting to previous character
			// Position: Before the character
			elseif ($this->OTLdata[$i]['uni'] == 0xFE8E || $this->OTLdata[$i]['uni'] == 0xFEC2 || $this->OTLdata[$i]['uni'] == 0xFEDE || $this->OTLdata[$i]['uni'] == 0xFEDA || $this->OTLdata[$i]['uni'] == 0xFB93) {
				$this->OTLdata[$i]['GPOSinfo']['kashida'] = 5;
			} // Priority 5   RA (0631) FEAE; Ya (064A)  FEF2 FEF4; Alef Maqsurah (0649) FEF0 FBE9
			// Final or Medial form
			// Connected to preceding medial BAA (0628) = FE92
			// Position: Before preceding medial Baa
			// Although not mentioned in spec, added Farsi Yeh (06CC) FBFD FBFF; equivalent to 064A or 0649
			elseif ($this->OTLdata[$i]['uni'] == 0xFEAE || $this->OTLdata[$i]['uni'] == 0xFEF2 || $this->OTLdata[$i]['uni'] == 0xFEF0 || $this->OTLdata[$i]['uni'] == 0xFEF4 || $this->OTLdata[$i]['uni'] == 0xFBE9 || $this->OTLdata[$i]['uni'] == 0xFBFD || $this->OTLdata[$i]['uni'] == 0xFBFF
			) {
				$checkpos = $i - 1;
				while (isset($this->OTLdata[$checkpos]) && $this->isMark($this->OTLdata[$checkpos]['hex'])) {
					$checkpos--;
				}
				if (isset($this->OTLdata[$checkpos]) && $this->OTLdata[$checkpos]['uni'] == 0xFE92) {
					$this->OTLdata[$checkpos]['GPOSinfo']['kashida'] = 4; // Before preceding BAA
				}
			} // Priority 6   WAW (0648) FEEE; Ain (0639) FECA; Qaf (0642) FED6; Fa (0641) FED2
			// Final form
			// Connecting to previous character
			// Position: Before the character
			elseif ($this->OTLdata[$i]['uni'] == 0xFEEE || $this->OTLdata[$i]['uni'] == 0xFECA || $this->OTLdata[$i]['uni'] == 0xFED6 || $this->OTLdata[$i]['uni'] == 0xFED2) {
				$this->OTLdata[$i]['GPOSinfo']['kashida'] = 3;
			}

			// Priority 7   Other connecting characters
			// Final form
			// Connecting to previous character
			// Position: Before the character
			/* This isn't in the spec, but using MS WORD as a basis, give a lower priority to the 3 characters already checked
			  in (5) above. Test case:
			  &#x62e;&#x652;&#x631;&#x64e;&#x649;&#x670;
			  &#x641;&#x64e;&#x62a;&#x64f;&#x630;&#x64e;&#x643;&#x651;&#x650;&#x631;
			 */

			if (!isset($this->OTLdata[$i]['GPOSinfo']['kashida'])) {
				if (GlyphString::inList($this->GSUBdata[$this->GSUBfont]['finals'], $this->OTLdata[$i]['hex'])) { // ANY OTHER FINAL FORM
					$this->OTLdata[$i]['GPOSinfo']['kashida'] = 2;
				} elseif (strpos('0FEAE 0FEF0 0FEF2', $this->OTLdata[$i]['hex']) !== false) { // not already included in 5 above
					$this->OTLdata[$i]['GPOSinfo']['kashida'] = 1;
				}
			}
		}

		// d. Apply Presentation Forms GSUB Lookups (+ any discretionary) - Apply one at a time in Feature order
		$tags = 'rlig calt liga clig mset';

		$omittags = 'locl ccmp nukt akhn rphf rkrf pref blwf abvf half pstf cfar vatu cjct init medi fina isol med2 fin2 fin3 ljmo vjmo tjmo';
		$usetags = $tags;
		if (!empty($this->mpdf->OTLtags)) {
			$usetags = $this->_applyTagSettings($tags, $GSUBFeatures, $omittags, false);
		}

		// One call per stage of HarfBuzz's Arabic plan, which puts rlig in the first, rclt and calt in
		// the next, and the ligature features with mset in the last
		foreach ($this->featureStages($usetags, ['rlig', 'rclt calt', 'liga clig mset']) as $tags) {
			$this->applyGSUBfeaturesInTurn($tags, $GSUBscriptTag, $GSUBlangsys, 0, []);
		}

		// e. NOT IN SPEC
		// If space precedes a mark -> substitute a &nbsp; before the Mark, to prevent line breaking Test:
		for ($ptr = 1; $ptr < count($this->OTLdata); $ptr++) {
			if ($this->OTLdata[$ptr]['general_category'] == Ucdn::UNICODE_GENERAL_CATEGORY_NON_SPACING_MARK && $this->OTLdata[$ptr - 1]['uni'] == 32) {
				$this->OTLdata[$ptr - 1]['uni'] = 0xa0;
				$this->OTLdata[$ptr - 1]['hex'] = '000A0';
			}
		}
	}

	/**
	 * Shaper I: the Indic scripts, and Sinhala and Khmer, which are written the same way.
	 *
	 * A syllable is typed in the order it is spoken and drawn in another order, so the run is grouped
	 * into syllables, each is put into the order the font's lookups expect to find it in, and the
	 * features are applied one syllable at a time. That is what restrictToSyllable is for, and why
	 * every rule below is applied inside the loop rather than over the whole run at once.
	 */
	private function shapeIndic($GSUBscriptTag, $GSUBlangsys, $GSUBFeatures, $scriptblock, $is_old_spec)
	{
		$this->restrictToSyllable = true;
		// a. First decompose/compose split mattras
		// Unicode normalisation is not applied first, so a cluster written with its nukta and
		// halant in the other order reaches the shaper as written. HarfBuzz normalises here.
		for ($ptr = 0; $ptr < count($this->OTLdata); $ptr++) {
			$char = $this->OTLdata[$ptr]['uni'];
			$sub = Indic::decompose_indic($char);
			if ($sub) {
				$newinfo = [];
				for ($i = 0; $i < count($sub); $i++) {
					$newinfo[$i] = [];
					$ucd_record = Ucdn::get_ucd_record($sub[$i]);
					$newinfo[$i]['general_category'] = $ucd_record[0];
					$newinfo[$i]['bidi_type'] = $ucd_record[2];
					$charasstr = GlyphString::of($sub[$i]);
					if ($this->isMark($charasstr)) {
						$newinfo[$i]['group'] = 'M';
					} else {
						$newinfo[$i]['group'] = 'C';
					}
					$newinfo[$i]['uni'] = $sub[$i];
					$newinfo[$i]['hex'] = $charasstr;
				}
				array_splice($this->OTLdata, $ptr, 1, $newinfo);
				$ptr += count($sub) - 1;
			}
			/* Only Composition-exclusion exceptions that we want to recompose. */
			if ($this->shaper == 'I') {
				if ($char == 0x09AF && isset($this->OTLdata[$ptr + 1]) && $this->OTLdata[$ptr + 1]['uni'] == 0x09BC) {
					$sub = 0x09DF;
					$newinfo = [];
					$newinfo[0] = [];
					$ucd_record = Ucdn::get_ucd_record($sub);
					$newinfo[0]['general_category'] = $ucd_record[0];
					$newinfo[0]['bidi_type'] = $ucd_record[2];
					$newinfo[0]['group'] = 'C';
					$newinfo[0]['uni'] = $sub;
					$newinfo[0]['hex'] = GlyphString::of($sub);
					array_splice($this->OTLdata, $ptr, 2, $newinfo);
				}
			}
		}
		// b. Analyse characters - group as syllables/clusters (Indic); invalid diacritics; add dotted circle
		$indic_category_string = '';
		foreach ($this->OTLdata as $eid => $c) {
			Indic::set_indic_properties($this->OTLdata[$eid], $scriptblock); // sets ['indic_category'] and ['indic_position']
			//$c['general_category']
			//$c['combining_class']
			//$c['uni'] =  $char;

			$indic_category_string .= Indic::$indic_category_char[$this->OTLdata[$eid]['indic_category']];
		}

		$broken_syllables = false;
		if ($this->shaper == 'I') {
			Indic::set_syllables($this->OTLdata, $indic_category_string, $broken_syllables);
		} elseif ($this->shaper == 'S') {
			Indic::set_syllables_sinhala($this->OTLdata, $indic_category_string, $broken_syllables);
		} elseif ($this->shaper == 'K') {
			Indic::set_syllables_khmer($this->OTLdata, $indic_category_string, $broken_syllables);
		}
		$indic_category_string = '';

		// c. Initial Re-ordering (Indic / Khmer / Sinhala)
		// Find base consonant
		// Decompose/compose and reorder Matras
		// Reorder marks to canonical order

		$indic_config = Indic::$indic_configs[$scriptblock];
		$dottedcircle = false;
		if ($broken_syllables) {
			if ($this->mpdf->_charDefined($this->mpdf->fonts[$this->fontkey]['cw'], 0x25CC)) {
				$dottedcircle = [];
				$ucd_record = Ucdn::get_ucd_record(0x25CC);
				$dottedcircle[0]['general_category'] = $ucd_record[0];
				$dottedcircle[0]['bidi_type'] = $ucd_record[2];
				$dottedcircle[0]['group'] = 'C';
				$dottedcircle[0]['uni'] = 0x25CC;
				$dottedcircle[0]['indic_category'] = Indic::OT_DOTTEDCIRCLE;
				$dottedcircle[0]['indic_position'] = Indic::POS_BASE_C;

				$dottedcircle[0]['hex'] = '025CC';  // TEMPORARY
			}
		}
		Indic::initial_reordering($this->OTLdata, $this->GSUBdata[$this->GSUBfont], $broken_syllables, $indic_config, $scriptblock, $is_old_spec, $dottedcircle);

		// d. Apply initial and basic shaping forms GSUB Lookups (one at a time)
		// Khmer writes its dependent forms round the base rather than reordering them, so it asks for
		// a different set. Indic and Sinhala are the only other shapers that reach here.
		// One call per stage of HarfBuzz's plan for them, which is the whole set for Khmer and a
		// feature at a time for Indic once locl and ccmp have gone through together
		$stages = $this->shaper == 'K'
			? ['locl ccmp pref blwf abvf pstf cfar']
			: ['locl ccmp', 'nukt', 'akhn', 'rphf', 'rkrf', 'pref', 'blwf', 'half', 'pstf', 'vatu', 'cjct'];
		foreach ($stages as $tags) {
			$this->_applyGSUBrulesIndic($tags, $GSUBscriptTag, $GSUBlangsys, $is_old_spec);
		}

		// e. Final Re-ordering (Indic / Khmer / Sinhala)
		// Reorder matras
		// Reorder reph
		// Reorder pre-base reordering consonants:

		Indic::final_reordering($this->OTLdata, $this->GSUBdata[$this->GSUBfont], $indic_config, $scriptblock, $is_old_spec);

		// f. Apply 'init' feature to first syllable in word (indicated by ['mask']) Indic::FLAG(Indic::INIT);
		if ($this->shaper == 'I' || $this->shaper == 'S') {
			$tags = 'init';
			$this->_applyGSUBrulesIndic($tags, $GSUBscriptTag, $GSUBlangsys, $is_old_spec);
		}

		// g. Apply Presentation Forms GSUB Lookups (+ any discretionary)
		$tags = 'pres abvs blws psts haln rlig calt liga clig mset';

		$omittags = 'locl ccmp nukt akhn rphf rkrf pref blwf abvf half pstf cfar vatu cjct init medi fina isol med2 fin2 fin3 ljmo vjmo tjmo';
		$usetags = $tags;
		if (!empty($this->mpdf->OTLtags)) {
			$usetags = $this->_applyTagSettings($tags, $GSUBFeatures, $omittags, false);
		}
		if ($this->shaper == 'K') {  // Features are applied one at a time, working through each codepoint
			$this->_applyGSUBrulesSingly($usetags, $GSUBscriptTag, $GSUBlangsys);
		} else {
			$this->_applyGSUBrules($usetags, $GSUBscriptTag, $GSUBlangsys);
		}
		$this->restrictToSyllable = false;
	}

	/**
	 * Shaper M: Myanmar, and only where the font offers the mym2 tag.
	 *
	 * Syllable-based like the Indic shaper, reordered by rules of its own. A font that offers only
	 * the older mymr tag goes through the generic path instead, which applyOTL settles before this
	 * is reached.
	 */
	private function shapeMyanmar($GSUBscriptTag, $GSUBlangsys, $GSUBFeatures)
	{
		$this->restrictToSyllable = true;
		// a. Analyse characters - group as syllables/clusters (Myanmar); invalid diacritics; add dotted circle
		$myanmar_category_string = '';
		foreach ($this->OTLdata as $eid => $c) {
			Myanmar::set_myanmar_properties($this->OTLdata[$eid]); // sets ['myanmar_category'] and ['myanmar_position']
			$myanmar_category_string .= Myanmar::$myanmar_category_char[$this->OTLdata[$eid]['myanmar_category']];
		}
		$broken_syllables = false;
		Myanmar::set_syllables($this->OTLdata, $myanmar_category_string, $broken_syllables);
		$myanmar_category_string = '';

		// b. Re-ordering (Myanmar mym2)
		$dottedcircle = false;
		if ($broken_syllables) {
			if ($this->mpdf->_charDefined($this->mpdf->fonts[$this->fontkey]['cw'], 0x25CC)) {
				$dottedcircle = [];
				$ucd_record = Ucdn::get_ucd_record(0x25CC);
				$dottedcircle[0]['general_category'] = $ucd_record[0];
				$dottedcircle[0]['bidi_type'] = $ucd_record[2];
				$dottedcircle[0]['group'] = 'C';
				$dottedcircle[0]['uni'] = 0x25CC;
				$dottedcircle[0]['myanmar_category'] = Myanmar::OT_DOTTEDCIRCLE;
				$dottedcircle[0]['myanmar_position'] = Myanmar::POS_BASE_C;
				$dottedcircle[0]['hex'] = '025CC';
			}
		}
		Myanmar::reordering($this->OTLdata, $this->GSUBdata[$this->GSUBfont], $broken_syllables, $dottedcircle);

		// c. Apply initial and basic shaping forms GSUB Lookups (one at a time)

		// One call per stage of HarfBuzz's Myanmar plan
		$stages = ['locl ccmp', 'rphf', 'pref', 'blwf', 'pstf'];
		foreach ($stages as $tags) {
			$this->_applyGSUBrulesMyanmar($tags, $GSUBscriptTag, $GSUBlangsys);
		}

		// d. Apply Presentation Forms GSUB Lookups (+ any discretionary)
		$tags = 'pres abvs blws psts haln rlig calt liga clig mset';
		$omittags = 'locl ccmp nukt akhn rphf rkrf pref blwf abvf half pstf cfar vatu cjct init medi fina isol med2 fin2 fin3 ljmo vjmo tjmo';
		$usetags = $tags;
		if (!empty($this->mpdf->OTLtags)) {
			$usetags = $this->_applyTagSettings($tags, $GSUBFeatures, $omittags, false);
		}
		$this->_applyGSUBrules($usetags, $GSUBscriptTag, $GSUBlangsys);
		$this->restrictToSyllable = false;
	}

	/**
	 * Shaper E: the South East Asian scripts - New Tai Lue, Cham and Tai Tham.
	 *
	 * Syllable-based, with no reordering. What these need is the invalid clusters found and a dotted
	 * circle put in front of a mark that has nothing to attach to, so that broken text reads as
	 * broken rather than as something else.
	 */
	private function shapeSouthEastAsian($GSUBscriptTag, $GSUBlangsys, $GSUBFeatures, $scriptblock)
	{
		/* HarfBuzz says: If the designer designed the font for the 'DFLT' script,
		 * use the default shaper.  Otherwise, use the SEA shaper.
		 * Note that for some simple scripts, there may not be *any*
		 * GSUB/GPOS needed, so there may be no scripts found! */

		$this->restrictToSyllable = true;
		// a. Analyse characters - group as syllables/clusters (Indic); invalid diacritics; add dotted circle
		$sea_category_string = '';
		foreach ($this->OTLdata as $eid => $c) {
			Sea::set_sea_properties($this->OTLdata[$eid], $scriptblock); // sets ['sea_category'] and ['sea_position']
			//$c['general_category']
			//$c['combining_class']
			//$c['uni'] =  $char;

			$sea_category_string .= Sea::$sea_category_char[$this->OTLdata[$eid]['sea_category']];
		}

		$broken_syllables = false;
		Sea::set_syllables($this->OTLdata, $sea_category_string, $broken_syllables);
		$sea_category_string = '';

		// b. Apply locl and ccmp shaping forms - before initial re-ordering; GSUB Lookups (one at a time)
		$tags = 'locl ccmp';
		$this->_applyGSUBrulesSingly($tags, $GSUBscriptTag, $GSUBlangsys);

		// c. Initial Re-ordering
		// Find base consonant
		// Decompose/compose and reorder Matras
		// Reorder marks to canonical order

		$dottedcircle = false;
		if ($broken_syllables) {
			if ($this->mpdf->_charDefined($this->mpdf->fonts[$this->fontkey]['cw'], 0x25CC)) {
				$dottedcircle = [];
				$ucd_record = Ucdn::get_ucd_record(0x25CC);
				$dottedcircle[0]['general_category'] = $ucd_record[0];
				$dottedcircle[0]['bidi_type'] = $ucd_record[2];
				$dottedcircle[0]['group'] = 'C';
				$dottedcircle[0]['uni'] = 0x25CC;
				$dottedcircle[0]['sea_category'] = Sea::OT_GB;
				$dottedcircle[0]['sea_position'] = Sea::POS_BASE_C;

				$dottedcircle[0]['hex'] = '025CC';  // TEMPORARY
			}
		}
		Sea::initial_reordering($this->OTLdata, $this->GSUBdata[$this->GSUBfont], $broken_syllables, $scriptblock, $dottedcircle);

		// d. Apply basic shaping forms GSUB Lookups (one at a time)
		// One call per stage of the plan HarfBuzz builds for these scripts
		$stages = ['pref', 'abvf blwf pstf'];
		foreach ($stages as $tags) {
			$this->_applyGSUBrulesSingly($tags, $GSUBscriptTag, $GSUBlangsys);
		}

		// e. Final Re-ordering

		Sea::final_reordering($this->OTLdata, $this->GSUBdata[$this->GSUBfont], $scriptblock);

		// f. Apply Presentation Forms GSUB Lookups (+ any discretionary)
		$tags = 'pres abvs blws psts';

		$omittags = 'locl ccmp nukt akhn rphf rkrf pref blwf abvf half pstf cfar vatu cjct init medi fina isol med2 fin2 fin3 ljmo vjmo tjmo';
		$usetags = $tags;
		if (!empty($this->mpdf->OTLtags)) {
			$usetags = $this->_applyTagSettings($tags, $GSUBFeatures, $omittags, false);
		}
		$this->_applyGSUBrules($usetags, $GSUBscriptTag, $GSUBlangsys);
		$this->restrictToSyllable = false;
	}

	/**
	 * The generic path: apply the font's features in the order the font lists them.
	 *
	 * Most scripts need nothing more. Thai and Lao decompose a few characters first, Tibetan and
	 * Myanmar v1 arrive here too, and everything else - Latin, Greek, Cyrillic, Hebrew, the CJK
	 * scripts - is laid out entirely by the lookups the font asks for.
	 *
	 * @return string The feature tags applied, which the positioning reads to tell an OpenType
	 *                small-caps run from one drawn with synthesised capitals
	 */
	private function shapeGeneric($GSUBscriptTag, $GSUBlangsys, $GSUBFeatures, $scriptblock)
	{
		// a. First decompose/compose in Thai / Lao - Tibetan
		// Decomposition for THAI or LAO
		/* This function implements the shaping logic documented here:
		 *
		 *   http://linux.thai.net/~thep/th-otf/shaping.html
		 *
		 * The first shaping rule listed there is needed even if the font has Thai
		 * OpenType tables.
		 *
		 *
		 * The following is NOT specified in the MS OT Thai spec, however, it seems
		 * to be what Uniscribe and other engines implement.  According to Eric Muller:
		 *
		 * When you have a SARA AM, decompose it in NIKHAHIT + SARA AA, *and* move the
		 * NIKHAHIT backwards over any tone mark (0E48-0E4B).
		 *
		 * <0E14, 0E4B, 0E33> -> <0E14, 0E4D, 0E4B, 0E32>
		 *
		 * This reordering is legit only when the NIKHAHIT comes from a SARA AM, not
		 * when it's there to start with. The string <0E14, 0E4B, 0E4D> is probably
		 * not what a user wanted, but the rendering is nevertheless nikhahit above
		 * chattawa.
		 *
		 * Same for Lao.
		 *
		 *          Thai        Lao
		 * SARA AM:     U+0E33  U+0EB3
		 * SARA AA:     U+0E32  U+0EB2
		 * Nikhahit:    U+0E4D  U+0ECD
		 *
		 * Testing shows that Uniscribe reorder the following marks:
		 * Thai:    <0E31,0E34..0E37,0E47..0E4E>
		 * Lao: <0EB1,0EB4..0EB7,0EC7..0ECE>
		 *
		 * Lao versions are the same as Thai + 0x80.
		 */
		if ($this->shaper == 'T' || $this->shaper == 'L') {
			for ($ptr = 0; $ptr < count($this->OTLdata); $ptr++) {
				$char = $this->OTLdata[$ptr]['uni'];
				if (($char & ~0x0080) == 0x0E33) { // if SARA_AM (U+0E33 or U+0EB3)
					$NIKHAHIT = $char + 0x1A;
					$SARA_AA = $char - 1;
					$sub = [$SARA_AA, $NIKHAHIT];

					$newinfo = [];
					$ucd_record = Ucdn::get_ucd_record($sub[0]);
					$newinfo[0]['general_category'] = $ucd_record[0];
					$newinfo[0]['bidi_type'] = $ucd_record[2];
					$charasstr = GlyphString::of($sub[0]);
					if ($this->isMark($charasstr)) {
						$newinfo[0]['group'] = 'M';
					} else {
						$newinfo[0]['group'] = 'C';
					}
					$newinfo[0]['uni'] = $sub[0];
					$newinfo[0]['hex'] = $charasstr;
					$this->OTLdata[$ptr] = $newinfo[0]; // Substitute SARA_AM => SARA_AA

					$ntones = 0; // number of (preceding) tone marks
					// IS_TONE_MARK ((x) & ~0x0080, 0x0E34 - 0x0E37, 0x0E47 - 0x0E4E, 0x0E31)
					while (isset($this->OTLdata[$ptr - 1 - $ntones]) && (
					($this->OTLdata[$ptr - 1 - $ntones]['uni'] & ~0x0080) == 0x0E31 ||
					(($this->OTLdata[$ptr - 1 - $ntones]['uni'] & ~0x0080) >= 0x0E34 &&
					($this->OTLdata[$ptr - 1 - $ntones]['uni'] & ~0x0080) <= 0x0E37) ||
					(($this->OTLdata[$ptr - 1 - $ntones]['uni'] & ~0x0080) >= 0x0E47 &&
					($this->OTLdata[$ptr - 1 - $ntones]['uni'] & ~0x0080) <= 0x0E4E)
					)
					) {
						$ntones++;
					}

					$newinfo = [];
					$ucd_record = Ucdn::get_ucd_record($sub[1]);
					$newinfo[0]['general_category'] = $ucd_record[0];
					$newinfo[0]['bidi_type'] = $ucd_record[2];
					$charasstr = GlyphString::of($sub[1]);
					if ($this->isMark($charasstr)) {
						$newinfo[0]['group'] = 'M';
					} else {
						$newinfo[0]['group'] = 'C';
					}
					$newinfo[0]['uni'] = $sub[1];
					$newinfo[0]['hex'] = $charasstr;
					// Insert NIKAHIT
					array_splice($this->OTLdata, $ptr - $ntones, 0, $newinfo);

					$ptr++;
				}
			}
		}

		if ($scriptblock == Ucdn::SCRIPT_TIBETAN) {
			// Reordering TIBETAN
			// Tibetan does not need to need a shaper generally, as long as characters are presented in the correct order
			// so we will do one minor change here:
			// From ICU: If the present character is a number, and the next character is a pre-number combining mark
			// then the two characters are reordered
			// From MS OTL spec the following are Digit modifiers (Md): 0F18–0F19, 0F3E–0F3F
			// Digits: 0F20–0F33
			// On testing only 0x0F3F (pre-based mark) seems to need re-ordering
			for ($ptr = 0; $ptr < count($this->OTLdata) - 1; $ptr++) {
				if (Indic::in_range($this->OTLdata[$ptr]['uni'], 0x0F20, 0x0F33) && $this->OTLdata[$ptr + 1]['uni'] == 0x0F3F) {
					$tmp = $this->OTLdata[$ptr + 1];
					$this->OTLdata[$ptr + 1] = $this->OTLdata[$ptr];
					$this->OTLdata[$ptr] = $tmp;
				}
			}

			// Decomposition for TIBETAN
			/* Recommended, but does not seem to change anything...
			  for($ptr=0; $ptr<count($this->OTLdata); $ptr++) {
			  $char = $this->OTLdata[$ptr]['uni'];
			  $sub = Indic::decompose_indic($char);
			  if ($sub) {
			  $newinfo = array();
			  for($i=0;$i<count($sub);$i++) {
			  $newinfo[$i] = array();
			  $ucd_record = Ucdn::get_ucd_record($sub[$i]);
			  $newinfo[$i]['general_category'] = $ucd_record[0];
			  $newinfo[$i]['bidi_type'] = $ucd_record[2];
			  $charasstr = GlyphString::of($sub[$i]);
			  if (strpos($this->GlyphClassMarks, $charasstr)!==false) { $newinfo[$i]['group'] =  'M'; }
			  else { $newinfo[$i]['group'] =  'C'; }
			  $newinfo[$i]['uni'] =  $sub[$i];
			  $newinfo[$i]['hex'] =  $charasstr;
			  }
			  array_splice($this->OTLdata, $ptr, 1, $newinfo);
			  $ptr += count($sub)-1;
			  }
			  }
			 */
		}

		// b. Apply all GSUB Lookups (in order specified in lookup list)
		$tags = 'locl ccmp pref blwf abvf pstf pres abvs blws psts haln rlig calt liga clig mset  RQD';
		// pref blwf abvf pstf required for Tibetan
		// " RQD" is a non-standard tag in Garuda font - presumably intended to be used by default ? "ReQuireD"
		// Being a 3 letter tag is non-standard, and does not allow it to be set by font-feature-settings

		/* ?Add these until shapers witten?
		  Hangul:   ljmo vjmo tjmo
		 */

		$omittags = '';
		$useGSUBtags = $tags;
		if (!empty($this->mpdf->OTLtags)) {
			$useGSUBtags = $this->_applyTagSettings($tags, $GSUBFeatures, $omittags, false);
		}
		// APPLY GSUB rules (as long as not Latin + SmallCaps - but not OTL smcp)
		if (!(($this->mpdf->textvar & TextVars::FC_SMALLCAPS) && $scriptblock == Ucdn::SCRIPT_LATIN && strpos($useGSUBtags, 'smcp') === false)) {
			$this->_applyGSUBrules($useGSUBtags, $GSUBscriptTag, $GSUBlangsys);
		}

		return $useGSUBtags;
	}

	/**
	 * Phases 6 to 10: positioning.
	 *
	 * Where substitution changes which glyphs are drawn, this changes where they are drawn: a pair
	 * kerned together, a mark placed over the base it belongs to, one cursive glyph joined to the
	 * next. It leaves the text alone and writes into OTLdata's GPOSinfo, which the drawing code
	 * reads alongside it.
	 */
	private function applyGPOS($GPOSscriptTag, $GPOSlangsys, $GPOSFeatures, $scriptblock, $is_old_spec, $useGSUBtags)
	{
		if (!$GPOSscriptTag || !$GPOSlangsys || !$GPOSFeatures) {
			return;
		}

		$this->readTable('GPOS');

		$this->Entry = [];
		$this->Exit = [];

		// 6. Load GPOS data, Coverage & Lookups
		if (!isset($this->GPOSdata[$this->fontkey])) {
			$this->GPOSdata[$this->fontkey]['LuCoverage'] = $this->loadRequiredLayoutData($this->fontkey . '.GPOSdata.json');
		}

		$this->LuCoverage = $this->GPOSdata[$this->fontkey]['LuCoverage'];

		$this->GPOSLookups = $this->mpdf->CurrentFont['GPOSLookups'];

		// 7. Select Feature tags to use (incl optional)
		$tags = 'abvm blwm mark mkmk curs cpsp dist requ'; // Default set
		// 'requ' is not listed in the Microsoft registry of Feature tags
		// Found in Arial Unicode MS, it repositions the baseline for punctuation in Kannada script

		// ZZZ96
		// Set kern to be included by default in non-Latin script (? just when shapers used)
		// Kern is used in some fonts to reposition marks etc. and is essential for correct display
		//if ($this->shaper) {$tags .= ' kern'; }
		if ($scriptblock != Ucdn::SCRIPT_LATIN) {
			$tags .= ' kern';
		}

		$omittags = '';
		$usetags = $tags;
		if (!empty($this->mpdf->OTLtags)) {
			$usetags = $this->_applyTagSettings($tags, $GPOSFeatures, $omittags, false);
		}

		// 8. Get GPOS LookupList from Feature tags
		$LookupList = $this->lookupsInLookupListOrder($GPOSFeatures, $usetags);

		// 9. Apply GPOS Lookups (in order specified in lookup list but selecting from specified tags)
		// APPLY THE GPOS RULES (as long as not Latin + SmallCaps - but not OTL smcp)
		if (!(($this->mpdf->textvar & TextVars::FC_SMALLCAPS) && $scriptblock == Ucdn::SCRIPT_LATIN && strpos($useGSUBtags, 'smcp') === false)) {
			$this->_applyGPOSrules($LookupList, $is_old_spec);
			// (sets: $this->OTLdata[n]['GPOSinfo'] XPlacement YPlacement XAdvance Entry Exit )
		}

		// 10. Process cursive text
		if (count($this->Entry) || count($this->Exit)) {
			// RTL
			$incurs = false;
			for ($i = (count($this->OTLdata) - 1); $i >= 0; $i--) {
				if (isset($this->Entry[$i]) && isset($this->Entry[$i]['Y']) && $this->Entry[$i]['dir'] == 'RTL') {
					$nextbase = $i - 1; // Set as next base ignoring marks (next base reading RTL in logical oder
					while (isset($this->OTLdata[$nextbase]['hex']) && $this->isMark($this->OTLdata[$nextbase]['hex'])) {
						$nextbase--;
					}
					if (isset($this->Exit[$nextbase]) && isset($this->Exit[$nextbase]['Y'])) {
						$diff = $this->Entry[$i]['Y'] - $this->Exit[$nextbase]['Y'];
						if ($incurs === false) {
							$incurs = $diff;
						} else {
							$incurs += $diff;
						}
						for ($j = ($i - 1); $j >= $nextbase; $j--) {
							if (isset($this->OTLdata[$j]['GPOSinfo']['YPlacement'])) {
								$this->OTLdata[$j]['GPOSinfo']['YPlacement'] += $incurs;
							} else {
								$this->OTLdata[$j]['GPOSinfo']['YPlacement'] = $incurs;
							}
						}
						if (isset($this->Exit[$i]['X']) && isset($this->Entry[$nextbase]['X'])) {
							$adj = -($this->Entry[$i]['X'] - $this->Exit[$nextbase]['X']);
							// If XAdvance is aplied - in order for PDF to position the Advance correctly need to place it on:
							// in RTL - the current glyph or the last of any associated marks
							if (isset($this->OTLdata[$nextbase + 1]['GPOSinfo']['XAdvance'])) {
								$this->OTLdata[$nextbase + 1]['GPOSinfo']['XAdvance'] += $adj;
							} else {
								$this->OTLdata[$nextbase + 1]['GPOSinfo']['XAdvance'] = $adj;
							}
						}
					} else {
						$incurs = false;
					}
				} elseif ($this->isMark($this->OTLdata[$i]['hex'])) {
					continue;
				} // ignore Marks
				else {
					$incurs = false;
				}
			}
			// LTR
			$incurs = false;
			for ($i = 0; $i < count($this->OTLdata); $i++) {
				if (isset($this->Exit[$i]) && isset($this->Exit[$i]['Y']) && $this->Exit[$i]['dir'] == 'LTR') {
					$nextbase = $i + 1; // Set as next base ignoring marks
					while (isset($this->OTLdata[$nextbase]['hex']) && $this->isMark($this->OTLdata[$nextbase]['hex'])) {
						$nextbase++;
					}
					if (isset($this->Entry[$nextbase]) && isset($this->Entry[$nextbase]['Y'])) {
						$diff = $this->Exit[$i]['Y'] - $this->Entry[$nextbase]['Y'];
						if ($incurs === false) {
							$incurs = $diff;
						} else {
							$incurs += $diff;
						}
						for ($j = ($i + 1); $j <= $nextbase; $j++) {
							if (isset($this->OTLdata[$j]['GPOSinfo']['YPlacement'])) {
								$this->OTLdata[$j]['GPOSinfo']['YPlacement'] += $incurs;
							} else {
								$this->OTLdata[$j]['GPOSinfo']['YPlacement'] = $incurs;
							}
						}
						if (isset($this->Exit[$i]['X']) && isset($this->Entry[$nextbase]['X'])) {
							$adj = -($this->Exit[$i]['X'] - $this->Entry[$nextbase]['X']);
							// If XAdvance is aplied - in order for PDF to position the Advance correctly need to place it on:
							// in LTR - the next glyph, ignoring marks
							if (isset($this->OTLdata[$nextbase]['GPOSinfo']['XAdvance'])) {
								$this->OTLdata[$nextbase]['GPOSinfo']['XAdvance'] += $adj;
							} else {
								$this->OTLdata[$nextbase]['GPOSinfo']['XAdvance'] = $adj;
							}
						}
					} else {
						$incurs = false;
					}
				} elseif ($this->isMark($this->OTLdata[$i]['hex'])) {
					continue;
				} // ignore Marks
				else {
					$incurs = false;
				}
			}
		}
	}

	/**
	 * Which shaper a script needs.
	 *
	 * Most scripts are laid out by applying the font's features in the order it lists them, which is
	 * what "" means here. The rest need their own rules run first - a cluster reordered, a joining
	 * form chosen, a syllable checked - and each of those has a shaper of its own.
	 *
	 * @return string One of I (Indic), A (Arabic), K (Khmer), T (Thai), L (Lao), S (Sinhala),
	 *                M (Myanmar), E (South East Asian) or "" for the generic path
	 */
	private function selectShaper($scriptblock)
	{
		if (Ucdn::SCRIPT_DEVANAGARI <= $scriptblock && $scriptblock <= Ucdn::SCRIPT_MALAYALAM) {
			return "I";
		} // INDIC shaper
		elseif ($scriptblock == Ucdn::SCRIPT_ARABIC || $scriptblock == Ucdn::SCRIPT_SYRIAC) {
			return "A";
		} // ARABIC shaper
		elseif ($scriptblock == Ucdn::SCRIPT_NKO || $scriptblock == Ucdn::SCRIPT_MANDAIC) {
			return "A";
		} // ARABIC shaper
		elseif ($scriptblock == Ucdn::SCRIPT_KHMER) {
			return "K";
		} // KHMER shaper
		elseif ($scriptblock == Ucdn::SCRIPT_THAI) {
			return "T";
		} // THAI shaper
		elseif ($scriptblock == Ucdn::SCRIPT_LAO) {
			return "L";
		} // LAO shaper
		elseif ($scriptblock == Ucdn::SCRIPT_SINHALA) {
			return "S";
		} // SINHALA shaper
		elseif ($scriptblock == Ucdn::SCRIPT_MYANMAR) {
			return "M";
		} // MYANMAR shaper
		elseif ($scriptblock == Ucdn::SCRIPT_NEW_TAI_LUE) {
			return "E";
		} // SEA South East Asian shaper
		elseif ($scriptblock == Ucdn::SCRIPT_CHAM) {
			return "E";
		} // SEA South East Asian shaper
		elseif ($scriptblock == Ucdn::SCRIPT_TAI_THAM) {
			return "E";
		} // SEA South East Asian shaper
		else {
			return "";
		}
	}

	/**
	 * Which shaper a run gets once the font's GSUB script for it is known.
	 *
	 * As HarfBuzz's hb_ot_shaper_categorize(): a font designed for DFLT, or one where the choice fell
	 * through to latn, is laid out by its features alone, and no script at all is not a reason to
	 * skip the shaper. Khmer, Thai and Lao keep theirs whatever was chosen, Arabic too, and Syriac
	 * everywhere but under DFLT. Myanmar also gives the pre-specification mymr to the default shaper.
	 *
	 * @param string $shaper        The shaper selectShaper() picked for the run's script
	 * @param int    $scriptblock   The run's Unicode script, as Ucdn::SCRIPT_*
	 * @param string $GSUBscriptTag The GSUB script chosen for it, or '' for none
	 *
	 * @return string The shaper, as selectShaper() names it
	 */
	private function shaperForScriptTag($shaper, $scriptblock, $GSUBscriptTag)
	{
		if ($shaper == 'K' || $shaper == 'T' || $shaper == 'L' || $scriptblock == Ucdn::SCRIPT_ARABIC) {
			return $shaper;
		}

		if ($scriptblock == Ucdn::SCRIPT_SYRIAC) {
			return $GSUBscriptTag == 'DFLT' ? '' : $shaper;
		}

		if ($GSUBscriptTag == 'DFLT' || $GSUBscriptTag == 'latn' || ($shaper == 'M' && $GSUBscriptTag == 'mymr')) {
			return '';
		}

		return $shaper;
	}

	/**
	 * @param string $table 'GSUB' or 'GPOS'
	 *
	 * @return array The features the table offers under a script and language system, by tag, or
	 *               none where it offers neither
	 */
	private function features($table, $scriptTag, $langsys)
	{
		return isset($this->mpdf->CurrentFont[$table . 'Features'][$scriptTag][$langsys])
			? $this->mpdf->CurrentFont[$table . 'Features'][$scriptTag][$langsys]
			: [];
	}

	/**
	 * Phase 3: which script and language system of the font to lay this run out with.
	 *
	 * The script the text is in and the script the font speaks for need not be the same tag - a font
	 * may offer the older "deva" where the text calls for "dev2", or offer nothing for the script at
	 * all - so each of GSUB and GPOS is asked separately what it has. GPOS reuses GSUB's answer where
	 * it can, because a font that offers a script in one usually offers it in the other, and asking
	 * twice would pick a different language system for the positioning than for the substitution.
	 *
	 * @return array [$GSUBscriptTag, $GSUBlangsys, $GPOSscriptTag, $GPOSlangsys, $is_old_spec]:
	 *               empty tags where the font offers nothing, and whether the script tag chosen is
	 *               the pre-OpenType-1.6 spelling, which the Indic rules are applied differently for
	 */
	private function selectScriptAndLanguage($scriptblock, $useOTL)
	{
		// Get scripttag based on actual text script
		$scripttag = Ucdn::$uni_scriptblock[$scriptblock];

		$GSUBscriptTag = '';
		$GSUBlangsys = '';
		$GPOSscriptTag = '';
		$GPOSlangsys = '';
		$is_old_spec = false;

		$ScriptLang = $this->mpdf->CurrentFont['GSUBScriptLang'];
		if (count($ScriptLang)) {
			list($GSUBscriptTag, $is_old_spec) = OtlTags::script($ScriptLang, $scripttag, $scriptblock, $this->shaper, $useOTL);
			if ($this->mpdf->fontLanguageOverride && strpos($ScriptLang[$GSUBscriptTag], $this->mpdf->fontLanguageOverride) !== false) {
				$GSUBlangsys = str_pad($this->mpdf->fontLanguageOverride, 4);
			} elseif ($GSUBscriptTag && isset($ScriptLang[$GSUBscriptTag]) && $ScriptLang[$GSUBscriptTag] != '') {
				$GSUBlangsys = OtlTags::language($this->mpdf->currentLang, $ScriptLang[$GSUBscriptTag]);
			}
		}
		$ScriptLang = $this->mpdf->CurrentFont['GPOSScriptLang'];

		// NB If after GSUB, the same script/lang exist for GPOS, just use these...
		if ($GSUBscriptTag && $GSUBlangsys && isset($ScriptLang[$GSUBscriptTag]) && strpos($ScriptLang[$GSUBscriptTag], $GSUBlangsys) !== false) {
			$GPOSlangsys = $GSUBlangsys;
			$GPOSscriptTag = $GSUBscriptTag;
		} // else repeat for GPOS
		// [Font XBRiyaz has GSUB tables for latn, but not GPOS for latn]
		elseif (count($ScriptLang)) {
			list($GPOSscriptTag, $dummy) = OtlTags::script($ScriptLang, $scripttag, $scriptblock, $this->shaper, $useOTL);
			if ($GPOSscriptTag && $this->mpdf->fontLanguageOverride && strpos($ScriptLang[$GPOSscriptTag], $this->mpdf->fontLanguageOverride) !== false) {
				$GPOSlangsys = str_pad($this->mpdf->fontLanguageOverride, 4);
			} elseif ($GPOSscriptTag && isset($ScriptLang[$GPOSscriptTag]) && $ScriptLang[$GPOSscriptTag] != '') {
				$GPOSlangsys = OtlTags::language($this->mpdf->currentLang, $ScriptLang[$GPOSscriptTag]);
			}
		}

		return [$GSUBscriptTag, $GSUBlangsys, $GPOSscriptTag, $GPOSlangsys, $is_old_spec];
	}

	/**
	 * Phase 11: put the subchunks back together into one run.
	 *
	 * Each was shaped on its own because each is a different script, and what the drawing code is
	 * given is one string with one OTLdata beside it: the positioning of every glyph by its place in
	 * that string, the bidi class and character behind each, and the group - S for a space, M for a
	 * mark, C for anything else - that line breaking and justification read.
	 *
	 * @param int $subchunk The index of the last subchunk, which is one less than how many there are
	 *
	 * @return string The shaped text
	 */
	private function reassemble($subchunk)
	{
		$newGPOSinfo = [];
		$newchar_data = [];
		$newgroup = '';
		$shaped = '';
		$ectr = 0;

		for ($sch = 0; $sch <= $subchunk; $sch++) {
			foreach ($this->schOTLdata[$sch] as $char) {
				if (isset($char['GPOSinfo'])) {
					$newGPOSinfo[$ectr] = $char['GPOSinfo'];
				}
				$newchar_data[$ectr] = ['bidi_class' => $char['bidi_type'], 'uni' => $char['uni']];
				$newgroup .= $char['group'];
				$shaped .= UtfString::code2utf($char['uni']);

				// Every character the shaping ended up with has to be in the subset, or the glyph a
				// substitution reached will not be in the font that gets embedded
				if (isset($this->mpdf->CurrentFont['subset'])) {
					$this->mpdf->CurrentFont['subset'][$char['uni']] = $char['uni'];
				}
				$ectr++;
			}
		}

		// This leaves OTLdata::GPOSinfo, ::char_data & ::group
		$this->OTLdata['GPOSinfo'] = $newGPOSinfo;
		$this->OTLdata['char_data'] = $newchar_data;
		$this->OTLdata['group'] = $newgroup;

		return $shaped;
	}

	/**
	 * Add the features the document asked for to a default set, and take out the ones it turned off.
	 *
	 * font-variant and font-feature-settings both reach here; the first four-letter tag in either is
	 * matched against what the font actually offers, so asking for a feature the font does not have
	 * changes nothing.
	 *
	 * @param string $tags     The features that would be used by default, space separated
	 * @param array  $Features The features this font offers for the script and language in hand
	 * @param string $omittags Features that may not be turned on here whatever the document says,
	 *                         because the shaper applies them itself
	 * @param bool   $onlytags Whether the document may only turn off features already in $tags,
	 *                         rather than add any
	 *
	 * @return string The features to apply, space separated
	 */
	function _applyTagSettings($tags, $Features, $omittags = '', $onlytags = false)
	{
		if (empty($this->mpdf->OTLtags['Plus']) && empty($this->mpdf->OTLtags['Minus']) && empty($this->mpdf->OTLtags['FFPlus']) && empty($this->mpdf->OTLtags['FFMinus'])) {
			return $tags;
		}

		// Use $tags as starting point
		$usetags = $tags;

		// Only set / unset tags which are in the font
		// Ignore tags which are in $omittags
		// If $onlytags, then just unset tags which are already in the Tag list

		$fp = $fm = $ffp = $ffm = '';

		// Font features to enable - set by font-variant-xx
		if (isset($this->mpdf->OTLtags['Plus'])) {
			$fp = $this->mpdf->OTLtags['Plus'];
		}
		preg_match_all('/([a-zA-Z0-9]{4})/', $fp, $m);
		for ($i = 0; $i < count($m[0]); $i++) {
			$t = $m[1][$i];
			// Is it a valid tag?
			if (isset($Features[$t]) && strpos($omittags, $t) === false && (!$onlytags || strpos($tags, $t) !== false )) {
				$usetags .= ' ' . $t;
			}
		}

		// Font features to disable - set by font-variant-xx
		if (isset($this->mpdf->OTLtags['Minus'])) {
			$fm = $this->mpdf->OTLtags['Minus'];
		}
		preg_match_all('/([a-zA-Z0-9]{4})/', $fm, $m);
		for ($i = 0; $i < count($m[0]); $i++) {
			$t = $m[1][$i];
			// Is it a valid tag?
			if (isset($Features[$t]) && strpos($omittags, $t) === false && (!$onlytags || strpos($tags, $t) !== false )) {
				$usetags = str_replace($t, '', $usetags);
			}
		}

		// Font features to enable - set by font-feature-settings
		if (isset($this->mpdf->OTLtags['FFPlus'])) {
			$ffp = $this->mpdf->OTLtags['FFPlus']; // Font Features - may include integer: salt4
		}
		preg_match_all('/([a-zA-Z0-9]{4})([\d+]*)/', $ffp, $m);
		for ($i = 0; $i < count($m[0]); $i++) {
			$t = $m[1][$i];
			// Is it a valid tag?
			if (isset($Features[$t]) && strpos($omittags, $t) === false && (!$onlytags || strpos($tags, $t) !== false )) {
				$usetags .= ' ' . $m[0][$i];  //  - may include integer: salt4
			}
		}

		// Font features to disable - set by font-feature-settings
		if (isset($this->mpdf->OTLtags['FFMinus'])) {
			$ffm = $this->mpdf->OTLtags['FFMinus'];
		}
		preg_match_all('/([a-zA-Z0-9]{4})/', $ffm, $m);
		for ($i = 0; $i < count($m[0]); $i++) {
			$t = $m[1][$i];
			// Is it a valid tag?
			if (isset($Features[$t]) && strpos($omittags, $t) === false && (!$onlytags || strpos($tags, $t) !== false )) {
				$usetags = str_replace($t, '', $usetags);
			}
		}
		return $usetags;
	}

	/**
	 * Apply a set of GSUB features, all together, in the order the font's lookup list gives them.
	 *
	 * The plain path, for scripts with no shaper of their own.
	 *
	 * @param string $usetags   The feature tags to apply, space separated, each optionally followed by
	 *                          the alternate it asks for
	 * @param string $scriptTag The OpenType script the text was assigned to
	 * @param string $langsys   The OpenType language system under it
	 */
	function _applyGSUBrules($usetags, $scriptTag, $langsys)
	{
		$stage = $this->lookupsInLookupListOrder($this->features('GSUB', $scriptTag, $langsys), $usetags);

		foreach ($stage as $lu => $take) {
			$this->applyGSUBlookupOverRun($lu, $take['tag'], $take['alternate'], $take['mask'], 0);
		}
	}

	/**
	 * Apply a set of GSUB features one feature at a time, each over the whole run before the next.
	 *
	 * What the South East Asian shaper and the Khmer presentation pass ask for, where a later feature
	 * is meant to see what an earlier one produced.
	 *
	 * The tags given are one stage of the plan HarfBuzz would build - see lookupsForStage() - so a
	 * caller whose features do not all share a stage makes a call for each.
	 *
	 * Unlike the two syllable-based shapers, which name their own tags, the list it is given can also
	 * carry what a document asked for.
	 *
	 * @param string $usetags   The feature tags to apply, space separated, each optionally followed by
	 *                          the alternate it asks for
	 * @param string $scriptTag The OpenType script the text was assigned to
	 * @param string $langsys   The OpenType language system under it
	 */
	function _applyGSUBrulesSingly($usetags, $scriptTag, $langsys)
	{
		$stage = $this->lookupsForStage($this->features('GSUB', $scriptTag, $langsys), $usetags, []);

		// The reverse Lookups are taken first, out of the pass below, because a reverse Lookup cannot
		// share the cursor the rest walk forward. The cost is its place in Lookup List order among the
		// Lookups of its own feature.
		foreach ($stage as $lu => $take) {
			if ($this->GSUBLookups[$lu]['Type'] == 8) {
				$this->applyGSUBlookupOverRun($lu, $take['tag'], $take['alternate'], $take['mask'], 0);
				unset($stage[$lu]);
			}
		}

		foreach ($stage as $lu => $take) {
			$this->applyGSUBlookupOverRun($lu, $take['tag'], $take['alternate'], $take['mask'], 0);
		}
	}

	/**
	 * Apply a set of GSUB features one at a time, for Myanmar.
	 *
	 * Myanmar's features apply to every glyph of the syllable the shaper grouped, so it names no mask.
	 *
	 * @param string $usetags   The feature tags to apply, space separated, each optionally followed by
	 *                          the alternate it asks for
	 * @param string $scriptTag The OpenType script the text was assigned to
	 * @param string $langsys   The OpenType language system under it
	 */
	function _applyGSUBrulesMyanmar($usetags, $scriptTag, $langsys)
	{
		$this->applyGSUBfeaturesInTurn($usetags, $scriptTag, $langsys, 0, []);
	}

	/**
	 * Apply a set of GSUB features one at a time, for the Indic scripts.
	 *
	 * As the Myanmar path, with one addition: several of these features apply only where the shaper
	 * marked a character for them - the reph, the pre-base form, the half form - so each glyph is
	 * tested against the mask the reordering left on it.
	 *
	 * @param string $usetags   The feature tags to apply, space separated, each optionally followed by
	 *                          the alternate it asks for
	 * @param string $scriptTag The OpenType script the text was assigned to
	 * @param string $langsys   The OpenType language system under it
	 * @param bool   $is_old_spec Whether the font uses the original Indic script tags rather than the
	 *                            v2 ones, which changes where the features are expected to apply
	 */
	function _applyGSUBrulesIndic($usetags, $scriptTag, $langsys, $is_old_spec)
	{
		$this->applyGSUBfeaturesInTurn($usetags, $scriptTag, $langsys, $is_old_spec, $this->indicFeatureMasks());
	}

	/**
	 * Apply each feature over the whole run before the next one starts.
	 *
	 * What the two syllable-based shapers and the Arabic presentation pass ask for: the features they
	 * name are staged, and a later one is meant to read the glyphs an earlier one made.
	 *
	 * As _applyGSUBrulesSingly(), the tags given are one stage of HarfBuzz's plan.
	 *
	 * @param array $featureMasks The bit a feature's glyphs must carry, by tag. A feature named here
	 *                            is applied only where the reordering marked a character for it;
	 *                            one that is not applies to every glyph of the syllable.
	 */
	private function applyGSUBfeaturesInTurn($usetags, $scriptTag, $langsys, $is_old_spec, array $featureMasks)
	{
		$stage = $this->lookupsForStage($this->features('GSUB', $scriptTag, $langsys), $usetags, $featureMasks);

		foreach ($stage as $lu => $take) {
			$this->applyGSUBlookupOverRun($lu, $take['tag'], $take['alternate'], $take['mask'], $is_old_spec);
		}
	}

	/**
	 * Take one Lookup over the run, from the first glyph to the last.
	 *
	 * A syllable-based shaper needs a rule not to match across a syllable boundary. That is not
	 * enforced here but in checkContextMatch() and checkContextMatchMultiple(), which refuse a match
	 * reaching outside the current syllable while restrictToSyllable is set.
	 *
	 * @param int    $lu          The Lookup to take
	 * @param string $tag         The feature tag it was selected under
	 * @param int    $tagInt      Which alternate that feature was asked for
	 * @param int    $mask        The bit a glyph must carry for this feature, 0 where it applies to all
	 * @param bool   $is_old_spec Whether the font uses the original Indic script tags
	 */
	private function applyGSUBlookupOverRun($lu, $tag, $tagInt, $mask, $is_old_spec)
	{
		$Type = $this->GSUBLookups[$lu]['Type'];
		$Flag = $this->GSUBLookups[$lu]['Flag'];
		$MarkFilteringSet = $this->GSUBLookups[$lu]['MarkFilteringSet'];

		if ($Type == 8) {
			$this->_applyGSUBreverseLookup($lu, $Flag, $MarkFilteringSet, $tag, $tagInt, $mask);
			return;
		}

		$ptr = 0;
		// Test each glyph sequentially
		while ($ptr < (count($this->OTLdata))) { // whilst there is another glyph ..0064
			$currGlyph = $this->OTLdata[$ptr]['hex'];
			$currGID = $this->OTLdata[$ptr]['uni'];
			$shift = null;
			foreach ($this->GSUBLookups[$lu]['Subtables'] as $c => $subtable_offset) {
				// The Coverage read for this subtable is the one for input position 0, which is the only
				// position a match can start at - see where TTFontFile reads it
				if (isset($this->GSLuCoverage[$lu][$c][$currGID])) {
					if ($mask && !($this->OTLdata[$ptr]['mask'] & $mask)) { // only apply when mask indicates
						continue;
					}
					// Get rules from font GSUB subtable
					$shift = $this->_applyGSUBsubtable($lu, $c, $ptr, $currGlyph, $currGID, $subtable_offset, $Type, $Flag, $MarkFilteringSet, $this->GSLuCoverage[$lu][$c], 0, $tag, $is_old_spec, $tagInt);

					if ($shift !== null) {
						break;
					}
				} // Special case for Indic  ZZZ99S
				// Check to substitute Halant-Consonant in PREF, BLWF or PSTF
				// i.e. new spec but GSUB tables have Consonant-Halant in Lookups e.g. FreeSerif, which
				// incorrectly just moved old spec tables to new spec. Uniscribe seems to cope with this
				// See also ttffontsuni.php
				// First check if current glyph is a Halant/Virama
				// Only a masked feature reaches this: with no mask there is no bit for the test below to
				// read, and pref, blwf and pstf all carry one
				elseif ($mask && static::_OTL_OLD_SPEC_COMPAT_1 && $Type == 4 && !$is_old_spec && strpos('0094D 009CD 00A4D 00ACD 00B4D 00BCD 00C4D 00CCD 00D4D', $currGlyph) !== false) {
					// only apply when 'pref blwf pstf' tags, and when mask indicates
					if (strpos('pref blwf pstf', $tag) !== false) {
						if (!($this->OTLdata[$ptr]['mask'] & $mask)) {
							continue;
						}

						if (!isset($this->OTLdata[$ptr + 1])) {
							continue;
						}

						$nextGlyph = $this->OTLdata[$ptr + 1]['hex'];
						$nextGID = $this->OTLdata[$ptr + 1]['uni'];
						if (isset($this->GSLuCoverage[$lu][$c][$nextGID])) {
							// Get rules from font GSUB subtable
							$shift = $this->_applyGSUBsubtableSpecial($lu, $c, $ptr, $currGlyph, $currGID, $nextGlyph, $nextGID, $subtable_offset, $Type, $this->GSLuCoverage[$lu][$c]);

							if ($shift !== null) {
								break;
							}
						}
					}
				}
			}
			$ptr += $shift === null ? 1 : $shift; // null: nothing applied, so step on one glyph
		}
	}

	/**
	 * The features a tag list asks for, in the order it names them and each of them once.
	 *
	 * An entry is a four character tag, which font-feature-settings may follow with the alternate it
	 * wants, 'salt4' - alternateWanted() reads that back off the list. A feature named twice, as a
	 * document asking for one the shaper already named leaves it, is applied once: a second pass
	 * reaches the glyphs the first one made, which HarfBuzz never offers a Lookup again.
	 *
	 * @return string[] The feature tags, four characters each
	 */
	private function featuresToApply($usetags)
	{
		$tags = [];
		foreach (explode(' ', $usetags) as $usetag) {
			$tags[] = substr($usetag, 0, 4);
		}

		return array_unique($tags);
	}

	/**
	 * The Lookups one feature offers, keyed by Lookup so that a feature naming one twice takes it
	 * once, and in Lookup List order rather than the order the feature names them.
	 *
	 * @return int[] The Lookup ids, in the order they are to be applied
	 */
	private function lookupsForFeature(array $features, $usetag)
	{
		if (!isset($features[$usetag])) {
			return [];
		}

		$LookupList = [];
		foreach ($features[$usetag] as $lu) {
			$LookupList[$lu] = true;
		}
		ksort($LookupList);

		return array_keys($LookupList);
	}

	/**
	 * The Lookups the features of one stage ask for, each of them once, in the order the tags named
	 * them.
	 *
	 * HarfBuzz collects the Lookups of the features of a stage of its plan and merges the duplicates,
	 * so a Lookup two features of one stage name is taken once, where one that features of two stages
	 * name is taken for each of them, over the glyphs the earlier stage made. That is why the paths
	 * that apply a feature at a time take a stage per call and not a whole plan.
	 *
	 * A merged Lookup is taken under the tag and the alternate of the first feature that named it, so
	 * that where only one of two tags asked for an alternate it is the tag list that decides whether
	 * that survives, and not the order the language system happened to list the two in. Its mask is
	 * the OR of the masks of every feature that named it, as HarfBuzz ORs the masks of the entries it
	 * merges: a feature with no mask of its own reaches every glyph, which is 0 here and a bit every
	 * glyph carries there, so 0 absorbs. Only the Khmer basic forms put masked and unmasked features
	 * in one stage.
	 *
	 * Ordering by the first feature costs a merged Lookup its place in the list HarfBuzz sorts by
	 * Lookup, which is the price of the per-feature passes: they have no such list to sort by.
	 * lookupsInLookupListOrder() puts a caller that has one back into it.
	 *
	 * @param array  $features     The features the font offers for the script and language in hand
	 * @param string $usetags      The feature tags of the stage, space separated, each optionally
	 *                             followed by the alternate it asks for
	 * @param array  $featureMasks The bit a feature's glyphs must carry, by tag
	 *
	 * @return array The tag to take each Lookup under, the alternate and the mask, by Lookup
	 */
	private function lookupsForStage(array $features, $usetags, array $featureMasks)
	{
		$stage = [];
		foreach ($this->featuresToApply($usetags) as $usetag) {
			$mask = isset($featureMasks[$usetag]) ? $featureMasks[$usetag] : 0;
			$tagInt = $this->alternateWanted($usetag, $usetags);

			foreach ($this->lookupsForFeature($features, $usetag) as $lu) {
				if (!isset($stage[$lu])) {
					$stage[$lu] = ['tag' => $usetag, 'alternate' => $tagInt, 'mask' => $mask];
					continue;
				}
				$stage[$lu]['mask'] = $stage[$lu]['mask'] && $mask ? $stage[$lu]['mask'] | $mask : 0;
			}
		}

		return $stage;
	}

	/**
	 * The Lookups a whole tag list asks for, in the order the font's Lookup List gives them.
	 *
	 * What the two plain paths take - _applyGSUBrules(), for the scripts with no shaper of their own,
	 * and the list applyGPOS() builds. Both name everything they have at once, which is one stage, and
	 * both have the font's Lookup List to walk it in, which is what separates them from the passes
	 * that take a feature at a time. Positioning reads only the tag of each entry: an alternate and a
	 * mask mean nothing to it.
	 *
	 * @return array As lookupsForStage(), sorted by Lookup
	 */
	private function lookupsInLookupListOrder(array $features, $usetags)
	{
		$stage = $this->lookupsForStage($features, $usetags, []);
		ksort($stage);

		return $stage;
	}

	/**
	 * Split a list of feature tags into the stages of the plan HarfBuzz would build for them.
	 *
	 * Callers whose tags are fixed name their stages outright. The Arabic presentation pass cannot:
	 * _applyTagSettings() may have added to its list or taken from it by the time the stages are
	 * wanted. A tag none of the stages names is one the document asked for, and joins the last of
	 * them, which is the stage HarfBuzz adds a user feature to. The Khmer presentation pass carries a
	 * document's tags too, but every one of them is in the same stage, so it needs no split.
	 *
	 * @param string   $usetags The tags to apply, space separated, each optionally followed by the
	 *                          alternate it asks for
	 * @param string[] $stages  The tags of each stage, space separated, in the order they are applied
	 *
	 * @return string[] The tags of each stage the list names something of, in the same order
	 */
	private function featureStages($usetags, array $stages)
	{
		$grouped = [];
		$last = count($stages) - 1;

		foreach (explode(' ', $usetags) as $entry) {
			if ($entry === '') {
				continue;
			}

			$at = $last;
			foreach ($stages as $i => $tags) {
				if (in_array(substr($entry, 0, 4), explode(' ', $tags), true)) {
					$at = $i;
					break;
				}
			}
			$grouped[$at][] = $entry;
		}
		ksort($grouped);

		$staged = [];
		foreach ($grouped as $entries) {
			$staged[] = implode(' ', $entries);
		}

		return $staged;
	}

	/**
	 * Which alternate of a feature the document asked for, as font-feature-settings names it - the
	 * fourth for 'salt4'. One where it named none.
	 */
	private function alternateWanted($tag, $usetags)
	{
		if (preg_match('/' . $tag . '([0-9]{1,2})/', $usetags, $m)) {
			return $m[1];
		}

		return 1;
	}

	/**
	 * Take a Lookup over the glyphs from the last one back to the first.
	 *
	 * Type 8, reverse chaining contextual single substitution, is the only Lookup applied in
	 * reverse order: each match reads a lookahead that has already been substituted and a backtrack
	 * that has not. It replaces exactly one glyph, so the cursor always steps by one.
	 */
	private function _applyGSUBreverseLookup($lu, $Flag, $MarkFilteringSet, $tag, $tagInt, $mask)
	{
		$subtables = $this->GSUBLookups[$lu]['Subtables'];
		$coverage = $this->GSLuCoverage[$lu];

		for ($ptr = count($this->OTLdata) - 1; $ptr >= 0; $ptr--) {
			if ($mask && !($this->OTLdata[$ptr]['mask'] & $mask)) { // only apply when mask indicates
				continue;
			}
			$currGlyph = $this->OTLdata[$ptr]['hex'];
			$currGID = $this->OTLdata[$ptr]['uni'];
			foreach ($subtables as $c => $subtable_offset) {
				if (!isset($coverage[$c][$currGID])) {
					continue;
				}
				// Get rules from font GSUB subtable
				if (null !== $this->_applyGSUBsubtable($lu, $c, $ptr, $currGlyph, $currGID, $subtable_offset, 8, $Flag, $MarkFilteringSet, $coverage[$c], 0, $tag, 0, $tagInt)) {
					break;
				}
			}
		}
	}

	/**
	 * The bit each Indic feature sets on the glyphs it may be applied to. The tags left out apply to
	 * every glyph in the syllable and have no bit of their own.
	 *
	 * @return array The mask, by feature tag
	 */
	private function indicFeatureMasks()
	{
		return [
			'rphf' => Indic::FLAG(Indic::RPHF),
			'pref' => Indic::FLAG(Indic::PREF),
			'blwf' => Indic::FLAG(Indic::BLWF),
			'abvf' => Indic::FLAG(Indic::ABVF),
			'half' => Indic::FLAG(Indic::HALF),
			'pstf' => Indic::FLAG(Indic::PSTF),
			'cfar' => Indic::FLAG(Indic::CFAR),
			'init' => Indic::FLAG(Indic::INIT),
		];
	}

	/**
	 * @return int|null Glyphs to advance by, null if the subtable did not apply
	 */
	function _applyGSUBsubtableSpecial($lookupID, $subtable, $ptr, $currGlyph, $currGID, $nextGlyph, $nextGID, $subtable_offset, $Type, $LuCoverage)
	{
		// The same guard the other two entry points make, on the glyph this one is indexed by: the
		// lookup is on the consonant after the Halant, not on the glyph the cursor is on
		if (!isset($LuCoverage[$nextGID])) {
			return null;
		}

		// Special case for Indic
		// Check to substitute Halant-Consonant in PREF, BLWF or PSTF
		// i.e. new spec but GSUB tables have Consonant-Halant in Lookups e.g. FreeSerif, which
		// incorrectly just moved old spec tables to new spec. Uniscribe seems to cope with this
		// See also ttffontsuni.php

		$this->reader->seek($subtable_offset);
		$SubstFormat = $this->reader->readUInt16();

		// Subtable contains Consonant - Halant
		// Text string contains Halant ($CurrGlyph) - Consonant ($nextGlyph)
		// Halant has already been matched, and already checked that $nextGID is in Coverage table
		// Only does: LookupType 4: Ligature Substitution Subtable : n to 1
		$Coverage = $subtable_offset + $this->reader->readUInt16();
		$NextGlyphPos = $LuCoverage[$nextGID];
		$LigSetCount = $this->reader->readUInt16();

		$this->reader->skip($NextGlyphPos * 2);
		$LigSet = $subtable_offset + $this->reader->readUInt16();

		$this->reader->seek($LigSet);
		$LigCount = $this->reader->readUInt16();
		// LigatureSet i.e. all starting with the same Glyph $nextGlyph [Consonant]
		$LigatureOffset = [];
		for ($g = 0; $g < $LigCount; $g++) {
			$LigatureOffset[$g] = $LigSet + $this->reader->readUInt16();
		}
		for ($g = 0; $g < $LigCount; $g++) {
			// Ligature tables
			$this->reader->seek($LigatureOffset[$g]);
			$LigGlyph = $this->reader->readUInt16();
			$substitute = $this->glyphToChar($LigGlyph);
			$CompCount = $this->reader->readUInt16();

			if ($CompCount != 2) {
				return null;
			} // Only expecting to work with 2:1 (and no ignore characters in between)

			$gid = $this->reader->readUInt16();
			$checkGlyph = $this->glyphToChar($gid); // Other component/input Glyphs starting at position 2 (arrayindex 1)

			if ($currGID == $checkGlyph) {
				$match = true;
			} else {
				$match = false;
				break;
			}

			$GlyphPos = [];
			$GlyphPos[] = $ptr;
			$GlyphPos[] = $ptr + 1;

			if ($match) {
				$shift = $this->GSUBsubstitute($ptr, $substitute, 4, $GlyphPos); // GlyphPos contains positions to set null
				if ($shift) {
					return 1;
				}
			}
		}

		return null;
	}

	/**
	 * Apply one GSUB subtable at one position in the string.
	 *
	 * One method per subtable structure below, named for the structure, so that each can be read
	 * against its own section of the spec. The parameter lists are long because a subtable needs its
	 * whole context - which lookup, which glyph, how deep the nesting is - and naming those is still
	 * plainer to read than threading one state array through and indexing it on every line.
	 *
	 * Lookup type 7, Extension, never arrives here: _getGSUBtables() resolves it at font-build time
	 * into the type and offset it points at.
	 *
	 * A subtable applies only to a glyph in its own Coverage, and the structures below index that
	 * Coverage by the glyph with no check of their own. A glyph arriving from a matched context has
	 * been tested against the context's Coverages and not against this subtable's, so the test belongs
	 * here rather than only at the callers.
	 *
	 * The loops that apply a lookup from the top of the list keep their own copy of it. That is not
	 * redundant: theirs stands ahead of the call, and on a run through Arabic turns away 88,700 of
	 * 90,000 glyphs - leaning on this guard alone measured 15% slower end to end.
	 *
	 * Whether a subtable applied and how far the cursor then moves are separate facts, and separate
	 * values: null where it did not apply, and otherwise the advance. The advance is not always
	 * positive - a Multiple Substitution to the empty sequence, which is how a font deletes a glyph,
	 * applies and moves the cursor by nothing, and one number cannot carry that and "did not apply" at
	 * once. Zero only ever follows glyphs being taken out, so the loops that add it to a cursor still
	 * reach the end of the string.
	 *
	 * @see https://learn.microsoft.com/en-us/typography/opentype/spec/gsub
	 *
	 * @return int|null Glyphs to advance by, null if the subtable did not apply
	 */
	function _applyGSUBsubtable($lookupID, $subtable, $ptr, $currGlyph, $currGID, $subtable_offset, $Type, $Flag, $MarkFilteringSet, $LuCoverage, $level, $currentTag, $is_old_spec, $tagInt)
	{
		if (!isset($LuCoverage[$currGID])) {
			return null;
		}

		$ignore = $this->getGCOMignoreSet($Flag, $MarkFilteringSet);

		$this->reader->seek($subtable_offset);
		$SubstFormat = $this->reader->readUInt16();

		switch ($Type) {
			case 1:
				return $this->_applyGSUBsingleSubst($lookupID, $subtable, $ptr, $currGlyph, $currGID, $subtable_offset, $Type, $Flag, $MarkFilteringSet, $LuCoverage, $level, $SubstFormat);

			case 2:
				return $this->_applyGSUBmultipleSubst($lookupID, $subtable, $ptr, $currGlyph, $currGID, $subtable_offset, $Type, $Flag, $MarkFilteringSet, $LuCoverage, $level, $SubstFormat);

			case 3:
				return $this->_applyGSUBalternateSubst($lookupID, $subtable, $ptr, $currGlyph, $currGID, $subtable_offset, $Type, $Flag, $MarkFilteringSet, $LuCoverage, $level, $tagInt, $SubstFormat);

			case 4:
				return $this->_applyGSUBligatureSubst($lookupID, $subtable, $ptr, $currGlyph, $currGID, $subtable_offset, $Type, $Flag, $MarkFilteringSet, $LuCoverage, $level, $ignore, $SubstFormat);

			case 5:
				switch ($SubstFormat) {
					case 1:
						return $this->_applyGSUBcontextSubstFormat1($lookupID, $subtable, $ptr, $currGlyph, $currGID, $subtable_offset, $Type, $LuCoverage, $level, $currentTag, $is_old_spec, $tagInt, $ignore, $SubstFormat);
					case 2:
						return $this->_applyGSUBcontextSubstFormat2($lookupID, $subtable, $ptr, $currGlyph, $currGID, $subtable_offset, $Type, $level, $currentTag, $is_old_spec, $tagInt, $ignore, $SubstFormat);
					case 3:
						return $this->_applyGSUBcontextSubstFormat3($lookupID, $subtable, $ptr, $currGlyph, $subtable_offset, $Type, $level, $currentTag, $is_old_spec, $tagInt, $ignore, $SubstFormat);
				}

				// A format this code does not know is skipped rather than refused, which is what the
				// if/elseif chain this replaced did by running off its end. GPOS throws instead.
				return null;

			case 6:
				switch ($SubstFormat) {
					case 1:
						return $this->_applyGSUBchainContextSubstFormat1($lookupID, $subtable, $ptr, $currGlyph, $currGID, $subtable_offset, $Type, $LuCoverage, $level, $currentTag, $is_old_spec, $tagInt, $ignore, $SubstFormat);
					case 2:
						return $this->_applyGSUBchainContextSubstFormat2($lookupID, $subtable, $ptr, $currGlyph, $currGID, $subtable_offset, $Type, $level, $currentTag, $is_old_spec, $tagInt, $ignore, $SubstFormat);
					case 3:
						return $this->_applyGSUBchainContextSubstFormat3($lookupID, $subtable, $ptr, $currGlyph, $subtable_offset, $Type, $level, $currentTag, $is_old_spec, $tagInt, $ignore, $SubstFormat);
				}

				return null;

			case 8:
				return $this->_applyGSUBreverseChainSingleSubst($lookupID, $subtable, $ptr, $currGlyph, $currGID, $subtable_offset, $Type, $Flag, $MarkFilteringSet, $LuCoverage, $level, $ignore, $SubstFormat);
		}

		throw new \Mpdf\MpdfException(sprintf('GSUB Lookup Type %s is not supported', $Type));
	}

	/**
	 * LookupType 1: Single Substitution
	 *
	 * One glyph for one glyph. Format 1 adds a delta to the glyph ID; format 2 names the replacement
	 * outright, indexed by the input glyph's Coverage Index.
	 *
	 * @see https://learn.microsoft.com/en-us/typography/opentype/spec/gsub#lookuptype-1-single-substitution-subtable
	 *
	 * @return int|null Glyphs to advance by, null if the subtable did not apply
	 */
	private function _applyGSUBsingleSubst($lookupID, $subtable, $ptr, $currGlyph, $currGID, $subtable_offset, $Type, $Flag, $MarkFilteringSet, $LuCoverage, $level, $SubstFormat)
	{
		// Flag = Ignore
		if ($this->lookupFlag->skips($Flag, $currGlyph, $MarkFilteringSet)) {
			return null;
		}
		$CoverageOffset = $subtable_offset + $this->reader->readUInt16();
		$GlyphPos = $LuCoverage[$currGID];
		// Format 1:
		if ($SubstFormat == 1) { // Calculated output glyph indices
			$DeltaGlyphID = $this->reader->readInt16();
			$this->reader->seek($CoverageOffset);
			$glyphs = $this->_getCoverageGID();
			// The modulo is how a font names a glyph below the one it covers, or above the end of the
			// range: Chiron Hei HK's 'hist' reaches glyph 1688 with 15324 from glyph 51900
			$GlyphID = ($glyphs[$GlyphPos] + $DeltaGlyphID) & 0xFFFF;
		}
		// Format 2:
		elseif ($SubstFormat == 2) { // Specified output glyph indices
			$GlyphCount = $this->reader->readUInt16();
			$this->reader->skip($GlyphPos * 2);
			$GlyphID = $this->reader->readUInt16();
		}

		$substitute = $this->glyphToChar($GlyphID);
		$this->GSUBsubstitute($ptr, $substitute, $Type);
		if ($this->debugOTL) {
			echo OtlDump::shapingStep($this->OTLdata, 'GSUB', $lookupID, $subtable, $Type, $SubstFormat, $ptr, $currGlyph, $level);
		}

		return 1;
	}

	/**
	 * LookupType 2: Multiple Substitution
	 *
	 * One glyph for a sequence of them, as when a precomposed character is decomposed for shaping.
	 *
	 * The sequence may be empty, which is how a font deletes a glyph. That applies like any other
	 * sequence and leaves the cursor where it is, since what stood after the glyph now stands on it.
	 *
	 * @see https://learn.microsoft.com/en-us/typography/opentype/spec/gsub#lookuptype-2-multiple-substitution-subtable
	 *
	 * @return int|null Glyphs to advance by, null if the subtable did not apply
	 */
	private function _applyGSUBmultipleSubst($lookupID, $subtable, $ptr, $currGlyph, $currGID, $subtable_offset, $Type, $Flag, $MarkFilteringSet, $LuCoverage, $level, $SubstFormat)
	{
		// Flag = Ignore
		if ($this->lookupFlag->skips($Flag, $currGlyph, $MarkFilteringSet)) {
			return null;
		}
		$Coverage = $subtable_offset + $this->reader->readUInt16();
		$GlyphPos = $LuCoverage[$currGID];
		$this->reader->skip(2);
		$this->reader->skip($GlyphPos * 2);
		$Sequences = $subtable_offset + $this->reader->readUInt16();

		$this->reader->seek($Sequences);
		$GlyphCount = $this->reader->readUInt16();
		$SubstituteGlyphs = [];
		for ($g = 0; $g < $GlyphCount; $g++) {
			$sgid = $this->reader->readUInt16();
			$SubstituteGlyphs[] = $this->glyphToChar($sgid);
		}

		// What it puts there is what the cursor moves by, which for the empty sequence is nothing
		$shift = $this->GSUBsubstitute($ptr, $SubstituteGlyphs, $Type);
		if ($this->debugOTL) {
			echo OtlDump::shapingStep($this->OTLdata, 'GSUB', $lookupID, $subtable, $Type, $SubstFormat, $ptr, $currGlyph, $level);
		}

		return $shift;
	}

	/**
	 * LookupType 3: Alternate Substitution
	 *
	 * A choice of glyphs for one glyph. Which alternate is taken comes from the feature's own index, so
	 * this is the one lookup type whose result depends on how the feature was asked for.
	 *
	 * @see https://learn.microsoft.com/en-us/typography/opentype/spec/gsub#lookuptype-3-alternate-substitution-subtable
	 *
	 * @return int|null Glyphs to advance by, null if the subtable did not apply
	 */
	private function _applyGSUBalternateSubst($lookupID, $subtable, $ptr, $currGlyph, $currGID, $subtable_offset, $Type, $Flag, $MarkFilteringSet, $LuCoverage, $level, $tagInt, $SubstFormat)
	{
		// Flag = Ignore
		if ($this->lookupFlag->skips($Flag, $currGlyph, $MarkFilteringSet)) {
			return null;
		}
		$Coverage = $subtable_offset + $this->reader->readUInt16();
		$AlternateSetCount = $this->reader->readUInt16();
		// Need to set alternate IF set by CSS3 font-feature for a tag
		// i.e. if this is 'salt' alternate may be set to 2
		// default value will be $alt=1 ( === index of 0 in list of alternates)
		$alt = 1; // $alt=1 points to Alternative[0]
		if ($tagInt > 1) {
			$alt = $tagInt;
		}
		if ($alt == 0) {
			return null;
		} // If specified alternate not present, cancel [ or could default $alt = 1 ?]

		$GlyphPos = $LuCoverage[$currGID];
		$this->reader->skip($GlyphPos * 2);

		$AlternateSets = $subtable_offset + $this->reader->readUInt16();
		$this->reader->seek($AlternateSets);

		$AlternateGlyphCount = $this->reader->readUInt16();
		if ($alt > $AlternateGlyphCount) {
			return null;
		} // If specified alternate not present, cancel [ or could default $alt = 1 ?]

		$this->reader->skip(($alt - 1) * 2);
		$GlyphID = $this->reader->readUInt16();

		$substitute = $this->glyphToChar($GlyphID);
		$this->GSUBsubstitute($ptr, $substitute, $Type);
		if ($this->debugOTL) {
			echo OtlDump::shapingStep($this->OTLdata, 'GSUB', $lookupID, $subtable, $Type, $SubstFormat, $ptr, $currGlyph, $level);
		}

		return 1;
	}

	/**
	 * LookupType 4: Ligature Substitution
	 *
	 * A sequence of glyphs for one glyph. The components are recorded against the ligature so that marks
	 * attached to any of them can still be positioned afterwards.
	 *
	 * @see https://learn.microsoft.com/en-us/typography/opentype/spec/gsub#lookuptype-4-ligature-substitution-subtable
	 *
	 * @return int|null Glyphs to advance by, null if the subtable did not apply
	 */
	private function _applyGSUBligatureSubst($lookupID, $subtable, $ptr, $currGlyph, $currGID, $subtable_offset, $Type, $Flag, $MarkFilteringSet, $LuCoverage, $level, $ignore, $SubstFormat)
	{
		// Flag = Ignore
		if ($this->lookupFlag->skips($Flag, $currGlyph, $MarkFilteringSet)) {
			return null;
		}
		$Coverage = $subtable_offset + $this->reader->readUInt16();
		$FirstGlyphPos = $LuCoverage[$currGID];

		$LigSetCount = $this->reader->readUInt16();

		$this->reader->skip($FirstGlyphPos * 2);
		$LigSet = $subtable_offset + $this->reader->readUInt16();

		$this->reader->seek($LigSet);
		$LigCount = $this->reader->readUInt16();
		// LigatureSet i.e. all starting with the same first Glyph $currGlyph
		$LigatureOffset = [];
		for ($g = 0; $g < $LigCount; $g++) {
			$LigatureOffset[$g] = $LigSet + $this->reader->readUInt16();
		}
		for ($g = 0; $g < $LigCount; $g++) {
			// Ligature tables
			$this->reader->seek($LigatureOffset[$g]);
			$LigGlyph = $this->reader->readUInt16(); // Output Ligature GlyphID
			$substitute = $this->glyphToChar($LigGlyph);
			$CompCount = $this->reader->readUInt16();

			$spos = $ptr;
			$match = true;
			$GlyphPos = [];
			$GlyphPos[] = $spos;
			for ($l = 1; $l < $CompCount; $l++) {
				$gid = $this->reader->readUInt16();
				$checkGlyph = $this->glyphToChar($gid); // Other component/input Glyphs starting at position 2 (arrayindex 1)

				$spos++;
				//while $this->OTLdata[$spos]['uni'] is an "ignore" =>  spos++
				while (isset($this->OTLdata[$spos]) && isset($ignore[$this->OTLdata[$spos]['uni']])) {
					$spos++;
				}

				if (isset($this->OTLdata[$spos]) && $this->OTLdata[$spos]['uni'] == $checkGlyph) {
					$GlyphPos[] = $spos;
				} else {
					$match = false;
					break;
				}
			}

			if ($match) {
				$shift = $this->GSUBsubstitute($ptr, $substitute, $Type, $GlyphPos); // GlyphPos contains positions to set null
				if ($this->debugOTL && $shift) {
					echo OtlDump::shapingStep($this->OTLdata, 'GSUB', $lookupID, $subtable, $Type, $SubstFormat, $ptr, $currGlyph, $level);
				}
				if ($shift) {
					return ($spos - $ptr + 1 - ($CompCount - 1));
				}
			}
		}

		return null;
	}

	/**
	 * LookupType 5, Format 1: Context Substitution by glyph
	 *
	 * Rules listing the glyphs that must follow, grouped by the first glyph of the context.
	 *
	 * @see https://learn.microsoft.com/en-us/typography/opentype/spec/gsub#51-context-substitution-format-1-simple-glyph-contexts
	 *
	 * @return int|null Glyphs to advance by, null if the subtable did not apply
	 */
	private function _applyGSUBcontextSubstFormat1($lookupID, $subtable, $ptr, $currGlyph, $currGID, $subtable_offset, $Type, $LuCoverage, $level, $currentTag, $is_old_spec, $tagInt, $ignore, $SubstFormat)
	{
		$CoverageTableOffset = $subtable_offset + $this->reader->readUInt16();
		$SubRuleSetCount = $this->reader->readUInt16();
		$SubRuleSetOffset = [];
		for ($b = 0; $b < $SubRuleSetCount; $b++) {
			$offset = $this->reader->readUInt16();
			if ($offset == 0x0000) {
				$SubRuleSetOffset[] = $offset;
			} else {
				$SubRuleSetOffset[] = $subtable_offset + $offset;
			}
		}

		// SubRuleSet tables: All contexts beginning with the same glyph
		// Select the SubRuleSet required using the position of the glyph in the coverage table
		$GlyphPos = $LuCoverage[$currGID];
		if ($SubRuleSetOffset[$GlyphPos] > 0) {
			$this->reader->seek($SubRuleSetOffset[$GlyphPos]);
			$SubRuleCnt = $this->reader->readUInt16();
			$SubRule = [];
			for ($b = 0; $b < $SubRuleCnt; $b++) {
				$SubRule[$b] = $SubRuleSetOffset[$GlyphPos] + $this->reader->readUInt16();
			}
			for ($b = 0; $b < $SubRuleCnt; $b++) {  // EACH RULE
				$this->reader->seek($SubRule[$b]);
				list($inputGlyphIDs, $SubstCount) = SequenceRule::plain($this->reader);

				// Position 0 is the glyph the Coverage table selected this rule set by
				$Input = array_merge([$this->OTLdata[$ptr]['uni']], $this->charsOf($inputGlyphIDs));

				// Type 5 is a plain context: it has no backtrack or lookahead sequence
				$matched = $this->checkContextMatch($Input, [], [], $ignore, $ptr);
				if ($matched) {
					if ($this->debugOTL) {
						echo OtlDump::shapingStep($this->OTLdata, 'GSUB', $lookupID, $subtable, $Type, $SubstFormat, $ptr, $currGlyph, $level);
					}
					return $this->_applyGSUBlookupRecords($SubstCount, $matched, $currentTag, $is_old_spec, $tagInt);
				}
			}
		}
		return null;
	}

	/**
	 * LookupType 5, Format 2: Context Substitution by class
	 *
	 * The same, matching glyph classes rather than individual glyphs, which is how one rule covers a
	 * whole category of glyph without listing it.
	 *
	 * @see https://learn.microsoft.com/en-us/typography/opentype/spec/gsub#52-context-substitution-format-2-class-based-glyph-contexts
	 *
	 * @return int|null Glyphs to advance by, null if the subtable did not apply
	 */
	private function _applyGSUBcontextSubstFormat2($lookupID, $subtable, $ptr, $currGlyph, $currGID, $subtable_offset, $Type, $level, $currentTag, $is_old_spec, $tagInt, $ignore, $SubstFormat)
	{
		$CoverageTableOffset = $subtable_offset + $this->reader->readUInt16();
		$InputClassDefOffset = $subtable_offset + $this->reader->readUInt16();
		$SubClassSetCnt = $this->reader->readUInt16();
		$SubClassSetOffset = [];
		for ($b = 0; $b < $SubClassSetCnt; $b++) {
			$offset = $this->reader->readUInt16();
			if ($offset == 0x0000) {
				$SubClassSetOffset[] = $offset;
			} else {
				$SubClassSetOffset[] = $subtable_offset + $offset;
			}
		}

		$InputClasses = $this->_getClasses($InputClassDefOffset);

		for ($s = 0; $s < $SubClassSetCnt; $s++) { // $SubClassSet is ordered by input class-may be NULL
			// Select $SubClassSet if currGlyph is in First Input Class
			if ($SubClassSetOffset[$s] > 0 && isset($InputClasses[$s][$currGID])) {
				$this->reader->seek($SubClassSetOffset[$s]);
				$SubClassRuleCnt = $this->reader->readUInt16();
				$SubClassRule = [];
				for ($b = 0; $b < $SubClassRuleCnt; $b++) {
					$SubClassRule[$b] = $SubClassSetOffset[$s] + $this->reader->readUInt16();
				}

				for ($b = 0; $b < $SubClassRuleCnt; $b++) {  // EACH RULE
					$this->reader->seek($SubClassRule[$b]);
					list($inputClassIndices, $SubstCount) = SequenceRule::plain($this->reader);

					// The rule set array is indexed by the class of the first input glyph, so the loop
					// index over it is that class, and that class is position 0
					$inputGlyphs = array_merge([$InputClasses[$s]], $this->classSets($InputClasses, $inputClassIndices));

					// Class 0 contains all the glyphs NOT in the other classes
					$class0excl = $this->getClassZeroExclusions($InputClassDefOffset);

					$matched = $this->checkContextMatchMultiple($inputGlyphs, [], [], $ignore, $ptr, $class0excl);
					if ($matched) {
						if ($this->debugOTL) {
							echo OtlDump::shapingStep($this->OTLdata, 'GSUB', $lookupID, $subtable, $Type, $SubstFormat, $ptr, $currGlyph, $level);
						}
						return $this->_applyGSUBlookupRecords($SubstCount, $matched, $currentTag, $is_old_spec, $tagInt);
					}
				}
			}
		}

		return null;
	}

	/**
	 * LookupType 5, Format 3: Context Substitution by coverage
	 *
	 * One rule, with a Coverage table per input position rather than a list of rules.
	 *
	 * @see https://learn.microsoft.com/en-us/typography/opentype/spec/gsub#53-context-substitution-format-3-coverage-based-glyph-contexts
	 *
	 * @return int|null Glyphs to advance by, null if the subtable did not apply
	 */
	private function _applyGSUBcontextSubstFormat3($lookupID, $subtable, $ptr, $currGlyph, $subtable_offset, $Type, $level, $currentTag, $is_old_spec, $tagInt, $ignore, $SubstFormat)
	{
		// NB Unlike Lookup Type 6 Format 3, the count of substitutions precedes the Coverage table offsets
		$InputGlyphCount = $this->reader->readUInt16();
		$SubstCount = $this->reader->readUInt16();
		$CoverageInputOffset = SequenceRule::coverageOffsets($this->reader, $subtable_offset, $InputGlyphCount);
		$save_pos = $this->reader->tell(); // Save the point just after the Coverage table offsets

		$CoverageInputGlyphs = $this->coverageSets($CoverageInputOffset);

		// Type 5 is a plain context: it has no backtrack or lookahead sequence
		$matched = $this->checkContextMatchMultiple($CoverageInputGlyphs, [], [], $ignore, $ptr);
		if ($matched) {
			if ($this->debugOTL) {
				echo OtlDump::shapingStep($this->OTLdata, 'GSUB', $lookupID, $subtable, $Type, $SubstFormat, $ptr, $currGlyph, $level);
			}

			$this->reader->seek($save_pos); // Return to just after the Coverage table offsets
			return $this->_applyGSUBlookupRecords($SubstCount, $matched, $currentTag, $is_old_spec, $tagInt);
		}

		return null;
	}

	/**
	 * LookupType 6, Format 1: Chained Context Substitution by glyph
	 *
	 * As 5.1, with backtrack and lookahead sequences either side of the input.
	 *
	 * @see https://learn.microsoft.com/en-us/typography/opentype/spec/gsub#61-chained-contexts-substitution-format-1-simple-glyph-contexts
	 *
	 * @return int|null Glyphs to advance by, null if the subtable did not apply
	 */
	private function _applyGSUBchainContextSubstFormat1($lookupID, $subtable, $ptr, $currGlyph, $currGID, $subtable_offset, $Type, $LuCoverage, $level, $currentTag, $is_old_spec, $tagInt, $ignore, $SubstFormat)
	{
		$Coverage = $subtable_offset + $this->reader->readUInt16();
		$GlyphPos = $LuCoverage[$currGID];
		$ChainSubRuleSetCount = $this->reader->readUInt16();
		// All of the ChainSubRule tables defining contexts that begin with the same first glyph are grouped together and defined in a ChainSubRuleSet table
		$this->reader->skip($GlyphPos * 2);
		$ChainSubRuleSet = $subtable_offset + $this->reader->readUInt16();
		$this->reader->seek($ChainSubRuleSet);
		$ChainSubRuleCount = $this->reader->readUInt16();

		for ($s = 0; $s < $ChainSubRuleCount; $s++) {
			$ChainSubRule[$s] = $ChainSubRuleSet + $this->reader->readUInt16();
		}

		for ($s = 0; $s < $ChainSubRuleCount; $s++) {
			$this->reader->seek($ChainSubRule[$s]);
			list($backtrackGlyphIDs, $inputGlyphIDs, $lookaheadGlyphIDs) = SequenceRule::chained($this->reader);

			$Backtrack = $this->charsOf($backtrackGlyphIDs);
			// Position 0 is the glyph the Coverage table selected this rule set by
			$Input = array_merge([$this->OTLdata[$ptr]['uni']], $this->charsOf($inputGlyphIDs));
			$Lookahead = $this->charsOf($lookaheadGlyphIDs);

			$matched = $this->checkContextMatch($Input, $Backtrack, $Lookahead, $ignore, $ptr);
			if ($matched) {
				if ($this->debugOTL) {
					echo OtlDump::shapingStep($this->OTLdata, 'GSUB', $lookupID, $subtable, $Type, $SubstFormat, $ptr, $currGlyph, $level);
				}
				$SubstCount = $this->reader->readUInt16();
				return $this->_applyGSUBlookupRecords($SubstCount, $matched, $currentTag, $is_old_spec, $tagInt);
			}
		}
		return null;
	}

	/**
	 * LookupType 6, Format 2: Chained Context Substitution by class
	 *
	 * As 5.2, with backtrack and lookahead, each matched against its own class definition.
	 *
	 * @see https://learn.microsoft.com/en-us/typography/opentype/spec/gsub#62-chained-contexts-substitution-format-2-class-based-glyph-contexts
	 *
	 * @return int|null Glyphs to advance by, null if the subtable did not apply
	 */
	private function _applyGSUBchainContextSubstFormat2($lookupID, $subtable, $ptr, $currGlyph, $currGID, $subtable_offset, $Type, $level, $currentTag, $is_old_spec, $tagInt, $ignore, $SubstFormat)
	{
		// NB Format 2 specifies fixed class assignments (identical for each position in the backtrack, input, or lookahead sequence) and exclusive classes (a glyph cannot be in more than one class at a time)

		$CoverageTableOffset = $subtable_offset + $this->reader->readUInt16();
		$BacktrackClassDefOffset = $subtable_offset + $this->reader->readUInt16();
		$InputClassDefOffset = $subtable_offset + $this->reader->readUInt16();
		$LookaheadClassDefOffset = $subtable_offset + $this->reader->readUInt16();
		$ChainSubClassSetCnt = $this->reader->readUInt16();
		$ChainSubClassSetOffset = [];
		for ($b = 0; $b < $ChainSubClassSetCnt; $b++) {
			$offset = $this->reader->readUInt16();
			if ($offset == 0x0000) {
				$ChainSubClassSetOffset[] = $offset;
			} else {
				$ChainSubClassSetOffset[] = $subtable_offset + $offset;
			}
		}

		$BacktrackClasses = $this->_getClasses($BacktrackClassDefOffset);
		$InputClasses = $this->_getClasses($InputClassDefOffset);
		$LookaheadClasses = $this->_getClasses($LookaheadClassDefOffset);

		for ($s = 0; $s < $ChainSubClassSetCnt; $s++) { // $ChainSubClassSet is ordered by input class-may be NULL
			// Select $ChainSubClassSet if currGlyph is in First Input Class
			if ($ChainSubClassSetOffset[$s] > 0 && isset($InputClasses[$s][$currGID])) {
				$this->reader->seek($ChainSubClassSetOffset[$s]);
				$ChainSubClassRuleCnt = $this->reader->readUInt16();
				$ChainSubClassRule = [];
				for ($b = 0; $b < $ChainSubClassRuleCnt; $b++) {
					$ChainSubClassRule[$b] = $ChainSubClassSetOffset[$s] + $this->reader->readUInt16();
				}

				for ($b = 0; $b < $ChainSubClassRuleCnt; $b++) {  // EACH RULE
					$this->reader->seek($ChainSubClassRule[$b]);
					list($backtrackClassIndices, $inputClassIndices, $lookaheadClassIndices) = SequenceRule::chained($this->reader);

					// The rule set array is indexed by the class of the first input glyph, so the loop
					// index over it is that class, and that class is position 0
					$inputGlyphs = array_merge([$InputClasses[$s]], $this->classSets($InputClasses, $inputClassIndices));
					$backtrackGlyphs = $this->classSets($BacktrackClasses, $backtrackClassIndices);
					$lookaheadGlyphs = $this->classSets($LookaheadClasses, $lookaheadClassIndices);

					// Class 0 contains all the glyphs NOT in the other classes, one set per sequence
					$class0excl = $this->getClassZeroExclusions($InputClassDefOffset);
					$bclass0excl = $this->getClassZeroExclusions($BacktrackClassDefOffset);
					$lclass0excl = $this->getClassZeroExclusions($LookaheadClassDefOffset);

					$matched = $this->checkContextMatchMultiple($inputGlyphs, $backtrackGlyphs, $lookaheadGlyphs, $ignore, $ptr, $class0excl, $bclass0excl, $lclass0excl);
					if ($matched) {
						if ($this->debugOTL) {
							echo OtlDump::shapingStep($this->OTLdata, 'GSUB', $lookupID, $subtable, $Type, $SubstFormat, $ptr, $currGlyph, $level);
						}
						$SubstCount = $this->reader->readUInt16();
						return $this->_applyGSUBlookupRecords($SubstCount, $matched, $currentTag, $is_old_spec, $tagInt);
					}
				}
			}
		}

		return null;
	}

	/**
	 * LookupType 6, Format 3: Chained Context Substitution by coverage
	 *
	 * As 5.3, with backtrack and lookahead, each a Coverage table per position.
	 *
	 * @see https://learn.microsoft.com/en-us/typography/opentype/spec/gsub#63-chained-contexts-substitution-format-3-coverage-based-glyph-contexts
	 *
	 * @return int|null Glyphs to advance by, null if the subtable did not apply
	 */
	private function _applyGSUBchainContextSubstFormat3($lookupID, $subtable, $ptr, $currGlyph, $subtable_offset, $Type, $level, $currentTag, $is_old_spec, $tagInt, $ignore, $SubstFormat)
	{
		// Each of the three sequences is a count and then one Coverage table offset per position.
		// NB Unlike Lookup Type 5 Format 3, the count of substitutions follows them rather than
		// preceding them.
		$CoverageBacktrackOffset = SequenceRule::coverageOffsets($this->reader, $subtable_offset, $this->reader->readUInt16());
		$CoverageInputOffset = SequenceRule::coverageOffsets($this->reader, $subtable_offset, $this->reader->readUInt16());
		$CoverageLookaheadOffset = SequenceRule::coverageOffsets($this->reader, $subtable_offset, $this->reader->readUInt16());
		$SubstCount = $this->reader->readUInt16();
		$save_pos = $this->reader->tell(); // Save the point just after SubstCount

		$CoverageBacktrackGlyphs = $this->coverageSets($CoverageBacktrackOffset);
		$CoverageInputGlyphs = $this->coverageSets($CoverageInputOffset);
		$CoverageLookaheadGlyphs = $this->coverageSets($CoverageLookaheadOffset);

		$matched = $this->checkContextMatchMultiple($CoverageInputGlyphs, $CoverageBacktrackGlyphs, $CoverageLookaheadGlyphs, $ignore, $ptr);
		if ($matched) {
			if ($this->debugOTL) {
				echo OtlDump::shapingStep($this->OTLdata, 'GSUB', $lookupID, $subtable, $Type, $SubstFormat, $ptr, $currGlyph, $level);
			}

			$this->reader->seek($save_pos); // Return to just after SubstCount
			return $this->_applyGSUBlookupRecords($SubstCount, $matched, $currentTag, $is_old_spec, $tagInt);
		}

		return null;
	}

	/**
	 * LookupType 8: Reverse Chaining Contextual Single Substitution
	 *
	 * The only lookup applied right to left, which is why the shaper walks the string backwards for it.
	 * Used for Nastaliq and for Arabic swash forms.
	 *
	 * @see https://learn.microsoft.com/en-us/typography/opentype/spec/gsub#lookuptype-8-reverse-chaining-contextual-single-substitution-subtable
	 *
	 * @return int|null Glyphs to advance by, null if the subtable did not apply
	 */
	private function _applyGSUBreverseChainSingleSubst($lookupID, $subtable, $ptr, $currGlyph, $currGID, $subtable_offset, $Type, $Flag, $MarkFilteringSet, $LuCoverage, $level, $ignore, $SubstFormat)
	{
		// Flag = Ignore
		if ($this->lookupFlag->skips($Flag, $currGlyph, $MarkFilteringSet)) {
			return null;
		}
		// Format 1 is the only one the specification defines
		if ($SubstFormat != 1) {
			throw new \Mpdf\MpdfException("GSUB Lookup Type " . $Type . ", Format " . $SubstFormat . " not supported.");
		}

		$CoverageTableOffset = $subtable_offset + $this->reader->readUInt16();
		$BacktrackGlyphCount = $this->reader->readUInt16();
		$CoverageBacktrackOffset = [];
		for ($b = 0; $b < $BacktrackGlyphCount; $b++) {
			$CoverageBacktrackOffset[] = $subtable_offset + $this->reader->readUInt16(); // in glyph sequence order
		}
		$LookaheadGlyphCount = $this->reader->readUInt16();
		$CoverageLookaheadOffset = [];
		for ($b = 0; $b < $LookaheadGlyphCount; $b++) {
			$CoverageLookaheadOffset[] = $subtable_offset + $this->reader->readUInt16(); // in glyph sequence order
		}
		// The substitute glyphs run parallel to the input Coverage table
		$GlyphCount = $this->reader->readUInt16();
		$save_pos = $this->reader->tell(); // Save the point just after GlyphCount
		$GlyphPos = $LuCoverage[$currGID];
		if ($GlyphPos >= $GlyphCount) {
			return null;
		}

		$CoverageBacktrackGlyphs = [];
		for ($b = 0; $b < $BacktrackGlyphCount; $b++) {
			$this->reader->seek($CoverageBacktrackOffset[$b]);
			$CoverageBacktrackGlyphs[$b] = $this->getCoverageUni();
		}
		$CoverageLookaheadGlyphs = [];
		for ($b = 0; $b < $LookaheadGlyphCount; $b++) {
			$this->reader->seek($CoverageLookaheadOffset[$b]);
			$CoverageLookaheadGlyphs[$b] = $this->getCoverageUni();
		}

		// The input sequence is the one glyph at $ptr, which the caller has already matched against
		// the input Coverage table, so only the backtrack and lookahead sequences are left to check
		if (!$this->checkContextMatchMultiple([[$currGID => 1]], $CoverageBacktrackGlyphs, $CoverageLookaheadGlyphs, $ignore, $ptr)) {
			return null;
		}

		$this->reader->seek($save_pos + (2 * $GlyphPos));
		$substitute = $this->glyphToChar($this->reader->readUInt16());

		$this->GSUBsubstitute($ptr, $substitute, $Type);
		if ($this->debugOTL) {
			echo OtlDump::shapingStep($this->OTLdata, 'GSUB', $lookupID, $subtable, $Type, $SubstFormat, $ptr, $currGlyph, $level);
		}

		return 1;
	}

	/**
	 * Move the recorded ligature and mark attachments up, after a substitution made the text longer.
	 *
	 * The attachments are held by position, so anything after the substitution has to be renumbered
	 * or a mark would come out attached to the wrong base.
	 *
	 * @param int $pos Where the substitution happened
	 * @param int $n   How many positions the text grew by
	 */
	function _updateLigatureMarks($pos, $n)
	{
		// Every renumbering below is guarded by one of these two, so with neither recorded the loops
		// walk the whole run to do nothing. A font that writes its joining forms as several glyphs
		// substitutes once per letter, which makes that walk quadratic in the length of the run.
		if (!$this->assocLigs && !$this->assocMarks) {
			return;
		}

		if ($n > 0) {
			// Update position of Ligatures and associated Marks
			// Foreach lig/assocMarks
			// Any position lpos or mpos > $pos + count($substitute)
			//  $this->assocMarks = array();    // assocMarks[$pos mpos] => array(compID, ligPos)
			//  $this->assocLigs = array(); // Ligatures[$pos lpos] => nc
			for ($p = count($this->OTLdata) - 1; $p >= ($pos + $n); $p--) {
				if (isset($this->assocLigs[$p])) {
					$tmp = $this->assocLigs[$p];
					unset($this->assocLigs[$p]);
					$this->assocLigs[($p + $n)] = $tmp;
				}
			}
			for ($p = count($this->OTLdata) - 1; $p >= 0; $p--) {
				if (isset($this->assocMarks[$p])) {
					if ($this->assocMarks[$p]['ligPos'] >= ($pos + $n)) {
						$this->assocMarks[$p]['ligPos'] += $n;
					}
					if ($p >= ($pos + $n)) {
						$tmp = $this->assocMarks[$p];
						unset($this->assocMarks[$p]);
						$this->assocMarks[($p + $n)] = $tmp;
					}
				}
			}
		} elseif ($n < 1) { // glyphs removed
			$nrem = -$n;
			// Update position of pre-existing Ligatures and associated Marks
			for ($p = ($pos + 1); $p < count($this->OTLdata); $p++) {
				if (isset($this->assocLigs[$p])) {
					$tmp = $this->assocLigs[$p];
					unset($this->assocLigs[$p]);
					$this->assocLigs[($p - $nrem)] = $tmp;
				}
			}
			for ($p = 0; $p < count($this->OTLdata); $p++) {
				if (isset($this->assocMarks[$p])) {
					if ($this->assocMarks[$p]['ligPos'] >= ($pos)) {
						$this->assocMarks[$p]['ligPos'] -= $nrem;
					}
					if ($p > $pos) {
						$tmp = $this->assocMarks[$p];
						unset($this->assocMarks[$p]);
						$this->assocMarks[($p - $nrem)] = $tmp;
					}
				}
			}
		}
	}

	/**
	 * Put the substitute glyphs in place of the ones at $pos, and carry the ligature and mark
	 * bookkeeping over the change in length.
	 *
	 * The number returned is how many glyphs now stand where the substitution was made - one for the
	 * 1:1 types, the length of the sequence for a Multiple Substitution, which is 0 where a font
	 * deletes a glyph by substituting the empty one. The exception is a Ligature Substitution, which
	 * returns 0 for a ligature it refused to form because its components crossed a syllable, so read
	 * that one as a yes or no rather than as a count.
	 *
	 * @return int
	 */
	function GSUBsubstitute($pos, $substitute, $Type, $GlyphPos = null)
	{

		// LookupType 1: Simple Substitution Subtable : 1 to 1
		// LookupType 3: Alternate Forms : 1 to 1(n)
		// LookupType 8: Reverse Chaining Contextual Single Substitution : 1 to 1
		if ($Type == 1 || $Type == 3 || $Type == 8) {
			$this->OTLdata[$pos]['uni'] = $substitute;
			$this->OTLdata[$pos]['hex'] = GlyphString::of($substitute);
			return 1;
		} // LookupType 2: Multiple Substitution Subtable : 1 to n
		elseif ($Type == 2) {
			// A font writes a deletion as a substitution to the empty sequence, and the splice below
			// then has nothing to put in the glyph's place
			$newOTLdata = [];
			for ($i = 0; $i < count($substitute); $i++) {
				$uni = $substitute[$i];
				$newOTLdata[$i] = [];
				$newOTLdata[$i]['uni'] = $uni;
				$newOTLdata[$i]['hex'] = GlyphString::of($uni);

				// Get types of new inserted chars - or replicate type of char being replaced
				//  $bt = Ucdn::get_bidi_class($uni);
				//  if (!$bt) {
				$bt = $this->OTLdata[$pos]['bidi_type'];
				//  }

				if ($this->isMark($newOTLdata[$i]['hex'])) {
					$gp = 'M';
				} elseif ($uni == 32) {
					$gp = 'S';
				} else {
					$gp = 'C';
				}

				// A glyph inserted by a substitution keeps no matra_type of its own, so a later Indic
				// reordering pass cannot see what it is. Not known to bite, but not known to be safe.

				$newOTLdata[$i]['bidi_type'] = $bt;
				$newOTLdata[$i]['group'] = $gp;

				// Need to update details of new glyphs inserted
				$newOTLdata[$i]['general_category'] = $this->OTLdata[$pos]['general_category'];

				if ($this->shaper == 'I' || $this->shaper == 'K' || $this->shaper == 'S') {
					$newOTLdata[$i]['indic_category'] = $this->OTLdata[$pos]['indic_category'];
					$newOTLdata[$i]['indic_position'] = $this->OTLdata[$pos]['indic_position'];
				} elseif ($this->shaper == 'M') {
					$newOTLdata[$i]['myanmar_category'] = $this->OTLdata[$pos]['myanmar_category'];
					$newOTLdata[$i]['myanmar_position'] = $this->OTLdata[$pos]['myanmar_position'];
				} elseif ($this->shaper == 'E') {
					$newOTLdata[$i]['sea_category'] = $this->OTLdata[$pos]['sea_category'];
					$newOTLdata[$i]['sea_position'] = $this->OTLdata[$pos]['sea_position'];
				}
				if (isset($this->OTLdata[$pos]['mask'])) {
					$newOTLdata[$i]['mask'] = $this->OTLdata[$pos]['mask'];
				}
				if (isset($this->OTLdata[$pos]['syllable'])) {
					$newOTLdata[$i]['syllable'] = $this->OTLdata[$pos]['syllable'];
				}
				// Only the Arabic and Syriac runs carry a form, and a glyph expanded out of one is
				// still in it - a medial form written as a base and its dots is medial throughout
				if (isset($this->OTLdata[$pos]['form'])) {
					$newOTLdata[$i]['form'] = $this->OTLdata[$pos]['form'];
				}
				// The same of the form the character's joining calls for, which is read before 'ccmp'
				// runs and so has to survive 'ccmp' taking a letter apart into its base and its dots
				if (isset($this->OTLdata[$pos]['joining'])) {
					$newOTLdata[$i]['joining'] = $this->OTLdata[$pos]['joining'];
				}
			}
			if ($newOTLdata && ($this->shaper == 'K' || $this->shaper == 'T' || $this->shaper == 'L')) {
				if ($this->OTLdata[$pos]['wordend']) {
					$newOTLdata[count($newOTLdata) - 1]['wordend'] = true;
				}
			}

			array_splice($this->OTLdata, $pos, 1, $newOTLdata); // Replace 1 with n
			// Update position of Ligatures and associated Marks
			// count($substitute)-1  is the number of glyphs added
			$nadd = count($substitute) - 1;
			$this->_updateLigatureMarks($pos, $nadd);
			return count($substitute);
		} // LookupType 4: Ligature Substitution Subtable : n to 1
		elseif ($Type == 4) {
			// Create Ligatures and associated Marks
			$firstGlyph = $this->OTLdata[$pos]['hex'];

			// If all components of the ligature are marks (and in the same syllable), we call this a mark ligature.
			$contains_marks = false;
			$contains_nonmarks = false;
			if (isset($this->OTLdata[$pos]['syllable'])) {
				$current_syllable = $this->OTLdata[$pos]['syllable'];
			} else {
				$current_syllable = 0;
			}
			for ($i = 0; $i < count($GlyphPos); $i++) {
				// If subsequent components are not Marks as well - don't ligate
				$unistr = $this->OTLdata[$GlyphPos[$i]]['hex'];
				if ($this->restrictToSyllable && isset($this->OTLdata[$GlyphPos[$i]]['syllable']) && $this->OTLdata[$GlyphPos[$i]]['syllable'] != $current_syllable) {
					return 0;
				}
				if ($this->isMark($unistr)) {
					$contains_marks = true;
				} else {
					$contains_nonmarks = true;
				}
			}
			if ($contains_marks && !$contains_nonmarks) {
				// Mark Ligature (all components are Marks)
				$firstMarkAssoc = '';
				if (isset($this->assocMarks[$pos])) {
					$firstMarkAssoc = $this->assocMarks[$pos];
				}
				// If all components of the ligature are marks, we call this a mark ligature.
				for ($i = 1; $i < count($GlyphPos); $i++) {
					// If subsequent components are not Marks as well - don't ligate
					//      $unistr = $this->OTLdata[$GlyphPos[$i]]['hex'];
					//      if (strpos($this->GlyphClassMarks, $unistr )===false) { return; }

					$nextMarkAssoc = '';
					if (isset($this->assocMarks[$GlyphPos[$i]])) {
						$nextMarkAssoc = $this->assocMarks[$GlyphPos[$i]];
					}
					// If first component was attached to a previous ligature component,
					// all subsequent components should be attached to the same ligature
					// component, otherwise we shouldn't ligate them.
					// If first component was NOT attached to a previous ligature component,
					// all subsequent components should also NOT be attached to any ligature component,
					if ($firstMarkAssoc != $nextMarkAssoc) {
						// unless they are attached to the first component itself!
						//          if (!is_array($nextMarkAssoc) || $nextMarkAssoc['ligPos']!= $pos) { return; }
						// Update/Edit - In test with myanmartext font
						// &#x1004;&#x103a;&#x1039;&#x1000;&#x1039;&#x1000;&#x103b;&#x103c;&#x103d;&#x1031;&#x102d;
						// => Lookup 17  E003 E066B E05A 102D
						// E003 and 102D should form a mark ligature, but 102D is already associated with (non-mark) ligature E05A
						// So instead of disallowing the mark ligature to form, just dissociate...
						if (!is_array($nextMarkAssoc) || $nextMarkAssoc['ligPos'] != $pos) {
							unset($this->assocMarks[$GlyphPos[$i]]);
						}
					}
				}

				/*
				 * - If it *is* a mark ligature, we don't allocate a new ligature id, and leave
				 *   the ligature to keep its old ligature id.  This will allow it to attach to
				 *   a base ligature in GPOS.  Eg. if the sequence is: LAM,LAM,SHADDA,FATHA,HEH,
				 *   and LAM,LAM,HEH form a ligature, they will leave SHADDA and FATHA wit a
				 *   ligature id and component value of 2.  Then if SHADDA,FATHA form a ligature
				 *   later, we don't want them to lose their ligature id/component, otherwise
				 *   GPOS will fail to correctly position the mark ligature on top of the
				 *   LAM,LAM,HEH ligature.
				 */
				// So if is_array($firstMarkAssoc) - the new (Mark) ligature should keep this association

				$lastPos = $GlyphPos[(count($GlyphPos) - 1)];
			} else {
				/*
				 * - Ligatures cannot be formed across glyphs attached to different components
				 *   of previous ligatures.  Eg. the sequence is LAM,SHADDA,LAM,FATHA,HEH, and
				 *   LAM,LAM,HEH form a ligature, leaving SHADDA,FATHA next to eachother.
				 *   However, it would be wrong to ligate that SHADDA,FATHA sequence.
				 *   There is an exception to this: If a ligature tries ligating with marks that
				 *   belong to it itself, go ahead, assuming that the font designer knows what
				 *   they are doing (otherwise it can break Indic stuff when a matra wants to
				 *   ligate with a conjunct...)
				 */

				/*
				 * - If a ligature is formed of components that some of which are also ligatures
				 *   themselves, and those ligature components had marks attached to *their*
				 *   components, we have to attach the marks to the new ligature component
				 *   positions!  Now *that*'s tricky!  And these marks may be following the
				 *   last component of the whole sequence, so we should loop forward looking
				 *   for them and update them.
				 *
				 *   Eg. the sequence is LAM,LAM,SHADDA,FATHA,HEH, and the font first forms a
				 *   'calt' ligature of LAM,HEH, leaving the SHADDA and FATHA with a ligature
				 *   id and component == 1.  Now, during 'liga', the LAM and the LAM-HEH ligature
				 *   form a LAM-LAM-HEH ligature.  We need to reassign the SHADDA and FATHA to
				 *   the new ligature with a component value of 2.
				 *
				 *   This in fact happened to a font...  See:
				 *   https://bugzilla.gnome.org/show_bug.cgi?id=437633
				 */

				$currComp = 0;
				for ($i = 0; $i < count($GlyphPos); $i++) {
					if ($i > 0 && isset($this->assocLigs[$GlyphPos[$i]])) { // One of the other components is already a ligature
						$nc = $this->assocLigs[$GlyphPos[$i]];
					} else {
						$nc = 1;
					}
					// While next char to right is a mark (but not the next matched glyph)
					// ?? + also include a Mark Ligature here
					$ic = 1;
					while ((($i == count($GlyphPos) - 1) || (isset($GlyphPos[$i + 1]) && ($GlyphPos[$i] + $ic) < $GlyphPos[$i + 1])) && isset($this->OTLdata[($GlyphPos[$i] + $ic)]) && $this->isMark($this->OTLdata[($GlyphPos[$i] + $ic)]['hex'])) {
						$newComp = $currComp;
						if (isset($this->assocMarks[$GlyphPos[$i] + $ic])) { // One of the inbetween Marks is already associated with a Lig
							// OK as long as it is associated with the current Lig
							//      if ($this->assocMarks[($GlyphPos[$i]+$ic)]['ligPos'] != ($GlyphPos[$i]+$ic)) { die("Problem #1"); }
							$newComp += $this->assocMarks[($GlyphPos[$i] + $ic)]['compID'];
						}
						$this->assocMarks[($GlyphPos[$i] + $ic)] = ['compID' => $newComp, 'ligPos' => $pos];
						$ic++;
					}
					$currComp += $nc;
				}
				$lastPos = $GlyphPos[(count($GlyphPos) - 1)] + $ic - 1;
				$this->assocLigs[$pos] = $currComp; // Number of components in new Ligature
			}

			// Now remove the unwanted glyphs and associated metadata
			$newOTLdata[0] = [];

			// Get types of new inserted chars - or replicate type of char being replaced
			//  $bt = Ucdn::get_bidi_class($substitute);
			//  if (!$bt) {
			$bt = $this->OTLdata[$pos]['bidi_type'];
			//  }

			if ($this->isMark(GlyphString::of($substitute))) {
				$gp = 'M';
			} elseif ($substitute == 32) {
				$gp = 'S';
			} else {
				$gp = 'C';
			}

			// Need to update details of new glyphs inserted
			$newOTLdata[0]['general_category'] = $this->OTLdata[$pos]['general_category'];

			$newOTLdata[0]['bidi_type'] = $bt;
			$newOTLdata[0]['group'] = $gp;

			// KASHIDA: If forming a ligature when the last component was identified as a kashida point (final form)
			// If previous/first component of ligature is a medial form, then keep this as a kashida point
			// TEST (Arabic Typesetting) &#x64a;&#x64e;&#x646;&#x62a;&#x64f;&#x645;
			$ka = 0;
			if (isset($this->OTLdata[$GlyphPos[(count($GlyphPos) - 1)]]['GPOSinfo']['kashida'])) {
				$ka = $this->OTLdata[$GlyphPos[(count($GlyphPos) - 1)]]['GPOSinfo']['kashida'];
			}
			if ($ka == 1 && isset($this->OTLdata[$pos]['form']) && $this->OTLdata[$pos]['form'] == 3) {
				$newOTLdata[0]['GPOSinfo']['kashida'] = $ka;
			}

			$newOTLdata[0]['uni'] = $substitute;
			$newOTLdata[0]['hex'] = GlyphString::of($substitute);

			if ($this->shaper == 'I' || $this->shaper == 'K' || $this->shaper == 'S') {
				$newOTLdata[0]['indic_category'] = $this->OTLdata[$pos]['indic_category'];
				$newOTLdata[0]['indic_position'] = $this->OTLdata[$pos]['indic_position'];
			} elseif ($this->shaper == 'M') {
				$newOTLdata[0]['myanmar_category'] = $this->OTLdata[$pos]['myanmar_category'];
				$newOTLdata[0]['myanmar_position'] = $this->OTLdata[$pos]['myanmar_position'];
			} elseif ($this->shaper == 'E') {
				$newOTLdata[0]['sea_category'] = $this->OTLdata[$pos]['sea_category'];
				$newOTLdata[0]['sea_position'] = $this->OTLdata[$pos]['sea_position'];
			}
			if (isset($this->OTLdata[$pos]['mask'])) {
				$newOTLdata[0]['mask'] = $this->OTLdata[$pos]['mask'];
			}
			if (isset($this->OTLdata[$pos]['syllable'])) {
				$newOTLdata[0]['syllable'] = $this->OTLdata[$pos]['syllable'];
			}
			// A ligature stands where its first component stood, so it joins as that character did
			if (isset($this->OTLdata[$pos]['joining'])) {
				$newOTLdata[0]['joining'] = $this->OTLdata[$pos]['joining'];
			}

			$newOTLdata[0]['is_ligature'] = true;

			array_splice($this->OTLdata, $pos, 1, $newOTLdata);

			// GlyphPos contains array of arr_pos to set null - not necessarily contiguous
			// +- Remove any assocMarks or assocLigs from the main components (the ones that are deleted)
			for ($i = count($GlyphPos) - 1; $i > 0; $i--) {
				$gpos = $GlyphPos[$i];
				array_splice($this->OTLdata, $gpos, 1);
				unset($this->assocLigs[$gpos]);
				unset($this->assocMarks[$gpos]);
			}
			//  $this->assocLigs = array(); // Ligatures[$posarr lpos] => nc
			//  $this->assocMarks = array();    // assocMarks[$posarr mpos] => array(compID, ligPos)
			// Update position of pre-existing Ligatures and associated Marks
			// Start after first GlyphPos
			// count($GlyphPos)-1  is the number of glyphs removed from string
			for ($p = ($GlyphPos[0] + 1); $p < (count($this->OTLdata) + count($GlyphPos) - 1); $p++) {
				$nrem = 0; // Number of Glyphs removed at this point in the string
				for ($i = 0; $i < count($GlyphPos); $i++) {
					if ($i > 0 && $p > $GlyphPos[$i]) {
						$nrem++;
					}
				}
				if (isset($this->assocLigs[$p])) {
					$tmp = $this->assocLigs[$p];
					unset($this->assocLigs[$p]);
					$this->assocLigs[($p - $nrem)] = $tmp;
				}
				if (isset($this->assocMarks[$p])) {
					$tmp = $this->assocMarks[$p];
					unset($this->assocMarks[$p]);
					if ($tmp['ligPos'] > $GlyphPos[0]) {
						$tmp['ligPos'] -= $nrem;
					}
					$this->assocMarks[($p - $nrem)] = $tmp;
				}
			}
			return 1;
		} else {
			return 0;
		}
	}

	/**
	 * Point the reader at one of the two layout tables, loading its cached bytes if this is the first
	 * time this font has needed them.
	 *
	 * @param string $tag 'GSUB' or 'GPOS'
	 */
	private function readTable($tag)
	{
		$this->otlTable = $tag;
		$this->otlCacheKey = $this->fontkey . '/' . $tag;

		if (isset($this->readers[$this->fontkey][$tag])) {
			$this->reader = $this->readers[$this->fontkey][$tag];
			$this->reader->seek(0);

			return;
		}

		$filename = $this->fontkey . '.' . $tag . '.dat';
		$bytes = $this->fontCache->loadIfPresent($filename);

		if (!$bytes) {
			throw new \Mpdf\MpdfException(sprintf(
				'Cannot read the %s table cached at %s',
				$tag,
				$this->fontCache->tempFilename($filename)
			));
		}

		$this->reader = $this->readers[$this->fontkey][$tag] = new BlobReader($bytes);
	}

	/**
	 * What the parser derived from GDEF, GSUB or GPOS and cached whole, for a table this font has. It is
	 * written for every such table the parser reads, so a miss can only be the entry going between
	 * renders that share a tempDir - unlike the per-script entry loadGsubDerivedData() reads, which the
	 * parser writes only where the script has anything to state and whose miss is ordinary.
	 *
	 * Nothing here can derive it again - only re-parsing the font can - so the miss is raised rather than
	 * shaped around, which is what readTable() does with the table bytes these are derived from.
	 *
	 * @param string $filename
	 *
	 * @return array
	 */
	private function loadRequiredLayoutData($filename)
	{
		$data = $this->fontCache->jsonLoadIfPresent($filename);

		if (null === $data) {
			throw new \Mpdf\MpdfException(sprintf(
				'Cannot read the layout data cached at %s',
				$this->fontCache->tempFilename($filename)
			));
		}

		return $data;
	}

	/**
	 * The line-breaking dictionary a registered font package provides for the current shaper, read
	 * once per Otl instance because it runs to megabytes and applyOTL is called per text chunk.
	 *
	 * @return string|null null when no package supplies one, in which case mPDF falls back to its
	 *                     ordinary line breaking
	 */
	private function lineBreakDictionary()
	{
		if (!isset($this->lbdicts[$this->shaper])) {
			if (empty($this->mpdf->lineBreakDictionaries[$this->shaper])
				|| !file_exists($this->mpdf->lineBreakDictionaries[$this->shaper])) {
				return null;
			}

			$this->lbdicts[$this->shaper] = file_get_contents($this->mpdf->lineBreakDictionaries[$this->shaper]);
		}

		return $this->lbdicts[$this->shaper];
	}

	/**
	 * Apply a list of GPOS lookups, in the order the font's lookup list gives them.
	 *
	 * Each lookup is walked over the whole run, a glyph at a time. Unlike GSUB, nothing here changes
	 * what the glyphs are, only where they are drawn.
	 *
	 * @param array $LookupList  The lookups to apply, as lookupsInLookupListOrder() answers them
	 * @param bool  $is_old_spec Whether the font uses the original Indic script tags rather than the
	 *                           v2 ones
	 */
	private function _applyGPOSrules($LookupList, $is_old_spec = false)
	{
		foreach ($LookupList as $lu => $take) {
			$tag = $take['tag'];
			$Type = $this->GPOSLookups[$lu]['Type'];
			$Flag = $this->GPOSLookups[$lu]['Flag'];
			$MarkFilteringSet = '';
			if (isset($this->GPOSLookups[$lu]['MarkFilteringSet'])) {
				$MarkFilteringSet = $this->GPOSLookups[$lu]['MarkFilteringSet'];
			}
			$ptr = 0;
			// Test each glyph sequentially
			while ($ptr < (count($this->OTLdata))) { // whilst there is another glyph ..0064
				$currGlyph = $this->OTLdata[$ptr]['hex'];
				$currGID = $this->OTLdata[$ptr]['uni'];
				$shift = null;
				foreach ($this->GPOSLookups[$lu]['Subtables'] as $c => $subtable_offset) {
					// The Coverage read for this subtable is the one for input position 0, which is the only
					// position a match can start at - see where TTFontFile reads it
					if (isset($this->LuCoverage[$lu][$c][$currGID])) {
						// Get rules from font GPOS subtable
						if (isset($this->OTLdata[$ptr]['bidi_type'])) {  // No need to check bidi_type - just a check that it exists
							$shift = $this->_applyGPOSsubtable($lu, $c, $ptr, $currGlyph, $currGID, $subtable_offset, $Type, $Flag, $MarkFilteringSet, $this->LuCoverage[$lu][$c], $tag, 0, $is_old_spec);
							if ($shift !== null) {
								break;
							}
						}
					}
				}
				$ptr += $shift === null ? 1 : $shift; // null: nothing applied, so step on one glyph
			}
		}
	}

	/**
	 * Apply one value record: move a glyph, or change how far the pen advances past it.
	 *
	 * A mark with a width of its own has that width replaced by the advance rather than added to it,
	 * and a placement applies to the base and to every mark that follows it, so that a cluster moves
	 * together.
	 *
	 * @param int   $basepos The glyph the record was matched at
	 * @param array $Value   The record, as ValueRecord::read() read it
	 */
	private function _applyGPOSvaluerecord($basepos, $Value)
	{

		// If current glyph is a mark with a defined width, any XAdvance is considered to REPLACE the character Advance Width
		// Test case <div style="font-family:myanmartext">&#x1004;&#x103a;&#x1039;&#x1000;&#x1039;&#x1000;&#x103b;&#x103c;&#x103d;&#x1031;&#x102d;</div>
		if ($this->isMark($this->OTLdata[$basepos]['hex'])) {
			$cw = round($this->mpdf->_getCharWidth($this->mpdf->CurrentFont['cw'], $this->OTLdata[$basepos]['uni']) * $this->mpdf->CurrentFont['unitsPerEm'] / 1000); // convert back to font design units
		} else {
			$cw = 0;
		}

		$apos = $this->_getXAdvancePos($basepos);

		if (isset($Value['XAdvance']) && ($Value['XAdvance'] - $cw) != 0) {
			// However DON'T REPLACE the character Advance Width if Advance Width is negative
			// Test case <div style="font-family: dejavusansmono">&#x440;&#x443;&#x301;&#x441;&#x441;&#x43a;&#x438;&#x439;</div>
			if ($Value['XAdvance'] < 0) {
				$cw = 0;
			}

			// For LTR apply XAdvanceL to the last mark following the base = at $apos
			// For RTL apply XAdvanceR to base = at $basepos
			if (isset($this->OTLdata[$apos]['GPOSinfo']['XAdvanceL'])) {
				$this->OTLdata[$apos]['GPOSinfo']['XAdvanceL'] += $Value['XAdvance'] - $cw;
			} else {
				$this->OTLdata[$apos]['GPOSinfo']['XAdvanceL'] = $Value['XAdvance'] - $cw;
			}
			if (isset($this->OTLdata[$basepos]['GPOSinfo']['XAdvanceR'])) {
				$this->OTLdata[$basepos]['GPOSinfo']['XAdvanceR'] += $Value['XAdvance'] - $cw;
			} else {
				$this->OTLdata[$basepos]['GPOSinfo']['XAdvanceR'] = $Value['XAdvance'] - $cw;
			}
		}

		// Any XPlacement (? and Y Placement) apply to base and marks (from basepos to apos)
		for ($a = $basepos; $a <= $apos; $a++) {
			if (isset($Value['XPlacement'])) {
				if (isset($this->OTLdata[$a]['GPOSinfo']['XPlacement'])) {
					$this->OTLdata[$a]['GPOSinfo']['XPlacement'] += $Value['XPlacement'];
				} else {
					$this->OTLdata[$a]['GPOSinfo']['XPlacement'] = $Value['XPlacement'];
				}
			}
			if (isset($Value['YPlacement'])) {
				if (isset($this->OTLdata[$a]['GPOSinfo']['YPlacement'])) {
					$this->OTLdata[$a]['GPOSinfo']['YPlacement'] += $Value['YPlacement'];
				} else {
					$this->OTLdata[$a]['GPOSinfo']['YPlacement'] = $Value['YPlacement'];
				}
			}
		}
	}

	/**
	 * Where an advance has to be recorded for the PDF to draw it in the right place: on the last of
	 * any marks immediately following the glyph, rather than on the glyph itself.
	 *
	 * A mark is not moved past in this way - not every font lists every mark in GDEF, and a mark that
	 * reaches here is treated as standing on its own.
	 *
	 * @param int $pos The glyph the advance was matched at
	 *
	 * @return int The glyph to record it on
	 */
	private function _getXAdvancePos($pos)
	{
		// NB Not all fonts have all marks specified in GlyphClassMarks
		// If the current glyph is not a base (but a mark) then ignore this, and apply to the current position
		if ($this->isMark($this->OTLdata[$pos]['hex'])) {
			return $pos;
		}

		while (isset($this->OTLdata[$pos + 1]['hex']) && $this->isMark($this->OTLdata[$pos + 1]['hex'])) {
			$pos++;
		}
		return $pos;
	}

	/**
	 * Apply one GPOS subtable at one position in the string.
	 *
	 * One method per subtable structure below, named for the structure, so that each can be read
	 * against its own section of the spec. See _applyGSUBsubtable on the parameter lists.
	 *
	 * Lookup type 9, Extension, never arrives here: _getGPOStables() resolves it at font-build time
	 * into the type and offset it points at.
	 *
	 * The Coverage test is the same one _applyGSUBsubtable makes, and is there for the same reason -
	 * read it there.
	 *
	 * @see https://learn.microsoft.com/en-us/typography/opentype/spec/gpos
	 *
	 * @return int|null Glyphs to advance by, null if the subtable did not apply
	 */
	private function _applyGPOSsubtable($lookupID, $subtable, $ptr, $currGlyph, $currGID, $subtable_offset, $Type, $Flag, $MarkFilteringSet, $LuCoverage, $tag, $level, $is_old_spec)
	{
		if (!isset($LuCoverage[$currGID])) {
			return null;
		}

		// RIGHT_TO_LEFT. Only cursive attachment reads it.
		$dir = ($Flag & 0x0001) == 1 ? 'RTL' : 'LTR';

		$ignore = $this->getGCOMignoreSet($Flag, $MarkFilteringSet);

		$this->reader->seek($subtable_offset);
		$PosFormat = $this->reader->readUInt16();

		switch ($Type) {
			case 1:
				return $this->_applyGPOSsingleAdjustment($lookupID, $subtable, $ptr, $currGlyph, $currGID, $subtable_offset, $Type, $LuCoverage, $level, $PosFormat);

			case 2:
				return $this->_applyGPOSpairAdjustment($lookupID, $subtable, $ptr, $currGlyph, $currGID, $subtable_offset, $Type, $LuCoverage, $level, $ignore, $PosFormat);

			case 3:
				return $this->_applyGPOScursiveAttachment($lookupID, $subtable, $ptr, $currGlyph, $currGID, $subtable_offset, $Type, $LuCoverage, $level, $dir, $PosFormat);

			case 4:
				return $this->_applyGPOSmarkToBase($lookupID, $subtable, $ptr, $currGlyph, $currGID, $subtable_offset, $Type, $LuCoverage, $level, $is_old_spec, $PosFormat);

			case 5:
				return $this->_applyGPOSmarkToLigature($lookupID, $subtable, $ptr, $currGlyph, $currGID, $subtable_offset, $Type, $LuCoverage, $level, $PosFormat);

			case 6:
				return $this->_applyGPOSmarkToMark($lookupID, $subtable, $ptr, $currGlyph, $currGID, $subtable_offset, $Type, $LuCoverage, $level, $ignore, $PosFormat);

			case 7:
				switch ($PosFormat) {
					case 1:
						return $this->_applyGPOScontextPosFormat1($lookupID, $subtable, $ptr, $currGlyph, $currGID, $subtable_offset, $Type, $LuCoverage, $tag, $level, $is_old_spec, $ignore, $PosFormat);
					case 2:
						return $this->_applyGPOScontextPosFormat2($lookupID, $subtable, $ptr, $currGlyph, $currGID, $subtable_offset, $Type, $tag, $level, $is_old_spec, $ignore, $PosFormat);
					case 3:
						return $this->_applyGPOScontextPosFormat3($lookupID, $subtable, $ptr, $currGlyph, $subtable_offset, $Type, $tag, $level, $is_old_spec, $ignore, $PosFormat);
				}

				throw new \Mpdf\MpdfException(sprintf('GPOS Lookup Type %s, Format %s is not supported', $Type, $PosFormat));

			case 8:
				switch ($PosFormat) {
					case 1:
						return $this->_applyGPOSchainContextPosFormat1($lookupID, $subtable, $ptr, $currGlyph, $currGID, $subtable_offset, $Type, $LuCoverage, $tag, $level, $is_old_spec, $ignore, $PosFormat);
					case 2:
						return $this->_applyGPOSchainContextPosFormat2($lookupID, $subtable, $ptr, $currGlyph, $currGID, $subtable_offset, $Type, $tag, $level, $is_old_spec, $ignore, $PosFormat);
					case 3:
						return $this->_applyGPOSchainContextPosFormat3($lookupID, $subtable, $ptr, $currGlyph, $subtable_offset, $Type, $tag, $level, $is_old_spec, $ignore, $PosFormat);
				}

				throw new \Mpdf\MpdfException(sprintf('GPOS Lookup Type %s, Format %s is not supported', $Type, $PosFormat));
		}

		throw new \Mpdf\MpdfException(sprintf('GPOS Lookup Type %s is not supported', $Type));
	}

	/**
	 * LookupType 1: Single Adjustment
	 *
	 * Move one glyph. Format 1 applies one value record to every covered glyph; format 2 carries one
	 * record per glyph, indexed by Coverage Index.
	 *
	 * @see https://learn.microsoft.com/en-us/typography/opentype/spec/gpos#lookup-type-1-single-adjustment-positioning-subtable
	 *
	 * @return int|null Glyphs to advance by, null if the subtable did not apply
	 */
	private function _applyGPOSsingleAdjustment($lookupID, $subtable, $ptr, $currGlyph, $currGID, $subtable_offset, $Type, $LuCoverage, $level, $PosFormat)
	{
		// Format 1:
		if ($PosFormat == 1) {
			$Coverage = $subtable_offset + $this->reader->readUInt16();
			$ValueFormat = $this->reader->readUInt16();
			$Value = ValueRecord::read($this->reader, $ValueFormat);
		}
		// Format 2:
		elseif ($PosFormat == 2) {
			$Coverage = $subtable_offset + $this->reader->readUInt16();
			$ValueFormat = $this->reader->readUInt16();
			$ValueCount = $this->reader->readUInt16();
			$GlyphPos = $LuCoverage[$currGID];
			$this->reader->skip($GlyphPos * ValueRecord::size($ValueFormat));
			$Value = ValueRecord::read($this->reader, $ValueFormat);
		}
		$this->_applyGPOSvaluerecord($ptr, $Value);
		if ($this->debugOTL) {
			echo OtlDump::shapingStep($this->OTLdata, 'GPOS', $lookupID, $subtable, $Type, $PosFormat, $ptr, $currGlyph, $level);
		}
		return 1;
	}

	/**
	 * LookupType 2: Pair Adjustment
	 *
	 * Move two adjacent glyphs relative to each other - this is where kerning lives when a font puts it
	 * in GPOS rather than in the old kern table. Both formats share the two value formats and the size
	 * they imply for a pair record, which is why those are read before the format is dispatched on.
	 *
	 * @see https://learn.microsoft.com/en-us/typography/opentype/spec/gpos#lookup-type-2-pair-adjustment-positioning-subtable
	 *
	 * @return int|null Glyphs to advance by, null if the subtable did not apply
	 */
	private function _applyGPOSpairAdjustment($lookupID, $subtable, $ptr, $currGlyph, $currGID, $subtable_offset, $Type, $LuCoverage, $level, $ignore, $PosFormat)
	{
		$Coverage = $subtable_offset + $this->reader->readUInt16();
		$ValueFormat1 = $this->reader->readUInt16();
		$ValueFormat2 = $this->reader->readUInt16();
		$sizeOfPair = ValueRecord::size($ValueFormat1) + ValueRecord::size($ValueFormat2);

		switch ($PosFormat) {
			case 1:
				return $this->_applyGPOSpairAdjustmentFormat1($lookupID, $subtable, $ptr, $currGlyph, $currGID, $subtable_offset, $Type, $LuCoverage, $level, $ignore, $PosFormat, $ValueFormat1, $ValueFormat2, $sizeOfPair);
			case 2:
				return $this->_applyGPOSpairAdjustmentFormat2($lookupID, $subtable, $ptr, $currGlyph, $subtable_offset, $Type, $level, $ignore, $PosFormat, $ValueFormat1, $ValueFormat2, $sizeOfPair);
		}

		throw new \Mpdf\MpdfException(sprintf('GPOS Lookup Type %s, Format %s is not supported', $Type, $PosFormat));
	}

	/**
	 * LookupType 2, Format 1: Pair Adjustment by glyph
	 *
	 * A set of second glyphs per first glyph, each with its own pair of value records.
	 *
	 * @see https://learn.microsoft.com/en-us/typography/opentype/spec/gpos#pair-adjustment-positioning-format-1-adjustments-for-glyph-pairs
	 *
	 * @return int|null Glyphs to advance by, null if the subtable did not apply
	 */
	private function _applyGPOSpairAdjustmentFormat1($lookupID, $subtable, $ptr, $currGlyph, $currGID, $subtable_offset, $Type, $LuCoverage, $level, $ignore, $PosFormat, $ValueFormat1, $ValueFormat2, $sizeOfPair)
	{
		$PairSetCount = $this->reader->readUInt16();
		$PairSetOffset = [];
		for ($p = 0; $p < $PairSetCount; $p++) {
			$PairSetOffset[] = $subtable_offset + $this->reader->readUInt16();
		}
		for ($p = 0; $p < $PairSetCount; $p++) {
			if ($LuCoverage[$currGID] == $p) {
				$this->reader->seek($PairSetOffset[$p]);
				//PairSet table
				$PairValueCount = $this->reader->readUInt16();
				for ($pv = 0; $pv < $PairValueCount; $pv++) {
					//PairValueRecord
					$gid = $this->reader->readUInt16();
					$SecondGlyph = $this->glyphToChar($gid);
					$FirstGlyph = $this->OTLdata[$ptr]['uni'];

					$checkpos = $ptr;
					$checkpos++;
					while (isset($this->OTLdata[$checkpos]) && isset($ignore[$this->OTLdata[$checkpos]['uni']])) {
						$checkpos++;
					}
					if (isset($this->OTLdata[$checkpos]) && $this->OTLdata[$checkpos]['uni'] == $SecondGlyph) {
						$matchedpos = $checkpos;
					} else {
						$matchedpos = false;
					}

					if ($matchedpos !== false) {
						$Value1 = ValueRecord::read($this->reader, $ValueFormat1);
						$Value2 = ValueRecord::read($this->reader, $ValueFormat2);
						if ($ValueFormat1) {
							$this->_applyGPOSvaluerecord($ptr, $Value1);
						}
						if ($ValueFormat2) {
							$this->_applyGPOSvaluerecord($matchedpos, $Value2);
							if ($this->debugOTL) {
								echo OtlDump::shapingStep($this->OTLdata, 'GPOS', $lookupID, $subtable, $Type, $PosFormat, $ptr, $currGlyph, $level);
							}
							return $matchedpos - $ptr + 1;
						}
						if ($this->debugOTL) {
							echo OtlDump::shapingStep($this->OTLdata, 'GPOS', $lookupID, $subtable, $Type, $PosFormat, $ptr, $currGlyph, $level);
						}
						return $matchedpos - $ptr;
					} else {
						$this->reader->skip($sizeOfPair);
					}
				}
			}
		}
		return null;
	}

	/**
	 * LookupType 2, Format 2: Pair Adjustment by class
	 *
	 * A grid of value records indexed by the classes of the two glyphs, which is how a font kerns whole
	 * categories of glyph without listing every pair.
	 *
	 * @see https://learn.microsoft.com/en-us/typography/opentype/spec/gpos#pair-adjustment-positioning-format-2-class-pair-adjustment
	 *
	 * @return int|null Glyphs to advance by, null if the subtable did not apply
	 */
	private function _applyGPOSpairAdjustmentFormat2($lookupID, $subtable, $ptr, $currGlyph, $subtable_offset, $Type, $level, $ignore, $PosFormat, $ValueFormat1, $ValueFormat2, $sizeOfPair)
	{
		$ClassDef1 = $subtable_offset + $this->reader->readUInt16();
		$ClassDef2 = $subtable_offset + $this->reader->readUInt16();
		$Class1Count = $this->reader->readUInt16();
		$Class2Count = $this->reader->readUInt16();

		// Every (class1, class2) pair has a record, so the grid's size is known - but nothing needs to
		// step over it, because each pair is reached by seeking to it rather than by reading in order
		$sizeOfValueRecords = $Class1Count * $Class2Count * $sizeOfPair;

		// NB Class1Count includes Class 0 even though it is not defined by $ClassDef1
		// i.e. Class1Count = 5; Class1 will contain array(indices 1-4);
		$Class1 = $this->_getClassDefinitionTable($ClassDef1);
		$Class2 = $this->_getClassDefinitionTable($ClassDef2);
		$FirstGlyph = $this->OTLdata[$ptr]['uni'];
		$checkpos = $ptr;
		$checkpos++;
		while (isset($this->OTLdata[$checkpos]) && isset($ignore[$this->OTLdata[$checkpos]['uni']])) {
			$checkpos++;
		}
		if (isset($this->OTLdata[$checkpos])) {
			$matchedpos = $checkpos;
		} else {
			return null;
		}

		$SecondGlyph = $this->OTLdata[$matchedpos]['uni'];
		for ($i = 0; $i < $Class1Count; $i++) {
			if (isset($Class1[$i]) && count($Class1[$i])) {
				$FirstClassPos = array_search($FirstGlyph, $Class1[$i]);
				if ($FirstClassPos === false) {
					continue;
				} else {
					for ($j = 0; $j < $Class2Count; $j++) {
						if (isset($Class2[$j]) && count($Class2[$j])) {
							$SecondClassPos = array_search($SecondGlyph, $Class2[$j]);
							if ($SecondClassPos === false) {
								continue;
							}

							// Get ValueRecord[$i][$j]
							$offs = ($i * $Class2Count * $sizeOfPair) + ($j * $sizeOfPair);
							$this->reader->seek($subtable_offset + 16 + $offs);

							$Value1 = ValueRecord::read($this->reader, $ValueFormat1);
							$Value2 = ValueRecord::read($this->reader, $ValueFormat2);
							if ($ValueFormat1) {
								$this->_applyGPOSvaluerecord($ptr, $Value1);
							}
							if ($ValueFormat2) {
								$this->_applyGPOSvaluerecord($matchedpos, $Value2);
								if ($this->debugOTL) {
									echo OtlDump::shapingStep($this->OTLdata, 'GPOS', $lookupID, $subtable, $Type, $PosFormat, $ptr, $currGlyph, $level);
								}
								return $matchedpos - $ptr + 1;
							}
							if ($this->debugOTL) {
								echo OtlDump::shapingStep($this->OTLdata, 'GPOS', $lookupID, $subtable, $Type, $PosFormat, $ptr, $currGlyph, $level);
							}
							return $matchedpos - $ptr;
						}
					}
				}
			}
		}
		return null;
	}

	/**
	 * LookupType 3: Cursive Attachment
	 *
	 * Join one glyph's exit anchor to the next glyph's entry anchor, which is what makes a cursive
	 * script connect. The only lookup that reads the writing direction.
	 *
	 * @see https://learn.microsoft.com/en-us/typography/opentype/spec/gpos#lookup-type-3-cursive-attachment-positioning-subtable
	 *
	 * @return int|null Glyphs to advance by, null if the subtable did not apply
	 */
	private function _applyGPOScursiveAttachment($lookupID, $subtable, $ptr, $currGlyph, $currGID, $subtable_offset, $Type, $LuCoverage, $level, $dir, $PosFormat)
	{
		$this->reader->skip(4);
		// Need default XAdvance for glyph
		$pdfWidth = $this->mpdf->_getCharWidth($this->mpdf->CurrentFont['cw'], hexdec($currGlyph)); // DON'T convert back to design units

		$CPos = $LuCoverage[$currGID];
		$this->reader->skip($CPos * 4);
		$EntryAnchor = $this->reader->readUInt16();
		$ExitAnchor = $this->reader->readUInt16();
		if ($EntryAnchor != 0) {
			$EntryAnchor += $subtable_offset;
			list($x, $y) = Anchor::coordinates($this->reader, $EntryAnchor);
			if ($dir == 'RTL') {
				if (round($pdfWidth) == round($x * 1000 / $this->mpdf->CurrentFont['unitsPerEm'])) {
					$x = 0;
				} else {
					$x = $x - ($pdfWidth * $this->mpdf->CurrentFont['unitsPerEm'] / 1000);
				}
			}

			$this->Entry[$ptr] = ['X' => $x, 'Y' => $y, 'dir' => $dir];
		}
		if ($ExitAnchor != 0) {
			$ExitAnchor += $subtable_offset;
			list($x, $y) = Anchor::coordinates($this->reader, $ExitAnchor);
			if ($dir == 'LTR') {
				if (round($pdfWidth) == round($x * 1000 / $this->mpdf->CurrentFont['unitsPerEm'])) {
					$x = 0;
				} else {
					$x = $x - ($pdfWidth * $this->mpdf->CurrentFont['unitsPerEm'] / 1000);
				}
			}
			$this->Exit[$ptr] = ['X' => $x, 'Y' => $y, 'dir' => $dir];
		}
		if ($this->debugOTL) {
			echo OtlDump::shapingStep($this->OTLdata, 'GPOS', $lookupID, $subtable, $Type, $PosFormat, $ptr, $currGlyph, $level);
		}
		return 1;
	}

	/**
	 * LookupType 4: Mark-to-Base Attachment
	 *
	 * Place a mark against a base glyph, by matching the mark's class to an anchor on the base.
	 *
	 * @see https://learn.microsoft.com/en-us/typography/opentype/spec/gpos#lookup-type-4-mark-to-base-attachment-positioning-subtable
	 *
	 * @return int|null Glyphs to advance by, null if the subtable did not apply
	 */
	private function _applyGPOSmarkToBase($lookupID, $subtable, $ptr, $currGlyph, $currGID, $subtable_offset, $Type, $LuCoverage, $level, $is_old_spec, $PosFormat)
	{
		$MarkCoverage = $subtable_offset + $this->reader->readUInt16();
		//$MarkCoverage is already set in $LuCoverage 00065|00073 etc
		$BaseCoverage = $subtable_offset + $this->reader->readUInt16();
		$ClassCount = $this->reader->readUInt16(); // Number of classes defined for marks = Number of mark glyphs in the MarkCoverage table
		$MarkArray = $subtable_offset + $this->reader->readUInt16(); // Offset to MarkArray table
		$BaseArray = $subtable_offset + $this->reader->readUInt16(); // Offset to BaseArray table

		$this->reader->seek($BaseCoverage);
		$BaseGlyphs = $this->coverageIndexByHex();

		$checkpos = $ptr;
		$checkpos--;

		// ZZZ93
		// In Lohit-Kannada font (old-spec), rules specify a Type 4 GPOS to attach below-forms to base glyph
		// the repositioning does not happen in MS Word, and shouldn't happen comparing with other fonts
		// ?Why not
		// This Fix blocks the GPOS rule if the "mark" is not actually classified as a mark in the GlyphClasses of GDEF
		// but only in Indic old-spec.
		// Test cases: &#xca8;&#xccd;&#xca8;&#xcc1; and &#xc95;&#xccd;&#xcb0;&#xccc;
		if ($this->shaper == 'I' && $is_old_spec && !$this->isMark($this->OTLdata[$ptr]['hex'])) {
			return;
		}

		// "To identify the base glyph that combines with a mark, the text-processing client must look backward in the glyph string from the mark to the preceding base glyph."
		while (isset($this->OTLdata[$checkpos]) && $this->isMark($this->OTLdata[$checkpos]['hex'])) {
			$checkpos--;
		}

		if (isset($this->OTLdata[$checkpos]) && isset($BaseGlyphs[$this->OTLdata[$checkpos]['hex']])) {
			$matchedpos = $checkpos;
		} else {
			$matchedpos = false;
		}

		if ($matchedpos !== false) {
			// Get the relevant MarkRecord
			$MarkPos = $LuCoverage[$currGID];
			$MarkRecord = MarkArray::record($this->reader, $MarkArray, $MarkPos); // e.g. Array ( [Class] => 0 [AnchorX] => -549 [AnchorY] => 1548 )
			//Mark Class is = $MarkRecord['Class']
			// Get the relevant BaseRecord
			$this->reader->seek($BaseArray);
			$BaseCount = $this->reader->readUInt16();
			$BasePos = $BaseGlyphs[$this->OTLdata[$matchedpos]['hex']];

			// Move to the BaseRecord we want
			$nSkip = (2 * $BasePos * $ClassCount );
			$this->reader->skip($nSkip);

			// Read BaseRecord we want for appropriate Class
			$nSkip = 2 * $MarkRecord['Class'];
			$this->reader->skip($nSkip);
			$offset = $this->reader->readUInt16();

			// A NULL offset is how a base states that it offers marks of this class nothing to attach
			// to. Added to the array start it would read the BaseArray's own header as an Anchor
			if ($offset == 0) {
				return null;
			}

			list($x, $y) = Anchor::coordinates($this->reader, $BaseArray + $offset);
			$BaseRecord = ['AnchorX' => $x, 'AnchorY' => $y]; // e.g. Array ( [AnchorX] => 660 [AnchorY] => 1556 )
			// Need default XAdvance for Base glyph
			$BaseWidth = $this->mpdf->_getCharWidth($this->mpdf->CurrentFont['cw'], $this->OTLdata[$matchedpos]['uni']) * $this->mpdf->CurrentFont['unitsPerEm'] / 1000; // convert back to font design units
			$this->OTLdata[$ptr]['GPOSinfo']['BaseWidth'] = $BaseWidth;
			// And any intervening (ignored) characters
			if (($ptr - $matchedpos) > 1) {
				for ($i = $matchedpos + 1; $i < $ptr; $i++) {
					$BaseWidthExtra = $this->mpdf->_getCharWidth($this->mpdf->CurrentFont['cw'], $this->OTLdata[$i]['uni']) * $this->mpdf->CurrentFont['unitsPerEm'] / 1000; // convert back to font design units
					$this->OTLdata[$ptr]['GPOSinfo']['BaseWidth'] += $BaseWidthExtra;
				}
			}

			// Align to previous Glyph by attachment - so need to add to previous placement values
			$prevXPlacement = (isset($this->OTLdata[$matchedpos]['GPOSinfo']['XPlacement']) ? $this->OTLdata[$matchedpos]['GPOSinfo']['XPlacement'] : 0);
			$prevYPlacement = (isset($this->OTLdata[$matchedpos]['GPOSinfo']['YPlacement']) ? $this->OTLdata[$matchedpos]['GPOSinfo']['YPlacement'] : 0);

			$this->OTLdata[$ptr]['GPOSinfo']['XPlacement'] = $prevXPlacement + $BaseRecord['AnchorX'] - $MarkRecord['AnchorX'];
			$this->OTLdata[$ptr]['GPOSinfo']['YPlacement'] = $prevYPlacement + $BaseRecord['AnchorY'] - $MarkRecord['AnchorY'];
			if ($this->debugOTL) {
				echo OtlDump::shapingStep($this->OTLdata, 'GPOS', $lookupID, $subtable, $Type, $PosFormat, $ptr, $currGlyph, $level);
			}
			return 1;
		}
		return null;
	}

	/**
	 * LookupType 5: Mark-to-Ligature Attachment
	 *
	 * Place a mark against one component of a ligature. Which component comes from the association GSUB
	 * recorded when it built the ligature.
	 *
	 * @see https://learn.microsoft.com/en-us/typography/opentype/spec/gpos#lookup-type-5-mark-to-ligature-attachment-positioning-subtable
	 *
	 * @return int|null Glyphs to advance by, null if the subtable did not apply
	 */
	private function _applyGPOSmarkToLigature($lookupID, $subtable, $ptr, $currGlyph, $currGID, $subtable_offset, $Type, $LuCoverage, $level, $PosFormat)
	{
		$MarkCoverage = $subtable_offset + $this->reader->readUInt16();
		//$MarkCoverage is already set in $LuCoverage 00065|00073 etc
		$LigatureCoverage = $subtable_offset + $this->reader->readUInt16();
		$ClassCount = $this->reader->readUInt16(); // Number of classes defined for marks = Number of mark glyphs in the MarkCoverage table
		$MarkArray = $subtable_offset + $this->reader->readUInt16(); // Offset to MarkArray table
		$LigatureArray = $subtable_offset + $this->reader->readUInt16(); // Offset to LigatureArray table

		$this->reader->seek($LigatureCoverage);
		$LigatureGlyphs = $this->coverageIndexByHex();

		$checkpos = $ptr;
		$checkpos--;

		// "To position a combining mark using a MarkToLigature attachment subtable, the text-processing client must work backward from the mark to the preceding ligature glyph."
		while (isset($this->OTLdata[$checkpos]) && $this->isMark($this->OTLdata[$checkpos]['hex'])) {
			$checkpos--;
		}

		if (isset($this->OTLdata[$checkpos]) && isset($LigatureGlyphs[$this->OTLdata[$checkpos]['hex']])) {
			$matchedpos = $checkpos;
		} else {
			$matchedpos = false;
		}

		if ($matchedpos !== false) {
			// Get the relevant MarkRecord
			$MarkPos = $LuCoverage[$currGID];
			$MarkRecord = MarkArray::record($this->reader, $MarkArray, $MarkPos); // e.g. Array ( [Class] => 0 [AnchorX] => -549 [AnchorY] => 1548 )
			//Mark Class is = $MarkRecord['Class']
			// Get the relevant LigatureRecord
			$this->reader->seek($LigatureArray);
			$LigatureCount = $this->reader->readUInt16();
			$LigaturePos = $LigatureGlyphs[$this->OTLdata[$matchedpos]['hex']];

			// Move to the LigatureAttach table Record we want
			$nSkip = (2 * $LigaturePos);
			$this->reader->skip($nSkip);
			$LigatureAttachOffset = $LigatureArray + $this->reader->readUInt16();
			$this->reader->seek($LigatureAttachOffset);
			$ComponentCount = $this->reader->readUInt16();
			$offsets = [];
			for ($comp = 0; $comp < $ComponentCount; $comp++) {
				// ComponentRecords
				for ($class = 0; $class < $ClassCount; $class++) {
					$offsets[$comp][$class] = $this->reader->readUInt16();
				}
			}

			// Get the specific component for this mark attachment
			if (isset($this->assocLigs[$matchedpos]) && isset($this->assocMarks[$ptr]['ligPos']) && $this->assocMarks[$ptr]['ligPos'] == $matchedpos) {
				$component = $this->assocMarks[$ptr]['compID'];
			} else {
				$component = $ComponentCount - 1;
			}

			$offset = $offsets[$component][$MarkRecord['Class']];
			if ($offset != 0) {
				$LigatureRecordOffset = $offset + $LigatureAttachOffset;
				list($x, $y) = Anchor::coordinates($this->reader, $LigatureRecordOffset);
				$LigatureRecord = ['AnchorX' => $x, 'AnchorY' => $y];

				// Need default XAdvance for Ligature glyph
				$LigatureWidth = $this->mpdf->_getCharWidth($this->mpdf->CurrentFont['cw'], $this->OTLdata[$matchedpos]['uni']) * $this->mpdf->CurrentFont['unitsPerEm'] / 1000; // convert back to font design units
				$this->OTLdata[$ptr]['GPOSinfo']['BaseWidth'] = $LigatureWidth;
				// And any intervening (ignored)characters
				if (($ptr - $matchedpos) > 1) {
					for ($i = $matchedpos + 1; $i < $ptr; $i++) {
						$LigatureWidthExtra = $this->mpdf->_getCharWidth($this->mpdf->CurrentFont['cw'], $this->OTLdata[$i]['uni']) * $this->mpdf->CurrentFont['unitsPerEm'] / 1000; // convert back to font design units
						$this->OTLdata[$ptr]['GPOSinfo']['BaseWidth'] += $LigatureWidthExtra;
					}
				}

				// Align to previous Ligature by attachment - so need to add to previous placement values
				if (isset($this->OTLdata[$matchedpos]['GPOSinfo']['XPlacement'])) {
					$prevXPlacement = $this->OTLdata[$matchedpos]['GPOSinfo']['XPlacement'];
				} else {
					$prevXPlacement = 0;
				}
				if (isset($this->OTLdata[$matchedpos]['GPOSinfo']['YPlacement'])) {
					$prevYPlacement = $this->OTLdata[$matchedpos]['GPOSinfo']['YPlacement'];
				} else {
					$prevYPlacement = 0;
				}

				$this->OTLdata[$ptr]['GPOSinfo']['XPlacement'] = $prevXPlacement + $LigatureRecord['AnchorX'] - $MarkRecord['AnchorX'];
				$this->OTLdata[$ptr]['GPOSinfo']['YPlacement'] = $prevYPlacement + $LigatureRecord['AnchorY'] - $MarkRecord['AnchorY'];
				if ($this->debugOTL) {
					echo OtlDump::shapingStep($this->OTLdata, 'GPOS', $lookupID, $subtable, $Type, $PosFormat, $ptr, $currGlyph, $level);
				}
				return 1;
			}
		}
		return null;
	}

	/**
	 * LookupType 6: Mark-to-Mark Attachment
	 *
	 * Place a mark against another mark, for stacked diacritics.
	 *
	 * @see https://learn.microsoft.com/en-us/typography/opentype/spec/gpos#lookup-type-6-mark-to-mark-attachment-positioning-subtable
	 *
	 * @return int|null Glyphs to advance by, null if the subtable did not apply
	 */
	private function _applyGPOSmarkToMark($lookupID, $subtable, $ptr, $currGlyph, $currGID, $subtable_offset, $Type, $LuCoverage, $level, $ignore, $PosFormat)
	{
		$Mark1Coverage = $subtable_offset + $this->reader->readUInt16(); // Combining Mark
		//$Mark1Coverage is already set in $LuCoverage 0065|0073 etc
		$Mark2Coverage = $subtable_offset + $this->reader->readUInt16(); // Base Mark
		$ClassCount = $this->reader->readUInt16(); // Number of classes defined for marks = No. of Combining mark1 glyphs in the MarkCoverage table
		$Mark1Array = $subtable_offset + $this->reader->readUInt16(); // Offset to MarkArray table
		$Mark2Array = $subtable_offset + $this->reader->readUInt16(); // Offset to Mark2Array table
		$this->reader->seek($Mark2Coverage);
		$Mark2Glyphs = $this->coverageIndexByHex();
		$checkpos = $ptr;
		$checkpos--;
		while (isset($this->OTLdata[$checkpos]) && isset($ignore[$this->OTLdata[$checkpos]['uni']])) {
			$checkpos--;
		}
		if (isset($this->OTLdata[$checkpos]) && isset($Mark2Glyphs[$this->OTLdata[$checkpos]['hex']])) {
			$matchedpos = $checkpos;
		} else {
			$matchedpos = false;
		}

		if ($matchedpos !== false) {
			// Get the relevant MarkRecord
			$Mark1Pos = $LuCoverage[$currGID];
			$Mark1Record = MarkArray::record($this->reader, $Mark1Array, $Mark1Pos); // e.g. Array ( [Class] => 0 [AnchorX] => -549 [AnchorY] => 1548 )
			//Mark Class is = $Mark1Record['Class']
			// Get the relevant Mark2Record
			$this->reader->seek($Mark2Array);
			$Mark2Count = $this->reader->readUInt16();
			$Mark2Pos = $Mark2Glyphs[$this->OTLdata[$matchedpos]['hex']];

			// Move to the Mark2Record we want
			$nSkip = (2 * $Mark2Pos * $ClassCount );
			$this->reader->skip($nSkip);

			// Read Mark2Record we want for appropriate Class
			$nSkip = 2 * $Mark1Record['Class'];
			$this->reader->skip($nSkip);
			$offset = $this->reader->readUInt16();

			// The same for the mark being attached to: a NULL offset says it offers this class nothing,
			// and added to the array start it would read the Mark2Array's own header as an Anchor
			if ($offset == 0) {
				return null;
			}

			list($x, $y) = Anchor::coordinates($this->reader, $Mark2Array + $offset);
			$Mark2Record = ['AnchorX' => $x, 'AnchorY' => $y]; // e.g. Array ( [AnchorX] => 660 [AnchorY] => 1556 )
			// Need default XAdvance for Mark2 glyph
			$Mark2Width = $this->mpdf->_getCharWidth($this->mpdf->CurrentFont['cw'], $this->OTLdata[$matchedpos]['uni']) * $this->mpdf->CurrentFont['unitsPerEm'] / 1000; // convert back to font design units
			// IF combining marks are set on different components of a ligature glyph, do not apply this rule
			// Test: arabictypesetting: &#x625;&#x650;&#x644;&#x64e;&#x649;&#x670;&#x653;
			// Test: arabictypesetting: &#x628;&#x651;&#x64e;&#x64a;&#x652;&#x646;&#x64e;&#x643;&#x64f;&#x645;&#x652;
			$prevLig = -1;
			$thisLig = -1;
			$prevComp = -1;
			$thisComp = -1;
			if (isset($this->assocMarks[$matchedpos])) {
				$prevLig = $this->assocMarks[$matchedpos]['ligPos'];
				$prevComp = $this->assocMarks[$matchedpos]['compID'];
			}
			if (isset($this->assocMarks[$ptr])) {
				$thisLig = $this->assocMarks[$ptr]['ligPos'];
				$thisComp = $this->assocMarks[$ptr]['compID'];
			}

			// However IF Mark2 (first in logical order, i.e. being attached to) is not associated with a base, carry on
			// This happens in Indic when the Mark being attached to e.g. [Halant Ma lig] -> MatraU,  [U+0B4D + U+B2E as E0F5]-> U+0B41 become E135
			if (isset($this->assocMarks[$matchedpos]) && ($prevLig != $thisLig || $prevComp != $thisComp)) {
				return null;
			}

			if (!isset($this->OTLdata[$matchedpos]['GPOSinfo']['BaseWidth']) || !$this->OTLdata[$matchedpos]['GPOSinfo']['BaseWidth']) {
				$this->OTLdata[$ptr]['GPOSinfo']['BaseWidth'] = $Mark2Width;
			}

			// ZZZ99Q - Test Case font-family: garuda &#xe19;&#xe49;&#xe33;
			if (isset($this->OTLdata[$matchedpos]['GPOSinfo']['BaseWidth']) && $this->OTLdata[$matchedpos]['GPOSinfo']['BaseWidth']) {
				$this->OTLdata[$ptr]['GPOSinfo']['BaseWidth'] = $this->OTLdata[$matchedpos]['GPOSinfo']['BaseWidth'];
			}

			// Align to previous Mark by attachment - so need to add the previous placement values
			$prevXPlacement = (isset($this->OTLdata[$matchedpos]['GPOSinfo']['XPlacement']) ? $this->OTLdata[$matchedpos]['GPOSinfo']['XPlacement'] : 0);
			$prevYPlacement = (isset($this->OTLdata[$matchedpos]['GPOSinfo']['YPlacement']) ? $this->OTLdata[$matchedpos]['GPOSinfo']['YPlacement'] : 0);
			$this->OTLdata[$ptr]['GPOSinfo']['XPlacement'] = $prevXPlacement + $Mark2Record['AnchorX'] - $Mark1Record['AnchorX'];
			$this->OTLdata[$ptr]['GPOSinfo']['YPlacement'] = $prevYPlacement + $Mark2Record['AnchorY'] - $Mark1Record['AnchorY'];
			if ($this->debugOTL) {
				echo OtlDump::shapingStep($this->OTLdata, 'GPOS', $lookupID, $subtable, $Type, $PosFormat, $ptr, $currGlyph, $level);
			}
			return 1;
		}
		return null;
	}

	/**
	 * LookupType 7, Format 1: Context Positioning by glyph
	 *
	 * Rules listing the glyphs that must follow, grouped by the first glyph of the context.
	 *
	 * @see https://learn.microsoft.com/en-us/typography/opentype/spec/gpos#context-positioning-subtable-format-1-simple-glyph-contexts
	 *
	 * @return int|null Glyphs to advance by, null if the subtable did not apply
	 */
	private function _applyGPOScontextPosFormat1($lookupID, $subtable, $ptr, $currGlyph, $currGID, $subtable_offset, $Type, $LuCoverage, $tag, $level, $is_old_spec, $ignore, $PosFormat)
	{
		$CoverageTableOffset = $subtable_offset + $this->reader->readUInt16();
		$PosRuleSetCount = $this->reader->readUInt16();

		// PosRuleSet tables: All contexts beginning with the same glyph
		// Select the PosRuleSet required using the position of the glyph in the coverage table
		$GlyphPos = $LuCoverage[$currGID];
		$this->reader->skip($GlyphPos * 2);
		$offset = $this->reader->readUInt16();
		if ($offset == 0x0000) {
			return null; // No context begins with this glyph
		}

		$PosRuleSet = $subtable_offset + $offset;
		$this->reader->seek($PosRuleSet);
		$PosRuleCnt = $this->reader->readUInt16();
		$PosRule = [];
		for ($b = 0; $b < $PosRuleCnt; $b++) {
			$PosRule[$b] = $PosRuleSet + $this->reader->readUInt16();
		}

		for ($b = 0; $b < $PosRuleCnt; $b++) {  // EACH RULE
			$this->reader->seek($PosRule[$b]);
			list($inputGlyphIDs, $PosCount) = SequenceRule::plain($this->reader);

			// Position 0 is the glyph the Coverage table selected this rule set by
			$Input = array_merge([$this->OTLdata[$ptr]['uni']], $this->charsOf($inputGlyphIDs));

			// Type 7 is a plain context: it has no backtrack or lookahead sequence
			$matched = $this->checkContextMatch($Input, [], [], $ignore, $ptr);
			if ($matched) {
				$shift = $this->_applyGPOSlookupRecords($PosCount, $matched, $tag, $is_old_spec);
				if ($this->debugOTL) {
					echo OtlDump::shapingStep($this->OTLdata, 'GPOS', $lookupID, $subtable, $Type, $PosFormat, $ptr, $currGlyph, $level);
				}

				return $shift;
			}
		}

		return null;
	}

	/**
	 * LookupType 7, Format 2: Context Positioning by class
	 *
	 * The same, matching glyph classes rather than individual glyphs.
	 *
	 * @see https://learn.microsoft.com/en-us/typography/opentype/spec/gpos#context-positioning-subtable-format-2-class-based-glyph-contexts
	 *
	 * @return int|null Glyphs to advance by, null if the subtable did not apply
	 */
	private function _applyGPOScontextPosFormat2($lookupID, $subtable, $ptr, $currGlyph, $currGID, $subtable_offset, $Type, $tag, $level, $is_old_spec, $ignore, $PosFormat)
	{
		$CoverageTableOffset = $subtable_offset + $this->reader->readUInt16();
		$InputClassDefOffset = $subtable_offset + $this->reader->readUInt16();
		$PosClassSetCnt = $this->reader->readUInt16();
		$PosClassSetOffset = [];
		for ($b = 0; $b < $PosClassSetCnt; $b++) {
			$offset = $this->reader->readUInt16();
			if ($offset == 0x0000) {
				$PosClassSetOffset[] = $offset;
			} else {
				$PosClassSetOffset[] = $subtable_offset + $offset;
			}
		}

		$InputClasses = $this->_getClasses($InputClassDefOffset);

		for ($s = 0; $s < $PosClassSetCnt; $s++) { // $ChainPosClassSet is ordered by input class-may be NULL
			// Select $PosClassSet if currGlyph is in First Input Class
			if ($PosClassSetOffset[$s] > 0 && isset($InputClasses[$s][$currGID])) {
				$this->reader->seek($PosClassSetOffset[$s]);
				$PosClassRuleCnt = $this->reader->readUInt16();
				$PosClassRule = [];
				for ($b = 0; $b < $PosClassRuleCnt; $b++) {
					$PosClassRule[$b] = $PosClassSetOffset[$s] + $this->reader->readUInt16();
				}

				for ($b = 0; $b < $PosClassRuleCnt; $b++) {  // EACH RULE
					$this->reader->seek($PosClassRule[$b]);
					list($inputClassIndices, $PosCount) = SequenceRule::plain($this->reader);

					// The rule set array is indexed by the class of the first input glyph, so the loop
					// index over it is that class, and that class is position 0
					$inputGlyphs = array_merge([$InputClasses[$s]], $this->classSets($InputClasses, $inputClassIndices));

					// Class 0 contains all the glyphs NOT in the other classes
					$class0excl = $this->getClassZeroExclusions($InputClassDefOffset);

					$matched = $this->checkContextMatchMultiple($inputGlyphs, [], [], $ignore, $ptr, $class0excl);
					if ($matched) {
						$shift = $this->_applyGPOSlookupRecords($PosCount, $matched, $tag, $is_old_spec);
						if ($this->debugOTL) {
							echo OtlDump::shapingStep($this->OTLdata, 'GPOS', $lookupID, $subtable, $Type, $PosFormat, $ptr, $currGlyph, $level);
						}

						return $shift;
					}
				}
			}
		}

		return null;
	}

	/**
	 * LookupType 7, Format 3: Context Positioning by coverage
	 *
	 * One rule, with a Coverage table per input position rather than a list of rules.
	 *
	 * @see https://learn.microsoft.com/en-us/typography/opentype/spec/gpos#context-positioning-subtable-format-3-coverage-based-glyph-contexts
	 *
	 * @return int|null Glyphs to advance by, null if the subtable did not apply
	 */
	private function _applyGPOScontextPosFormat3($lookupID, $subtable, $ptr, $currGlyph, $subtable_offset, $Type, $tag, $level, $is_old_spec, $ignore, $PosFormat)
	{
		// NB Unlike Lookup Type 8 Format 3, the count of positionings precedes the Coverage table offsets
		$InputGlyphCount = $this->reader->readUInt16();
		$PosCount = $this->reader->readUInt16();
		$CoverageInputOffset = SequenceRule::coverageOffsets($this->reader, $subtable_offset, $InputGlyphCount);
		$save_pos = $this->reader->tell(); // Save the point just after the Coverage table offsets

		$CoverageInputGlyphs = $this->coverageSets($CoverageInputOffset);

		// Type 7 is a plain context: it has no backtrack or lookahead sequence
		$matched = $this->checkContextMatchMultiple($CoverageInputGlyphs, [], [], $ignore, $ptr);
		if ($matched) {
			$this->reader->seek($save_pos); // Return to just after the Coverage table offsets
			$shift = $this->_applyGPOSlookupRecords($PosCount, $matched, $tag, $is_old_spec);
			if ($this->debugOTL) {
				echo OtlDump::shapingStep($this->OTLdata, 'GPOS', $lookupID, $subtable, $Type, $PosFormat, $ptr, $currGlyph, $level);
			}

			return $shift;
		}

		return null;
	}

	/**
	 * LookupType 8, Format 1: Chained Context Positioning by glyph
	 *
	 * As 7.1, with backtrack and lookahead sequences either side of the input.
	 *
	 * @see https://learn.microsoft.com/en-us/typography/opentype/spec/gpos#chained-context-positioning-subtable-format-1-simple-glyph-contexts
	 *
	 * @return int|null Glyphs to advance by, null if the subtable did not apply
	 */
	private function _applyGPOSchainContextPosFormat1($lookupID, $subtable, $ptr, $currGlyph, $currGID, $subtable_offset, $Type, $LuCoverage, $tag, $level, $is_old_spec, $ignore, $PosFormat)
	{
		$CoverageTableOffset = $subtable_offset + $this->reader->readUInt16();
		$ChainPosRuleSetCount = $this->reader->readUInt16();

		// All of the ChainPosRule tables defining contexts that begin with the same first glyph are grouped together in a ChainPosRuleSet table
		$GlyphPos = $LuCoverage[$currGID];
		$this->reader->skip($GlyphPos * 2);
		$offset = $this->reader->readUInt16();
		if ($offset == 0x0000) {
			return null; // No context begins with this glyph
		}

		$ChainPosRuleSet = $subtable_offset + $offset;
		$this->reader->seek($ChainPosRuleSet);
		$ChainPosRuleCount = $this->reader->readUInt16();
		$ChainPosRule = [];
		for ($s = 0; $s < $ChainPosRuleCount; $s++) {
			$ChainPosRule[$s] = $ChainPosRuleSet + $this->reader->readUInt16();
		}

		for ($s = 0; $s < $ChainPosRuleCount; $s++) {  // EACH RULE
			$this->reader->seek($ChainPosRule[$s]);
			list($backtrackGlyphIDs, $inputGlyphIDs, $lookaheadGlyphIDs) = SequenceRule::chained($this->reader);

			$Backtrack = $this->charsOf($backtrackGlyphIDs);
			// Position 0 is the glyph the Coverage table selected this rule set by
			$Input = array_merge([$this->OTLdata[$ptr]['uni']], $this->charsOf($inputGlyphIDs));
			$Lookahead = $this->charsOf($lookaheadGlyphIDs);

			$matched = $this->checkContextMatch($Input, $Backtrack, $Lookahead, $ignore, $ptr);
			if ($matched) {
				$PosCount = $this->reader->readUInt16();
				$shift = $this->_applyGPOSlookupRecords($PosCount, $matched, $tag, $is_old_spec);
				if ($this->debugOTL) {
					echo OtlDump::shapingStep($this->OTLdata, 'GPOS', $lookupID, $subtable, $Type, $PosFormat, $ptr, $currGlyph, $level);
				}

				return $shift;
			}
		}

		return null;
	}

	/**
	 * LookupType 8, Format 2: Chained Context Positioning by class
	 *
	 * As 7.2, with backtrack and lookahead, each matched against its own class definition.
	 *
	 * @see https://learn.microsoft.com/en-us/typography/opentype/spec/gpos#chained-context-positioning-subtable-format-2-class-based-glyph-contexts
	 *
	 * @return int|null Glyphs to advance by, null if the subtable did not apply
	 */
	private function _applyGPOSchainContextPosFormat2($lookupID, $subtable, $ptr, $currGlyph, $currGID, $subtable_offset, $Type, $tag, $level, $is_old_spec, $ignore, $PosFormat)
	{
		$CoverageTableOffset = $subtable_offset + $this->reader->readUInt16();
		$BacktrackClassDefOffset = $subtable_offset + $this->reader->readUInt16();
		$InputClassDefOffset = $subtable_offset + $this->reader->readUInt16();
		$LookaheadClassDefOffset = $subtable_offset + $this->reader->readUInt16();
		$ChainPosClassSetCnt = $this->reader->readUInt16();
		$ChainPosClassSetOffset = [];
		for ($b = 0; $b < $ChainPosClassSetCnt; $b++) {
			$offset = $this->reader->readUInt16();
			if ($offset == 0x0000) {
				$ChainPosClassSetOffset[] = $offset;
			} else {
				$ChainPosClassSetOffset[] = $subtable_offset + $offset;
			}
		}

		$BacktrackClasses = $this->_getClasses($BacktrackClassDefOffset);
		$InputClasses = $this->_getClasses($InputClassDefOffset);
		$LookaheadClasses = $this->_getClasses($LookaheadClassDefOffset);

		for ($s = 0; $s < $ChainPosClassSetCnt; $s++) { // $ChainPosClassSet is ordered by input class-may be NULL
			// Select $ChainPosClassSet if currGlyph is in First Input Class
			if ($ChainPosClassSetOffset[$s] > 0 && isset($InputClasses[$s][$currGID])) {
				$this->reader->seek($ChainPosClassSetOffset[$s]);
				$ChainPosClassRuleCnt = $this->reader->readUInt16();
				$ChainPosClassRule = [];
				for ($b = 0; $b < $ChainPosClassRuleCnt; $b++) {
					$ChainPosClassRule[$b] = $ChainPosClassSetOffset[$s] + $this->reader->readUInt16();
				}

				for ($b = 0; $b < $ChainPosClassRuleCnt; $b++) {  // EACH RULE
					$this->reader->seek($ChainPosClassRule[$b]);
					list($backtrackClassIndices, $inputClassIndices, $lookaheadClassIndices) = SequenceRule::chained($this->reader);

					// The rule set array is indexed by the class of the first input glyph, so the loop
					// index over it is that class, and that class is position 0
					$inputGlyphs = array_merge([$InputClasses[$s]], $this->classSets($InputClasses, $inputClassIndices));
					$backtrackGlyphs = $this->classSets($BacktrackClasses, $backtrackClassIndices);
					$lookaheadGlyphs = $this->classSets($LookaheadClasses, $lookaheadClassIndices);

					// Class 0 contains all the glyphs NOT in the other classes, one set per sequence
					$class0excl = $this->getClassZeroExclusions($InputClassDefOffset);
					$bclass0excl = $this->getClassZeroExclusions($BacktrackClassDefOffset);
					$lclass0excl = $this->getClassZeroExclusions($LookaheadClassDefOffset);

					$matched = $this->checkContextMatchMultiple($inputGlyphs, $backtrackGlyphs, $lookaheadGlyphs, $ignore, $ptr, $class0excl, $bclass0excl, $lclass0excl);
					if ($matched) {
						$PosCount = $this->reader->readUInt16();
						$shift = $this->_applyGPOSlookupRecords($PosCount, $matched, $tag, $is_old_spec);
						if ($this->debugOTL) {
							echo OtlDump::shapingStep($this->OTLdata, 'GPOS', $lookupID, $subtable, $Type, $PosFormat, $ptr, $currGlyph, $level);
						}

						return $shift;
					}
				}
			}
		}

		return null;
	}

	/**
	 * LookupType 8, Format 3: Chained Context Positioning by coverage
	 *
	 * As 7.3, with backtrack and lookahead, each a Coverage table per position.
	 *
	 * @see https://learn.microsoft.com/en-us/typography/opentype/spec/gpos#chained-context-positioning-subtable-format-3-coverage-based-glyph-contexts
	 *
	 * @return int|null Glyphs to advance by, null if the subtable did not apply
	 */
	private function _applyGPOSchainContextPosFormat3($lookupID, $subtable, $ptr, $currGlyph, $subtable_offset, $Type, $tag, $level, $is_old_spec, $ignore, $PosFormat)
	{
		// Each of the three sequences is a count and then one Coverage table offset per position.
		// NB Unlike Lookup Type 7 Format 3, the count of positionings follows them rather than
		// preceding them.
		$CoverageBacktrackOffset = SequenceRule::coverageOffsets($this->reader, $subtable_offset, $this->reader->readUInt16());
		$CoverageInputOffset = SequenceRule::coverageOffsets($this->reader, $subtable_offset, $this->reader->readUInt16());
		$CoverageLookaheadOffset = SequenceRule::coverageOffsets($this->reader, $subtable_offset, $this->reader->readUInt16());
		$PosCount = $this->reader->readUInt16();
		$save_pos = $this->reader->tell(); // Save the point just after PosCount

		$CoverageBacktrackGlyphs = $this->coverageSets($CoverageBacktrackOffset);
		$CoverageInputGlyphs = $this->coverageSets($CoverageInputOffset);
		$CoverageLookaheadGlyphs = $this->coverageSets($CoverageLookaheadOffset);

		$matched = $this->checkContextMatchMultiple($CoverageInputGlyphs, $CoverageBacktrackGlyphs, $CoverageLookaheadGlyphs, $ignore, $ptr);
		if ($matched) {
			$this->reader->seek($save_pos); // Return to just after PosCount
			$shift = $this->_applyGPOSlookupRecords($PosCount, $matched, $tag, $is_old_spec);
			if ($this->debugOTL) {
				echo OtlDump::shapingStep($this->OTLdata, 'GPOS', $lookupID, $subtable, $Type, $PosFormat, $ptr, $currGlyph, $level);
			}

			return $shift;
		}

		return null;
	}

	/**
	 * Apply the nested lookups a matched context asks for, per SubstLookupRecord.
	 *
	 * Every contextual and chained-contextual substitution subtable ends the same way: having matched
	 * a sequence of glyphs, it names some number of other lookups to run, each at a position within
	 * that sequence. The reader is positioned at the records.
	 *
	 *     uint16   sequenceIndex       which glyph of the matched input to apply the lookup at
	 *     uint16   lookupListIndex     which lookup to apply
	 *
	 * A record pointing past the end of the input sequence is skipped rather than treated as an
	 * error; the spec says the index is into the input sequence, and a font that names a longer one
	 * than it matched is describing a position that does not exist.
	 *
	 * Only a subtable whose context matched arrives here, so this always applied. A matched context
	 * ends its lookup whether or not the lookups it names did anything, and one that named no records,
	 * or whose records did nothing, used to read as not having matched and send the glyph on to the
	 * next subtable to match a shorter context and substitute there.
	 *
	 * Where the cursor goes is the end of the matched input, carried through whatever the nested
	 * lookups add or remove. It is not the advance one of them returned: that counts from the position
	 * the record named, which is the context's own start only where that is sequence index 0, and the
	 * last record to shift anything was overwriting what the others left.
	 *
	 * A nested lookup that changes the number of glyphs moves the positions the records after it name,
	 * and where it grew one glyph into several, those several are what the matched sequence holds
	 * there. Shaping every contextual sequence in the corpus reaches none of that, so it is written
	 * from HarfBuzz's apply_lookup rather than from anything observed.
	 *
	 * The counterpart for positioning is _applyGPOSlookupRecords, which is the same shape without the
	 * bookkeeping - positioning cannot change the number of glyphs.
	 *
	 * @see https://learn.microsoft.com/en-us/typography/opentype/spec/chapter2#sequence-context-format-1-simple-glyph-contexts
	 *
	 * @param int   $SubstCount SubstCount, the number of records to read
	 * @param array $matched    Position in OTLdata of each glyph of the matched input sequence
	 *
	 * @return int Glyphs to advance by, the length of the matched input
	 */
	private function _applyGSUBlookupRecords($SubstCount, $matched, $currentTag, $is_old_spec, $tagInt)
	{
		$SubstLookupRecord = [];
		for ($p = 0; $p < $SubstCount; $p++) {
			$SubstLookupRecord[$p]['SequenceIndex'] = $this->reader->readUInt16();
			$SubstLookupRecord[$p]['LookupListIndex'] = $this->reader->readUInt16();
		}

		$start = $matched[0];
		$end = $matched[count($matched) - 1] + 1;

		for ($p = 0; $p < $SubstCount; $p++) {
			$index = $SubstLookupRecord[$p]['SequenceIndex'];
			if ($index >= count($matched)) {
				continue;
			}

			$lu = $SubstLookupRecord[$p]['LookupListIndex'];
			$luType = $this->GSUBLookups[$lu]['Type'];
			$luFlag = $this->GSUBLookups[$lu]['Flag'];
			$luMarkFilteringSet = $this->GSUBLookups[$lu]['MarkFilteringSet'];

			$luptr = $matched[$index];
			$lucurrGlyph = $this->OTLdata[$luptr]['hex'];
			$lucurrGID = $this->OTLdata[$luptr]['uni'];

			$before = count($this->OTLdata);
			foreach ($this->GSUBLookups[$lu]['Subtables'] as $luc => $lusubtable_offset) {
				if (null !== $this->_applyGSUBsubtable($lu, $luc, $luptr, $lucurrGlyph, $lucurrGID, $lusubtable_offset, $luType, $luFlag, $luMarkFilteringSet, $this->GSLuCoverage[$lu][$luc], 1, $currentTag, $is_old_spec, $tagInt)) {
					break;
				}
			}

			$delta = count($this->OTLdata) - $before;
			if ($delta === 0) {
				continue;
			}

			$end += $delta;
			if ($end < $luptr) {
				// It took out everything between here and the end of the match, so there is nothing
				// left for the records after this one to name
				$end = $luptr;
				break;
			}

			$next = $index + 1;
			if ($delta > 0) {
				array_splice($matched, $next, 0, range($luptr + 1, $luptr + $delta));
				$next += $delta;
			} else {
				// Never more entries than the match has left to give up
				$dropped = min(-$delta, count($matched) - $next);
				array_splice($matched, $next, $dropped);
				$delta = -$dropped;
			}
			for ($m = $next, $last = count($matched); $m < $last; $m++) {
				$matched[$m] += $delta;
			}
		}

		return $end - $start;
	}

	/**
	 * Apply the lookups a matched GPOS context asks for, at the positions the match found.
	 *
	 * Every contextual and chaining format ends the same way: having matched a sequence of glyphs,
	 * it names some number of lookups to run, each at a position within that sequence. The reader is
	 * positioned at the records.
	 *
	 *     uint16   sequenceIndex       which glyph of the matched input to apply the lookup at
	 *     uint16   lookupListIndex     which lookup to apply
	 *
	 * The counterpart for substitution is _applyGSUBlookupRecords, which documents why a record
	 * pointing past the end of the input sequence is skipped rather than treated as an error, why a
	 * matched context reports having applied even when nothing it named did anything, and why the
	 * cursor goes to the end of the matched input rather than wherever a nested lookup left it.
	 *
	 * Nothing here carries the matched positions through a length change as that one does. Positioning
	 * moves glyphs without adding or removing any, so the match cannot shift under it.
	 *
	 * @see https://learn.microsoft.com/en-us/typography/opentype/spec/gpos#chained-sequence-context-positioning-format-3-coverage-based-glyph-contexts
	 *
	 * @param int   $PosCount PosCount, the number of records to read
	 * @param array $matched  Position in OTLdata of each glyph of the matched input sequence
	 *
	 * @return int Glyphs to advance by, the length of the matched input
	 */
	private function _applyGPOSlookupRecords($PosCount, $matched, $tag, $is_old_spec)
	{
		$PosLookupRecord = [];
		for ($p = 0; $p < $PosCount; $p++) { // EACH LOOKUP
			$PosLookupRecord[$p]['SequenceIndex'] = $this->reader->readUInt16();
			$PosLookupRecord[$p]['LookupListIndex'] = $this->reader->readUInt16();
		}

		// Nothing below moves these, so unlike the substitution side they are read once
		$inputGlyphCount = count($matched);
		$start = $matched[0];
		$end = $matched[$inputGlyphCount - 1] + 1;

		for ($p = 0; $p < $PosCount; $p++) {
			// Apply  $PosLookupRecord[$p]['LookupListIndex']  at   $PosLookupRecord[$p]['SequenceIndex']
			if ($PosLookupRecord[$p]['SequenceIndex'] >= $inputGlyphCount) {
				continue;
			}
			$lu = $PosLookupRecord[$p]['LookupListIndex'];
			$luType = $this->GPOSLookups[$lu]['Type'];
			$luFlag = $this->GPOSLookups[$lu]['Flag'];
			if (isset($this->GPOSLookups[$lu]['MarkFilteringSet'])) {
				$luMarkFilteringSet = $this->GPOSLookups[$lu]['MarkFilteringSet'];
			} else {
				$luMarkFilteringSet = '';
			}

			$luptr = $matched[$PosLookupRecord[$p]['SequenceIndex']];
			$lucurrGlyph = $this->OTLdata[$luptr]['hex'];
			$lucurrGID = $this->OTLdata[$luptr]['uni'];

			foreach ($this->GPOSLookups[$lu]['Subtables'] as $luc => $lusubtable_offset) {
				if (null !== $this->_applyGPOSsubtable($lu, $luc, $luptr, $lucurrGlyph, $lucurrGID, $lusubtable_offset, $luType, $luFlag, $luMarkFilteringSet, $this->LuCoverage[$lu][$luc], $tag, 1, $is_old_spec)) {
					break;
				}
			}
		}

		return $end - $start;
	}

	/**
	 * Match a context rule that names its glyphs one by one, against the text at one position.
	 *
	 * The three sequences are tested outwards from the input: backtrack walking back from it,
	 * lookahead forward past it, with the glyphs the lookup's flags skip passed over in each
	 * direction. Where the shaper works a syllable at a time, a match that would run outside the
	 * current syllable fails.
	 *
	 * @param array  $Input     The input sequence, keyed from 1, as glyph ids
	 * @param array  $Backtrack The backtrack sequence, nearest first
	 * @param array  $Lookahead The lookahead sequence, nearest first
	 * @param array  $ignore    The glyphs the lookup's flags say to skip, keyed by codepoint
	 * @param int    $ptr       Where in the text to try the match
	 *
	 * @return array|false The position each input glyph matched at, or false if the rule did not match
	 */
	private function checkContextMatch($Input, $Backtrack, $Lookahead, $ignore, $ptr)
	{
		// Input etc are single numbers - GSUB Format 6.1
		// Input starts with (1=>xxx)
		// return false if no match, else an array of ptr for matches (0=>0, 1=>3,...)

		$current_syllable = (isset($this->OTLdata[$ptr]['syllable']) ? $this->OTLdata[$ptr]['syllable'] : 0);

		// BACKTRACK
		$checkpos = $ptr;
		for ($i = 0; $i < count($Backtrack); $i++) {
			$checkpos--;
			while (isset($this->OTLdata[$checkpos]) && isset($ignore[$this->OTLdata[$checkpos]['uni']])) {
				$checkpos--;
			}
			// If outside scope of current syllable - return no match
			if ($this->restrictToSyllable && isset($this->OTLdata[$checkpos]['syllable']) && $this->OTLdata[$checkpos]['syllable'] != $current_syllable) {
				return false;
			} elseif (!isset($this->OTLdata[$checkpos]) || $this->OTLdata[$checkpos]['uni'] != $Backtrack[$i]) {
				return false;
			}
		}

		// INPUT
		$matched = [0 => $ptr];
		$checkpos = $ptr;
		for ($i = 1; $i < count($Input); $i++) {
			$checkpos++;
			while (isset($this->OTLdata[$checkpos]) && isset($ignore[$this->OTLdata[$checkpos]['uni']])) {
				$checkpos++;
			}
			// If outside scope of current syllable - return no match
			if ($this->restrictToSyllable && isset($this->OTLdata[$checkpos]['syllable']) && $this->OTLdata[$checkpos]['syllable'] != $current_syllable) {
				return false;
			} elseif (isset($this->OTLdata[$checkpos]) && $this->OTLdata[$checkpos]['uni'] == $Input[$i]) {
				$matched[] = $checkpos;
			} else {
				return false;
			}
		}

		// LOOKAHEAD
		for ($i = 0; $i < count($Lookahead); $i++) {
			$checkpos++;
			while (isset($this->OTLdata[$checkpos]) && isset($ignore[$this->OTLdata[$checkpos]['uni']])) {
				$checkpos++;
			}
			// If outside scope of current syllable - return no match
			if ($this->restrictToSyllable && isset($this->OTLdata[$checkpos]['syllable']) && $this->OTLdata[$checkpos]['syllable'] != $current_syllable) {
				return false;
			} elseif (!isset($this->OTLdata[$checkpos]) || $this->OTLdata[$checkpos]['uni'] != $Lookahead[$i]) {
				return false;
			}
		}

		return $matched;
	}

	/**
	 * Match the glyphs around $ptr against a sequence of sets, one per position.
	 *
	 * Every contextual and chaining format that matches more than one glyph at a position comes here -
	 * GSUB 5.2, 5.3, 6.2, 6.3 and GPOS 7.2, 7.3, 8.2, 8.3 - whether the sets are classes of a ClassDef
	 * or Coverage tables. Each set is a map of unicode => 1, so a position is one hash lookup; the
	 * coverage-based formats used to pass their tables as "00641|00642|..." strings and be matched with
	 * strpos(), which reads from the front of the table for every position of every rule.
	 *
	 * Position 0 of $Input is not read - the caller has already matched the glyph at $ptr against it.
	 *
	 * @param array $ignore     Characters to walk past at every position, as a map of unicode => 1
	 * @param array $class0excl The glyphs in every class but 0, which is what a rule naming class 0 at
	 *                          an input position matches anything but (GSUB 5.2, 6.2, GPOS 7.2, 8.2)
	 * @param array $bclass0excl Likewise for backtrack positions, $lclass0excl for lookahead
	 *                           (GSUB 6.2, GPOS 8.2)
	 *
	 * @return array|false Position in OTLdata of each glyph of the matched input sequence, false if the
	 *                     context does not match
	 */
	private function checkContextMatchMultiple($Input, $Backtrack, $Lookahead, $ignore, $ptr, $class0excl = [], $bclass0excl = [], $lclass0excl = [])
	{

		$current_syllable = (isset($this->OTLdata[$ptr]['syllable']) ? $this->OTLdata[$ptr]['syllable'] : 0);

		// BACKTRACK
		$checkpos = $ptr;
		for ($i = 0; $i < count($Backtrack); $i++) {
			$checkpos--;
			while (isset($this->OTLdata[$checkpos]) && isset($ignore[$this->OTLdata[$checkpos]['uni']])) {
				$checkpos--;
			}
			// If outside scope of current syllable - return no match
			if ($this->restrictToSyllable && isset($this->OTLdata[$checkpos]['syllable']) && $this->OTLdata[$checkpos]['syllable'] != $current_syllable) {
				return false;
			} // If Class 0 specified, matches anything NOT in $bclass0excl
			elseif (!$Backtrack[$i] && isset($this->OTLdata[$checkpos]) && isset($bclass0excl[$this->OTLdata[$checkpos]['uni']])) {
				return false;
			} elseif (!isset($this->OTLdata[$checkpos]) || !isset($Backtrack[$i][$this->OTLdata[$checkpos]['uni']])) {
				return false;
			}
		}

		// INPUT
		$matched = [0 => $ptr];
		$checkpos = $ptr;
		for ($i = 1; $i < count($Input); $i++) { // Start at 1 - already matched the first InputGlyph
			$checkpos++;
			while (isset($this->OTLdata[$checkpos]) && isset($ignore[$this->OTLdata[$checkpos]['uni']])) {
				$checkpos++;
			}
			// If outside scope of current syllable - return no match
			if ($this->restrictToSyllable && isset($this->OTLdata[$checkpos]['syllable']) && $this->OTLdata[$checkpos]['syllable'] != $current_syllable) {
				return false;
			} // If Input Class 0 specified, matches anything NOT in $class0excl
			elseif (!$Input[$i] && isset($this->OTLdata[$checkpos]) && !isset($class0excl[$this->OTLdata[$checkpos]['uni']])) {
				$matched[] = $checkpos;
			} elseif (isset($this->OTLdata[$checkpos]) && isset($Input[$i][$this->OTLdata[$checkpos]['uni']])) {
				$matched[] = $checkpos;
			} else {
				return false;
			}
		}

		// LOOKAHEAD
		for ($i = 0; $i < count($Lookahead); $i++) {
			$checkpos++;
			while (isset($this->OTLdata[$checkpos]) && isset($ignore[$this->OTLdata[$checkpos]['uni']])) {
				$checkpos++;
			}
			// If outside scope of current syllable - return no match
			if ($this->restrictToSyllable && isset($this->OTLdata[$checkpos]['syllable']) && $this->OTLdata[$checkpos]['syllable'] != $current_syllable) {
				return false;
			} // If Class 0 specified, matches anything NOT in $lclass0excl
			elseif (!$Lookahead[$i] && isset($this->OTLdata[$checkpos]) && isset($lclass0excl[$this->OTLdata[$checkpos]['uni']])) {
				return false;
			} elseif (!isset($this->OTLdata[$checkpos]) || !isset($Lookahead[$i][$this->OTLdata[$checkpos]['uni']])) {
				return false;
			}
		}
		return $matched;
	}

	/**
	 * A Class Definition table as a list per class, for GPOS pair positioning, which needs a glyph's
	 * position within its class rather than only its membership.
	 *
	 * Class 0 is kept here, unlike in _getClasses: a PairPos subtable counts it among its classes and
	 * indexes its value records by class number.
	 *
	 * @return array class => list of unicodes, in the table's own order
	 */
	private function _getClassDefinitionTable($offset)
	{
		if (!isset($this->LuDataCache[$this->otlCacheKey]['classDef'][$offset])) {
			$this->reader->seek($offset);
			$this->LuDataCache[$this->otlCacheKey]['classDef'][$offset] = array_map([$this, 'charsOf'], ClassDef::glyphsByClass($this->reader));
		}

		return $this->LuDataCache[$this->otlCacheKey]['classDef'][$offset];
	}

	/**
	 * The characters a Lookup's flag says to skip over, as a set keyed by codepoint.
	 *
	 * Every position a rule tests is first walked past the characters this names, so the test is made
	 * for every position of every rule of every subtable offered a glyph. It used to be made with
	 * strpos() over a "00641|00642|..." string of every mark the font defines - 71 KB of it in Noto
	 * Sans Duployan - and scanning that was where nearly all the time went in shaping a word of a font
	 * whose rules run into the thousands.
	 *
	 * The set is a property of the flag and of GDEF, so it is built once per flag.
	 *
	 * @return array map of unicode => 1
	 */
	private function getGCOMignoreSet($flag, $MarkFilteringSet)
	{
		$key = $flag . ':' . $MarkFilteringSet;

		if (!isset($this->LuDataCache[$this->otlCacheKey]['ignore'][$key])) {
			$set = [];
			foreach (explode('|', $this->lookupFlag->glyphs($flag, $MarkFilteringSet)) as $hex) {
				if ($hex !== '') {
					$set[hexdec($hex)] = 1;
				}
			}

			$this->LuDataCache[$this->otlCacheKey]['ignore'][$key] = $set;
		}

		return $this->LuDataCache[$this->otlCacheKey]['ignore'][$key];
	}

	/**
	 * @deprecated Use Mpdf\Shaper\OtlData::split()
	 */
	public function splitOTLdata(&$cOTLdata, $OTLcutoffpos, $OTLrestartpos = '')
	{
		return OtlData::split($cOTLdata, $OTLcutoffpos, $OTLrestartpos);
	}

	/**
	 * @deprecated Use Mpdf\Shaper\OtlData::slice()
	 */
	public function sliceOTLdata($OTLdata, $pos, $len)
	{
		return OtlData::slice($OTLdata, $pos, $len);
	}

	/**
	 * @deprecated Use Mpdf\Shaper\OtlData::prependChar()
	 */
	public function prependOTLchar(&$cOTLdata, $charData, $group)
	{
		OtlData::prependChar($cOTLdata, $charData, $group);
	}

	/**
	 * @deprecated Use Mpdf\Shaper\OtlData::removeChar()
	 */
	public function removeChar(&$txt, &$cOTLdata, $char)
	{
		OtlData::removeChar($txt, $cOTLdata, $char, $this->mpdf->mb_enc);
	}

	/**
	 * @deprecated Use Mpdf\Shaper\OtlData::nbspToSpace()
	 */
	public function replaceSpace(&$txt, &$cOTLdata)
	{
		OtlData::nbspToSpace($txt, $cOTLdata, $this->mpdf->mb_enc);
	}

	/**
	 * @deprecated Use Mpdf\Shaper\OtlData::trim()
	 */
	public function trimOTLdata(&$cOTLdata, $Left = true, $Right = true)
	{
		OtlData::trim($cOTLdata, $Left, $Right);
	}

	/**
	 * @param int $gid A glyph id
	 *
	 * @return int The character it stands for, from the map the parser built
	 */
	private function glyphToChar($gid)
	{
		return (ord($this->glyphIDtoUni[$gid * 3]) << 16) + (ord($this->glyphIDtoUni[$gid * 3 + 1]) << 8) + ord($this->glyphIDtoUni[$gid * 3 + 2]);
	}

	/**
	 * The glyph IDs a Coverage table covers, for a Single Substitution Format 1, which adds a delta
	 * to a glyph ID rather than naming a replacement.
	 *
	 * Cached apart from coverageIndexByHex below: the same table, projected differently.
	 */
	private function _getCoverageGID()
	{
		$offset = $this->reader->tell();

		if (!isset($this->LuDataCache[$this->otlCacheKey]['coverageGID'][$offset])) {
			$this->LuDataCache[$this->otlCacheKey]['coverageGID'][$offset] = Coverage::glyphs($this->reader);
		}

		return $this->LuDataCache[$this->otlCacheKey]['coverageGID'][$offset];
	}

	/**
	 * The characters a Coverage table covers, each with its Coverage Index, for the mark attachment
	 * subtables, which find the glyph a mark attaches to and then index a parallel array by it.
	 *
	 * @return int[] hex => Coverage Index, the first where two glyphs stand for one character
	 */
	private function coverageIndexByHex()
	{
		$offset = $this->reader->tell();

		if (!isset($this->LuDataCache[$this->otlCacheKey]['coverageIndex'][$offset])) {
			$indexes = [];
			foreach (Coverage::glyphs($this->reader) as $index => $glyphID) {
				$hex = GlyphString::of($this->glyphToChar($glyphID));
				if (!isset($indexes[$hex])) {
					$indexes[$hex] = $index;
				}
			}

			$this->LuDataCache[$this->otlCacheKey]['coverageIndex'][$offset] = $indexes;
		}

		return $this->LuDataCache[$this->otlCacheKey]['coverageIndex'][$offset];
	}

	/**
	 * The characters a Coverage table covers, as a set keyed by codepoint.
	 *
	 * This is what the contextual formats match against, and they match a position against a whole
	 * Coverage table at a time, so what matters is that one test is one hash lookup. They used to join
	 * the table into a "00641|00642|..." string and search that with strpos(), which reads the table
	 * from the front for every position of every rule: for a font whose chaining rules run into the
	 * thousands and whose Coverage tables name thousands of glyphs, that scanning was most of the time
	 * spent shaping a word.
	 *
	 * Cached apart from coverageIndexByHex above: the same table, projected differently.
	 *
	 * @return array map of unicode => 1
	 */
	private function getCoverageUni()
	{
		$offset = $this->reader->tell();

		if (!isset($this->LuDataCache[$this->otlCacheKey]['coverageUni'][$offset])) {
			$g = [];
			foreach (Coverage::glyphs($this->reader) as $glyphID) {
				$g[$this->glyphToChar($glyphID)] = 1;
			}

			$this->LuDataCache[$this->otlCacheKey]['coverageUni'][$offset] = $g;
		}

		return $this->LuDataCache[$this->otlCacheKey]['coverageUni'][$offset];
	}

	/**
	 * The character each glyph of a sequence stands for.
	 *
	 * What a Format 1 rule lists is glyph ids, and what the run being shaped holds is characters, so
	 * every glyph sequence read from a rule is translated before it is matched against anything.
	 *
	 * @param int[] $glyphIDs In glyph sequence order
	 *
	 * @return int[] One character per position
	 */
	private function charsOf(array $glyphIDs)
	{
		$chars = [];
		foreach ($glyphIDs as $glyphID) {
			$chars[] = $this->glyphToChar($glyphID);
		}

		return $chars;
	}

	/**
	 * The characters each position of a class sequence matches.
	 *
	 * A class the table does not define is left empty rather than absent, which is how
	 * checkContextMatchMultiple reads class 0 - and a class no glyph is in is class 0 in everything
	 * but name.
	 *
	 * @param array $classes      class => map of unicode => 1, as _getClasses returns it
	 * @param int[] $classIndices The class each position names, in glyph sequence order
	 *
	 * @return array One set per position
	 */
	private function classSets(array $classes, array $classIndices)
	{
		$sets = [];
		foreach ($classIndices as $i => $class) {
			$sets[$i] = isset($classes[$class]) ? $classes[$class] : '';
		}

		return $sets;
	}

	/**
	 * The characters each position of a Format 3 sequence matches, by following its Coverage tables.
	 *
	 * @param int[] $offsets Absolute, from the start of the file, in glyph sequence order
	 *
	 * @return array One set per position
	 */
	private function coverageSets(array $offsets)
	{
		$sets = [];
		foreach ($offsets as $i => $offset) {
			$this->reader->seek($offset);
			$sets[$i] = $this->getCoverageUni();
		}

		return $sets;
	}

	/**
	 * A Class Definition table as a set per class, for testing whether a character is in one.
	 *
	 * Class 0 is dropped. The spec makes it the class of every glyph the table does not mention, so a
	 * font that assigns it explicitly is saying nothing - except FreeSerif under "blws", which defines
	 * class 0 and appears to mean something by it. Whatever it means, mPDF has never acted on it.
	 *
	 * A glyph no character reaches is dropped too: there is no character for a rule to match.
	 *
	 * @return array class => map of unicode => 1
	 */
	private function _getClasses($offset)
	{
		if (!isset($this->LuDataCache[$this->otlCacheKey]['classes'][$offset])) {
			$this->reader->seek($offset);
			$GlyphByClass = [];

			foreach (ClassDef::pairs($this->reader) as $pair) {
				list($glyphID, $class) = $pair;
				$uni = $this->glyphToChar($glyphID);

				if ($class > 0 && $uni) {
					$GlyphByClass[$class][$uni] = 1;
				}
			}

			$this->LuDataCache[$this->otlCacheKey]['classes'][$offset] = $GlyphByClass;
		}

		return $this->LuDataCache[$this->otlCacheKey]['classes'][$offset];
	}

	/**
	 * Every glyph a Class Definition table puts in a class other than 0.
	 *
	 * Class 0 is every glyph the table does not name, so a rule that matches class 0 at a position
	 * matches anything that is not in one of the other classes - and this is the set it is tested
	 * against. It is a property of the table, so it is worked out once per table and kept beside the
	 * classes themselves.
	 *
	 * Every contextual format that matches by class used to build it again for each rule it read, and
	 * each build walks every glyph of every class. A class-based chaining subtable of a Nastaliq font
	 * offers hundreds of rules at a glyph and its classes name thousands of glyphs between them, so
	 * that was most of the cost of shaping one.
	 *
	 * The loop stops at the number of classes rather than at the highest class number, which is how it
	 * has always read: _getClasses() drops a class whose glyphs no character reaches, so the two are
	 * not always the same, and a table with a gap in its class numbers leaves the classes above the
	 * gap out of the set.
	 *
	 * @return array map of unicode => 1
	 */
	private function getClassZeroExclusions($offset)
	{
		if (!isset($this->LuDataCache[$this->otlCacheKey]['class0excl'][$offset])) {
			$classes = $this->_getClasses($offset);
			$excluded = [];

			for ($class = 1; $class <= count($classes); $class++) {
				if (isset($classes[$class]) && is_array($classes[$class])) {
					$excluded = $excluded + $classes[$class];
				}
			}

			$this->LuDataCache[$this->otlCacheKey]['class0excl'][$offset] = $excluded;
		}

		return $this->LuDataCache[$this->otlCacheKey]['class0excl'][$offset];
	}
}
