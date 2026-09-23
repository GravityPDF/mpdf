<?php

namespace Mpdf\Writer;

use Mpdf\Color\ColorModeConverter;
use Mpdf\Strict;
use Mpdf\Mpdf;

final class ColorWriter
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
	 * @var \Mpdf\Color\ColorModeConverter
	 */
	private $colorModeConverter;

	public function __construct(Mpdf $mpdf, BaseWriter $writer, ColorModeConverter $colorModeConverter)
	{
		$this->mpdf = $mpdf;
		$this->writer = $writer;
		$this->colorModeConverter = $colorModeConverter;
	}

	public function writeSpotColors() // _putspotcolors
	{
		// PDF/X-4 printing to grey or RGB permits no DeviceCMYK, even as the alternate a device without
		// the ink falls back to, so the alternate is RGB there
		$rgb = count($this->mpdf->spotColors) && $this->mpdf->pdfxConvertsCmyk();
		if ($rgb) {
			$calibratedRgb = $this->writer->calibratedRgb();
			$alternate = $calibratedRgb ? $calibratedRgb . ' 0 R' : '/DeviceRGB';
		}

		foreach ($this->mpdf->spotColors as $name => $color) {

			$this->writer->object();

			$this->writer->write('[/Separation /' . str_replace(' ', '#20', $name));
			if ($rgb) {
				$c = $this->colorModeConverter->cmyk2rgb([4, $color['c'], $color['m'], $color['y'], $color['k']]);
				$this->writer->write($alternate . ' <<');
				$this->writer->write('/Range [0 1 0 1 0 1] /C0 [1 1 1] ');
				$this->writer->write(sprintf('/C1 [%.3F %.3F %.3F] ', $c[1] / 255, $c[2] / 255, $c[3] / 255));
			} else {
				$this->writer->write('/DeviceCMYK <<');
				$this->writer->write('/Range [0 1 0 1 0 1 0 1] /C0 [0 0 0 0] ');
				$this->writer->write(sprintf('/C1 [%.3F %.3F %.3F %.3F] ', $color['c'] / 100, $color['m'] / 100, $color['y'] / 100, $color['k'] / 100));
			}
			$this->writer->write('/FunctionType 2 /Domain [0 1] /N 1>>]');
			$this->writer->write('endobj');

			$this->mpdf->spotColors[$name]['n'] = $this->mpdf->n;
		}
	}

}
