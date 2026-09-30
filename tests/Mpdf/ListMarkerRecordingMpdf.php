<?php

namespace Mpdf;

/**
 * Records the type and the image each list marker is asked to draw, as the cascade and setCSS() hand them over,
 * before the image is looked for.
 */
class ListMarkerRecordingMpdf extends Mpdf
{

	/** The list-style-type value of each marker set, in order. */
	public $markerTypes = [];

	/** The list-style-image value of each marker set, in order. */
	public $markerImages = [];

	/**
	 * @param string $listitemtype The marker's list-style-type
	 * @param string $listitemimage The marker's list-style-image
	 * @param string $listitemposition The marker's list-style-position
	 */
	function _setListMarker($listitemtype, $listitemimage, $listitemposition)
	{
		$this->markerTypes[] = $listitemtype;
		$this->markerImages[] = $listitemimage;

		return parent::_setListMarker($listitemtype, $listitemimage, $listitemposition);
	}

}
