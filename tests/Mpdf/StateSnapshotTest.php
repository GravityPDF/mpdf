<?php

namespace Mpdf;

use Mpdf\Container\SimpleContainer;

/**
 * Mpdf::getStateSnapshot() and restoreStateSnapshot() carry the table of contents look-ahead and the measuring
 * pass of a page-break-inside:avoid block
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
	 * A spot colour first met in the contents' look-ahead stays defined: the colour cache will not register it again
	 * when the pass that draws the contents for good finds the colour there
	 */
	public function testASpotColourInTheContentsIsDefined()
	{
		$pdf = $this->render('<style>.mpdf_toc_t_level_0 { color: spot(ZED,50,0,100,0,0); }</style><tocpagebreak /><tocentry content="One" /><p>Body</p>');

		$this->assertSame(1, substr_count($pdf, '/Separation /ZED'));
		$this->assertSame('1', $this->spotInkOf('One', $pdf));
	}

	/**
	 * A spot colour in the contents, painted ahead of time for an index, keeps its number from the colour the body goes
	 * on to use: each text prints in its own ink
	 */
	public function testTheContentsAndTheBodyAfterAnIndexKeepTheirOwnSpotColours()
	{
		$mpdf = $this->mpdf();
		$mpdf->WriteHTML('<style>.mpdf_toc_t_level_0 { color: spot(TOCINK,50,0,100,0,0); } .later { color: spot(BODYINK,50,100,0,0,0); }</style>'
			. '<tocpagebreak /><tocentry content="One" /><indexentry content="Term" /><p>Body</p><pagebreak /><indexinsert /><p class="later">Later</p>');
		$pdf = $this->output($mpdf);

		$this->assertSame(1, substr_count($pdf, '/Separation /TOCINK'));
		$this->assertSame(1, substr_count($pdf, '/Separation /BODYINK'));
		$this->assertEquals($mpdf->spotColors['TOCINK']['i'], $this->spotInkOf('One', $pdf));
		$this->assertEquals($mpdf->spotColors['BODYINK']['i'], $this->spotInkOf('Later', $pdf));
	}

	/**
	 * A spot colour first met in a kept block that moves stays defined when the block is laid out on its new page
	 */
	public function testASpotColourInAKeptBlockThatMovesIsDefined()
	{
		$mpdf = $this->mpdf();
		$mpdf->WriteHTML($this->filler(27) . $this->keptBlock(4, '', 'color: spot(ZED,50,0,100,0,0);'));
		$pdf = $this->output($mpdf);

		$this->assertCount(2, $mpdf->pages);
		$this->assertSame(1, substr_count($pdf, '/Separation /ZED'));
		$this->assertSame('1', $this->spotInkOf('Kept', $pdf));
	}

	/**
	 * A spot colour met before a kept block moves survives the block's unwinding, under the same number
	 */
	public function testASpotColourMetBeforeAKeptBlockMovesKeepsItsNumber()
	{
		$pdf = $this->render('<p style="color: spot(FIRST,50,0,100,0,0);">Before</p>' . $this->filler(27) . $this->keptBlock(4, '', 'color: spot(FIRST,50,0,100,0,0);'));

		$this->assertSame(1, substr_count($pdf, '/Separation /FIRST'));
		$this->assertSame('1', $this->spotInkOf('Before', $pdf));
		$this->assertSame('1', $this->spotInkOf('Kept', $pdf));
	}

	/**
	 * What a PDF/A or PDF/X document cannot use, drawn only in the contents or only in a kept block that moves, in the
	 * colour each standard refuses and the image every one does
	 *
	 * @return array[]
	 */
	public static function refusedThroughAnUnwoundPass()
	{
		$contents = '<style>.mpdf_toc_t_level_0 { color: %s; }</style><tocpagebreak /><tocentry content="One" /><p>Body</p>';
		$filler = str_repeat('<p>Filler</p>', 27);
		$lines = str_repeat('<p>Kept</p>', 4);
		$kept = $filler . '<div style="page-break-inside: avoid; color: %s;">' . $lines . '</div>';

		return [
			'PDF/A, colour in the contents' => ['PDFA', sprintf($contents, 'cmyk(0,50,100,0)'), 'CMYK color'],
			'PDF/A, colour in a kept block' => ['PDFA', sprintf($kept, 'cmyk(0,50,100,0)'), 'CMYK color'],
			'PDF/X, colour in the contents' => ['PDFX', sprintf($contents, 'rgb(255,0,0)'), 'RGB color'],
			'PDF/X, colour in a kept block' => ['PDFX', sprintf($kept, 'rgb(255,0,0)'), 'RGB color'],
			'PDF/A, image in the contents' => ['PDFA', '<tocpagebreak toc-prehtml="&lt;img src=&quot;%s&quot; /&gt;" /><tocentry content="One" /><p>Body</p>', 'Transparency'],
			'PDF/A, image in a kept block' => ['PDFA', $filler . '<div style="page-break-inside: avoid;"><img src="%s" />' . $lines . '</div>', 'Transparency'],
		];
	}

	/**
	 * A warning is raised only when a colour or image is first converted, and the pass that follows finds it cached.
	 * It has to survive the unwinding, and be reported once
	 *
	 * @dataProvider refusedThroughAnUnwoundPass
	 *
	 * @param string $standard PDFA or PDFX
	 * @param string $html
	 * @param string $warning What the one warning starts with
	 */
	public function testAWarningRaisedInAnUnwoundPassIsReportedOnce($standard, $html, $warning)
	{
		$mpdf = $this->mpdf(['mode' => '', 'default_font' => 'dejavusans', 'PDFAversion' => '1-B', 'PDFXversion' => '1a', $standard => true]);
		$mpdf->SetTitle('Title');
		$mpdf->WriteHTML(sprintf($html, $this->pngImage()));

		try {
			$this->output($mpdf);
			$this->fail('The document should have been refused');
		} catch (MpdfException $e) {
			$this->assertStringContainsString('PDFA/PDFX warnings generated', $e->getMessage());
		}

		$this->assertCount(1, preg_grep('/^' . $warning . '/', $mpdf->PDFAXwarnings), implode("\n", $mpdf->PDFAXwarnings));
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

	/**
	 * The number of the spot colour a piece of text is printed in
	 *
	 * @param string $text
	 * @param string $pdf
	 *
	 * @return string
	 */
	private function spotInkOf($text, $pdf)
	{
		$this->assertSame(1, preg_match('#/CS(\d+) cs [^\n]*\(' . $text . '\) Tj#', $pdf, $match), $text);

		return $match[1];
	}
}
