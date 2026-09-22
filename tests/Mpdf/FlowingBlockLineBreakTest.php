<?php

namespace Mpdf;

/**
 * WriteFlowingBlock() measures a run one character at a time and breaks the line at the first
 * character that does not fit, so every width it adds up - the soft hyphen that takes none, a small
 * capital drawn at the scaled width of its upper-case form, letter and word spacing, a kerning pair -
 * shows up as where the lines of a long paragraph end. finishFlowingBlock() measures each line again
 * with GetStringWidth() before drawing it, so the breaks are the only place those widths are seen.
 * Each style breaks the paragraph differently from the plain one at this width.
 */
class FlowingBlockLineBreakTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	const SHY = "\xc2\xad"; // U+00AD SOFT HYPHEN

	/**
	 * @dataProvider paragraphs
	 *
	 * @param string $style
	 * @param array $config
	 * @param string[] $expected
	 */
	public function testALongParagraphBreaksWhereItsCharactersStopFitting($style, $config, $expected)
	{
		$this->assertSame($expected, $this->drawnLines($style, $config));
	}

	/**
	 * One paragraph per width WriteFlowingBlock() works out, then all of them at once.
	 *
	 * @return array[]
	 */
	public function paragraphs()
	{
		$kerning = ['useKerning' => true];

		return [
			'soft hyphens alone' => ['font-family: dejavuserif', [], [
				'AVAILABLE To',
				'Wave, Yo-yo:',
				'Typographers',
				'measure every',
				'character of a',
				'paragraph before',
				'they decide where',
				'its lines end —',
				'kerning pulls AV,',
				'Ta and Yo together,',
				'letter spacing',
				'opens the whole',
				'line, and a soft',
				'hyphen offers a',
				'place to break a',
				'long word such as',
				'extraordinarily or in-',
				'comprehensibilities',
				'or counterrevolu-',
				'tionaries when',
				'nothing else fits.',
			]],
			'small caps without OTL' => ['font-family: dejavuserif; font-variant: small-caps', [], [
				'AVAILABLE To',
				'Wave, Yo-yo:',
				'Typographers',
				'measure every',
				'character of a',
				'paragraph before',
				'they decide where',
				'its lines end —',
				'kerning pulls AV,',
				'Ta and Yo',
				'together, letter',
				'spacing opens the',
				'whole line, and a',
				'soft hyphen',
				'offers a place to',
				'break a long word',
				'such as extraordi-',
				'narily or incompre-',
				'hensibilities or',
				'counterrevolu-',
				'tionaries when',
				'nothing else fits.',
			]],
			'small caps with OTL' => ['font-family: dejavusans; font-variant: small-caps', [], [
				'AVAILABLE To',
				'Wave, Yo-yo:',
				'Typographers',
				'measure every',
				'character of a',
				'paragraph before',
				'they decide where',
				'its lines end —',
				'kerning pulls AV, Ta',
				'and Yo together,',
				'letter spacing opens',
				'the whole line, and',
				'a soft hyphen offers',
				'a place to break a',
				'long word such as',
				'extraordinarily or',
				'incomprehensibilities',
				'or counterrevolu-',
				'tionaries when',
				'nothing else fits.',
			]],
			'letter spacing' => ['font-family: dejavuserif; letter-spacing: 0.35mm', [], [
				'AVAILABLE To',
				'Wave, Yo-yo:',
				'Typographers',
				'measure every',
				'character of a',
				'paragraph',
				'before they',
				'decide where its',
				'lines end —',
				'kerning pulls AV,',
				'Ta and Yo',
				'together, letter',
				'spacing opens',
				'the whole line,',
				'and a soft',
				'hyphen offers a',
				'place to break a',
				'long word such',
				'as extraordinari-',
				'ly or incompre-',
				'hensibilities or',
				'counterrevolu-',
				'tionaries when',
				'nothing else fits.',
			]],
			'word spacing' => ['font-family: dejavuserif; word-spacing: 1.5mm', [], [
				'AVAILABLE To',
				'Wave, Yo-yo:',
				'Typographers',
				'measure every',
				'character of a',
				'paragraph before',
				'they decide where',
				'its lines end —',
				'kerning pulls AV,',
				'Ta and Yo',
				'together, letter',
				'spacing opens the',
				'whole line, and a',
				'soft hyphen offers',
				'a place to break',
				'a long word such',
				'as extraordinarily',
				'or incomprehensi-',
				'bilities or counter-',
				'revolutionaries',
				'when nothing else',
				'fits.',
			]],
			'kerning table' => ['font-family: dejavuserif; font-kerning: normal', $kerning, [
				'AVAILABLE To',
				'Wave, Yo-yo:',
				'Typographers',
				'measure every',
				'character of a',
				'paragraph before',
				'they decide where',
				'its lines end —',
				'kerning pulls AV, Ta',
				'and Yo together,',
				'letter spacing',
				'opens the whole',
				'line, and a soft',
				'hyphen offers a',
				'place to break a',
				'long word such as',
				'extraordinarily or in-',
				'comprehensibilities',
				'or counterrevolu-',
				'tionaries when',
				'nothing else fits.',
			]],
			'everything at once' => ['font-family: dejavuserif; font-kerning: normal; font-variant: small-caps; letter-spacing: 0.2mm; word-spacing: 1mm', $kerning, [
				'AVAILABLE To',
				'Wave, Yo-yo:',
				'Typographers',
				'measure every',
				'character of a',
				'paragraph',
				'before they',
				'decide where its',
				'lines end —',
				'kerning pulls',
				'AV, Ta and Yo',
				'together, letter',
				'spacing opens',
				'the whole line,',
				'and a soft',
				'hyphen offers a',
				'place to break',
				'a long word',
				'such as extraor-',
				'dinarily or in-',
				'comprehensibili-',
				'ties or counter-',
				'revolutionaries',
				'when nothing',
				'else fits.',
			]],
		];
	}

	/**
	 * @param string $style
	 * @param array $config
	 *
	 * @return string[] The text of each line, in the order it is drawn in
	 */
	private function drawnLines($style, $config)
	{
		$mpdf = new TextRecordingMpdf($config + ['mode' => 'utf-8', 'format' => [70, 400]]);
		$mpdf->WriteHTML('<p style="font-size: 11pt; ' . $style . '">' . $this->text() . '</p>');
		$lines = $mpdf->drawnText;
		$mpdf->cleanup();

		return $lines;
	}

	/**
	 * Kerning pairs, capitals and long words that offer soft hyphens, with punctuation and a
	 * character outside Latin-1.
	 *
	 * @return string
	 */
	private function text()
	{
		return str_replace('|', self::SHY, 'AVAILABLE To Wave, Yo-yo: Typographers measure every character of a paragraph '
			. 'before they decide where its lines end — kerning pulls AV, Ta and Yo together, letter spacing opens '
			. 'the whole line, and a soft hyphen offers a place to break a long word such as ex|tra|or|di|nar|i|ly '
			. 'or in|com|pre|hen|si|bil|i|ties or coun|ter|rev|o|lu|tion|ar|ies when nothing else fits.');
	}

}
