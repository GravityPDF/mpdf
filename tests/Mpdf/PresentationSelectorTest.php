<?php

namespace Mpdf;

use Mpdf\Fonts\FontRegistry;

/**
 * What becomes of U+FE0F on its way through the shaper, which HarfBuzz lets a ligature match with or
 * without. Two fonts that differ only in their keycap: TestEmoji-COLRv0 forms it from the digit and
 * U+20E3 as Noto does, TestEmoji-FE0F from the digit, U+FE0F and U+20E3 as Twemoji does.
 */
class PresentationSelectorTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * @param string $font  A font in tests/data/ttf/color
	 * @param int[]  $codepoints
	 *
	 * @return int[] The codepoints of the line as it was drawn
	 */
	private function drawn($font, array $codepoints)
	{
		$mpdf = new TextRecordingMpdf([
			'mode' => 'utf-8',
			'fontRegistry' => new FontRegistry([]),
			'fontDir' => [__DIR__ . '/../data/ttf/color'],
			'fontdata' => ['emoji' => ['R' => $font, 'useOTL' => 0xFF]],
			'default_font' => 'emoji',
		]);
		$mpdf->WriteHTML('<p>' . implode('', array_map('Mpdf\Utils\UtfString::code2utf', $codepoints)) . '</p>');

		return $mpdf->drawnCodepoints(0);
	}

	/**
	 * The keycap forms from the fully qualified sequence in either font
	 *
	 * @dataProvider fonts
	 *
	 * @param string $font A font in tests/data/ttf/color
	 */
	public function testAKeycapFormsWhetherTheFontsSequenceHasTheSelectorOrNot($font)
	{
		$drawn = $this->drawn($font, [0x31, 0xFE0F, 0x20E3]);

		$this->assertCount(1, $drawn);
		$this->assertGreaterThanOrEqual(0xE000, $drawn[0]);
	}

	/**
	 * Twemoji gives U+FE0F a full em of advance, so one no ligature takes in is taken out after
	 *
	 * @dataProvider fonts
	 *
	 * @param string $font A font in tests/data/ttf/color
	 */
	public function testASelectorNoLigatureTakesInIsNotDrawn($font)
	{
		$this->assertSame([0x2764], $this->drawn($font, [0x2764, 0xFE0F]));
	}

	/**
	 * @return array[] Each font, as its keycap is formed
	 */
	public function fonts()
	{
		return [
			'Noto\'s sequences' => ['TestEmoji-COLRv0.ttf'],
			'Twemoji\'s sequences' => ['TestEmoji-FE0F.ttf'],
		];
	}
}
