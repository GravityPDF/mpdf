<?php

namespace Mpdf\Writer;

use Mpdf\Strict;
use Mpdf\Mpdf;
use Mpdf\Form;

final class PageWriter
{

	use Strict;

	/**
	 * @var \Mpdf\Mpdf
	 */
	private $mpdf;

	/**
	 * @var \Mpdf\Form
	 */
	private $form;

	/**
	 * @var \Mpdf\Writer\BaseWriter
	 */
	private $writer;

	/**
	 * @var \Mpdf\Writer\MetadataWriter
	 */
	private $metadataWriter;

	public function __construct(Mpdf $mpdf, Form $form, BaseWriter $writer, MetadataWriter $metadataWriter)
	{
		$this->mpdf = $mpdf;
		$this->form = $form;
		$this->writer = $writer;
		$this->metadataWriter = $metadataWriter;
	}

	public function writePages() // _putpages
	{
		if ($this->mpdf->PDFX) {
			$this->removePdfxAnnotations();
		}

		$nb = $this->mpdf->page;
		$filter = $this->mpdf->compress ? '/Filter /FlateDecode ' : '';

		if ($this->mpdf->DefOrientation === 'P') {
			$defwPt = $this->mpdf->fwPt;
			$defhPt = $this->mpdf->fhPt;
		} else {
			$defwPt = $this->mpdf->fhPt;
			$defhPt = $this->mpdf->fwPt;
		}

		$annotid = (3 + 2 * $nb);

		// Active Forms
		$totaladdnum = 0;
		for ($n = 1; $n <= $nb; $n++) {
			if (isset($this->mpdf->PageLinks[$n])) {
				$totaladdnum += count($this->mpdf->PageLinks[$n]);
			}

			/* -- ANNOTATIONS -- */
			if (isset($this->mpdf->PageAnnots[$n])) {
				foreach ($this->mpdf->PageAnnots[$n] as $k => $pl) {
					if (!empty($pl['opt']['popup']) || !empty($pl['opt']['file'])) {
						$totaladdnum += 2;
					} else {
						$totaladdnum++;
					}
				}
			}
			/* -- END ANNOTATIONS -- */

			/* -- FORMS -- */
			if (count($this->form->forms) > 0) {
				$this->form->countPageForms($n, $totaladdnum);
			}
			/* -- END FORMS -- */
		}

		/* -- FORMS -- */
		// Make a note in the radio button group of the obj_id it will have
		$ctr = 0;
		if (count($this->form->form_radio_groups)) {
			foreach ($this->form->form_radio_groups as $name => $frg) {
				$this->form->form_radio_groups[$name]['obj_id'] = $annotid + $totaladdnum + $ctr;
				$ctr++;
			}
		}
		/* -- END FORMS -- */

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
			if ($this->mpdf->isPdfx4()) {
				// Blended in the colour space of the output intent
				$spaces = [1 => '/DeviceGray', 3 => '/DeviceRGB', 4 => '/DeviceCMYK'];
				$this->writer->write('/Group << /Type /Group /S /Transparency /CS ' . $spaces[$this->mpdf->pdfxOutputChannels()] . ' >> ');
			} elseif (!$this->mpdf->PDFA && !$this->mpdf->PDFX) {
				if ($this->mpdf->restrictColorSpace === 3) {
					$this->writer->write('/Group << /Type /Group /S /Transparency /CS /DeviceCMYK >> ');
				} elseif ($this->mpdf->restrictColorSpace === 1) {
					$this->writer->write('/Group << /Type /Group /S /Transparency /CS /DeviceGray >> ');
				} else {
					$this->writer->write('/Group << /Type /Group /S /Transparency /CS /DeviceRGB >> ');
				}
			}

			$annotsnum = 0;
			$embeddedfiles = []; // mPDF 5.7.2 /EmbeddedFiles

			if (isset($this->mpdf->PageLinks[$n])) {
				$annotsnum += count($this->mpdf->PageLinks[$n]);
			}

			if (isset($this->mpdf->PageAnnots[$n])) {
				foreach ($this->mpdf->PageAnnots[$n] as $k => $pl) {
					if (!empty($pl['opt']['file'])) {
						$embeddedfiles[$annotsnum + 1] = true;
					} // mPDF 5.7.2 /EmbeddedFiles
					if (!empty($pl['opt']['popup']) || !empty($pl['opt']['file'])) {
						$annotsnum += 2;
					} else {
						$annotsnum++;
					}
					$this->mpdf->PageAnnots[$n][$k]['pageobj'] = $this->mpdf->n;
				}
			}

			// Active Forms
			$formsnum = 0;
			if (count($this->form->forms) > 0) {
				foreach ($this->form->forms as $val) {
					if ($val['page'] == $n) {
						$formsnum++;
					}
				}
			}

			if ($annotsnum || $formsnum) {

				$s = '/Annots [ ';

				for ($i = 0; $i < $annotsnum; $i++) {
					if (!isset($embeddedfiles[$i])) {
						$s .= ($annotid + $i) . ' 0 R ';
					} // mPDF 5.7.2 /EmbeddedFiles
				}

				$annotid += $annotsnum;

				/* -- FORMS -- */
				if (count($this->form->forms) > 0) {
					$this->form->addFormIds($n, $s, $annotid);
				}
				/* -- END FORMS -- */

				$s .= '] ';
				$this->writer->write($s);
			}

			$this->writer->write('/Contents ' . ($this->mpdf->n + 1) . ' 0 R>>');
			$this->writer->write('endobj');

			// Page content
			$this->writer->object();
			$p = $this->mpdf->compress ? gzcompress($thispage) : $thispage;
			$this->writer->write('<<' . $filter . '/Length ' . strlen($p) . '>>');
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

	/**
	 * PDF/X permits no interactive form field, no file attachment, and no other annotation within the
	 * area printed: the BleedBox, or the TrimBox where there is no bleed. Each is removed before the
	 * annotations are counted, which numbers their objects.
	 */
	private function removePdfxAnnotations()
	{
		$removed = count($this->form->forms) > 0 || count($this->form->form_radio_groups) > 0;
		$this->form->forms = [];
		$this->form->form_radio_groups = [];

		for ($n = 1; $n <= $this->mpdf->page; $n++) {
			$area = $this->printedArea($n);

			if (isset($this->mpdf->PageLinks[$n])) {
				foreach ($this->mpdf->PageLinks[$n] as $key => $pl) {
					if (self::overlaps([$pl[0], $pl[1] - $pl[3], $pl[0] + $pl[2], $pl[1]], $area)) {
						unset($this->mpdf->PageLinks[$n][$key]);
						$removed = true;
					}
				}
			}

			if (isset($this->mpdf->PageAnnots[$n])) {
				$widthPt = $this->mpdf->pageDim[$n]['w'] * Mpdf::SCALE;
				$heightPt = $this->mpdf->pageDim[$n]['h'] * Mpdf::SCALE;
				foreach ($this->mpdf->PageAnnots[$n] as $key => $pl) {
					// The marker, placed as MetadataWriter::writeAnnotations() places it
					$x = $pl['x'];
					if ($this->mpdf->annotMargin != 0 || $x <= 0) {
						$x = $widthPt / Mpdf::SCALE - $this->mpdf->annotMargin;
					}
					$left = $x * Mpdf::SCALE;
					$top = $heightPt - $pl['y'] * Mpdf::SCALE;

					if (!empty($pl['opt']['file']) || self::overlaps([$left, $top - 20, $left + 20, $top], $area)) {
						unset($this->mpdf->PageAnnots[$n][$key]);
						$removed = true;
					}
				}
			}
		}

		if ($removed && !$this->mpdf->PDFXauto) {
			$this->mpdf->PDFAXwarnings[] = 'Form fields, file attachments and annotations within the TrimBox or BleedBox are not permitted in ' . $this->mpdf->pdfxVersionLabel() . ' files. (Removed)';
		}
	}

	/**
	 * @param int $n A page
	 *
	 * @return float[] [xMin, yMin, xMax, yMax] of its BleedBox, or its TrimBox where it has no bleed, in points
	 */
	private function printedArea($n)
	{
		$dim = $this->mpdf->pageDim[$n];
		$x = $dim['outer_width_LR'] * Mpdf::SCALE;
		$y = $dim['outer_width_TB'] * Mpdf::SCALE;
		$bleed = $x || $y ? $dim['bleedMargin'] * Mpdf::SCALE : 0;

		return [$x - $bleed, $y - $bleed, $dim['w'] * Mpdf::SCALE - $x + $bleed, $dim['h'] * Mpdf::SCALE - $y + $bleed];
	}

	/**
	 * @param float[] $a [xMin, yMin, xMax, yMax]
	 * @param float[] $b [xMin, yMin, xMax, yMax]
	 *
	 * @return bool Whether the two rectangles overlap
	 */
	private static function overlaps(array $a, array $b)
	{
		return $a[0] < $b[2] && $a[2] > $b[0] && $a[1] < $b[3] && $a[3] > $b[1];
	}

}
