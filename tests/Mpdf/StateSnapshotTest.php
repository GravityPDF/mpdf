<?php

namespace Mpdf;

use Mpdf\Container\SimpleContainer;

/**
 * Mpdf::getStateSnapshot() and restoreStateSnapshot() carry the table of contents look-ahead
 */
class StateSnapshotTest extends BaseMpdfTest
{

	use PageStreams;

	/**
	 * CurrentFont is a reference into the font table. Restoring a snapshot taken in one font while another was
	 * selected must bind it to the restored selection: SetFont() does not while the family, style and size
	 * already match, and the glyphs Write() registered afterwards went to the other font's subset
	 */
	public function testRestoringASnapshotRebindsTheCurrentFont()
	{
		$this->mpdf = new Mpdf(['mode' => 'utf-8', 'default_font' => 'dejavusans']);
		$this->mpdf->WriteHTML('<p>Before</p>');
		$snapshot = $this->mpdf->getStateSnapshot();

		$this->mpdf->SetFont('dejavusans', 'B');
		$this->mpdf->restoreStateSnapshot($snapshot);
		$this->mpdf->Write(5, "\xCE\xA9");

		$this->assertSame('dejavusans', $this->mpdf->FontFamily . $this->mpdf->FontStyle);
		$this->assertArrayHasKey(937, $this->mpdf->fonts['dejavusans']['subset']);
	}

	/**
	 * An index paints the pending contents ahead of time to number its entries. The @page the contents selects
	 * has to go when that is unwound, or the index is laid out in the contents' margins
	 */
	public function testAnIndexIsNotLaidOutInThePageSelectedByTheContents()
	{
		$this->mpdf->WriteHTML('<style>@page tocpage { margin-left: 70mm; margin-top: 90mm; }</style>'
			. '<tocpagebreak toc-page-selector="tocpage" /><tocentry content="One" /><indexentry content="One" />'
			. '<p>Body</p><pagebreak /><indexinsert />');

		$this->assertNull($this->mpdf->page_box['current']);
		$this->assertEquals(15, $this->mpdf->lMargin);
		$this->assertEquals(16, $this->mpdf->tMargin);
	}

	/**
	 * The cases an SVG drawn with a gradient and opacity goes through a pass that is unwound, as the shape of the
	 * document: drawn in the contents only, in the contents and after an index, and in a kept block that moves
	 *
	 * @return array[]
	 */
	public static function svgThroughAnUnwoundPass()
	{
		return [
			'in the contents' => ['<tocpagebreak toc-prehtml="%1$s" /><tocentry content="One" /><p>Body</p>'],
			'in the contents, then after an index' => ['<tocpagebreak toc-prehtml="%1$s" /><tocentry content="One" /><indexentry content="Term" /><p>Body</p><pagebreak /><indexinsert />%2$s'],
			'in a kept block that moves' => [str_repeat('<p>Filler</p>', 27) . '<div style="page-break-inside: avoid;">%2$s<p>Kept</p></div>'],
		];
	}

	/**
	 * A form object holds the numbers of the gradient and the graphics state it draws with. Both have to be there
	 * at the end, once each, or the page paints a shading the document does not have
	 *
	 * @dataProvider svgThroughAnUnwoundPass
	 *
	 * @param string $html
	 */
	public function testAnSvgKeepsTheGradientAndOpacityItDrawsWith($html)
	{
		$img = '<img src="data:image/svg+xml;base64,' . base64_encode($this->gradientSvg()) . '" />';
		$mpdf = $this->mpdf();
		$mpdf->WriteHTML(sprintf($html, htmlspecialchars($img, ENT_QUOTES), $img));
		$pdf = $this->output($mpdf);

		$this->assertCount(1, $mpdf->formobjects);
		$this->assertCount(2, $mpdf->gradients, 'the gradient and its opacity mask');
		$this->assertSame(1, substr_count($pdf, '/Sh1 sh'));
		$this->assertSame(2, substr_count($pdf, '/ShadingType'));
		$this->assertStringContainsString('/ca 0.5', $pdf);
	}

	/**
	 * A form object is parsed again after a pass that was unwound, from the bytes the first pass read: a file is read,
	 * and a remote image requested, once
	 *
	 * @dataProvider svgThroughAnUnwoundPass
	 *
	 * @param string $html
	 */
	public function testAnSvgIsReadOnceThoughAPassIsUnwound($html)
	{
		$file = tempnam(sys_get_temp_dir(), 'mpdf');
		$loader = new CountingContentLoader($this->gradientSvg());
		$img = '<img src="' . $file . '" />';
		$mpdf = $this->mpdf([], new SimpleContainer(['localContentLoader' => $loader]));
		$mpdf->WriteHTML(sprintf($html, htmlspecialchars($img, ENT_QUOTES), $img));
		$pdf = $this->output($mpdf);
		unlink($file);

		$this->assertSame(1, $loader->loads);
		$this->assertSame(2, substr_count($pdf, '/ShadingType'));
	}

	/**
	 * An SVG filled with a half-transparent gradient
	 *
	 * @return string
	 */
	private function gradientSvg()
	{
		return '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100"><defs><linearGradient id="g"><stop offset="0" stop-color="#f0f" stop-opacity="0.5"/><stop offset="1" stop-color="#0ff"/></linearGradient></defs><rect width="100" height="100" fill="url(#g)" fill-opacity="0.5"/></svg>';
	}
}
