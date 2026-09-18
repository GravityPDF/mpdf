<?php

namespace Mpdf;

/**
 * A font cache entry that goes between the moment a caller decides it wants it and the moment the read
 * lands is a cache miss, and every font path in the library used to read it as an empty entry instead:
 * has() said it was there, file_get_contents() warned, and what it handed back was drawn with.
 *
 * The entries below are what the run reads, and each is expired once under a render that has just
 * written it. What the document then holds has to be what it holds when nothing expired - the entry is
 * derived from the font file, so making it again is always open to the caller - except for the layout
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
		$directories = [];

		foreach (new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($this->tempDir, \FilesystemIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::CHILD_FIRST
		) as $item) {
			$item->isDir() ? $directories[] = $item->getPathname() : unlink($item->getPathname());
		}

		foreach ($directories as $directory) {
			rmdir($directory);
		}

		rmdir($this->tempDir);

		parent::tear_down();
	}

	/**
	 * The font program and the size it was repackaged to are two entries read one after the other, and
	 * either of them gone left the document with a font file object of no bytes at all.
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
	 * The /W array of the CIDFont, which said nothing at all where its entry had gone - leaving every
	 * character in the document to be drawn at the font's default width.
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
	 * any one of them gone means making all three again. The widths going used to be fatal: the writer
	 * divides by the number of characters it reads out of them.
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

		set_error_handler(array($this, 'record'));

		try {
			$mpdf->WriteHTML('<p style="font-family:' . self::FONT . '">ABCE GHIJ</p>');
			$pdf = $mpdf->Output('', 'S');
		} finally {
			restore_error_handler();
		}

		$mpdf->cleanup();

		return $pdf;
	}

	/**
	 * The read that loses the race by a hair suppresses its own warning, so that a handler converting
	 * warnings to exceptions cannot make a cache miss fatal, and the miss it reports is acted on rather
	 * than being left for the reader of a log to notice.
	 */
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

		/* PHP 8.1 reflects a private member without being asked, and 8.5 deprecates the asking. */
		if (PHP_VERSION_ID < 80100) {
			$property->setAccessible(true);
		}

		return $property;
	}

	public function record($severity, $message, $file, $line)
	{
		if (!(error_reporting() & $severity)) {
			return true;
		}

		$this->raised[] = sprintf('%s in %s:%d', $message, basename($file), $line);

		return true;
	}

	private function assertEmbedsTheFontProgram($pdf)
	{
		preg_match('#/Length (\d+)\s*/Filter /FlateDecode\s*/Length1 (\d+)#', $pdf, $font);

		$this->assertNotSame([], $font, 'the document embeds no font program');
		$this->assertGreaterThan(0, (int) $font[1]);
		$this->assertGreaterThan(0, (int) $font[2]);
	}
}
