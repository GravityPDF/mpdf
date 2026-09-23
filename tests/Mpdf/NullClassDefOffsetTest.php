<?php

namespace Mpdf;

use Mpdf\Fonts\FontCache;

/**
 * What a class-based context subtable that states no Class Definition table is read as.
 *
 * A chained context Format 2 subtable with no backtrack or lookahead sets those ClassDef offsets to
 * 0, and every glyph is then in class 0 for that sequence. Adding a 0 offset to the subtable's own
 * start reads the subtable header as a ClassDef instead: its format field reads as the ClassDef
 * format, one of its offsets as the range count, and the ranges are read past the end of the table -
 * 74,000 reads for one page of Newa text in the Noto Sans Newa macOS ships, which is where this was
 * found (#326). The classes that reading fabricates are not empty, so it is not only noise: a rule
 * can match on a class the font never defined.
 *
 * NotoSans-NullClassDef-Synthetic is the glyph set, cmap and GDEF of NotoSans-GSUBClassZero-Synthetic
 * beside it - Noto Sans cut down to space, A, B and C with a few unmapped glyphs - given a GSUB of
 * three lookups:
 *
 *   #0  single substitution, B to b.sc
 *   #1  single substitution, B to a.sc
 *   #2  a chained context, Format 2, covering A, whose backtrack and lookahead ClassDef offsets are
 *       both 0 and whose input ClassDef puts A in class 1 and B in class 2. Its rule set for input
 *       class 1 holds two rules, tried in order:
 *         #0  A then class 2, with one lookahead position of class 1, running lookup #0 there
 *         #1  A then class 2, with no lookahead, running lookup #1 there
 *
 * `calt` is the only feature, on DFLT and latn, and runs lookup #2 alone.
 *
 * With no lookahead ClassDef nothing is in class 1, so rule #0 can never match and rule #1 draws
 * a.sc for every B after an A. That is what `hb-shape` makes of the same font. Read as a ClassDef the
 * subtable header puts A and the space glyph in class 1, so rule #0 did match and mPDF drew b.sc.
 *
 * The rule names class 1 rather than the class 0 a real font would state at a position it has no
 * ClassDef for, because class 1 is where the header, read as a ClassDef, puts A and the space.
 * NotoSans-ClassZeroContext-Synthetic, built from this font, covers class 0 at those positions.
 */
class NullClassDefOffsetTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	const FONT = 'NotoSans-NullClassDef-Synthetic';

	/**
	 * a.sc, which the second rule substitutes. The glyph has no codepoint of its own, so it is mapped
	 * into the Private Use Area - U+E001, the second unmapped glyph the font carries.
	 */
	const A_SMALL_CAP = 0xE001;

	/**
	 * @var string[] What PHP raised while the recording handler was installed
	 */
	private $raised = [];

	/**
	 * @var string[] The temporary directories the rendered documents were given
	 */
	private $tempDirs = [];

	/**
	 * Each rendered document is given a directory of its own, so they are taken away again here
	 * rather than left in the system temporary directory.
	 */
	public function tear_down()
	{
		foreach ($this->tempDirs as $dir) {
			$this->remove($dir);
		}

		parent::tear_down();
	}

	/**
	 * Generating the metrics reads every GSUB rule in the font, so a document reaches this whatever
	 * its text. A caller that promotes warnings to exceptions cannot parse the font at all.
	 */
	public function testGeneratingMetricsReadsNoClassDefAtTheSubtableItself()
	{
		$this->parse();

		$this->assertSame([], $this->raised);
	}

	/**
	 * The shaper reads the subtable again out of the cached GSUB, once for each glyph the subtable's
	 * Coverage table matches.
	 */
	public function testShapingReadsNoClassDefAtTheSubtableItself()
	{
		$this->draw('<p>ABA</p>');

		$this->assertSame([], $this->raised);
	}

	/**
	 * The rule that names class 1 at its lookahead position never fires, so the run is drawn by the
	 * rule behind it.
	 *
	 * @dataProvider dataRuns
	 *
	 * @param string $html       What the document is written from
	 * @param int[]  $codepoints The codepoints of the line the drawing code is handed
	 */
	public function testARuleNamingAClassOfAnAbsentClassDefIsNotMatched($html, array $codepoints)
	{
		$mpdf = $this->draw($html);

		$this->assertSame($codepoints, $mpdf->drawnCodepoints(0));
	}

	/**
	 * A run, and the codepoints it should draw. The glyph after the B is the one the fabricated
	 * ClassDef puts in class 1: a letter in the first, the space glyph in the second.
	 *
	 * @return array
	 */
	public function dataRuns()
	{
		return [
			'a letter ahead' => ['<p>ABA</p>', [0x41, self::A_SMALL_CAP, 0x41]],
			'a space ahead' => ['<p>AB A</p>', [0x41, self::A_SMALL_CAP, 0x20, 0x41]],
		];
	}

	/**
	 * Parses the font under a handler of its own, so that a warning is recorded rather than thrown by
	 * PHPUnit's.
	 */
	private function parse()
	{
		$ttf = new TTFontFile(new FontCache(new Cache(__DIR__ . '/tmp/mpdf/ttfontdata')), 'win');

		$this->record();

		try {
			// A fontkey of its own each time: the cache is keyed on it, so a shared one would have a
			// later parse read what an earlier one wrote
			$ttf->getMetrics(__DIR__ . '/../data/ttf/' . self::FONT . '.ttf', uniqid('', true), 0, false, false, 0xFF);
		} finally {
			restore_error_handler();
		}
	}

	/**
	 * Renders a document in the font, recording both what PHP raised and what was drawn.
	 *
	 * @param string $html
	 *
	 * @return TextRecordingMpdf
	 */
	private function draw($html)
	{
		// A directory of its own, so that the font cache this run writes is the one it reads back
		$tempDir = sys_get_temp_dir() . '/mpdf-null-classdef-' . uniqid('', true);
		$this->tempDirs[] = $tempDir;

		$this->record();

		try {
			$mpdf = new TextRecordingMpdf([
				'tempDir' => $tempDir,
				'fontDir' => [__DIR__ . '/../data/ttf'],
				'fontdata' => [strtolower(self::FONT) => [
					'R' => self::FONT . '.ttf',
					'useOTL' => 0xFF,
				]],
				'default_font' => strtolower(self::FONT),
			]);

			// Selecting the font generated the metrics, which is the parser's half of this and has a test
			// of its own; what this one records is what shaping raises
			$this->raised = [];

			$mpdf->WriteHTML($html);
			$mpdf->Output('', 'S');
			$mpdf->cleanup();
		} finally {
			restore_error_handler();
		}

		return $mpdf;
	}

	/**
	 * Installs a handler that records what PHP raises and hands nothing on, so that the reads being
	 * tested can finish and the line that raised is named in the failure.
	 */
	private function record()
	{
		set_error_handler(function ($number, $message, $path, $line) {
			$this->raised[] = sprintf('%s in %s:%d', $message, basename($path), $line);

			return true;
		});
	}

	/**
	 * Deletes a directory and everything under it.
	 *
	 * @param string $dir
	 */
	private function remove($dir)
	{
		if (!is_dir($dir)) {
			return;
		}

		$entries = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ($entries as $entry) {
			if ($entry->isDir()) {
				rmdir($entry->getPathname());
			} else {
				unlink($entry->getPathname());
			}
		}

		rmdir($dir);
	}

}
