<?php

namespace Mpdf\Tag;

/**
 * The fallback parentheses around a ruby annotation, which mPDF draws inline.
 */
class Rp extends InlineTag
{

	/**
	 * @var string
	 */
	protected $pdfuaStructType = 'RP';
}
