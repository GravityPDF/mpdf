<?php

namespace Mpdf;

/**
 * Mpdf::getStateSnapshot() and restoreStateSnapshot() carry the table of contents look-ahead and the measuring
 * pass of a page-break-inside:avoid block
 */
class StateSnapshotTest extends BaseMpdfTest
{

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
}
