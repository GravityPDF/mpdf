<?php

namespace Mpdf;

/**
 * The features the syllable shapers own are withheld from the presentation pass, and only there.
 *
 * Otl::PRESENTATION_OMIT_TAGS is the list four shaping paths hand to _applyTagSettings(), so that a
 * document's font-feature-settings cannot put one of those features into the presentation pass,
 * which carries no masks and would take its Lookups over every glyph of the run. A script with no
 * shaper applies the same features itself, unmasked, from its own tag list, and passes an empty
 * list: there the request is a document's to make.
 *
 * That boundary is what a single shared list can get wrong, and what this pins. Jomolhari states
 * ccmp alone of the tags in the list, decomposing uni0F73 into uni0F71 and uni0F72, and Tibetan
 * reaches the shaperless path - so turning ccmp off there must still be honoured, though ccmp is a
 * tag the Khmer and Indic paths would refuse.
 *
 * The other direction - that a shaping path refuses the same request - is not asserted here because
 * no packaged font makes it observable: re-applying a basic form over glyphs it has already been
 * applied to matches nothing, so the refusal and the acceptance draw the same run. Measuring it needs
 * a font built to show it, as #269's does.
 */
class PresentationOmitTagsTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	public function testAScriptWithNoShaperMayTurnOffAFeatureTheShapingPathsWithhold()
	{
		$this->assertNotFalse(
			strpos(Otl::PRESENTATION_OMIT_TAGS, 'ccmp'),
			'ccmp is the tag this reaches through, and must be one a shaping path withholds'
		);

		$this->assertSame([0xF612], $this->drawn(''));
		$this->assertSame([0x0F40, 0x0F73], $this->drawn("font-feature-settings:'ccmp' 0;"));
	}

	/**
	 * @param string $css added to the paragraph
	 *
	 * @return int[] the code points of the line as it is handed to the drawing code
	 */
	private function drawn($css)
	{
		$mpdf = new TextRecordingMpdf(['mode' => 'utf-8']);
		$mpdf->WriteHTML('<p style="font-family:jomolhari;' . $css . '">&#x0F40;&#x0F73;</p>');

		return array_values(unpack('N*', mb_convert_encoding($mpdf->drawnText[0], 'UTF-32BE', 'UTF-8')));
	}

}
