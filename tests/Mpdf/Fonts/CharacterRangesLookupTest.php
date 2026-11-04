<?php

namespace Mpdf\Fonts;

use Mpdf\Cache;
use Mpdf\Container\SimpleContainer;
use Mpdf\Fonts\Fixtures\DirectoryFontRegistration;
use Mpdf\Log\Context;
use Mpdf\Mpdf;
use Mpdf\RecordingFontFileFinder;
use Mpdf\TestLogger;
use Psr\Log\NullLogger;
use Snapshots\CharacterRangesSnapshotTest;
use Snapshots\Snapshot;

/**
 * The substitution scan reads which characters a backup font has from a ranges file, so the only
 * font file it asks the finder for is the one it moves text into.
 */
class CharacterRangesLookupTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * @var string A tempDir of the test's own, so the font cache starts empty
	 */
	private $tempDir;

	/**
	 * @var string A directory the test writes fonts and ranges files into
	 */
	private $fontDir;

	/**
	 * Gives each test an empty font cache and an empty font directory
	 */
	protected function set_up()
	{
		parent::set_up();

		$this->tempDir = sys_get_temp_dir() . '/mpdf-character-ranges-lookup-' . uniqid('', true);
		$this->fontDir = $this->tempDir . '/fonts';
		mkdir($this->fontDir, 0777, true);
		unset($GLOBALS['mpdfCharacterRangesIncluded']);
	}

	/**
	 * Removes the font cache and the font directory
	 */
	protected function tear_down()
	{
		$files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->tempDir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
		foreach ($files as $file) {
			$file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
		}
		rmdir($this->tempDir);
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
	 */
	public function testARenderLooksUpOnlyTheFontsItDrawsIn($html, array $backupSubsFont, array $onDisk, array $lookups)
	{
		$finder = $this->finder($onDisk);
		$this->render($html, $backupSubsFont, $finder);

		$this->assertSame($lookups, $finder->lookups);
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
			'a core font paragraph' => [
				'<p style="font-family: chelvetica">Hello Привет</p>',
				$backups,
				['DejaVuSans.ttf', 'DejaVuSansCondensed.ttf'],
				['DejaVuSans.ttf', 'DejaVuSansCondensed.ttf'],
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
			$pdfs[] = $mpdf->OutputBinaryData();

			$this->assertSame(['Sun-ExtA.ttf', 'Garuda.ttf', 'Sun-ExtB.ttf'], array_values(array_unique($finder->lookups)));
		}

		$this->assertSame($pdfs[0], $pdfs[1]);
	}

	/**
	 * A font with no shipped ranges file is read on the first render, which writes the ranges of its
	 * widths into the font cache; the second render reads them there, does not look the font up, and
	 * makes the same document
	 */
	public function testTheFirstRenderWritesTheRangesTheSecondReads()
	{
		$this->copyFont(__DIR__ . '/../../data/ttf/angerthas.ttf');
		$config = ['fontDir' => [$this->fontDir], 'fontdata' => ['angerthas' => ['R' => 'angerthas.ttf']]];

		$first = $this->finder(null);
		$cold = $this->render('<p>Hello 日本</p>', ['angerthas', 'sun-exta'], $first, $config);
		$written = json_decode(file_get_contents($this->tempDir . '/mpdf/ttfontdata/angerthas.ranges.json'), true);

		$second = $this->finder(null);
		$warm = $this->render('<p>Hello 日本</p>', ['angerthas', 'sun-exta'], $second, $config);

		$this->assertContains('angerthas.ttf', $first->lookups);
		$this->assertNotContains('angerthas.ttf', $second->lookups);
		$this->assertSame((new CharacterRangesCommand())->rangesOf($this->fontDir . '/angerthas.ttf')->toArray()['ranges'], $written['ranges']);
		$this->assertSame($cold, $warm);
	}

	/**
	 * A BMPonly family's widths stop at U+FFFF, so it gets no ranges file
	 */
	public function testABmpOnlyFamilyWritesNoRanges()
	{
		$this->copyFont(__DIR__ . '/../../data/ttf/angerthas.ttf');

		$this->render('<p>Hello</p>', [], $this->finder(null), [
			'fontDir' => [$this->fontDir],
			'fontdata' => ['angerthas' => ['R' => 'angerthas.ttf']],
			'default_font' => 'angerthas',
			'BMPonly' => ['angerthas'],
		]);

		$this->assertFileExists($this->tempDir . '/mpdf/ttfontdata/angerthas.mtx.json');
		$this->assertFileDoesNotExist($this->tempDir . '/mpdf/ttfontdata/angerthas.ranges.json');
	}

	/**
	 * The file a family's package ships is read ahead of the font cache's
	 */
	public function testThePackageFileBeatsTheFontCache()
	{
		$this->writeRanges($this->fontDir . '/test.ttf.ranges.php', [[0x42, 0x42]]);
		$this->writeCachedRanges('test', [[0x41, 0x41]], 'test.ttf');

		$finder = $this->finder(['DejaVuSans.ttf']);
		$mpdf = $this->packageMpdf($finder);

		$this->assertCovers([0x42], [0x41], $mpdf, 'test');
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
	 * Only the package a family is registered from ships its ranges, so a PHP file for the font in
	 * another package's directory is never included
	 */
	public function testAPhpFileInAnotherPackageIsNeverIncluded()
	{
		mkdir($this->fontDir . '/own');
		mkdir($this->fontDir . '/other');
		copy(__DIR__ . '/../../data/ttf/angerthas.ttf', $this->fontDir . '/own/angerthas.ttf');
		$this->writeIncludeTrap($this->fontDir . '/other/angerthas.ttf.ranges.php');

		$finder = $this->finder(null);
		$mpdf = $this->mpdf([
			'fontRegistry' => new FontRegistry([
				new \Mpdf\Fonts\DejavuFamily\Registration(),
				new DirectoryFontRegistration($this->fontDir . '/own', ['angerthas' => ['R' => 'angerthas.ttf']]),
				new DirectoryFontRegistration($this->fontDir . '/other', []),
			]),
		], $finder);
		$finder->lookups = [];

		$this->assertCovers([0x43], [], $mpdf, 'angerthas');

		$this->assertArrayNotHasKey('mpdfCharacterRangesIncluded', $GLOBALS);
		$this->assertSame(['angerthas.ttf'], $finder->lookups);
	}

	/**
	 * An invalid ranges file is passed over with a warning, and the font is read as it would be without one
	 */
	public function testAnInvalidFileFallsBackToTheFont()
	{
		$this->copyFont(__DIR__ . '/../../data/ttf/angerthas.ttf');
		file_put_contents($this->fontDir . '/angerthas.ttf.ranges.php', "<?php\n\nreturn ['format' => 1, 'size' => 1, 'ranges' => [[0x42, 0x41]]];\n");

		$finder = $this->finder(null);
		$logger = new TestLogger();
		$mpdf = $this->packageMpdf($finder, ['angerthas' => ['R' => 'angerthas.ttf']]);

		$this->assertCovers([0x43], [], $mpdf, 'angerthas', $logger);
		$this->assertSame(['angerthas.ttf'], $finder->lookups);
		$this->assertTrue($logger->hasRecordThatContains($this->fontDir . '/angerthas.ttf.ranges.php', 'warning'));
	}

	/**
	 * A shipped file written for a font of another size than the package's own font file is passed over
	 * with a warning naming both, and the font is read
	 */
	public function testAShippedFileOfAnotherSizeThanThePackageFontIsPassedOver()
	{
		$this->copyFont(__DIR__ . '/../../data/ttf/angerthas.ttf');
		$this->writeRanges($this->fontDir . '/angerthas.ttf.ranges.php', [[0x1E900, 0x1E910]], 12345);

		$finder = $this->finder(null);
		$logger = new TestLogger();
		$mpdf = $this->packageMpdf($finder, ['angerthas' => ['R' => 'angerthas.ttf']]);

		$this->assertCovers([0x43], [0x1E900], $mpdf, 'angerthas', $logger);
		$this->assertSame(['angerthas.ttf'], $finder->lookups);
		$this->assertTrue($logger->hasRecordThatPasses(function ($record) {
			return strpos($record['message'], $this->fontDir . '/angerthas.ttf.ranges.php') !== false
				&& strpos($record['message'], '"' . $this->fontDir . '/angerthas.ttf"') !== false
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
		$this->copyFont(__DIR__ . '/../../data/ttf/angerthas.ttf');
		file_put_contents($this->fontDir . '/angerthas.ttf.ranges.php', "<?php\n\nreturn ['format' => 1, 'size' => 12345, 'ranges' => [[0x50, 0x40]]];\n");

		$logger = new TestLogger();
		$this->assertCovers([0x43], [], $this->packageMpdf($this->finder(null), ['angerthas' => ['R' => 'angerthas.ttf']]), 'angerthas', $logger);

		$this->assertCount(1, $logger->records);
		$this->assertStringContainsString('12345 bytes', $logger->records[0]['message']);
	}

	/**
	 * A font the package fetches on demand is not in its directory yet, so the shipped file is trusted
	 * whatever size it was written for
	 */
	public function testAShippedFileIsTrustedWhileThePackageFontIsMissing()
	{
		$this->writeRanges($this->fontDir . '/test.ttf.ranges.php', [[0x42, 0x42]], 12345);

		$finder = $this->finder(['DejaVuSans.ttf']);
		$logger = new TestLogger();

		$this->assertCovers([0x42], [0x41], $this->packageMpdf($finder), 'test', $logger);
		$this->assertSame([], $finder->lookups);
		$this->assertSame([], $logger->records);
	}

	/**
	 * Where the finder loads a font of another size than the shipped file was written for, from outside
	 * the package, the file has already decided what it was asked, so a warning names it
	 */
	public function testAShippedFileOfAnotherSizeThanTheFontLoadedWarnsOnceItLoads()
	{
		mkdir($this->fontDir . '/package');
		$this->copyFont(__DIR__ . '/../../data/ttf/angerthas.ttf');
		$this->writeRanges($this->fontDir . '/package/angerthas.ttf.ranges.php', [[0x1E900, 0x1E910]], 12345);

		$logger = new TestLogger();
		$mpdf = $this->packageMpdf($this->finder(null), ['angerthas' => ['R' => 'angerthas.ttf']], $this->fontDir . '/package');
		$mpdf->setLogger($logger);
		$mpdf->backupSubsFont = ['angerthas'];
		$mpdf->WriteHTML('<p>Hello &#x1E900;</p><p>Again &#x1E901;</p>');

		$this->assertTrue($logger->hasRecordThatPasses(function ($record) {
			return strpos($record['message'], $this->fontDir . '/package/angerthas.ttf.ranges.php') !== false
				&& strpos($record['message'], '12345 bytes') !== false
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
			$logger ?: new NullLogger()
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
	 * @param string $font
	 */
	private function copyFont($font)
	{
		copy($font, $this->fontDir . '/' . basename($font));
	}

	/**
	 * Writes a PHP ranges file as mpdf-font-ranges writes one
	 *
	 * @param string  $path
	 * @param int[][] $ranges
	 * @param int     $size   The size of font it says it was written for
	 */
	private function writeRanges($path, array $ranges, $size = 1)
	{
		file_put_contents($path, $this->ranges($ranges, $size)->toPhp());
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
	 * @param int[][] $ranges
	 * @param int     $size
	 *
	 * @return CharacterRanges
	 */
	private function ranges(array $ranges, $size = 1)
	{
		return CharacterRanges::fromData(['format' => CharacterRanges::FORMAT, 'size' => $size, 'ranges' => $ranges], 'test', new NullLogger());
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
	 * @return Mpdf Writing into the test's own tempDir, and as reproducibly as a snapshot does
	 */
	private function mpdf(array $config, RecordingFontFileFinder $finder)
	{
		$mpdf = new Mpdf($config + [
			'mode' => 'utf-8',
			'tempDir' => $this->tempDir,
			'default_font' => 'dejavusans',
			'useSubstitutions' => true,
			'exposeVersion' => false,
			'creationDate' => Snapshot::CREATION_DATE,
		], new SimpleContainer(['fontFileFinder' => $finder]));
		$mpdf->SetCompression(false);

		return $mpdf;
	}

	/**
	 * @param RecordingFontFileFinder $finder
	 * @param array                   $fonts     The package's fonts
	 * @param string|null             $directory The package's directory, or null for the test's font
	 *                                           directory
	 *
	 * @return Mpdf A document in DejaVu Sans with one other font package, which finds fonts in the test's
	 *              font directory too, with the lookups its constructor made forgotten
	 */
	private function packageMpdf(RecordingFontFileFinder $finder, array $fonts = ['test' => ['R' => 'test.ttf']], $directory = null)
	{
		$mpdf = $this->mpdf([
			'fontDir' => [$this->fontDir],
			'fontRegistry' => new FontRegistry([
				new \Mpdf\Fonts\DejavuFamily\Registration(),
				new DirectoryFontRegistration($directory === null ? $this->fontDir : $directory, $fonts),
			]),
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
		$fontDir = [$this->fontDir, __DIR__ . '/../../../packages/Dejavu-Family/fonts'];
		$finder->setDirectories($fontDir);

		$mpdf = $this->mpdf([
			'fontRegistry' => new FontRegistry([]),
			'fontDir' => $fontDir,
			'fontdata' => ['dejavusans' => ['R' => 'DejaVuSans.ttf'], 'angerthas' => ['R' => 'angerthas.ttf']],
		], $finder);
		$finder->lookups = [];

		return $mpdf;
	}

	/**
	 * Renders with the shipped font packages, in DejaVu Sans unless the config says otherwise
	 *
	 * @param string                  $html
	 * @param string[]                $backupSubsFont
	 * @param RecordingFontFileFinder $finder
	 * @param array                   $config
	 *
	 * @return string The PDF
	 */
	private function render($html, array $backupSubsFont, RecordingFontFileFinder $finder, array $config = [])
	{
		$mpdf = $this->mpdf($config + [
			'fontRegistry' => new FontRegistry([
				new \Mpdf\Fonts\DejavuFamily\Registration(),
				new \Mpdf\Fonts\FreeFamily\Registration(),
				new \Mpdf\Fonts\SunExt\Registration(),
			]),
		], $finder);
		$mpdf->backupSubsFont = $backupSubsFont;

		$mpdf->WriteHTML($html);

		return $mpdf->OutputBinaryData();
	}

}
