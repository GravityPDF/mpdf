<?php

namespace Mpdf\Tag;

/**
 * An image map. Under PDF/UA it collects its areas so the image that uses it can link them; it
 * draws nothing and has no structure element of its own.
 */
class Map extends Tag
{

	/**
	 * @param array $attr
	 * @param array $ahtml
	 * @param int   $ihtml
	 */
	public function open($attr, &$ahtml, &$ihtml)
	{
		if (!$this->mpdf->PDFUA) {
			return;
		}
		if (empty($attr['NAME'])) {
			$this->ua->addWarning('PDF/UA-1: <map> missing name attribute; ignored.');
			return;
		}
		$name = strtolower($attr['NAME']);
		$this->ua->getImageMapRegistry()->openMap($name);
	}

	/**
	 * @param array $ahtml
	 * @param int   $ihtml
	 */
	public function close(&$ahtml, &$ihtml)
	{
		if (!$this->mpdf->PDFUA) {
			return;
		}
		$this->ua->getImageMapRegistry()->closeMap();
	}
}
