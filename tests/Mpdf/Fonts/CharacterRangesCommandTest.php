<?php

namespace Mpdf\Fonts;

/**
 * bin/mpdf-font-ranges, run in process, and the files it writes beside the fonts that ship.
 */
class CharacterRangesCommandTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * @var string
	 */
	private $dir;

	/**
	 * Copies a font into a directory of the test's own
	 */
	protected function set_up()
	{
		parent::set_up();

		$this->dir = sys_get_temp_dir() . '/mpdf-font-ranges-' . uniqid('', true);
		mkdir($this->dir . '/into', 0777, true);
		copy(__DIR__ . '/../../data/ttf/angerthas.ttf', $this->dir . '/angerthas.ttf');
	}

	/**
	 * Removes the directory and what was written in it
	 */
	protected function tear_down()
	{
		foreach (array_merge(glob($this->dir . '/into/*'), glob($this->dir . '/*.*')) as $file) {
			unlink($file);
		}
		rmdir($this->dir . '/into');
		rmdir($this->dir);

		parent::tear_down();
	}

	/**
	 * Without options it writes PHP beside the font, the format mPDF reads from a font package
	 */
	public function testItWritesPhpBesideTheFontByDefault()
	{
		list($status, $out) = $this->runCommand([$this->dir . '/angerthas.ttf']);

		$this->assertSame(0, $status);
		$this->assertSame($this->dir . '/angerthas.ttf.ranges.php' . "\n", $out);
		$this->assertSame($this->ranges()->toPhp(), file_get_contents($this->dir . '/angerthas.ttf.ranges.php'));
	}

	/**
	 * --into writes into the directory named, and nothing beside the font
	 */
	public function testIntoWritesIntoTheDirectory()
	{
		list($status) = $this->runCommand(['--into=' . $this->dir . '/into', $this->dir . '/angerthas.ttf']);

		$this->assertSame(0, $status);
		$this->assertFileExists($this->dir . '/into/angerthas.ttf.ranges.php');
		$this->assertFileDoesNotExist($this->dir . '/angerthas.ttf.ranges.php');
	}

	/**
	 * A command line it cannot act on writes nothing and fails
	 *
	 * @dataProvider invalidArgumentsProvider
	 *
	 * @param string[] $arguments
	 */
	public function testAnInvalidCommandLineFails(array $arguments)
	{
		$arguments = str_replace('{dir}', $this->dir, $arguments);
		list($status, , $err) = $this->runCommand($arguments);

		$this->assertSame(1, $status);
		$this->assertNotSame('', $err);
		$this->assertSame([], glob($this->dir . '/*.ranges.*'));
	}

	/**
	 * @return array[]
	 */
	public function invalidArgumentsProvider()
	{
		return [
			'no font' => [[]],
			'an unknown option' => [['--frmat=php', '{dir}/angerthas.ttf']],
			'into a directory that is not there' => [['--into={dir}/missing', '{dir}/angerthas.ttf']],
			'a font that is not there' => [['{dir}/missing.ttf']],
		];
	}

	/**
	 * Each font package's regular fonts ship the ranges file composer ranges:update writes, so the file
	 * cannot drift from the font. A package whose fonts are not installed is skipped.
	 *
	 * @dataProvider packagedFontProvider
	 *
	 * @param string $font
	 */
	public function testTheShippedRangesAreWhatTheCommandWrites($font)
	{
		if (!is_file($font)) {
			$this->markTestSkipped(sprintf('%s is not installed', $font));
		}

		$this->assertFileExists($font . '.ranges.php', 'composer ranges:update has not been run for it');
		$this->assertSame(
			(new CharacterRangesCommand())->rangesOf($font)->toPhp(),
			file_get_contents($font . '.ranges.php'),
			'composer ranges:update would rewrite ' . $font . '.ranges.php'
		);
	}

	/**
	 * @return string[][] The regular font of each family the registered packages provide
	 */
	public function packagedFontProvider()
	{
		return array_map(function ($font) {
			return [$font];
		}, CharacterRangesCommand::packagedFonts());
	}

	/**
	 * @param string[] $arguments
	 *
	 * @return array [exit status, what it wrote to stdout, what it wrote to stderr]
	 */
	private function runCommand(array $arguments)
	{
		$stdout = fopen('php://memory', 'w+');
		$stderr = fopen('php://memory', 'w+');

		$status = (new CharacterRangesCommand())->run($arguments, $stdout, $stderr);

		rewind($stdout);
		rewind($stderr);

		return [$status, stream_get_contents($stdout), stream_get_contents($stderr)];
	}

	/**
	 * @return CharacterRanges What the command should write for the font
	 */
	private function ranges()
	{
		return (new CharacterRangesCommand())->rangesOf($this->dir . '/angerthas.ttf');
	}

}
