<?php

namespace Mpdf\Ua;

/**
 * A ligature the ToUnicode CMap cannot map back to its characters is wrapped in a Span with
 * the characters as /ActualText (Matterhorn 24-001).
 *
 * @group pdfua
 */
class LigatureActualTextTest extends PdfUaTestCase
{

	/**
	 * DejaVuSerif with OTL on, which its default registration leaves off.
	 *
	 * @return array fontdata config
	 */
	private function dejavuSerifWithOtl()
	{
		return [
			'dejavuserif' => [
				'R'      => 'DejaVuSerif.ttf',
				'B'      => 'DejaVuSerif-Bold.ttf',
				'I'      => 'DejaVuSerif-Italic.ttf',
				'BI'     => 'DejaVuSerif-BoldItalic.ttf',
				'useOTL' => 0xFF,
			],
		];
	}

	/**
	 * The fi ligature in "find" carries f and i as its /ActualText.
	 */
	public function testFiLigatureProducesActualText()
	{
		$mpdf = $this->makeMpdf(['fontdata' => $this->dejavuSerifWithOtl()]);
		$pdf  = $this->getOutput($mpdf, '<p style="font-family: dejavuserif;">find</p>');

		$this->assertStringContainsString(
			'/Span <</ActualText <FEFF00660069>>>',
			$pdf,
			'fi ligature must be wrapped with /ActualText <FEFF00660069>'
		);
		$this->assertStringContainsString('BDC', $pdf);
		$this->assertStringContainsString('EMC', $pdf);
	}

	/**
	 * DejaVuSerif forms ff before fi, so "office" carries an ff /ActualText.
	 */
	public function testFfLigatureProducesActualText()
	{
		$mpdf = $this->makeMpdf(['fontdata' => $this->dejavuSerifWithOtl()]);
		$pdf  = $this->getOutput($mpdf, '<p style="font-family: dejavuserif;">office</p>');

		$this->assertStringContainsString(
			'/Span <</ActualText <FEFF00660066>>>',
			$pdf,
			'ff ligature in "office" must be wrapped with /ActualText <FEFF00660066>'
		);
	}

	/**
	 * A three-character ligature, ffl in "ruffled", carries all three characters.
	 */
	public function testFflLigatureProducesActualText()
	{
		$mpdf = $this->makeMpdf(['fontdata' => $this->dejavuSerifWithOtl()]);
		$pdf  = $this->getOutput($mpdf, '<p style="font-family: dejavuserif;">ruffled</p>');

		$this->assertStringContainsString(
			'/Span <</ActualText <FEFF00660066006C>>>',
			$pdf,
			'ffl ligature in "ruffled" must be wrapped with /ActualText <FEFF00660066006C>'
		);
	}

	/**
	 * Text without a ligature gets no wrapper.
	 */
	public function testNoLigatureNoWrapper()
	{
		$mpdf = $this->makeMpdf(['fontdata' => $this->dejavuSerifWithOtl()]);
		$pdf  = $this->getOutput($mpdf, '<p style="font-family: dejavuserif;">hello</p>');

		$this->assertStringNotContainsString(
			'/Span <</ActualText',
			$pdf,
			'Text with no ligatures must not produce an /ActualText wrapper'
		);
	}

	/**
	 * A ligature the font's ToUnicode CMap already maps to its characters gets no wrapper.
	 */
	public function testToUnicodeCoveredSkipsWrapper()
	{
		$mpdf = $this->makeMpdf(['fontdata' => $this->dejavuSerifWithOtl()]);

		$mpdf->SetFont('dejavuserif', '', 12);

		// U+FB01, the fi ligature, mapped to f and i
		$mpdf->CurrentFont['toUnicodeMultiChar'] = [
			64257 => [102, 105],
		];

		$pdf = $this->getOutput($mpdf, '<p style="font-family: dejavuserif;">find</p>');

		$this->assertStringNotContainsString(
			'/Span <</ActualText <FEFF00660069>>>',
			$pdf,
			'When ToUnicode already covers the fi ligature, the wrapper must be skipped'
		);
	}

