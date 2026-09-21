<?php

namespace Mpdf\Fonts;

/**
 * What the four golden masters have in common: a corpus, a fixture per font, and a way to rewrite
 * one from the current code.
 *
 * Each pins one seam of OpenType support against every font in tests/data/ttf - what the parser
 * hands the shaper, what the dump reports, what the subsetter emits, what the shaper makes of a run
 * of text - and each differs only in capture(), which is the thing being pinned. The rest is the
 * same: list the fonts, name a fixture file, read it, write it.
 *
 * @see GoldenMasterUpdate, which is the command line all four are driven from
 */
abstract class GoldenMaster
{

	const FONT_DIR = __DIR__ . '/../../data/ttf';

	/**
	 * Where the scratch files a capture makes go. Given by the caller only in a test that wants to
	 * look at them.
	 *
	 * @var string
	 */
	protected $tmpDir;

	/**
	 * @param string|null $tmpDir Where a capture's scratch files go. Defaults to a directory named
	 *                            after this master under tests/Mpdf/tmp.
	 */
	public function __construct($tmpDir = null)
	{
		$this->tmpDir = $tmpDir === null ? __DIR__ . '/../tmp/mpdf/' . $this->name() : $tmpDir;
	}

	/**
	 * @return string What this master pins, as a directory-safe word. Names both the fixture
	 *                directory and the scratch directory.
	 */
	abstract protected function name();

	/**
	 * @return string The file extension its fixtures are written with
	 */
	abstract protected function extension();

	/**
	 * @return string The fixture for one font, as it would be written now
	 */
	abstract public function capture($name);

	/**
	 * Every font in tests/data/ttf, as a PHPUnit data provider.
	 *
	 * Taken from the directory rather than written down, so that a font added to the corpus is
	 * picked up by all four masters at once.
	 */
	public function fonts()
	{
		$fonts = [];
		foreach (glob(self::FONT_DIR . '/*.ttf') as $file) {
			$name = basename($file, '.ttf');
			$fonts[$name] = [$name];
		}
		ksort($fonts);

		return $fonts;
	}

	/**
	 * @return string The directory this master's fixtures are committed in
	 */
	public function fixtureDir()
	{
		return __DIR__ . '/../../data/' . $this->name();
	}

	/**
	 * @param string $name A font name, as fonts() gives it
	 *
	 * @return string The path its fixture is written to
	 */
	public function fixtureFile($name)
	{
		return $this->fixtureDir() . '/' . $name . '.' . $this->extension();
	}

	/**
	 * @param string $name A font name, as fonts() gives it
	 *
	 * @return bool Whether that font has a committed fixture
	 */
	public function hasFixture($name)
	{
		return file_exists($this->fixtureFile($name));
	}

	/**
	 * @param string $name A font name, as fonts() gives it
	 *
	 * @return string The committed fixture, as it stands
	 */
	public function loadFixture($name)
	{
		return file_get_contents($this->fixtureFile($name));
	}

	/**
	 * Rewrite one fixture from the current code.
	 *
	 * @return string The file written
	 */
	public function update($name)
	{
		$capture = $this->capture($name);
		$file = $this->fixtureFile($name);

		if (!is_dir($this->fixtureDir())) {
			mkdir($this->fixtureDir(), 0777, true);
		}

		file_put_contents($file, $capture);

		return $file;
	}

	/**
	 * A font refused by name reports the path it was given, which is this machine's, and a fixture
	 * must carry nothing of the machine that made it.
	 *
	 * Separators are normalised on both sides before the root is cut: Windows resolves its own root
	 * with backslashes and the caller appended the rest with forward ones, so the message arrives
	 * carrying both.
	 */
	protected function withoutPath($message)
	{
		$root = str_replace('\\', '/', realpath(__DIR__ . '/../../..')) . '/';

		return str_replace($root, '', str_replace('\\', '/', $message));
	}
}
