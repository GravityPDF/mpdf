<?php

namespace Mpdf\Shaper;

use Mpdf\TextRecordingMpdf;
use Mpdf\Ucdn;

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
