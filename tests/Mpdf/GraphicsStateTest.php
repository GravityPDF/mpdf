<?php

namespace Mpdf;

/**
 * A "Q" puts back the graphics state that its "q" saved. The setters only write an operator when its value
 * differs from the one they last wrote, so unless what they remember goes back with it they stay quiet about
 * a value the restore has just undone, and whatever is drawn next takes the state the page was left in
 * rather than the one it asked for. See GravityPDF/mpdf#71.
 */
class GraphicsStateTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;

	/**
	 * Two images asking for the same border. A CSS transform wraps each one's border in "q <matrix> cm ...
	 * Q", so the second draws its own from the state the page held before the first.
	 */
	private function twoTransformedImages()
	{
		$image = '<img src="' . $this->pngImage() . '" style="width: 40mm; height: 20mm; border: 1mm solid #c00; transform: rotate(20deg)">';

		return $image . $image;
	}

	public function testEachTransformedImageStrokesItsBorderInTheWidthItAsksFor()
	{
		$streams = $this->pages($this->render($this->twoTransformedImages()));

		$this->assertSame(2, substr_count($streams[0], '2.835 w'), 'Both borders should be stroked 1mm wide');
	}

	public function testEachTransformedImageStrokesItsBorderInTheColourItAsksFor()
	{
		$streams = $this->pages($this->render($this->twoTransformedImages()));

		$this->assertSame(2, substr_count($streams[0], '0.800 0.000 0.000 RG'), 'Both borders should be stroked in #c00');
	}

	public function testALineWidthSetInsideATransformIsSetAgainForWhatFollowsIt()
	{
		$mpdf = $this->mpdf();
		$mpdf->WriteHTML('<p>Page</p>');

		$mpdf->SetLineWidth(1);
		$mpdf->StartTransform();
		$mpdf->SetLineWidth(2);
		$mpdf->Line(10, 10, 50, 10);
		$mpdf->StopTransform();
		$mpdf->SetLineWidth(2);
		$mpdf->Line(10, 20, 50, 20);

		$streams = $this->pages($this->output($mpdf));

		$this->assertSame(2, substr_count($streams[0], '5.669 w'), 'The width the restore undid should be written again for the line after it');
	}

	/**
	 * A block's solid borders are each stroked inside a clip of their own. Where a page break closes an
	 * outer block and then a rounded one inside it, the rounded border strokes in the page's line width,
	 * which must be the 0.1mm the outer block reset it to rather than whatever was in force before the clips.
	 */
	public function testABlockBorderLeavesTheRoundedBorderAfterItTheWidthItAsksFor()
	{
		$mpdf = $this->mpdf();
		$mpdf->WriteHTML('<p>Page</p>');
		$mpdf->SetLineWidth(0.2);
		$mpdf->Line(10, 10, 50, 10);
		$mpdf->WriteHTML('<div style="border: 0.1mm solid #000"><div style="border: 0.1mm solid #203; border-radius: 2mm">' . $this->filler(40) . '</div></div>');

		$widths = $this->strokeWidths($this->pages($this->output($mpdf))[0]);

		$this->assertSame(['0.283'], array_values(array_unique(array_slice($widths, 1))), 'Every border should be stroked 0.1mm wide');
	}

	/**
	 * A width set between Rotate() and Rotate(0) is undone by the "Q" the second call writes.
	 */
	public function testALineWidthSetInsideARotationIsSetAgainForWhatFollowsIt()
	{
		$mpdf = $this->mpdf();
		$mpdf->WriteHTML('<p>Page</p>');

		$mpdf->SetLineWidth(1);
		$mpdf->Rotate(30, 50, 50);
		$mpdf->SetLineWidth(2);
		$mpdf->Line(10, 10, 50, 10);
		$mpdf->Rotate(0);
		$mpdf->SetLineWidth(2);
		$mpdf->Line(10, 20, 50, 20);

		$this->assertSame(['5.669', '5.669'], $this->strokeWidths($this->pages($this->output($mpdf))[0]));
	}

	/**
	 * A table hiding its overflow is drawn inside a clip. The font and line width its cells set go when the
	 * clip does, so the body's own, which the table resets them to afterwards, are already in force again.
	 */
	public function testAClippedTableLeavesTheBodyFontAndWidthInForceAfterIt()
	{
		$streams = $this->pages($this->render('<p>Page</p><table style="overflow: hidden; border-collapse: collapse"><tr><td style="font-size: 20pt; border: 1mm solid #000">Cell</td></tr></table>'));
		$afterTable = substr($streams[0], strrpos($streams[0], "\nQ\n"));

		$this->assertStringNotContainsString('11.000 Tf', $afterTable, 'The body font should not be set again');
		$this->assertStringNotContainsString(' w', $afterTable, 'The body line width should not be set again');
	}

	/**
	 * The line width in force at each "S" in a content stream, following it through "q" and "Q".
	 *
	 * @param string $stream
	 *
	 * @return string[]
	 */
	private function strokeWidths($stream)
	{
		preg_match_all('/\S+/', $stream, $tokens);

		$saved = [];
		$width = null;
		$previous = null;
		$widths = [];
		foreach ($tokens[0] as $token) {
			if ($token === 'q') {
				$saved[] = $width;
			} elseif ($token === 'Q') {
				$width = array_pop($saved);
			} elseif ($token === 'w') {
				$width = $previous;
			} elseif ($token === 'S') {
				$widths[] = $width;
			}
			$previous = $token;
		}

		return $widths;
	}

}
