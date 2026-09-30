<?php

namespace Snapshots;

use Mpdf\CssMode;

/**
 * @media blocks and media attributes matched against the print medium and the page's size and orientation, in
 * cssMode standard
 *
 * @group snapshot
 */
class MediaQuerySnapshotTest extends MediaQuerySnapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'media-queries';
	}

	/**
	 * @return string
	 */
	protected function mode()
	{
		return CssMode::STANDARD;
	}

	/**
	 * @return string[]
	 */
	protected function captions()
	{
		return [
			'types' => 'print and not screen apply: the first two paragraphs are green and bold. not print and screen do not: the last two are black at the normal size.',
			'orientation' => 'The page is portrait: the first paragraph is green and bold, the second black at the normal size.',
			'width' => 'The page is 210mm, about 794px, wide: the first two paragraphs are green and bold, the last two black at the normal size.',
			'unknown' => 'A feature mPDF does not know makes its query fail, and a &lt;style media="not print"&gt; block is left out: both paragraphs are black at the normal size.',
			'landscape' => 'This page\'s stylesheet is matched against this page, 297mm (about 1123px) wide: the first two paragraphs are green and bold, the third black at the normal size.',
		];
	}

}
