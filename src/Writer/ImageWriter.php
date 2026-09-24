<?php

namespace Mpdf\Writer;

use Mpdf\Strict;
use Mpdf\Mpdf;

final class ImageWriter
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

	/**
	 * Writes each image the document holds, with the palette or colour profile it names, and names the
	 * soft mask of one that has one at the object that mask was written at
	 */
	public function writeImages()
	{
		$filter = $this->mpdf->compress ? '/Filter /FlateDecode ' : '';

		// Soft masks, which ISO 32000 has be DeviceGray even where other grey images may not be, by number
		$masks = [];
		foreach ($this->mpdf->images as $info) {
			if (isset($info['masked'])) {
				$masks[$info['masked']] = true;
			}
		}

		// The object each image was written at, by its number, which is what a soft mask is named by
		$written = [];

		foreach ($this->mpdf->images as $file => $info) {

			$calibrated = empty($info['icc']) && ($info['cs'] === 'DeviceRGB' || $info['cs'] === 'Indexed') ? $this->writer->calibratedRgb() : null;
			$rgb = $calibrated ? $calibrated . ' 0 R' : '/DeviceRGB';
			$gray = $info['cs'] === 'DeviceGray' && empty($info['icc']) && !isset($masks[$info['i']]) ? $this->writer->grayColorSpace() : '/DeviceGray';

			$this->writer->object();

			$this->mpdf->images[$file]['n'] = $this->mpdf->n;
			$written[$info['i']] = $this->mpdf->n;

			$this->writer->write('<</Type /XObject');
			$this->writer->write('/Subtype /Image');
			$this->writer->write('/Width ' . $info['w']);
			$this->writer->write('/Height ' . $info['h']);

			// PDF/X does not permit interpolation
			if (isset($info['interpolation']) && $info['interpolation'] && !$this->mpdf->PDFX) {
				$this->writer->write('/Interpolate true'); // mPDF 6 - image interpolation shall be performed by a conforming reader
			}

			if (isset($info['masked'])) {
				$this->writer->write('/SMask ' . $this->maskObject($file, $info['masked'], $written) . ' 0 R');
			}

			// set color space
			$icc = false;
			if (isset($info['icc']) && ( $info['icc'] !== false)) {
				// ICC Colour Space
				$icc = true;
				$this->writer->write('/ColorSpace [/ICCBased ' . ($this->mpdf->n + 1) . ' 0 R]');
			} elseif ($info['cs'] === 'Indexed') {
				if ($this->mpdf->isPdfx1a() || ($this->mpdf->PDFA && $this->mpdf->restrictColorSpace === 3)) {
					throw new \Mpdf\MpdfException('PDFA1-b and PDFX/1-a files do not permit using mixed colour space (' . $file . ').');
				}
				$this->writer->write('/ColorSpace [/Indexed ' . $rgb . ' ' . (strlen($info['pal']) / 3 - 1) . ' ' . ($this->mpdf->n + 1) . ' 0 R]');
			} else {
				if ($info['cs'] === 'DeviceRGB') {
					$this->writer->write('/ColorSpace ' . $rgb);
				} elseif ($info['cs'] === 'DeviceGray') {
					$this->writer->write('/ColorSpace ' . $gray);
				} else {
					$this->writer->write('/ColorSpace /' . $info['cs']);
				}
				if ($info['cs'] === 'DeviceCMYK') {
					if ($this->mpdf->PDFA && $this->mpdf->restrictColorSpace !== 3) {
						throw new \Mpdf\MpdfException('PDFA1-b does not permit Images using mixed colour space (' . $file . ').');
					}
					if ($info['type'] === 'jpg') {
						$this->writer->write('/Decode [1 0 1 0 1 0 1 0]');
					}
				} elseif (($this->mpdf->isPdfx1a() || ($this->mpdf->PDFA && $this->mpdf->restrictColorSpace === 3)) && $info['cs'] === 'DeviceRGB') {
					throw new \Mpdf\MpdfException('PDFA1-b and PDFX/1-a files do not permit using mixed colour space (' . $file . ').');
				}
			}

			$this->writer->write('/BitsPerComponent ' . $info['bpc']);

			if (isset($info['f']) && $info['f']) {
				$this->writer->write('/Filter /' . $info['f']);
			}

			if (isset($info['parms'])) {
				$this->writer->write($info['parms']);
			}

			if (isset($info['trns']) && is_array($info['trns'])) {
				$trns = '';
				$maskCount = count($info['trns']);
				for ($i = 0; $i < $maskCount; $i++) {
					$trns .= $info['trns'][$i] . ' ' . $info['trns'][$i] . ' ';
				}
				$this->writer->write('/Mask [' . $trns . ']');
			}

			$this->writer->write('/Length ' . $this->writer->streamLength($info['data']) . '>>');
			$this->writer->stream($info['data']);

			unset($this->mpdf->images[$file]['data']);

			$this->writer->write('endobj');

			if ($icc) { // ICC colour profile
				$this->writer->object();
				$icc = $this->mpdf->compress ? gzcompress($info['icc']) : $info['icc'];
				$this->writer->write('<</N ' . $info['ch'] . ' ' . $filter . '/Length ' . $this->writer->streamLength($icc) . '>>');
				$this->writer->stream($icc);
				$this->writer->write('endobj');
			} elseif ($info['cs'] === 'Indexed') { // Palette
				$this->writer->object();
				$pal = $this->mpdf->compress ? gzcompress($info['pal']) : $info['pal'];
				$this->writer->write('<<' . $filter . '/Length ' . $this->writer->streamLength($pal) . '>>');
				$this->writer->stream($pal);
				$this->writer->write('endobj');
			}
		}
	}

	/**
	 * The object an image's soft mask was written at
	 *
	 * ImageProcessor::register() puts a mask in ahead of the image it masks, so it has been written by
	 * the time the image names it. Taking the object from the mask itself, rather than counting back
	 * from the image's, is what keeps a palette or a colour profile written in between from being
	 * named as the mask - which renders wrong and says nothing.
	 *
	 * @throws \Mpdf\MpdfException Where the mask is not among the images written before this one
	 *
	 * @param string $file    The image's key in Mpdf::$images, for the message where its mask is missing
	 * @param int    $mask    The mask's number, as the image's 'masked' holds it
	 * @param int[]  $written The object each image written so far was written at, by its number
	 *
	 * @return int
	 */
	private function maskObject($file, $mask, array $written)
	{
		if (!isset($written[$mask])) {
			throw new \Mpdf\MpdfException(sprintf('The soft mask of image "%s" was not written before it', $file));
		}

		return $written[$mask];
	}

}
