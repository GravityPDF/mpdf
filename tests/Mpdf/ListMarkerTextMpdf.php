<?php

namespace Mpdf;

/**
 * Records the text of each list marker drawn outside its item
 */
class ListMarkerTextMpdf extends Mpdf
{

	/** The text of each marker, in order. */
	public $markers = [];

	/**
	 * @param string $t
	 * @param string $link
	 * @param string $intlink
	 * @param bool $return
	 * @return array|null
	 */
	function _saveTextBuffer($t, $link = '', $intlink = '', $return = false)
	{
		if (strpos($t, Mpdf::OBJECT_IDENTIFIER . 'type=listmarker,objattr=') === 0) {
			$objattr = unserialize(substr($t, strlen(Mpdf::OBJECT_IDENTIFIER . 'type=listmarker,objattr='), -strlen(Mpdf::OBJECT_IDENTIFIER)));
			$this->markers[] = $objattr['text'];
		}

		return parent::_saveTextBuffer($t, $link, $intlink, $return);
	}
}
