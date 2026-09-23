<?php

namespace Mpdf\Writer;

use Mpdf\Strict;
use Mpdf\Mpdf;

final class PageWriter
{

	use Strict;

	/**
	 * @var \Mpdf\Mpdf
	 */
	private $mpdf;

	/**
	 * @var \Mpdf\Writer\BaseWriter
	 */
	private $writer;

	/**
	 * @var \Mpdf\Writer\MetadataWriter
	 */
	private $metadataWriter;

	public function __construct(Mpdf $mpdf, BaseWriter $writer, MetadataWriter $metadataWriter)
	{
		$this->mpdf = $mpdf;
		$this->writer = $writer;
		$this->metadataWriter = $metadataWriter;
	}

	public function writePages() // _putpages
	{
		$nb = $this->mpdf->page;
		$filter = $this->mpdf->compress ? '/Filter /FlateDecode ' : '';

		if ($this->mpdf->DefOrientation === 'P') {
			$defwPt = $this->mpdf->fwPt;
			$defhPt = $this->mpdf->fhPt;
		} else {
			$defwPt = $this->mpdf->fhPt;
			$defhPt = $this->mpdf->fwPt;
		}

		$annots = $this->metadataWriter->numberAnnotations(3 + 2 * $nb);

		// Select unused fonts (usually default font)
		$unused = [];
		foreach ($this->mpdf->fonts as $fk => $font) {
			if (isset($font['type']) && $font['type'] === 'TTF' && !$font['used']) {
				$unused[] = $fk;
			}
		}

		for ($n = 1; $n <= $nb; $n++) {

			$thispage = $this->mpdf->pages[$n];

			if (isset($this->mpdf->OrientationChanges[$n])) {
				$hPt = $this->mpdf->pageDim[$n]['w'] * Mpdf::SCALE;
				$wPt = $this->mpdf->pageDim[$n]['h'] * Mpdf::SCALE;
				$owidthPt_LR = $this->mpdf->pageDim[$n]['outer_width_TB'] * Mpdf::SCALE;
				$owidthPt_TB = $this->mpdf->pageDim[$n]['outer_width_LR'] * Mpdf::SCALE;
			} else {
				$wPt = $this->mpdf->pageDim[$n]['w'] * Mpdf::SCALE;
				$hPt = $this->mpdf->pageDim[$n]['h'] * Mpdf::SCALE;
				$owidthPt_LR = $this->mpdf->pageDim[$n]['outer_width_LR'] * Mpdf::SCALE;
				$owidthPt_TB = $this->mpdf->pageDim[$n]['outer_width_TB'] * Mpdf::SCALE;
			}

			// Remove references to unused fonts (usually default font)
			foreach ($unused as $fk) {
				if ($this->mpdf->fonts[$fk]['sip'] || $this->mpdf->fonts[$fk]['smp']) {
					foreach ($this->mpdf->fonts[$fk]['subsetfontids'] as $k => $fid) {
						$thispage = preg_replace('/\s\/F' . $fid . ' \d[\d.]* Tf\s/is', ' ', $thispage);
					}
				} else {
					$thispage = preg_replace('/\s\/F' . $this->mpdf->fonts[$fk]['i'] . ' \d[\d.]* Tf\s/is', ' ', $thispage);
				}
			}

			// Remove the content that visibility leaves out of a PDF/A or PDF/X document, then any marker left unpaired
			if (strpos($thispage, '___DROPPED___') !== false) {
				$thispage = preg_replace('/___DROPPED___START' . $this->mpdf->uniqstr . '.*?___DROPPED___END' . $this->mpdf->uniqstr . '/s', '', $thispage);
				$thispage = str_replace(['___DROPPED___START' . $this->mpdf->uniqstr, '___DROPPED___END' . $this->mpdf->uniqstr], '', $thispage);
			}

			// Clean up repeated /GS1 gs statements
			// For some reason using + for repetition instead of {2,20} crashes PHP Script Interpreter ???
			$thispage = preg_replace('/(\/GS1 gs\n){2,20}/', "/GS1 gs\n", $thispage);

			$thispage = preg_replace('/(\s*___BACKGROUND___PATTERNS' . $this->mpdf->uniqstr . '\s*)/', ' ', $thispage);
			$thispage = preg_replace('/(\s*___HEADER___MARKER' . $this->mpdf->uniqstr . '\s*)/', ' ', $thispage);
			$thispage = preg_replace('/(\s*___PAGE___START' . $this->mpdf->uniqstr . '\s*)/', ' ', $thispage);
			$thispage = preg_replace('/(\s*___TABLE___BACKGROUNDS' . $this->mpdf->uniqstr . '\s*)/', ' ', $thispage);

			// mPDF 5.7.3 TRANSFORMS
			while (preg_match('/(\% BTR(.*?)\% ETR)/is', $thispage, $m)) {
				$thispage = preg_replace('/(\% BTR.*?\% ETR)/is', '', $thispage, 1) . "\n" . $m[2];
			}

			// Page
			$this->writer->object();
			$this->writer->write('<</Type /Page');
			$this->writer->write('/Parent 1 0 R');

			if (isset($this->mpdf->OrientationChanges[$n])) {

				$this->writer->write(sprintf('/MediaBox [0 0 %.3F %.3F]', $hPt, $wPt));

				// If BleedBox is defined, it must be larger than the TrimBox, but smaller than the MediaBox
				$bleedMargin = $this->mpdf->pageDim[$n]['bleedMargin'] * Mpdf::SCALE;

				if ($bleedMargin && ($owidthPt_TB || $owidthPt_LR)) {
					$x0 = $owidthPt_TB - $bleedMargin;
					$y0 = $owidthPt_LR - $bleedMargin;
					$x1 = $hPt - $owidthPt_TB + $bleedMargin;
					$y1 = $wPt - $owidthPt_LR + $bleedMargin;
					$this->writer->write(sprintf('/BleedBox [%.3F %.3F %.3F %.3F]', $x0, $y0, $x1, $y1));
				}

				$this->writer->write(sprintf('/TrimBox [%.3F %.3F %.3F %.3F]', $owidthPt_TB, $owidthPt_LR, $hPt - $owidthPt_TB, $wPt - $owidthPt_LR));

				if ($this->mpdf->displayDefaultOrientation) {
					if ($this->mpdf->DefOrientation === 'P') {
						$this->writer->write('/Rotate 270');
					} else {
						$this->writer->write('/Rotate 90');
					}
				}

			} else { // elseif($wPt != $defwPt || $hPt != $defhPt) {

				$this->writer->write(sprintf('/MediaBox [0 0 %.3F %.3F]', $wPt, $hPt));
				$bleedMargin = $this->mpdf->pageDim[$n]['bleedMargin'] * Mpdf::SCALE;

				if ($bleedMargin && ($owidthPt_TB || $owidthPt_LR)) {
					$x0 = $owidthPt_LR - $bleedMargin;
					$y0 = $owidthPt_TB - $bleedMargin;
					$x1 = $wPt - $owidthPt_LR + $bleedMargin;
					$y1 = $hPt - $owidthPt_TB + $bleedMargin;
					$this->writer->write(sprintf('/BleedBox [%.3F %.3F %.3F %.3F]', $x0, $y0, $x1, $y1));
				}

				$this->writer->write(sprintf('/TrimBox [%.3F %.3F %.3F %.3F]', $owidthPt_LR, $owidthPt_TB, $wPt - $owidthPt_LR, $hPt - $owidthPt_TB));
			}
			$this->writer->write('/Resources 2 0 R');

			// Important to keep in RGB colorSpace when using transparency
			if ($this->mpdf->transparencyAllowed()) {
				if ($this->mpdf->restrictColorSpace === 3) {
					$this->writer->write('/Group << /Type /Group /S /Transparency /CS /DeviceCMYK >> ');
				} elseif ($this->mpdf->restrictColorSpace === 1) {
					$this->writer->write('/Group << /Type /Group /S /Transparency /CS /DeviceGray >> ');
				} else {
					$this->writer->write('/Group << /Type /Group /S /Transparency /CS /DeviceRGB >> ');
				}
			}

			if ($annots[$n]) {
				$s = '/Annots [ ';
				foreach ($annots[$n] as $id) {
					$s .= $id . ' 0 R ';
				}
				$s .= '] ';
				$this->writer->write($s);
			}

			$this->writer->write('/Contents ' . ($this->mpdf->n + 1) . ' 0 R>>');
			$this->writer->write('endobj');

			// Page content
			$this->writer->object();
			$p = $this->mpdf->compress ? gzcompress($thispage) : $thispage;
			$this->writer->write('<<' . $filter . '/Length ' . $this->writer->streamLength($p) . '>>');
			$this->writer->stream($p);
			$this->writer->write('endobj');
		}

		$this->metadataWriter->writeAnnotations(); // mPDF 5.7.2

		// Pages root
		$this->mpdf->offsets[1] = $this->mpdf->buffer->getLength();
		$this->writer->write('1 0 obj');
		$this->writer->write('<</Type /Pages');

		$kids = '/Kids [';

		for ($i = 0; $i < $nb; $i++) {
			$kids .= (3 + 2 * $i) . ' 0 R ';
		}

		$this->writer->write($kids . ']');
		$this->writer->write('/Count ' . $nb);
		$this->writer->write(sprintf('/MediaBox [0 0 %.3F %.3F]', $defwPt, $defhPt));
		$this->writer->write('>>');
		$this->writer->write('endobj');
	}

}
