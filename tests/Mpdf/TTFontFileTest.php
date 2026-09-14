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
	 * @throws MpdfException
	 */
	public function set_up()
	{
		parent::set_up();

		$this->ttf = new TTFontFile(
			new FontCache(
				new Cache(__DIR__ . '/tmp/mpdf/ttfontdata')
			),
			'win'
		);
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
	 * Parse a font, collecting every diagnostic PHP raised doing it. Deprecations are not converted to
	 * exceptions, so a handler is what sees them.
	 *
	 * @return string[]
	 */
	private function diagnosticsWhileParsing($file)
	{
		$raised = [];

		set_error_handler(function ($number, $message, $path, $line) use (&$raised) {
			$raised[] = sprintf('%s in %s:%d', $message, basename($path), $line);

			return true;
		});

		try {
			$this->ttf->getMetrics(__DIR__ . '/../data/ttf/' . $file, uniqid('', true), 0, false, false, 0xFF);
		} finally {
			restore_error_handler();
		}

		return $raised;
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
	 * Everything the parser extracted. The reader it extracted them with is not public, so it does
	 * not appear here and its position does not have to be excluded.
	 */
	private function metrics($file, $debug)
	{
		$ttf = new TTFontFile(new FontCache(new Cache(__DIR__ . '/tmp/mpdf/ttfontdata')), 'win');
		$ttf->getMetrics(__DIR__ . '/../data/ttf/' . $file, uniqid('', true), 0, $debug, false, 0xFF);

		$vars = get_object_vars($ttf);
		unset($vars['fontkey'], $vars['fontCache']);

		return $vars;
	}

}
