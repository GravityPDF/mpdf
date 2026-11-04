<?php

namespace Mpdf\Fonts;

use Mpdf\Cache;
use Mpdf\Container\SimpleContainer;
use Mpdf\Log\Context;
use Mpdf\Mpdf;
use Mpdf\RecordingFontFileFinder;
use Mpdf\TestLogger;
use Psr\Log\NullLogger;
use Snapshots\CharacterRangesSnapshotTest;

/**
 * The substitution scan reads which characters a backup font has from a ranges file, so the only
 * font file it asks the finder for is the one it moves text into.
 */
class CharacterRangesLookupTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use TempFontDirectory;

	/**
	 * @var string A directory the test writes ranges files into, which stands in for
	 *             mPDF's data/character-ranges
	 */
	private $rangesDir;

	/**
	 * Gives each test an empty font cache and empty font and ranges directories
	 */
	protected function set_up()
	{
		parent::set_up();

		$this->makeTempFontDirectory('character-ranges-lookup');
		$this->rangesDir = $this->tempDir . '/ranges';
		mkdir($this->rangesDir, 0777, true);
		unset($GLOBALS['mpdfCharacterRangesIncluded']);
	}

	/**
	 * Removes the font cache and the directories
	 */
	protected function tear_down()
	{
		$this->removeTempFontDirectory();
		unset($GLOBALS['mpdfCharacterRangesIncluded']);

		parent::tear_down();
	}

	/**
	 * Each render asks the finder for the document font and for the backup font it draws in, and for no
	 * backup font it only considered
	 *
	 * @dataProvider lookupProvider
	 *
	 * @param string   $html
	 * @param string[] $backupSubsFont
	 * @param string[] $onDisk          The font files the finder can find
	 * @param string[] $lookups         The font files the finder is asked for
	 * @param string   $defaultFont
	 */
	public function testARenderLooksUpOnlyTheFontsItDrawsIn($html, array $backupSubsFont, array $onDisk, array $lookups, $defaultFont = 'dejavusans')
	{
		$finder = $this->finder($onDisk);
		$this->render($html, $backupSubsFont, $finder, ['default_font' => $defaultFont]);

		$this->assertSame($lookups, array_values(array_unique($finder->lookups)));
	}

	/**
	 * @return array[]
	 */
	public function lookupProvider()
	{
		$backups = ['dejavusanscondensed', 'freesans', 'sun-exta'];

		return [
			'Japanese is in Sun-ExtA only' => [
				'<p>日本</p>',
				$backups,
				['DejaVuSans.ttf', 'Sun-ExtA.ttf'],
				['DejaVuSans.ttf', 'Sun-ExtA.ttf'],
			],
			'a character none of them has' => [
				'<p>&#x1E900;</p>',
				$backups,
				['DejaVuSans.ttf'],
				['DejaVuSans.ttf'],
			],
			'Plane 2 that Sun-ExtB has' => [
				'<p>&#x20000;</p>',
				$backups,
				['DejaVuSans.ttf', 'Sun-ExtB.ttf'],
				['DejaVuSans.ttf', 'Sun-ExtB.ttf'],
			],
			'a core font document' => [
				'<p>Hello Привет</p>',
				$backups,
				['DejaVuSansCondensed.ttf'],
				['DejaVuSansCondensed.ttf'],
				'chelvetica',
			],
		];
	}

	/**
	 * The character-ranges snapshot, made with only the three fonts it draws in on disk, is the document
	 * made with every font there
	 */
	public function testTheSnapshotWithThreeFontsIsTheDocumentWithAllOfThem()
	{
		$pdfs = [];
		foreach ([CharacterRangesSnapshotTest::ON_DISK, null] as $onDisk) {
			$finder = $this->finder($onDisk);
			$mpdf = $this->mpdf(CharacterRangesSnapshotTest::config(), $finder);
			$mpdf->backupSubsFont = CharacterRangesSnapshotTest::backupSubsFont();
			$mpdf->WriteHTML(CharacterRangesSnapshotTest::html());
			$pdfs[] = $this->withoutDates($mpdf->OutputBinaryData());

			$this->assertSame(['Sun-ExtA.ttf', 'Garuda.ttf', 'Sun-ExtB.ttf'], array_values(array_unique($finder->lookups)));
		}

		$this->assertSame($pdfs[0], $pdfs[1]);
	}

	/**
	 * A font mPDF does not ship is read on the first render, which writes the ranges of its widths into
	 * the font cache; the second render reads them there, does not look the font up, and makes the same
	 * document
	 */
	public function testTheFirstRenderWritesTheRangesTheSecondReads()
	{
		$this->copyFont(__DIR__ . '/../../data/ttf/angerthas.ttf');
		$config = $this->angerthasConfig();

		$first = $this->finder(null);
		$cold = $this->render('<p>Hello 日本</p>', ['angerthas', 'sun-exta'], $first, $config);
		$written = json_decode(file_get_contents($this->tempDir . '/mpdf/ttfontdata/angerthas.ranges.json'), true);

		$second = $this->finder(null);
		$warm = $this->render('<p>Hello 日本</p>', ['angerthas', 'sun-exta'], $second, $config);

		$command = new CharacterRangesCommand();
		$ranges = $command->rangesOf($this->fontDir . '/angerthas.ttf')->toArray();

		$this->assertContains('angerthas.ttf', $first->lookups);
		$this->assertNotContains('angerthas.ttf', $second->lookups);
		$this->assertSame($ranges['ranges'], $written['ranges']);
		$this->assertSame($cold, $warm);
	}

	/**
	 * A font cache from before ranges were written has no ranges file and no cacheFormat in its metrics,
	 * and gains the file the next time the font's metrics are read
	 */
	public function testAFontCacheFromBeforeRangesGainsThemOnItsNextLoad()
	{
		$this->copyFont(__DIR__ . '/../../data/ttf/angerthas.ttf');
		$config = $this->angerthasConfig();
		$cache = $this->tempDir . '/mpdf/ttfontdata/';

		$this->render('<p>Hello 日本</p>', ['angerthas', 'sun-exta'], $this->finder(null), $config);

		$metrics = json_decode(file_get_contents($cache . 'angerthas.mtx.json'), true);
		unset($metrics['cacheFormat']);
		file_put_contents($cache . 'angerthas.mtx.json', json_encode($metrics));
		unlink($cache . 'angerthas.ranges.json');

		$this->render('<p>Hello 日本</p>', ['angerthas', 'sun-exta'], $this->finder(null), $config);

		$this->assertFileExists($cache . 'angerthas.ranges.json');
	}

	/**
	 * A BMPonly family's widths stop at U+FFFF, so it gets no ranges file
	 */
	public function testABmpOnlyFamilyWritesNoRanges()
	{
		$this->copyFont(__DIR__ . '/../../data/ttf/angerthas.ttf');

		$this->render('<p>Hello</p>', [], $this->finder(null), $this->angerthasConfig() + [
			'default_font' => 'angerthas',
			'BMPonly' => ['angerthas'],
		]);

		$this->assertFileExists($this->tempDir . '/mpdf/ttfontdata/angerthas.mtx.json');
		$this->assertFileDoesNotExist($this->tempDir . '/mpdf/ttfontdata/angerthas.ranges.json');
	}

	/**
	 * The file mPDF ships for a family is read ahead of the font cache's
	 */
	public function testTheShippedFileBeatsTheFontCache()
	{
		$this->writeRanges($this->rangesDir . '/Garuda.ttf.ranges.php', [[0x42, 0x42]]);
		$this->writeCachedRanges('garuda', [[0x41, 0x41]], 'Garuda.ttf');

		$finder = $this->finder(['DejaVuSans.ttf']);
		$mpdf = $this->garudaMpdf($finder);

		$this->assertCovers([0x42], [0x41], $mpdf, 'garuda');
		$this->assertSame([], $finder->lookups);
	}

	/**
	 * A font cache file written for another font file under the same family is passed over, and the
	 * font is read
	 */
	public function testAFontCacheFileOfAnotherFontIsPassedOver()
	{
		$this->copyFont(__DIR__ . '/../../data/ttf/angerthas.ttf');
		$this->writeCachedRanges('angerthas', [[0x1E900, 0x1E900]], 'other.ttf');

		$finder = $this->finder(null);
		$this->assertCovers([0x43], [0x1E900], $this->plainMpdf($finder), 'angerthas');
		$this->assertSame(['angerthas.ttf'], $finder->lookups);
	}

	/**
	 * A font directory can be where downloads land, so a PHP file in one is never included: the font
	 * is read instead
	 */
	public function testAPhpFileInAPlainFontDirectoryIsNeverIncluded()
	{
		$this->copyFont(__DIR__ . '/../../data/ttf/angerthas.ttf');
		$this->writeIncludeTrap($this->fontDir . '/angerthas.ttf.ranges.php');

		$finder = $this->finder(null);
		$this->assertCovers([0x43], [], $this->plainMpdf($finder), 'angerthas');

		$this->assertArrayNotHasKey('mpdfCharacterRangesIncluded', $GLOBALS);
		$this->assertSame(['angerthas.ttf'], $finder->lookups);
	}

	/**
	 * The font cache can be written by anything, so a PHP file in it is never included, even for a
	 * family mPDF ships: the font is read instead
	 */
	public function testAPhpFileInTheFontCacheIsNeverIncluded()
	{
		copy(__DIR__ . '/../../data/ttf/angerthas.ttf', $this->fontDir . '/Garuda.ttf');
		mkdir($this->tempDir . '/mpdf/ttfontdata', 0777, true);
		$this->writeIncludeTrap($this->tempDir . '/mpdf/ttfontdata/Garuda.ttf.ranges.php');
		$this->writeIncludeTrap($this->tempDir . '/mpdf/ttfontdata/garuda.ranges.php');

		$finder = $this->finder(null);
		$this->assertCovers([0x43], [], $this->garudaMpdf($finder), 'garuda');

		$this->assertArrayNotHasKey('mpdfCharacterRangesIncluded', $GLOBALS);
		$this->assertSame(['Garuda.ttf'], $finder->lookups);
	}

	/**
	 * A family mPDF does not ship, or ships with another font file, reads no shipped ranges file, even
	 * where one has its font file's name: the font it names is not known to be the one mPDF ships, and
	 * what fontdata names does not decide what is included
	 *
	 * @dataProvider notShippedProvider
	 *
	 * @param string $family
	 * @param string $file   The font file fontdata gives the family
	 * @param string $trap   The name of the file in the ranges directory
	 */
	public function testAFamilyAsMpdfDoesNotShipItIncludesNoRangesFile($family, $file, $trap)
	{
		copy(__DIR__ . '/../../data/ttf/angerthas.ttf', $this->fontDir . '/' . $file);
		$this->writeIncludeTrap($this->rangesDir . '/' . $trap);

		$finder = $this->finder(null);
		$mpdf = $this->garudaMpdf($finder, [$family => ['R' => $file]]);

		$this->assertCovers([0x43], [], $mpdf, $family);

		$this->assertArrayNotHasKey('mpdfCharacterRangesIncluded', $GLOBALS);
		$this->assertSame([$file], $finder->lookups);
	}

	/**
	 * @return array[]
	 */
	public function notShippedProvider()
	{
		return [
			'another family with a shipped font file' => ['mine', 'Garuda.ttf', 'Garuda.ttf.ranges.php'],
			'a shipped family with another font file' => ['garuda', 'Other.ttf', 'Other.ttf.ranges.php'],
			'a font file that leads out of the directory' => ['garuda', '../evil.ttf', '../evil.ttf.ranges.php'],
		];
	}

	/**
	 * An invalid ranges file is passed over with a warning, and the font is read as it would be without one
	 */
	public function testAnInvalidFileFallsBackToTheFont()
	{
		copy(__DIR__ . '/../../data/ttf/angerthas.ttf', $this->fontDir . '/Garuda.ttf');

		// The font is the size the file says, so the file gets as far as being validated
		file_put_contents($this->rangesDir . '/Garuda.ttf.ranges.php', sprintf(
			"<?php\n\nreturn ['format' => 1, 'size' => %d, 'ranges' => [[0x42, 0x41]]];\n",
			filesize($this->fontDir . '/Garuda.ttf')
		));

		$finder = $this->finder(null);
		$logger = new TestLogger();
		$mpdf = $this->garudaMpdf($finder);

		$this->assertCovers([0x43], [], $mpdf, 'garuda', $logger);
		$this->assertSame(['Garuda.ttf'], $finder->lookups);
		$this->assertTrue($logger->hasRecordThatContains($this->rangesDir . '/Garuda.ttf.ranges.php', 'warning'));
	}

	/**
	 * A shipped file written for a font of another size than mPDF's own font file is passed over with a
	 * warning naming both, and the font is read
	 */
	public function testAShippedFileOfAnotherSizeThanTheShippedFontIsPassedOver()
	{
		copy(__DIR__ . '/../../data/ttf/angerthas.ttf', $this->fontDir . '/Garuda.ttf');
		$this->writeRanges($this->rangesDir . '/Garuda.ttf.ranges.php', [[0x1E900, 0x1E910]], 12345);

		$finder = $this->finder(null);
		$logger = new TestLogger();
		$mpdf = $this->garudaMpdf($finder);

		$this->assertCovers([0x43], [0x1E900], $mpdf, 'garuda', $logger);
		$this->assertSame(['Garuda.ttf'], $finder->lookups);

		$rangesFile = $this->rangesDir . '/Garuda.ttf.ranges.php';
		$font = $this->fontDir . '/Garuda.ttf';
		$this->assertTrue($logger->hasRecordThatPasses(function ($record) use ($rangesFile, $font) {
			return strpos($record['message'], $rangesFile) !== false
				&& strpos($record['message'], '"' . $font . '"') !== false
				&& strpos($record['message'], '12345 bytes') !== false
				&& $record['context'] === ['context' => Context::FONTS];
		}, 'warning'));
	}

	/**
	 * The size is compared before the ranges are read, so a file of another font is passed over for its
	 * size alone, whatever its ranges hold
	 */
	public function testAShippedFileOfAnotherSizeIsPassedOverBeforeItsRangesAreRead()
	{
		copy(__DIR__ . '/../../data/ttf/angerthas.ttf', $this->fontDir . '/Garuda.ttf');
		file_put_contents($this->rangesDir . '/Garuda.ttf.ranges.php', "<?php\n\nreturn ['format' => 1, 'size' => 12345, 'ranges' => [[0x50, 0x40]]];\n");

		$logger = new TestLogger();
		$this->assertCovers([0x43], [], $this->garudaMpdf($this->finder(null)), 'garuda', $logger);

		$this->assertCount(1, $logger->records);
		$this->assertStringContainsString('12345 bytes', $logger->records[0]['message']);
	}

	/**
	 * A font fetched on demand is not in mPDF's own directory yet, so the shipped file is trusted
	 * whatever size it was written for
	 */
	public function testAShippedFileIsTrustedWhileTheShippedFontIsMissing()
	{
		$this->writeRanges($this->rangesDir . '/Garuda.ttf.ranges.php', [[0x42, 0x42]], 12345);

		$finder = $this->finder(['DejaVuSans.ttf']);
		$logger = new TestLogger();

		$this->assertCovers([0x42], [0x41], $this->garudaMpdf($finder), 'garuda', $logger);
		$this->assertSame([], $finder->lookups);
		$this->assertSame([], $logger->records);
	}

	/**
	 * Where the finder loads a font of another size than the shipped file was written for, from outside
	 * mPDF's own, the file has already decided what it was asked, so a warning names it
	 */
	public function testAShippedFileOfAnotherSizeThanTheFontLoadedWarnsOnceItLoads()
	{
		copy(__DIR__ . '/../../data/ttf/angerthas.ttf', $this->fontDir . '/Garuda.ttf');

		$logger = new TestLogger();
		$mpdf = $this->garudaMpdf($this->finder(null));
		$mpdf->setLogger($logger);
		$mpdf->backupSubsFont = ['garuda'];
		$mpdf->WriteHTML('<p>Hello ภาษาไทย</p><p>Again ภาษาไทย</p>');

		$this->assertTrue($logger->hasRecordThatPasses(function ($record) {
			return strpos($record['message'], '/data/character-ranges/Garuda.ttf.ranges.php') !== false
				&& strpos($record['message'], 'bytes') !== false
				&& strpos($record['message'], 'font loaded') !== false
				&& $record['context'] === ['context' => Context::FONTS];
		}, 'warning'));
	}

	/**
	 * @param int[]                         $covered
	 * @param int[]                         $missing
	 * @param Mpdf                          $mpdf
	 * @param string                        $family
	 * @param \Psr\Log\LoggerInterface|null $logger  Told of what the lookup passes over
	 */
	private function assertCovers(array $covered, array $missing, Mpdf $mpdf, $family, $logger = null)
	{
		$substitution = new FontSubstitution(
			$mpdf,
			new FontCache(new Cache($this->tempDir . '/mpdf/ttfontdata')),
			$logger ?: new NullLogger(),
			$this->rangesDir,
			$this->fontDir
		);
		$cw = $substitution->widths($family);

		foreach ($covered as $codepoint) {
			$this->assertTrue($mpdf->_charDefined($cw, $codepoint), sprintf('U+%04X is covered', $codepoint));
		}

		foreach ($missing as $codepoint) {
			$this->assertFalse($mpdf->_charDefined($cw, $codepoint), sprintf('U+%04X is missing', $codepoint));
		}
	}

	/**
	 * @param string[]|null $onDisk
	 *
	 * @return RecordingFontFileFinder
	 */
	private function finder($onDisk)
	{
		$finder = new RecordingFontFileFinder([]);
		$finder->onDisk = $onDisk;

		return $finder;
	}

	/**
	 * Writes a ranges file into the font cache as MetricsGenerator writes one
	 *
	 * @param string  $family
	 * @param int[][] $ranges
	 * @param string  $font   The font file it records it was built from
	 */
	private function writeCachedRanges($family, array $ranges, $font)
	{
		MetricsGenerator::writeCachedRanges(new FontCache(new Cache($this->tempDir . '/mpdf/ttfontdata')), $family, $this->ranges($ranges), $font, 0, 0);
	}

	/**
	 * Writes a ranges file that, if included, leaves a mark in $GLOBALS
	 *
	 * @param string $path
	 */
	private function writeIncludeTrap($path)
	{
		file_put_contents(
			$path,
			"<?php\n\n\$GLOBALS['mpdfCharacterRangesIncluded'] = true;\n\nreturn ['format' => 1, 'size' => 1, 'ranges' => [[0x41, 0x41]]];\n"
		);
	}

	/**
	 * @param array                   $config Over a UTF-8 document in DejaVu Sans with substitutions on
	 * @param RecordingFontFileFinder $finder
	 *
	 * @return Mpdf Writing into the test's own tempDir, with compression off
	 */
	private function mpdf(array $config, RecordingFontFileFinder $finder)
	{
		$config += [
			'mode' => 'utf-8',
			'tempDir' => $this->tempDir,
			'default_font' => 'dejavusans',
			'useSubstitutions' => true,
			'exposeVersion' => false,
		];
		$finder->setDirectories(isset($config['fontDir']) ? $config['fontDir'] : [__DIR__ . '/../../../ttfonts']);

		$mpdf = new Mpdf($config, new SimpleContainer(['fontFileFinder' => $finder]));
		$mpdf->SetCompression(false);

		return $mpdf;
	}

	/**
	 * @return array The config of a document that can use angerthas from the test's font directory, the
	 *               fonts mPDF ships staying findable
	 */
	private function angerthasConfig()
	{
		$fontVariables = new \Mpdf\Config\FontVariables();
		$defaults = $fontVariables->getDefaults();

		return [
			'fontDir' => [$this->fontDir, __DIR__ . '/../../../ttfonts'],
			'fontdata' => $defaults['fontdata'] + ['angerthas' => ['R' => 'angerthas.ttf']],
		];
	}

	/**
	 * @param RecordingFontFileFinder $finder
	 * @param array                   $fonts  The fonts besides DejaVu Sans
	 *
	 * @return Mpdf A document in DejaVu Sans that finds fonts in the test's font directory first, with the
	 *               lookups its constructor made forgotten
	 */
	private function garudaMpdf(RecordingFontFileFinder $finder, array $fonts = ['garuda' => ['R' => 'Garuda.ttf']])
	{
		$mpdf = $this->mpdf([
			'fontDir' => [$this->fontDir, __DIR__ . '/../../../ttfonts'],
			'fontdata' => ['dejavusans' => ['R' => 'DejaVuSans.ttf']] + $fonts,
		], $finder);
		$finder->lookups = [];

		return $mpdf;
	}

	/**
	 * @param RecordingFontFileFinder $finder
	 *
	 * @return Mpdf A document in DejaVu Sans reading angerthas from the test's font directory as a plain
	 *              fontDir entry, with the lookups its constructor made forgotten
	 */
	private function plainMpdf(RecordingFontFileFinder $finder)
	{
		return $this->garudaMpdf($finder, ['angerthas' => ['R' => 'angerthas.ttf']]);
	}

	/**
	 * Renders in DejaVu Sans unless the config says otherwise
	 *
	 * @param string                  $html
	 * @param string[]                $backupSubsFont
	 * @param RecordingFontFileFinder $finder
	 * @param array                   $config
	 *
	 * @return string The PDF, without what makes each render differ
	 */
	private function render($html, array $backupSubsFont, RecordingFontFileFinder $finder, array $config = [])
	{
		$mpdf = $this->mpdf($config, $finder);
		$mpdf->backupSubsFont = $backupSubsFont;

		$mpdf->WriteHTML($html);

		return $this->withoutDates($mpdf->OutputBinaryData());
	}

	/**
	 * @param string $pdf
	 *
	 * @return string The PDF with its dates and document ID blanked, as v6 cannot fix them
	 */
	private function withoutDates($pdf)
	{
		return preg_replace(
			['~/(CreationDate|ModDate) \(D:[^)]*\)~', '~/ID \[<[0-9a-f]+> <[0-9a-f]+>\]~'],
			['/$1 ()', '/ID []'],
			$pdf
		);
	}

}
