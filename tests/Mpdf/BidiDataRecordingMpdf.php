<?php

namespace Mpdf;

/**
 * Records the code points each chunk is given basic bidi data for, and the bidi classes it gets.
 */
class BidiDataRecordingMpdf extends Mpdf
{

	/**
	 * @var array[] One entry per chunk: its code points, then the bidi class of each.
	 */
	public $bidiData = [];

	/**
	 * Records the code points and the bidi classes worked out from them.
	 *
	 * @param array $chunkOTLdata
	 * @param int[] $unicode
	 * @param bool  $is_strong
	 */
	public function getBasicOTLdata(&$chunkOTLdata, $unicode, &$is_strong)
	{
		parent::getBasicOTLdata($chunkOTLdata, $unicode, $is_strong);

		$classes = [];
		foreach ($chunkOTLdata['char_data'] as $char) {
			$classes[] = $char['bidi_class'];
		}

		$this->bidiData[] = [$unicode, $classes];
	}

}
