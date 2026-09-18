<?php

namespace Mpdf;

use Mpdf\Fonts\FontCache;

class TTFontFileTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{
	/**
	 * @var TTFontFile
	 */
	protected $ttf;

	/**
	 * @var FontCache
	 */
	protected $fontCache;

	/**
	 * @throws MpdfException
	 */
	public function set_up()
	{
		parent::set_up();

		$this->fontCache = new FontCache(new Cache(__DIR__ . '/tmp/mpdf/ttfontdata'));
		$this->ttf = new TTFontFile($this->fontCache, 'win');
	}

	/**
	 * Verify fonts can be successfully parsed when useOTL is enabled, without throwing any PHP notices/warnings
	 */
	public function testGetMetricWithOtl()
	{
		$this->ttf->getMetrics(__DIR__ . '/../data/ttf/Poppins-Regular.ttf', (string) time(), 0, false, false, 0xFF);
		$this->assertSame('Poppins-Regular', $this->ttf->fullName);

		$this->ttf->getMetrics(__DIR__ . '/../data/ttf/NotoSans-Regular.ttf', (string) time(), 0, false, false, 0xFF);
		$this->assertSame('NotoSans-Regular', $this->ttf->fullName);
	}

	/**
	 * A key of rtlSUB is a codepoint written as hex, and PHP reads some of those as numbers: '0E007' is
	 * exponent notation for 0, while '0072A' beside it is not numeric at all. Sorting the table by the
	 * default comparison therefore orders it by a relation that is not transitive, and which order that
	 * settles on is down to the sort the PHP version happens to use - a font carrying both kinds of key
	 * wrote one cache on PHP 5.6 and another on 8.5, so its fontcache master could only pass on one of
	 * them.
	 */
	public function testTheRightToLeftFormTableIsKeyedInAnOrderEveryPhpVersionAgreesOn()
	{
		$fontkey = uniqid('rtlsub', true);
		$this->ttf->getMetrics(__DIR__ . '/../data/ttf/NotoSansSyriac-Joining-Subset.ttf', $fontkey, 0, false, false, 0xFF);

		$cached = $this->fontCache->jsonLoad($fontkey . '.GSUB.syrc.DFLT.json');
		$keys = array_keys($cached['rtlSUB']);

		$sorted = $keys;
		sort($sorted, SORT_STRING);

		$this->assertSame($sorted, $keys);
	}

	/**
	 * Verify a font whose GSUB lookups carry UseMarkFilteringSet parses rather than throwing
	 */
	public function testGetMetricsWithMarkGlyphSets()
	{
		$this->ttf->getMetrics(__DIR__ . '/../data/ttf/NotoSansSinhala-Subset.ttf', (string) time(), 0, false, false, 0xFF);
		$this->assertSame('NotoSansSinhala-Regular', $this->ttf->fullName);
	}

	/**
	 * Verify a font carrying a GSUB Lookup Type 5, Format 3 subtable parses rather than throwing
	 */
	public function testGetMetricsWithACoverageBasedContextLookup()
	{
		$this->ttf->getMetrics(__DIR__ . '/../data/ttf/NotoSansTakri-GSUB53-Subset.ttf', (string) time(), 0, false, false, 0xFF);
		$this->assertSame('NotoSansTakri-Regular', $this->ttf->fullName);

		$this->ttf->getMetrics(__DIR__ . '/../data/ttf/NotoSansSaurashtra-GSUB53-Subset.ttf', (string) time(), 0, false, false, 0xFF);
		$this->assertSame('NotoSansSaurashtra-Regular', $this->ttf->fullName);
	}

	/**
	 * A reverse chaining Lookup is read at metrics time like any other, so a font carrying one has to
	 * get through getMetrics() before its glyphs can ever be shaped
	 */
	public function testGetMetricsWithAReverseChainingLookup()
	{
		$this->ttf->getMetrics(__DIR__ . '/../data/ttf/NotoSansCoptic-GSUB81-Subset.ttf', (string) time(), 0, false, false, 0xFF);

		$this->assertSame('NotoSansCoptic-Regular', $this->ttf->fullName);
	}

