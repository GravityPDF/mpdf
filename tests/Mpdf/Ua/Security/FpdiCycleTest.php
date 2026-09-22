<?php

namespace Mpdf\Ua\Security;

use Mpdf\Ua\PdfUaTestCase;

/**
 * An imported tagged PDF whose structure tree loops back on itself, or runs
 * deeper than the merger will follow, is imported with a warning instead of
 * recursing without end.
 *
 * @group pdfua
 * @group security
 */
class FpdiCycleTest extends PdfUaTestCase
{

	/** @var string[] */
	private $tempFiles = [];

	/**
	 * Removes the source PDFs the test wrote.
	 */
	protected function tear_down()
	{
		parent::tear_down();
		foreach ($this->tempFiles as $f) {
			if (file_exists($f)) {
				@unlink($f);
			}
		}
		$this->tempFiles = [];
	}

	/**
	 * Two structure elements naming each other in /K produce a document and a cycle warning.
	 */
	public function testCyclicStructKDoesNotCrash()
	{
		$path = $this->writeTempPdf($this->buildCyclicPdf());

		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$mpdf->SetSourceFile($path);
		$tplId = $mpdf->ImportPage(1);
		$mpdf->UseTemplate($tplId);
		$output = $mpdf->Output(null, 'S');

		$this->assertNotEmpty($output, 'Document must still produce PDF bytes after cycle is broken.');

		$cycleWarning = false;
		foreach ($mpdf->getPdfUaWarnings() as $w) {
			if (stripos($w, 'cycle') !== false) {
				$cycleWarning = true;
				break;
			}
		}
		$this->assertTrue($cycleWarning, 'Expected a "cycle detected" warning from FpdiStructMerger.');
	}

	/**
	 * A /K chain four times deeper than the merger's depth cap is imported quickly with a depth warning.
	 */
	public function testDeepStructKDoesNotCrash()
	{
		$path = $this->writeTempPdf($this->buildDeepPdf(4096));

		$mpdf = $this->makeMpdf(['PDFUAauto' => true]);
		$mpdf->SetSourceFile($path);
		$tplId = $mpdf->ImportPage(1);
		$mpdf->UseTemplate($tplId);
		$start = microtime(true);
		$output = $mpdf->Output(null, 'S');
		$elapsed = microtime(true) - $start;

		$this->assertNotEmpty($output);
		$this->assertLessThan(10.0, $elapsed, 'Depth-capped import should be fast.');

		$depthWarning = false;
		foreach ($mpdf->getPdfUaWarnings() as $w) {
			if (stripos($w, 'depth exceeded') !== false) {
				$depthWarning = true;
				break;
			}
		}
		$this->assertTrue($depthWarning, 'Expected a "depth exceeded" warning from FpdiStructMerger.');
	}

	/**
	 * @param string $bytes The PDF
	 *
	 * @return string The path of a temporary file holding it, removed in tear_down()
	 */
	private function writeTempPdf($bytes)
	{
		$path = tempnam(sys_get_temp_dir(), 'mpdf_cyclic_') . '.pdf';
		file_put_contents($path, $bytes);
		$this->tempFiles[] = $path;
		return $path;
	}

	/**
	 * A tagged PDF whose structure elements 6 and 7 each name the other in /K.
	 *
	 * @return string
	 */
	private function buildCyclicPdf()
	{
		$objects = [
			1 => '<< /Type /Catalog /Pages 2 0 R /StructTreeRoot 5 0 R /MarkInfo << /Marked true >> >>',
			2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
			3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 100 100] /Contents 4 0 R /StructParents 0 /Resources << >> >>',
			4 => "<< /Length 42 >>\nstream\n/P <</MCID 0>> BDC (hi) Tj EMC\nendstream",
			5 => '<< /Type /StructTreeRoot /K [6 0 R] /ParentTree 9 0 R /ParentTreeNextKey 1 >>',
			6 => '<< /Type /StructElem /S /Document /P 5 0 R /K [7 0 R] /Pg 3 0 R >>',
			7 => '<< /Type /StructElem /S /Sect /P 6 0 R /K [6 0 R] /Pg 3 0 R >>',
			9 => '<< /Nums [0 [6 0 R]] >>',
		];
		return $this->assemblePdf($objects);
	}

	/**
	 * A tagged PDF whose structure elements each hold the next in /K, the last holding MCID 0.
	 *
	 * @param int $depth How many elements the chain has
	 *
	 * @return string
	 */
	private function buildDeepPdf($depth)
	{
		$objects = [
			1 => '<< /Type /Catalog /Pages 2 0 R /StructTreeRoot 5 0 R /MarkInfo << /Marked true >> >>',
			2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
			3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 100 100] /Contents 4 0 R /StructParents 0 /Resources << >> >>',
			4 => "<< /Length 42 >>\nstream\n/P <</MCID 0>> BDC (hi) Tj EMC\nendstream",
			5 => '<< /Type /StructTreeRoot /K [6 0 R] /ParentTree ' . (6 + $depth) . ' 0 R /ParentTreeNextKey 1 >>',
		];
		for ($i = 0; $i < $depth; $i++) {
			$objNum = 6 + $i;
			$kEntry = ($i === $depth - 1) ? '[0]' : '[' . (6 + $i + 1) . ' 0 R]';
			$parent = ($i === 0) ? '5' : (string) (6 + $i - 1);
			$objects[$objNum] = '<< /Type /StructElem /S /Document /P ' . $parent . ' 0 R /K ' . $kEntry . ' /Pg 3 0 R >>';
		}
		$objects[6 + $depth] = '<< /Nums [0 [6 0 R]] >>';
		return $this->assemblePdf($objects);
	}

	/**
	 * @param string[] $objects Object bodies keyed by object number
	 *
	 * @return string The objects written out as a PDF with an xref table and trailer
	 */
	private function assemblePdf(array $objects)
	{
		$out     = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
		$offsets = [];
		foreach ($objects as $n => $body) {
			$offsets[$n] = strlen($out);
			$out .= "$n 0 obj\n$body\nendobj\n";
		}
		$xrefOff = strlen($out);
		$maxN    = max(array_keys($objects));
		$out    .= "xref\n0 " . ($maxN + 1) . "\n";
		$out    .= sprintf("%010d %05d f \n", 0, 65535);
		for ($i = 1; $i <= $maxN; $i++) {
			if (isset($offsets[$i])) {
				$out .= sprintf("%010d %05d n \n", $offsets[$i], 0);
			} else {
				$out .= sprintf("%010d %05d f \n", 0, 65535);
			}
		}
		$out .= "trailer\n<< /Size " . ($maxN + 1) . " /Root 1 0 R >>\nstartxref\n$xrefOff\n%%EOF\n";
		return $out;
	}
}
