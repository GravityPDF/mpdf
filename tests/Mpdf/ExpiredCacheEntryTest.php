<?php

namespace Mpdf;

/**
 * A font cache entry that goes between the moment a caller decides it wants it and the moment the read
 * lands is a cache miss, not an empty entry. Every font path in the library asked has() and then read,
 * so what the lost race handed back - nothing - was drawn with.
 *
 * The entries below are what a render reads, and each is expired once under a render that has just
 * written it. What the document then holds has to be what it holds when nothing expired, because every
 * entry is derived from the font file and making it again is open to the caller - except the layout
 * tables the parser derives, which only re-parsing the font can rebuild and which are raised instead.
 */
class ExpiredCacheEntryTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	const FONT = 'nestedadvance';

	/** Noto Sans cut down to the capitals, with a GSUB the run below reaches */
	const FONT_FILE = 'NotoSans-GSUB53-NestedAdvance-Synthetic.ttf';

	const CREATION_DATE = 946684800;

	private $tempDir;

	/** Diagnostics the render raised, less the ones raised under suppression */
	private $raised;

	protected function set_up()
	{
		parent::set_up();

		$this->tempDir = sys_get_temp_dir() . '/mpdf-expired-entry-' . uniqid('', true);
		$this->raised = [];

		mkdir($this->tempDir, 0777, true);
	}

	protected function tear_down()
	{
		/* The two directories a Cache makes under a tempDir, innermost first */
		foreach ([$this->tempDir . '/mpdf/ttfontdata', $this->tempDir . '/mpdf'] as $directory) {
			foreach (glob($directory . '/*') as $entry) {
				if (is_file($entry)) {
					unlink($entry);
				}
			}

			rmdir($directory);
		}

		rmdir($this->tempDir);

		parent::tear_down();
	}

	/**
	 * The font program and the size it was repackaged to are two entries read one after the other, and
	 * either of them gone leaves nothing to put in the font file object.
	 */
	public function testAnExpiredFontProgramIsRepackagedRatherThanEmbeddedEmpty()
	{
		$expected = $this->render();

		$this->assertEmbedsTheFontProgram($expected);
		$this->assertSame($expected, $this->render([self::FONT . '.ps.z']));
		$this->assertSame($expected, $this->render([self::FONT . '.ps.json']));
		$this->assertNothingRaised();
	}

	/**
	 * The /W array of the CIDFont. Written from nothing it says nothing, and every character in the
	 * document is then drawn at the font's default width.
	 */
	public function testAnExpiredWidthsRunIsWrittenAgainRatherThanLeftOutOfTheFont()
	{
		$expected = $this->render();

		$this->assertMatchesRegularExpression('#/W \[#', $expected);
		$this->assertSame($expected, $this->render([self::FONT . '.cw']));
		$this->assertNothingRaised();
	}

	/**
	 * The CIDToGIDMap, which every glyph the document draws is looked up through.
	 */
	public function testAnExpiredGlyphMapIsBuiltAgainRatherThanEmbeddedEmpty()
	{
		$expected = $this->render();

		$this->assertSame($expected, $this->render([self::FONT . '.cgm']));
		$this->assertNothingRaised();
	}

	/**
	 * The character widths, the glyph map and the metrics are written together by MetricsGenerator, so
	 * any one of them gone means making all three again. The widths are the fatal one: the writer divides
	 * by the number of characters it reads out of them.
	 */
	public function testExpiredMetricsAreGeneratedAgainRatherThanLeavingTheFontWithout()
	{
		$expected = $this->render();

		foreach (['cw.dat', 'gid.dat', 'mtx.json'] as $suffix) {
			$this->assertSame($expected, $this->render([self::FONT . '.' . $suffix]), $suffix);
		}

		$this->assertNothingRaised();
	}

	/**
	 * Nothing outside TTFontFile can derive the coverage of a lookup again, and shaping without it
	 * silently drops every substitution the font asks for, so the miss is raised. readTable() already
	 * raises the same miss for the table bytes these are derived from.
	 */
	public function testExpiredLayoutDataIsRaisedRatherThanShapedAround()
	{
		$this->render();

		$this->expectException(\Mpdf\MpdfException::class);
		$this->expectExceptionMessage('Cannot read the layout data cached at');

		$this->render([self::FONT . '.GSUBdata.json']);
	}

	/**
	 * @param string[] $expiring Cache entries to expire under the render, once each
	 *
	 * @return string The document
	 */
	private function render(array $expiring = [])
	{
		$mpdf = new Mpdf([
			'mode' => 'utf-8',
			'tempDir' => $this->tempDir,
			'creationDate' => self::CREATION_DATE,
			'exposeVersion' => false,
			// Otherwise every font is subsetted, and a subset is built from the font file rather than
			// read from the entries this covers
			'percentSubset' => 0,
			'fontDir' => [__DIR__ . '/../data/ttf'],
			'fontdata' => [self::FONT => ['R' => self::FONT_FILE, 'useOTL' => 0xFF]],
		]);
		$mpdf->SetCompression(false);

		if ($expiring) {
			$this->expire($mpdf, $expiring);
		}

		set_error_handler(function ($severity, $message, $file, $line) {
			/* Suppression is honoured: only the read that loses the race inside Cache::loadIfPresent()
			 * raises anything, and it suppresses itself so that a handler cannot make a miss fatal. */
			if (error_reporting() & $severity) {
				$this->raised[] = sprintf('%s in %s:%d', $message, basename($file), $line);
			}

			return true;
		});

		try {
			$mpdf->WriteHTML('<p style="font-family:' . self::FONT . '">ABCE GHIJ</p>');
			$pdf = $mpdf->Output('', 'S');
		} finally {
			restore_error_handler();
		}

		$mpdf->cleanup();

		return $pdf;
	}

	private function assertNothingRaised()
	{
		$this->assertSame([], $this->raised);
	}

	/**
	 * Hand the render a Cache that loses the race for each entry named. Every collaborator that reads
	 * the font cache is given the one FontCache, so the Cache behind it is the single seam they all
	 * read through.
	 */
	private function expire(Mpdf $mpdf, array $expiring)
	{
		$fontCache = $this->property('Mpdf\Mpdf', 'fontCache')->getValue($mpdf);
		$cache = new ExpiredEntryCache($this->tempDir . '/mpdf/ttfontdata', $expiring);

		$this->property('Mpdf\Fonts\FontCache', 'cache')->setValue($fontCache, $cache);
	}

	/**
	 * @return \ReflectionProperty
	 */
	private function property($class, $name)
	{
		$property = new \ReflectionProperty($class, $name);

		// A no-op from PHP 8.1 and deprecated from 8.5
		if (PHP_VERSION_ID < 80100) {
			$property->setAccessible(true);
		}

		return $property;
	}

	private function assertEmbedsTheFontProgram($pdf)
	{
		preg_match('#/Length (\d+)\s*/Filter /FlateDecode\s*/Length1 (\d+)#', $pdf, $font);

		$this->assertNotSame([], $font, 'the document embeds no font program');
		$this->assertGreaterThan(0, (int) $font[1]);
		$this->assertGreaterThan(0, (int) $font[2]);
	}
}
