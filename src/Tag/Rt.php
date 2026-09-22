<?php

namespace Mpdf\Tag;

/**
 * A ruby annotation, which mPDF draws inline after its base.
 */
class Rt extends InlineTag
{

	/**
	 * @var string
	 */
	protected $pdfuaStructType = 'RT';
}
