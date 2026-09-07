<?php

namespace Mpdf\Writer;

use Mpdf\Strict;
use Mpdf\Mpdf;

final class FormWriter
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

	public function __construct(Mpdf $mpdf, BaseWriter $writer)
	{
		$this->mpdf = $mpdf;
		$this->writer = $writer;
	}

	public function writeFormObjects() // _putformobjects
	{
		if (!$this->mpdf->formobjects) {
			return;
		}

		$resources = $this->getResources();

		foreach ($this->mpdf->formobjects as $file => $info) {

			$this->writer->object();

			$this->mpdf->formobjects[$file]['n'] = $this->mpdf->n;

			$this->writer->write('<</Type /XObject');
			$this->writer->write('/Subtype /Form');
			$this->writer->write('/Group ' . ($this->mpdf->n + 1) . ' 0 R');
			$this->writer->write('/BBox [' . $info['x'] . ' ' . $info['y'] . ' ' . ($info['w'] + $info['x']) . ' ' . ($info['h'] + $info['y']) . ']');
			$this->writer->write('/Resources <<' . $resources . '>>');

			if ($this->mpdf->compress) {
				$this->writer->write('/Filter /FlateDecode');
			}

			$data = $this->mpdf->compress ? gzcompress($info['data']) : $info['data'];
			$this->writer->write('/Length ' . strlen($data) . '>>');
			$this->writer->stream($data);

			unset($this->mpdf->formobjects[$file]['data']);

			$this->writer->write('endobj');

			// Required for SVG transparency (opacity) to work
			$this->writer->object();
			$this->writer->write('<</Type /Group');
			$this->writer->write('/S /Transparency');
			$this->writer->write('>>');
			$this->writer->write('endobj');
		}
	}

	/**
	 * A Form XObject with no /Resources of its own inherits the page's, which PDF 1.4 allowed
	 * but PDF/A does not. The entries an SVG can reference are already flagged as it is drawn.
	 *
	 * As in _putpatterns, this hands every Form XObject the resources of every Form XObject.
	 *
	 * @return string
	 */
	private function getResources()
	{
		$resources = ['/ProcSet [/PDF /Text /ImageB /ImageC /ImageI]'];

		$extgstates = [];
		foreach ($this->mpdf->extgstates as $k => $extgstate) {
			if (!isset($extgstate['fo']) || !$extgstate['fo']) {
				continue;
			}

			if (isset($extgstate['trans'])) {
				$extgstates[] = '/' . $extgstate['trans'] . ' ' . $extgstate['n'] . ' 0 R';
			} else {
				$extgstates[] = '/GS' . $k . ' ' . $extgstate['n'] . ' 0 R';
			}
		}

		if (count($extgstates)) {
			$resources[] = '/ExtGState <<' . implode(' ', $extgstates) . '>>';
		}

		/* -- BACKGROUNDS -- */
		$shadings = [];
		if (isset($this->mpdf->gradients) && count($this->mpdf->gradients) > 0) {
			foreach ($this->mpdf->gradients as $id => $grad) {
				// 'id' is the shading's object number, filled in by _putshaders; a gradient it
				// does not write (a soft mask, say) is not nameable here
				if (!isset($grad['fo']) || !$grad['fo'] || !isset($grad['id'])) {
					continue;
				}

				$shadings[] = '/Sh' . $id . ' ' . $grad['id'] . ' 0 R';
			}
		}

		if (count($shadings)) {
			$resources[] = '/Shading <<' . implode(' ', $shadings) . '>>';
		}
		/* -- END BACKGROUNDS -- */

		$fonts = [];
		foreach ($this->mpdf->fonts as $font) {
			if (!isset($font['fo']) || !$font['fo']) {
				continue;
			}

			if (isset($font['type']) && $font['type'] === 'TTF') {
				if (!$font['used']) {
					continue;
				}

				if ($font['sip'] || $font['smp']) {
					foreach ($font['n'] as $k => $fid) {
						$fonts[] = '/F' . $font['subsetfontids'][$k] . ' ' . $fid . ' 0 R';
					}

					continue;
				}
			}

			$fonts[] = '/F' . $font['i'] . ' ' . $font['n'] . ' 0 R';
		}

		if (count($fonts)) {
			$resources[] = '/Font <<' . implode(' ', $fonts) . '>>';
		}

		return implode(' ', $resources);
	}
}
