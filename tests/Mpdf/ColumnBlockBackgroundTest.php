<?php

namespace Mpdf;

class ColumnBlockBackgroundTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{
	use PageStreams;

	/**
	 * Both CSS modes, since the warnings came in each
	 *
	 * @return array
	 */
	public static function cssModeProvider()
	{
		return [
			'standard' => [CssMode::STANDARD],
			'legacy' => [CssMode::LEGACY],
		];
	}

	/**
	 * A translucent block inside a block with a border but no background, in columns, renders without a warning and
	 * still paints its own translucent background
	 *
	 * @dataProvider cssModeProvider
	 *
	 * @param string $cssMode
	 */
	public function testTranslucentBlockInBorderedBlockInColumnsRendersSilently($cssMode)
	{
		$pdf = $this->assertDrawsSilently(function (Mpdf $mpdf) {
			$mpdf->WriteHTML('<columns column-count="2" /><div style="border: 1px solid black"><div style="background: rgba(255, 0, 0, 0.5)">Text</div></div>');
		}, ['cssMode' => $cssMode]);

		$this->assertMatchesRegularExpression('#/GS\d+ gs 1\.000 0\.000 0\.000 rg#', $pdf, 'The translucent background should be painted');
	}

	/**
	 * A block in columns whose background sets no colour takes its parent's, and renders without a warning when the
	 * parent has none either
	 *
	 * @dataProvider cssModeProvider
	 *
	 * @param string $cssMode
	 */
	public function testTransparentBlockInColumnsRendersSilently($cssMode)
	{
		$this->assertDrawsSilently(function (Mpdf $mpdf) {
			$mpdf->WriteHTML('<columns column-count="2" /><div><div style="background-color: transparent">Text</div></div>');
		}, ['cssMode' => $cssMode]);
	}
}
