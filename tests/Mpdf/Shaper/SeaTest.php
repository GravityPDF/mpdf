<?php

namespace Mpdf\Shaper;

use Mpdf\TextRecordingMpdf;

/**
 * The South East Asian shaper categorises every character of a run before it groups them into
 * clusters, then applies 'locl' and 'ccmp' before it reorders each cluster. A substitution in that
 * pass makes glyphs the categorising pass never saw, and Otl handed them on without a
 * ['sea_category'] of their own - which the reorderer reads on the last glyph of a broken cluster.
 *
 * The shaper is only reached under a Tai Tham script tag, and no Tai Tham font in reach offers one:
 * Lanna Alif has latn, and Noto Sans Tai Tham DFLT and latn, under which HarfBuzz and mPDF both use
 * the default shaper. NotoSansTaiTham-LanaScript-Synthetic is a subset of Noto Sans Tai Tham 2.002
 * (OFL 1.1) with its script retagged lana and its Sakot ligatures moved from 'liga' to 'ccmp'. The
 * ligature has no codepoint of its own, so it is mapped into the Private Use Area as the subset is
 * built. `hb-shape` draws what each test below expects.
 *
 * What the read turns on is that the last glyph of the cluster came out of that pass, not how long
 * the cluster is. The font has no dotted circle to put in front of the ligature, so the cluster is
 * one glyph; Lanna Alif, which has one, warned on a cluster of two.
 */
class SeaTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/** U+1A20 TAI THAM LETTER HIGH KA */
	const HIGH_KA = 0x1A20;

	/** U+1A60 TAI THAM SIGN SAKOT, which subscripts the consonant after it */
	const SAKOT = 0x1A60;

	/** The subscript High Ka that the font's 'ccmp' substitutes for Sakot + High Ka */
	const SAKOT_HIGH_KA = 0xE002;

	/**
	 * A Sakot with no consonant in front of it is a broken cluster, and the reorderer reads the
	 * category of its last glyph before deciding whether to reorder anything. By then the Sakot and
	 * the consonant after it are one ligated glyph the categorising pass never saw, so the read raised
	 * a warning per run.
	 */
	public function testAGlyphSubstitutedBeforeTheReorderingCarriesACategoryIntoIt()
	{
		$raised = [];

		set_error_handler(function ($number, $message, $file) use (&$raised) {
			if (false !== strpos($file, 'Sea.php')) {
				$raised[] = $message;
			}

			return true;
		});

		$drawn = $this->drawn([self::SAKOT, self::HIGH_KA]);

		restore_error_handler();

		$this->assertSame([], $raised);
		$this->assertSame([self::SAKOT_HIGH_KA], $drawn);
	}

	/**
	 * The same Sakot inside a well-formed cluster, which is what the rule is for: the consonant in
	 * front of it is the base, and the pair after it is subscripted under it.
	 */
	public function testASakotInsideAClusterSubscriptsTheConsonantAfterIt()
	{
		$this->assertSame(
			[self::HIGH_KA, self::SAKOT_HIGH_KA],
			$this->drawn([self::HIGH_KA, self::SAKOT, self::HIGH_KA])
		);
	}

	/**
	 * A Sakot on its own ligates with nothing, so no glyph the categorising pass missed ever reaches
	 * the reorderer and the character is drawn as it was written.
	 */
	public function testASakotWithNothingAfterItIsDrawnAsItself()
	{
		$this->assertSame([self::SAKOT], $this->drawn([self::SAKOT]));
	}

	/**
	 * @param int[] $codepoints
	 *
	 * @return int[] the codepoints of the line as it is handed to the drawing code, in visual order
	 */
	private function drawn($codepoints)
	{
		$html = '';
		foreach ($codepoints as $codepoint) {
			$html .= sprintf('&#x%04X;', $codepoint);
		}

		$mpdf = new TextRecordingMpdf([
			'fontDir' => [__DIR__ . '/../../data/ttf'],
			'fontdata' => ['notosanstaithamlanascriptsynthetic' => [
				'R' => 'NotoSansTaiTham-LanaScript-Synthetic.ttf',
				'useOTL' => 0xFF,
			]],
		]);
		$mpdf->WriteHTML('<p style="font-family:notosanstaithamlanascriptsynthetic">' . $html . '</p>');

		return array_values(unpack('N*', mb_convert_encoding($mpdf->drawnText[0], 'UTF-32BE', 'UTF-8')));
	}

}
