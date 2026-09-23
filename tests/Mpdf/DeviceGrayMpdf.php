<?php

namespace Mpdf;

/**
 * mPDF as it wrote grey before the ICC-based grey colour space: in DeviceGray, whatever the output intent.
 *
 * The baseline PdfX4Test compares the pixels of the ICC-based grey against.
 */
class DeviceGrayMpdf extends Mpdf
{

	/**
	 * @return bool Never
	 */
	public function writesCalibratedGray()
	{
		return false;
	}

}
