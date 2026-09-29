<?php

namespace Mpdf;

/**
 * Records the open elements as each piece of text is read, in the flow or in a table cell, and counts the
 * page-break-inside: avoid blocks put back. The record is part of the document's state, so a pass that is put back
 * takes its own entries with it
 */
class OpenElementsRecordingMpdf extends UnwindCountingMpdf
{

	/** @var array[] Each piece of text read, and the open elements when it was: [text, frames] */
	public $openElementsAtText = [];

	/**
	 * Records the text before buffering it for a block
	 *
	 * @param string $t
	 * @param string $link
	 * @param string $intlink
	 * @param bool $return
	 *
	 * @return mixed
	 */
	function _saveTextBuffer($t, $link = '', $intlink = '', $return = false)
	{
		$this->record($t);

		return parent::_saveTextBuffer($t, $link, $intlink, $return);
	}

	/**
	 * Records the text before buffering it for a table cell
	 *
	 * @param string $t
	 * @param string $link
	 * @param string $intlink
	 *
	 * @return mixed
	 */
	function _saveCellTextBuffer($t, $link = '', $intlink = '')
	{
		$this->record($t);

		return parent::_saveCellTextBuffer($t, $link, $intlink);
	}

	/**
	 * Keeps text that is more than white space and not an object mPDF buffers in its place
	 *
	 * @param string $t
	 */
	private function record($t)
	{
		if (trim($t) !== '' && strpos($t, Mpdf::OBJECT_IDENTIFIER) === false) {
			$this->openElementsAtText[] = [trim($t), $this->getOpenElements()];
		}
	}
}
