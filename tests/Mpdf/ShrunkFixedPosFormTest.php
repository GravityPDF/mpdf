<?php

namespace Mpdf;

/**
 * An active field in a block WriteFixedPosHTML() shrinks to fit its box keeps an appearance that matches the shrunk
 * widget (#457)
 */
class ShrunkFixedPosFormTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;

	/**
	 * The appearance is drawn at the size /DA names, with each line of text and each highlight inside its box, and the
	 * sizes /DA and /BS name are written to three decimals
	 *
	 * @dataProvider fields
	 *
	 * @param string $field
	 */
	public function testAppearanceIsScaledWithTheWidget($field)
	{
		$mpdf = $this->mpdf(['useActiveForms' => true]);
		$filler = str_repeat('<p>Filler line that makes the block taller than its box.</p>', 12);
		$mpdf->WriteFixedPosHTML('<form>' . $field . '</form>' . $filler, 20, 40, 120, 40, 'auto');
		$pdf = $this->output($mpdf);

		$widget = $this->object($pdf, $this->annotationRefs($pdf)[0][0]);
		$this->assertSame(1, preg_match('/\/DA \(\/F(\d+) (\d+(?:\.\d{1,3})?) Tf /', $widget, $da));
		$this->assertSame(1, preg_match('/\/BS << \/W \d+(?:\.\d{1,3})? /', $widget));
		$this->assertSame(1, preg_match('/\/AP << \/N (?:<< \/Push )?(\d+) 0 R/', $widget, $ref));

		$appearance = $this->object($pdf, $ref[1]);
		$this->assertSame(1, preg_match('/\/BBox \[0 0 ([\d.]+) ([\d.]+)\]/', $appearance, $box));
		$this->assertSame(1, preg_match('/stream\n(.*)\nendstream/s', $appearance, $stream));
		$this->assertSame(1, preg_match('/^q ([\d.]+) 0 0 \1 0 0 cm /', $stream[1], $matrix));
		$scale = (float) $matrix[1];
		$this->assertLessThan(1, $scale);

		$this->assertSame(1, preg_match('/BT \/F' . $da[1] . ' ([\d.]+) Tf ET/', $stream[1], $tf));
		$size = $tf[1] * $scale;
		$this->assertEqualsWithDelta((float) $da[2], $size, 0.001);

		$desc = $this->fontDescriptor($mpdf, $da[1]);
		preg_match_all('/BT -?[\d.]+ (-?[\d.]+) Td/', $stream[1], $baselines);
		$this->assertNotEmpty($baselines[1]);
		foreach ($baselines[1] as $baseline) {
			$this->assertGreaterThanOrEqual(0, $baseline * $scale + $desc['Descent'] / 1000 * $size);
			$this->assertLessThanOrEqual((float) $box[2], $baseline * $scale + $desc['Ascent'] / 1000 * $size);
		}

		preg_match_all('/([\d.]+) ([\d.]+) ([\d.]+) ([\d.]+) re f/', $stream[1], $rects, PREG_SET_ORDER);
		foreach ($rects as $rect) {
			$this->assertLessThanOrEqual($box[1] + 0.002, ($rect[1] + $rect[3]) * $scale);
			$this->assertLessThanOrEqual($box[2] + 0.002, ($rect[2] + $rect[4]) * $scale);
		}
	}

	/**
	 * A text field, a list box with a highlighted option, and a push button
	 *
	 * @return string[][]
	 */
	public function fields()
	{
		return [
			'text field' => ['<input type="text" name="t" value="Typed value" size="30" />'],
			'list box' => ['<select name="s" size="3"><option value="1">One</option><option value="2" selected="selected">Two</option><option value="3">Three</option></select>'],
			'push button' => ['<input type="button" name="b" value="Press" />'],
		];
	}

	/**
	 * A field in a block that fits its box keeps the appearance it was laid out with
	 */
	public function testAppearanceOfAFieldThatFitsIsNotScaled()
	{
		$mpdf = $this->mpdf(['useActiveForms' => true]);
		$mpdf->WriteFixedPosHTML('<form><input type="text" name="t" value="Typed value" /></form>', 20, 40, 120, 40, 'auto');
		$pdf = $this->output($mpdf);

		$widget = $this->object($pdf, $this->annotationRefs($pdf)[0][0]);
		$this->assertSame(1, preg_match('/\/AP << \/N (\d+) 0 R/', $widget, $ref));
		$this->assertStringNotContainsString(' cm ', $this->object($pdf, $ref[1]));
	}

	/**
	 * The descriptor of the font a PDF names /F$number
	 *
	 * @param \Mpdf\Mpdf $mpdf
	 * @param string $number
	 *
	 * @return mixed[]
	 */
	private function fontDescriptor(Mpdf $mpdf, $number)
	{
		foreach ($mpdf->fonts as $font) {
			if ($font['i'] == $number) {
				return $font['desc'];
			}
		}

		$this->fail('No font is written as /F' . $number);
	}
}
