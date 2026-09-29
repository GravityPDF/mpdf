<?php

namespace Mpdf;

/**
 * Records the path of open elements each time the CSS merger asks for the element it is styling, which it does when
 * a compiled rule is filed under that element
 */
class StyledPathRecordingMpdf extends Mpdf
{

	/** @var array[] Each time the path was asked for: the tag of every frame on it, joined by >, or null for none */
	public $styledPaths = [];

	/**
	 * Records the path before handing it back
	 *
	 * @return array[]|null
	 */
	public function getStyledElementPath()
	{
		$path = parent::getStyledElementPath();
		$this->styledPaths[] = $path === null ? null : implode('>', array_column($path, 'tag'));

		return $path;
	}
}
