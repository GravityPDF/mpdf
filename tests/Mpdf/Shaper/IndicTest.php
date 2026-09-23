<?php

namespace Mpdf\Shaper;

use Mpdf\TextRecordingMpdf;
use Mpdf\Unicode\Ucdn;

/**
 * rphf, pref, blwf, half, abvf, pstf and init are applied only where this shaper asked for them, and
 * it asks by setting a bit on the glyph. The bit lives in ['mask'], which Otl reads for every glyph a
 * masked feature is offered - so a glyph with no mask at all is a glyph those features read past.
 */
class IndicTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * Syllables are tagged as set_syllables() writes them: a serial in the high nibble so that
	 * neighbouring clusters can be told apart, and the cluster type in the low nibble
	 */
	private function syllable($serial, $type)
	{
		return ($serial << 4) | $type;
	}

	private function reorder($info)
	{
		Indic::initial_reordering_syllable(
			$info,
			[],
			Indic::$indic_configs[Ucdn::SCRIPT_DEVANAGARI],
			Ucdn::SCRIPT_DEVANAGARI,
			false,
			0,
			count($info)
		);

		return $info;
	}

	/**
	 * A cluster of characters this shaper has nothing to say about is returned untouched, and used to
	 * be returned before the masks were set up. Every masked feature then read a mask that was not
	 * there.
	 */
	public function testAClusterTheShaperDeclinesToReorderStillCarriesAMask()
	{
		$info = $this->reorder([[
			'uni' => 0x0041,
			'syllable' => $this->syllable(1, Indic::NON_INDIC_CLUSTER),
			'indic_category' => Indic::OT_X,
			'indic_position' => Indic::POS_END,
		]]);

		$this->assertSame(0, $info[0]['mask']);
	}

	/**
	 * The other way out before any mask is set: a broken cluster whose last glyph is the dotted circle
	 * inserted into it, which Uniscribe leaves alone rather than forming a Reph from.
	 */
	public function testABrokenClusterEndingInItsDottedCircleCarriesAMask()
	{
		$info = $this->reorder([
			[
				'uni' => 0x0951,
				'syllable' => $this->syllable(1, Indic::BROKEN_CLUSTER),
				'indic_category' => Indic::OT_A,
				'indic_position' => Indic::POS_END,
			],
			[
				'uni' => 0x25CC,
				'syllable' => $this->syllable(1, Indic::BROKEN_CLUSTER),
				'indic_category' => Indic::OT_DOTTEDCIRCLE,
				'indic_position' => Indic::POS_BASE_C,
			],
		]);

		$this->assertSame([0, 0], [$info[0]['mask'], $info[1]['mask']]);
	}

	/**
	 * A Sinhala virama after a dotted circle is one such cluster, and reached Otl's masked features as
	 * two warnings a feature. A null reads as 0, so the feature was not applied - which is what a
	 * mask of 0 asks for, and is why nothing is drawn differently now that the mask is there.
	 */
	public function testAMarkWithNoBaseIsShapedWithoutReadingAMaskThatIsNotThere()
	{
		$drawn = $this->render('kaputaunicode', [0x25CC, 0x0DCA]);

		$this->assertSame([], $drawn['diagnostics']);
		$this->assertSame([0x25CC, 0x0DCA], $drawn['text']);
	}

	/**
	 * And a lone Sinhala left matra reaches the 'init' pass of the final reordering, which reads the
	 * same key from the other side.
	 */
	public function testALoneMatraIsShapedWithoutReadingAMaskThatIsNotThere()
	{
		$drawn = $this->render('freeserif', [0x0DD9]);

		$this->assertSame([], $drawn['diagnostics']);
		$this->assertSame([0x0DD9], $drawn['text']);
	}

	/**
	 * Consonant, virama, consonant in each script, with whether HarfBuzz's config for that script
	 * applies blwf before the base (BLWF_MODE_PRE_AND_POST) as well as after it.
	 *
	 * @return array[] script, code points, whether the new-spec pre-base glyphs carry the blwf bit
	 */
	public function dataPreBaseBelowForms()
	{
		return [
			'Devanagari' => [Ucdn::SCRIPT_DEVANAGARI, [0x0915, 0x094D, 0x0915], true],
			'Bengali' => [Ucdn::SCRIPT_BENGALI, [0x0995, 0x09CD, 0x0995], true],
			'Gurmukhi' => [Ucdn::SCRIPT_GURMUKHI, [0x0A15, 0x0A4D, 0x0A15], true],
			'Gujarati' => [Ucdn::SCRIPT_GUJARATI, [0x0A95, 0x0ACD, 0x0A95], true],
			'Oriya' => [Ucdn::SCRIPT_ORIYA, [0x0B15, 0x0B4D, 0x0B15], true],
			'Tamil' => [Ucdn::SCRIPT_TAMIL, [0x0B95, 0x0BCD, 0x0B95], true],
			'Malayalam' => [Ucdn::SCRIPT_MALAYALAM, [0x0D15, 0x0D4D, 0x0D15], true],
			'Telugu' => [Ucdn::SCRIPT_TELUGU, [0x0C15, 0x0C4D, 0x0C15], false],
			'Kannada' => [Ucdn::SCRIPT_KANNADA, [0x0C95, 0x0CCD, 0x0C95], false],
		];
	}

	/**
	 * The consonant and virama before the base are marked for blwf as well as for half in the scripts
	 * whose HarfBuzz config is BLWF_MODE_PRE_AND_POST, and for half only in Telugu and Kannada. The
	 * base itself is marked for neither.
	 *
	 * @dataProvider dataPreBaseBelowForms
	 */
	public function testPreBaseGlyphsAreMarkedForBlwfWhereTheScriptAppliesItBeforeTheBase($script, $codepoints, $preBaseBlwf)
	{
		$info = $this->reorderConjunct($script, $codepoints, false);

		$preBase = Indic::FLAG(Indic::HALF) | ($preBaseBlwf ? Indic::FLAG(Indic::BLWF) : 0);
		$this->assertSame([$preBase, $preBase, 0], array_column($info, 'mask'));
	}

	/**
	 * A font with only the original Indic script tags gets no pre-base blwf bit, as in HarfBuzz,
	 * which reserves it for the v2 tags.
	 */
	public function testAnOldSpecFontGetsNoPreBaseBlwf()
	{
		$info = $this->reorderConjunct(Ucdn::SCRIPT_DEVANAGARI, [0x0915, 0x094D, 0x0915], true);

		$preBase = Indic::FLAG(Indic::HALF);
		$this->assertSame([$preBase, $preBase, 0], array_column($info, 'mask'));
	}

	/**
	 * Run initial_reordering_syllable() over consonant, virama, consonant with a font offering none
	 * of the features the base search reads, so the last consonant is the base.
	 *
	 * @param int   $script     The UCDN script number
	 * @param int[] $codepoints Consonant, virama, consonant
	 * @param bool  $isOldSpec  Whether the font has only the original Indic script tags
	 *
	 * @return array[] The glyph info after reordering
	 */
	private function reorderConjunct($script, $codepoints, $isOldSpec)
	{
		$info = [];
		foreach ($codepoints as $i => $codepoint) {
			$info[] = [
				'uni' => $codepoint,
				'syllable' => $this->syllable(1, Indic::CONSONANT_SYLLABLE),
				'indic_category' => $i === 1 ? Indic::OT_H : Indic::OT_C,
				'indic_position' => $i === 1 ? Indic::POS_END : Indic::POS_BASE_C,
			];
		}

		Indic::initial_reordering_syllable(
			$info,
			['rphf' => [], 'pref' => [], 'blwf' => [], 'pstf' => []],
			Indic::$indic_configs[$script],
			$script,
			$isOldSpec,
			0,
			count($info)
		);

		return $info;
	}

	/**
	 * @return array the diagnostics Otl and the shaper raised, and the codepoints of the drawn line
	 */
	private function render($font, $codepoints)
	{
		$diagnostics = [];

		set_error_handler(function ($no, $message, $file) use (&$diagnostics) {
			if (false !== strpos($file, 'Indic.php') || false !== strpos($file, 'Otl.php')) {
				$diagnostics[] = $message;
			}

			return true;
		});

		$html = '';
		foreach ($codepoints as $codepoint) {
			$html .= sprintf('&#x%04X;', $codepoint);
		}

		$mpdf = new TextRecordingMpdf();
		$mpdf->WriteHTML('<p style="font-family:' . $font . '">' . $html . '</p>');

		restore_error_handler();

		return [
			'diagnostics' => array_unique($diagnostics),
			'text' => array_values(unpack('N*', mb_convert_encoding($mpdf->drawnText[0], 'UTF-32BE', 'UTF-8'))),
		];
	}

}