	/**
	 * MarkGlyphSetsDef coverage offsets are relative to that table, not to the file. Seeking to them as
	 * absolute offsets lands in the table directory and yields empty sets, which silently disables every
	 * UseMarkFilteringSet lookup. U+0DCA/U+0DD2/U+0DD3 are the subset's Sinhala marks; the second set's
	 * glyph has no cmap entry, so it is mapped into the Private Use Area.
	 */
	public function testGetMetricsReadsMarkGlyphSetsCoverage()
	{
		$this->ttf->getMetrics(__DIR__ . '/../data/ttf/NotoSansSinhala-Subset.ttf', (string) time(), 0, false, false, 0xFF);

		$this->assertSame([' 00DCA| 00DD2| 00DD3', ' 0E00A'], $this->ttf->MarkGlyphSets);
	}

	/**
	 * The Indic pre-pass that indexes 'locl' substitutions by their replacement read ['replace'] on
	 * every entry tagged 'locl'. A contextual entry keeps its replacements in ['rules'] and has no
	 * such key, so it read null once per font load.
	 *
	 * The subset is the contextual 'locl' of Noto Sans Devanagari 2.005 (OFL 1.1): a chained context
	 * under the Santali language system whose backtrack is U+0905 or U+0906 and whose nested lookup
	 * substitutes a Santali nukta for U+093C. Nothing is indexed from it either way, so the
	 * diagnostic is all there was to fix.
	 */
	public function testAContextualLoclEntryIsNotReadForATopLevelReplacement()
	{
		$raised = $this->diagnosticsWhileParsing('NotoSansDevanagari-ContextualLocl-Subset.ttf');

		$this->assertSame([], $raised);
		$this->assertSame('NotoSansDevanagari-Regular', $this->ttf->fullName);
	}

	/**
	 * A joining form can be a Multiple Substitution, so the replacement recorded for one can name more
	 * than one glyph. The whole string was pushed into the Private Use Area list as if it named one,
	 * and hexdec() raised PHP 8's invalid-characters deprecation on it and returned a number far
	 * outside the PUA, so every glyph in it was dropped.
	 *
	 * The subset is Noto Sans Arabic 2.012 (OFL 1.1) cut down to U+0628 and U+06CC. The Farsi Yeh's
	 * initial and medial forms are Multiple Substitutions replacing it with the dotless form and the
	 * pair of dots to draw under it - E007 here, which has no codepoint of its own and is the glyph
	 * the whole-string push lost.
	 */
	public function testAJoiningFormOfMoreThanOneGlyphContributesEachOfThem()
	{
		$raised = $this->diagnosticsWhileParsing('NotoSansArabic-MultipleForm-Subset.ttf');

		$this->assertSame([], $raised);
		$this->assertSame('\x{0E000}-\x{0E003}\x{0E005}-\x{0E007}', $this->ttf->rtlPUAstr);
	}

	/**
	 * \Mpdf\Otl looks one glyph up in the kashida `finals` string, so a final form of several glyphs
	 * belongs in it as the base alone. Held whole, an entry put its marks in as final forms of their
	 * own and hid its first glyph from the exclusion list beside it, which is tested the same way:
	 * U+06CC's form here begins with U+FEAE, one of the fifteen, and all four codes went in.
	 *
	 * @see \Mpdf\KashidaFinalFormTest, for what the shaper then made of them
	 */
	public function testAFinalFormOfMoreThanOneGlyphContributesOnlyTheBaseItIsDrawnOn()
	{
		$fontkey = uniqid('', true);
		$this->ttf->getMetrics(__DIR__ . '/../data/ttf/NotoSansArabic-MultipleFinal-Subset.ttf', $fontkey, 0, false, false, 0xFF);

		$gsub = $this->fontCache->jsonLoad($fontkey . '.GSUB.arab.DFLT.json');

		$this->assertSame('0E001 0E005', $gsub['rtlSUB']['00628'][1]);
		$this->assertSame('0FEAE 0E006', $gsub['rtlSUB']['006CC'][1]);
		$this->assertSame('0E001 ', $gsub['finals']);
	}

