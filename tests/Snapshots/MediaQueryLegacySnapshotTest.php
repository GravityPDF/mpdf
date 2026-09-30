<?php

namespace Snapshots;

use Mpdf\CssMode;

/**
 * @media blocks and media attributes in cssMode legacy, which applies a query list when it contains print or all
 * anywhere, as mPDF v7 did, whatever the page
 *
 * @group snapshot
 */
class MediaQueryLegacySnapshotTest extends MediaQuerySnapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'media-queries-legacy';
	}

	/**
	 * @return string
	 */
	protected function mode()
	{
		return CssMode::LEGACY;
	}

	/**
	 * @return string[]
	 */
	protected function captions()
	{
		return [
			'types' => 'The queries containing print apply: the first paragraph is green and bold, and the third large and red. not screen and screen do not: the second and fourth are black at the normal size.',
			'orientation' => 'Both queries contain print, so both apply whatever the page: the first paragraph is green and bold, the second large and red.',
			'width' => 'No query contains print or all, so none applies: all four paragraphs are black at the normal size.',
			'unknown' => 'The (hover: hover) query contains neither print nor all: the first paragraph is black at the normal size. The media attribute not print contains print, so its block applies: the second paragraph is large and red.',
			'landscape' => 'The queries containing print apply whatever the page, and the width query does not: the first paragraph is green and bold, the second black at the normal size, the third large and red.',
		];
	}

}