	/**
	 * The /ActualText span is marked content without an MCID, nested inside the marked
	 * content of the paragraph it belongs to.
	 */
	public function testLigatureSpanNestedInsideParentStructRange()
	{
		$mpdf = $this->makeMpdf(['fontdata' => $this->dejavuSerifWithOtl()]);
		$pdf  = $this->getOutput($mpdf, '<p style="font-family: dejavuserif;">find</p>');

		$outerBdcFound = preg_match('/MCID\s+\d+\s*>>+\s*BDC/', $pdf, $m, PREG_OFFSET_CAPTURE);
		$this->assertSame(1, $outerBdcFound, 'Outer MCID-bearing BDC must be present in the PDF');
		$outerBdcPos = $m[0][1];

		$spanBdcFound = preg_match(
			'/\/Span\s*<<\/ActualText\s*<FEFF[0-9A-F]+>\s*>>\s*BDC/',
			$pdf,
			$m2,
			PREG_OFFSET_CAPTURE
		);
		$this->assertSame(1, $spanBdcFound, '/Span /ActualText BDC must be present');
		$spanBdcPos = $m2[0][1];
		$spanBdcEnd = $spanBdcPos + strlen($m2[0][0]);

		$spanEmcFound = preg_match('/EMC/', $pdf, $m3, PREG_OFFSET_CAPTURE, $spanBdcEnd);
		$this->assertSame(1, $spanEmcFound, 'EMC closing the /Span must appear after the /Span BDC');
		$spanEmcPos = $m3[0][1];
		$spanEmcEnd = $spanEmcPos + strlen($m3[0][0]);

		$outerEmcFound = preg_match('/EMC/', $pdf, $m4, PREG_OFFSET_CAPTURE, $spanEmcEnd);
		$this->assertSame(1, $outerEmcFound, 'Outer struct closing EMC must appear after the Span EMC');
		$outerEmcPos = $m4[0][1];

		$this->assertLessThan(
			$spanBdcPos,
			$outerBdcPos,
			'Outer struct BDC must precede /Span BDC'
		);
		$this->assertLessThan(
			$spanEmcPos,
			$spanBdcPos,
			'/Span BDC must precede its closing EMC'
		);
		$this->assertLessThan(
			$outerEmcPos,
			$spanEmcPos,
			'/Span EMC must precede the outer struct closing EMC'
		);

		$spanBdcSnippet = substr($pdf, $spanBdcPos, 80);
		$this->assertStringNotContainsString(
			'MCID',
			$spanBdcSnippet,
			'/Span /ActualText BDC must not contain MCID'
		);
	}

	/**
	 * "fine office difficulty" gets one fi and two ff wrappers: DejaVuSerif forms no ffi
	 * ligature, leaving the i of "difficulty" to the CMap.
	 *
	 * @group pdfua
	 */
	public function testDejaVuSerifLigatureSentenceProducesExpectedWrappers()
	{
		$mpdf = $this->makeMpdf(['fontdata' => $this->dejavuSerifWithOtl()]);
		$pdf  = $this->getOutput($mpdf, '<p style="font-family: dejavuserif;">fine office difficulty</p>');

		$wrappers = $this->actualTexts($pdf);

		$this->assertContains(
			'FEFF00660069',
			$wrappers,
			'fi ligature in "fine" must produce /ActualText <FEFF00660069>'
		);

		$ffCount = count(array_keys($wrappers, 'FEFF00660066'));
		$this->assertGreaterThanOrEqual(
			1,
			$ffCount,
			'ff ligature in "office" must produce /ActualText <FEFF00660066>'
		);

		$this->assertCount(
			3,
			$wrappers,
			'Exemplar sentence must produce exactly 3 ActualText wrappers (fi from "fine", ff from "office", ff from "difficulty")'
		);
	}

	/**
	 * Without PDFUA a ligature gets no wrapper and is still drawn with Tj.
	 *
	 * The ligature's source text is recorded only under PDFUA: recording it gives the ligature
	 * GPOSinfo, which routes the run through the slower TJ path.
	 */
	public function testNoWrapperWhenPdfuaDisabled()
	{
		$mpdf = new \Mpdf\Mpdf([
			'mode'     => 'en-GB',
			'fontdata' => $this->dejavuSerifWithOtl(),
		]);
		$mpdf->compress = false;

		$pdf = $this->getOutput($mpdf, '<p style="font-family: dejavuserif;">find</p>');

		$this->assertStringNotContainsString(
			'/Span <</ActualText',
			$pdf,
			'ActualText wrappers must not appear when PDFUA mode is disabled'
		);

		$this->assertSame(
			0,
			substr_count($pdf, ' TJ'),
			'a non-PDFUA ligature must not be pushed onto the TJ path by a phantom GPOSinfo'
		);
		$this->assertGreaterThanOrEqual(
			1,
			substr_count($pdf, ' Tj'),
			'the non-PDFUA ligature run must render via the Tj fast path'
		);
	}
}
