<?php

namespace Mpdf\Fonts;

use Mpdf\Cache;
use Mpdf\TTFontFile;

/**
 * A SIP font drawn with more than 255 characters is embedded as several subset fonts, which
 * FontWriter builds one after another on the one subsetter. The character map they are all built
 * from is read for the first and kept for the rest, so each program has to come out as it would from
 * a subsetter that had built nothing before it.
 */
class FontSubsetterSipTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * @var string
	 */
	private $tmpDir = __DIR__ . '/../tmp/mpdf/subsetter-sip';

	/**
	 * @return FontSubsetter
	 */
	private function subsetter()
	{
		return new FontSubsetter(new TTFontFile(new FontCache(new Cache($this->tmpDir)), 'win'));
	}

	/**
	 * @return string
	 */
	private function sunExtB()
	{
		return __DIR__ . '/../../../packages/SunExt/fonts/Sun-ExtB.ttf';
	}

	/**
	 * @return string
	 */
	private function dejaVu()
	{
		return __DIR__ . '/../../../packages/Dejavu-Family/fonts/DejaVuSans.ttf';
	}

	/**
	 * Four subsets as Mpdf fills them for 700 Plane 2 characters, and as FontWriter hands them on: the
	 * first seeded with ASCII, each later one starting from character 0, all padded to 98 codes and
	 * without code 0.
	 *
	 * @return array[]
	 */
	private function subsets()
	{
		$characters = range(0x20000, 0x20000 + 700);
		$subsets = [array_merge(range(0, 127), array_splice($characters, 0, 127))];
		while ($characters) {
			$subsets[] = array_merge([0], array_splice($characters, 0, 254));
		}

		foreach ($subsets as $j => $subset) {
			for ($n = count($subset); $n < 98; $n++) {
				$subset[$n] = 0;
			}
			unset($subset[0]);
			$subsets[$j] = $subset;
		}

		return $subsets;
	}

	/**
	 * Build one subset font the way FontWriter does, keeping what it reads off the subsetter after.
	 *
	 * @return array The program, and the two values FontWriter reads besides
	 */
	private function build(FontSubsetter $subsetter, $file, array $subset, $useOTL)
	{
		return [$subsetter->makeSubsetSIP($file, $subset, 0, false, $useOTL), $subsetter->defaultWidth, $subsetter->maxUniChar];
	}

	/**
	 * Every subset font of a document is the same program built after the others as built alone
	 *
	 * @dataProvider useOtlProvider
	 */
	public function testBuildsEachSubsetAsASubsetterThatHadBuiltNothingElse($useOTL)
	{
		$subsets = $this->subsets();
		$this->assertCount(4, $subsets);

		$shared = $this->subsetter();
		foreach ($subsets as $j => $subset) {
			$this->assertSame($this->build($this->subsetter(), $this->sunExtB(), $subset, $useOTL), $this->build($shared, $this->sunExtB(), $subset, $useOTL), 'subset ' . $j);
		}
	}

	/**
	 * @return array[]
	 */
	public function useOtlProvider()
	{
		return [[0], [0xFF]];
	}

	/**
	 * A subsetter asked for another font, or for the same font laid out another way, reads the
	 * character map again rather than building from the one it kept
	 */
	public function testReadsTheCharacterMapAgainForAnotherFontOrUseOtlSetting()
	{
		// With OTL on, each glyph no character maps is given one from U+E000, and then from U+2CEB0
		// once the Private Use Area is full, so these draw a glyph only where useOTL is set
		$subset = array_merge(range(1, 127), [0xE000, 0xE001, 0x2CEB0, 0x2CEB1]);
		$shared = $this->subsetter();

		foreach ([[$this->sunExtB(), 0], [$this->dejaVu(), 0], [$this->sunExtB(), 0], [$this->sunExtB(), 0xFF], [$this->sunExtB(), 0]] as $i => $build) {
			list($file, $useOTL) = $build;
			$this->assertSame($this->build($this->subsetter(), $file, $subset, $useOTL), $this->build($shared, $file, $subset, $useOTL), 'build ' . $i);
		}
	}
}