	/**
	 * The parser's half of the modulo the spec adds a Single Substitution Format 1 delta by. Adding
	 * without it lands outside glyphToChar, and unicode_hex() made U+0000 of the null that came back,
	 * so the rule named the null character. @see DeltaGlyphIdTest for the shaper's half and for the
	 * fonts that wrap.
	 *
	 * The synthetic covers glyph 32770 and adds 32767, so the sum is 65537 and the substitute is glyph
	 * 1 - the only glyph in the font with no character of its own, which is why it is the only one in
	 * the Private Use Area. A font can only spell a wrap with tens of thousands of glyphs: an int16
	 * delta cannot carry a sum past either end from anywhere closer.
	 */
	public function testASingleSubstitutionDeltaIsAddedModulo65536()
	{
		$raised = $this->diagnosticsWhileParsing('NotoSansArabic-GSUB11Wrap-Synthetic.ttf');

		$this->assertSame([], $raised);
		$this->assertSame('\x{0E000}', $this->ttf->rtlPUAstr);
	}

	/**
	 * A lookup flag naming a mark attachment class says to skip every mark outside that class. A font
	 * may name a class GDEF does not define - Carlito and NATS both set the flag without a
	 * MarkAttachClassDef table at all - and then no mark is in the class, so every one of them is
	 * skipped. Reading MarkAttachmentType by the class instead gave null, which is no marks skipped.
	 *
	 * The subset is Carlito 1.104 (OFL 1.1) cut to U+0069, U+006A and U+0313, keeping the two 'ccmp'
	 * chained contexts that carry the flag and the dotless forms they substitute.
	 */
	public function testALookupNamingAMarkAttachmentClassTheFontDoesNotDefineParses()
	{
		$raised = $this->diagnosticsWhileParsing('Carlito-MarkAttachmentType-Subset.ttf');

		$this->assertSame([], $raised);
		$this->assertSame('Carlito-Regular', $this->ttf->fullName);
	}

	/**
	 * Class 0 of a Class Definition is every glyph the other classes do not name, so it has no list of
	 * glyphs to match against and mPDF matches nothing at such a position. Every input position above
	 * the first already said so; the first read InputClasses by the class and got null.
	 *
	 * The subset is Molengo 0.11 (OFL 1.1) cut to U+0069, U+006A, U+0268 and the marks its one 'ccmp'
	 * lookup classifies. That lookup's only rule set is class 0's, and its Coverage holds nothing but
	 * glyphs the font puts in class 1, so the rules it states cannot fire either way.
	 */
	public function testAContextRuleSetForClassZeroIsReadLikeEveryOther()
	{
		$raised = $this->diagnosticsWhileParsing('Molengo-GSUB52Class0-Subset.ttf');

		$this->assertSame([], $raised);
		$this->assertSame('Molengo-Regular', $this->ttf->fullName);
	}

	/**
	 * A feature that runs no lookups is not offered to the shaper: there is nothing for it to do, and
	 * the lookup index the features are ordered by is the one it has not got.
	 *
	 * The subset is Sedan SC 1.100 (OFL 1.1), whose 'smcp' lists no lookups at all. The subsetter drops
	 * a feature with none, so it is put back. The 'aalt' and 'c2sc' left in it are what says the real
	 * features still come out in lookup order.
	 */
	public function testAFeatureThatRunsNoLookupsIsNotOffered()
	{
		$raised = $this->diagnosticsWhileParsing('SedanSC-EmptyFeature-Subset.ttf');

		$this->assertSame([], $raised);
		$this->assertSame(
			['aalt' => [0, 1], 'c2sc' => [2], 'ccmp' => [3]],
			$this->ttf->GSUBFeatures['latn']['DFLT']
		);
	}

