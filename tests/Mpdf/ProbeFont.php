<?php

namespace Mpdf;

/**
 * One document in one TrueType font, for the tests that argue about whether the font was embedded
 * whole or cut down to what was drawn.
 *
 * Poppins is 154KB and covers 470 characters - small enough that a document can draw a share of it
 * worth measuring, and large enough for a threshold to sit either side of that share.
 */
trait ProbeFont
{

	/**
	 * @param string $html   What the document draws
	 * @param array  $config What it is given beyond the font, typically the two options
	 *
	 * @return array The font as it was written, and the document it was written into
	 */
	private function embed($html, array $config = [])
	{
		$mpdf = new Mpdf($config + [
			'mode' => 'utf-8',
			'fontDir' => [__DIR__ . '/../data/ttf'],
			'fontdata' => ['probe' => ['R' => 'Poppins-Regular.ttf', 'useOTL' => 0]],
			'default_font' => 'probe',
		]);
		$mpdf->WriteHTML($html);
		$pdf = $mpdf->Output('', 'S');
		$mpdf->cleanup();

		return [$mpdf->fonts['probe'], $pdf];
	}

	/**
	 * Every letter in both cases: 52 distinct characters, 11% of the font.
	 *
	 * @return string
	 */
	private function alphabet()
	{
		return '<p>ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz</p>';
	}

	/**
	 * A subset wears a six-letter tag on its name, which is what PDF/A and PDF/X ask of one and what
	 * tells the two branches apart in the output.
	 */
	private function assertSubsetted($font, $pdf)
	{
		$this->assertTrue($font['asSubset']);
		$this->assertStringContainsString('/BaseFont /MPDFAA+Poppins', $pdf);
	}

	private function assertEmbeddedWhole($font, $pdf)
	{
		$this->assertFalse($font['asSubset']);
		$this->assertStringContainsString('/BaseFont /Poppins-Regular', $pdf);
		$this->assertStringNotContainsString('MPDFAA+', $pdf);
	}
}
