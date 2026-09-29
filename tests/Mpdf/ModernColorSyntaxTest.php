<?php

namespace Mpdf;

/**
 * rgb() and hsl() written with spaces, and with a slash before the alpha, in every property that takes a colour. Each
 * case must draw what the same colour written with commas draws, without a warning.
 */
class ModernColorSyntaxTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;

	/**
	 * @dataProvider colorsProvider
	 *
	 * @param string $modern HTML using the space-separated syntax
	 * @param string $legacy The same HTML using commas
	 * @param string $drawn  An operator, or an opacity, the document must contain
	 */
	public function testDrawsAsTheCommaSyntaxDoes($modern, $legacy, $drawn)
	{
		$expected = $this->drawn($legacy);
		$actual = $this->drawn($modern);

		$this->assertSame($expected, $actual);
		$this->assertStringContainsString($drawn, implode("\n", $actual['pages']) . "\n" . implode("\n", $actual['opacities']));
	}

	/**
	 * Each case: the space-separated syntax, the comma-separated one, and something only the right colour draws
	 *
	 * @return string[][]
	 */
	public function colorsProvider()
	{
		$block = '<div style="%s">Text</div>';
		$sheet = '<style>div.c { %s }</style><div class="c">Text</div>';

		$cases = [
			'color in a stylesheet' => [$sheet, 'color: rgb(255 0 0)', 'color: rgb(255,0,0)', '1.000 0.000 0.000 rg'],
			'color in a style attribute' => [$block, 'color: rgb(255 0 0)', 'color: rgb(255,0,0)', '1.000 0.000 0.000 rg'],
			'background-color with a slash alpha' => [$sheet, 'background-color: rgb(255 0 0 / 50%)', 'background-color: rgba(255,0,0,0.5)', '/ca 0.5'],
			'background with hsl and a slash alpha' => [$sheet, 'background: hsl(120 100% 25% / .5)', 'background: hsla(120,100%,25%,0.5)', '/ca 0.5'],
			'border' => [$sheet, 'border: 1mm solid rgb(255 0 0)', 'border: 1mm solid rgb(255,0,0)', '1.000 0.000 0.000 RG'],
			'border-color with two colours' => [
				$sheet,
				'border: 1mm solid; border-color: rgb(255 0 0) hsl(240 100% 50%)',
				'border: 1mm solid; border-color: rgb(255,0,0) hsl(240,100%,50%)',
				'0.000 0.000 1.000 RG',
			],
			'border-color with spaces after the commas' => [
				$sheet,
				'border: 1mm solid; border-color: rgb(255, 0, 0)',
				'border: 1mm solid; border-color: rgb(255,0,0)',
				'1.000 0.000 0.000 RG',
			],
			'border-color with cmyk() and spaces after the commas' => [
				$sheet,
				'border: 1mm solid; border-color: cmyk(0, 100, 0, 0)',
				'border: 1mm solid; border-color: cmyk(0,100,0,0)',
				'0.000 1.000 0.000 0.000 K',
			],
			'box-shadow' => [$sheet, 'box-shadow: 1mm 1mm rgb(255 0 0 / .5)', 'box-shadow: 1mm 1mm rgba(255,0,0,0.5)', '/ca 0.5'],
			'text-shadow' => [$sheet, 'text-shadow: 1mm 1mm rgb(0 0 255)', 'text-shadow: 1mm 1mm rgb(0,0,255)', '0.000 0.000 1.000 rg'],
			'linear-gradient' => [
				$sheet,
				'height: 10mm; background: linear-gradient(to right, rgb(255 0 0) 10%, hsl(240 100% 50%) 90%)',
				'height: 10mm; background: linear-gradient(to right, rgb(255,0,0) 10%, hsl(240,100%,50%) 90%)',
				'/Sh',
			],
			'hsl() with deg' => [$sheet, 'color: hsl(240deg 100% 50%)', 'color: hsl(240,100%,50%)', '0.000 0.000 1.000 rg'],
			'hsl() with turn' => [$sheet, 'color: hsl(0.5turn 100% 50%)', 'color: hsl(180,100%,50%)', '0.000 1.000 1.000 rg'],
			'hsl() with rad' => [$sheet, 'color: hsl(3.14159265rad 100% 50%)', 'color: hsl(180,100%,50%)', '0.000 1.000 1.000 rg'],
		];

		$data = [];
		foreach ($cases as $name => $case) {
			$data[$name] = [sprintf($case[0], $case[1]), sprintf($case[0], $case[2]), $case[3]];
		}

		$data['bgcolor attribute'] = [
			'<table><tr><td bgcolor="rgb(0 0 255)">Text</td></tr></table>',
			'<table><tr><td bgcolor="rgb(0,0,255)">Text</td></tr></table>',
			'0.000 0.000 1.000 rg',
		];

		return $data;
	}

	/**
	 * What a document draws: its page content streams, and the fill and stroke opacities of its graphics states.
	 * Asserts that drawing it raised no warning.
	 *
	 * @param string $html
	 *
	 * @return string[][]
	 */
	private function drawn($html)
	{
		$pdf = $this->assertDrawsSilently(function (Mpdf $mpdf) use ($html) {
			$mpdf->WriteHTML($html);
		});

		preg_match_all('#/(?:ca|CA) [\d.]+#', $pdf, $opacities);

		return ['pages' => $this->pages($pdf), 'opacities' => $opacities[0]];
	}

}