	/**
	 * The two bytes at the head of the width table record how many characters it covers, and the filter
	 * that fills the table admits characters up to 196,607 - three times what the field can hold. chr()
	 * raises PHP 8's out-of-range deprecation on the overflow, which is fatal to anyone promoting
	 * warnings, and writes a header that reads back as a number the font never had.
	 *
	 * The font is 256 blank glyphs cycled through a format 12 cmap over U+0020-U+101EF, which is 65,999
	 * characters once U+FFFF is dropped - enough to reach past the field, and 7 KB of doing it. Adobe
	 * Blank, where this was found, counts 194,521.
	 */
	public function testTheCharacterCountHeaderStopsAtWhatTwoBytesHold()
	{
		$raised = $this->diagnosticsWhileParsing('Blank-WideCmap-Synthetic.ttf', 0);

		$this->assertSame([], $raised);
		$this->assertSame(0xFFFF, $this->characterCount($this->ttf->charWidths));
	}

	/**
	 * Under the limit the header is still the font's own count. Writer\FontWriter reads these two bytes
	 * back and divides by them, which is why they cannot simply be left zeroed.
	 */
	public function testTheCharacterCountHeaderIsTheCountWhereItFits()
	{
		$raised = $this->diagnosticsWhileParsing('Poppins-Regular.ttf', 0);

		$this->assertSame([], $raised);
		$this->assertSame(470, $this->characterCount($this->ttf->charWidths));
	}

	/**
	 * debugfont mode validates each table's version as it goes, so it walks the byte offsets by a
	 * different route than normal mode and has its own chance to lose its place
	 */
	public function testGetMetricWithOtlAndFontDebug()
	{
		$this->ttf->getMetrics(__DIR__ . '/../data/ttf/Poppins-Regular.ttf', uniqid('', true), 0, true, false, 0xFF);
		$this->assertSame('Poppins-Regular', $this->ttf->fullName);

		$this->ttf->getMetrics(__DIR__ . '/../data/ttf/NotoSans-Regular.ttf', uniqid('', true), 0, true, false, 0xFF);
		$this->assertSame('NotoSans-Regular', $this->ttf->fullName);
	}

	/**
	 * Not throwing is not enough - a misplaced skip() can read plausible garbage. The two modes
	 * differ only in what they validate, so everything they extract has to agree.
	 *
	 * @dataProvider fontProvider
	 */
	public function testFontDebugReadsTheSameMetricsAsNormalMode($file, $name)
	{
		$normal = $this->metrics($file, false);
		$debug = $this->metrics($file, true);

		// fontRevision is only read when validating the head table, so normal mode leaves it null
		unset($normal['fontRevision'], $debug['fontRevision']);

		$this->assertSame($name, $normal['fullName']);
		$this->assertEquals($normal, $debug);
	}

	public function fontProvider()
	{
		return [
			['Poppins-Regular.ttf', 'Poppins-Regular'],
			['NotoSans-Regular.ttf', 'NotoSans-Regular'],
			['Manjari-Regular.ttf', 'Manjari-Regular'],
		];
	}

	/**
	 * Parse a font, collecting every diagnostic PHP raised doing it. Deprecations are not converted to
	 * exceptions, so a handler is what sees them.
	 *
	 * @return string[]
	 */
	private function diagnosticsWhileParsing($file, $useOTL = 0xFF)
	{
		$raised = [];

		set_error_handler(function ($number, $message, $path, $line) use (&$raised) {
			$raised[] = sprintf('%s in %s:%d', $message, basename($path), $line);

			return true;
		});

		try {
			$this->ttf->getMetrics(__DIR__ . '/../data/ttf/' . $file, uniqid('', true), 0, false, false, $useOTL);
		} finally {
			restore_error_handler();
		}

		return $raised;
	}

	/**
	 * @return int The number of characters the width table says it covers
	 */
	private function characterCount($charWidths)
	{
		return (ord($charWidths[0]) << 8) + ord($charWidths[1]);
	}

	/**
	 * Everything the parser extracted. The reader it extracted them with is not public, so it does
	 * not appear here and its position does not have to be excluded.
	 */
	private function metrics($file, $debug)
	{
		$ttf = new TTFontFile($this->fontCache, 'win');
		$ttf->getMetrics(__DIR__ . '/../data/ttf/' . $file, uniqid('', true), 0, $debug, false, 0xFF);

		$vars = get_object_vars($ttf);
		unset($vars['fontkey'], $vars['fontCache']);

		return $vars;
	}

}
