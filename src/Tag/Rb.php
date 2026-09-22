<?php

namespace Mpdf\Tag;

/**
 * A ruby base. A base that is bare text is content of the Ruby element itself.
 */
class Rb extends InlineTag
{

	/**
	 * @var string
	 */
	protected $pdfuaStructType = 'RB';
}
