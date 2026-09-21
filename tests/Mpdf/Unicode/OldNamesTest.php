<?php

namespace Mpdf\Unicode;

use Mpdf\Mpdf;

/**
 * Ucdn and Bidi were Mpdf\Ucdn and Mpdf\Bidi, and the font bundles' READMEs tell a configuration to
 * name the base script through the old one, so both old names still answer
 */
class OldNamesTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * A script constant and a lookup read through the old name are Ucdn's own
	 */
	public function testTheOldUcdnNameReadsTheSameTables()
	{
		$this->assertSame(Ucdn::SCRIPT_LATIN, \Mpdf\Ucdn::SCRIPT_LATIN);
		$this->assertSame(Ucdn::SCRIPT_ARABIC, \Mpdf\Ucdn::get_script(0x0627));
	}

	/**
	 * The old Bidi name is the same algorithm
	 */
	public function testTheOldBidiNameIsTheSameClass()
	{
		$this->assertInstanceOf('Mpdf\Unicode\Bidi', new \Mpdf\Bidi());
	}

	/**
	 * The configuration the READMEs give still builds a document
	 */
	public function testABaseScriptNamedThroughTheOldNameIsAccepted()
	{
		$mpdf = new Mpdf(['baseScript' => \Mpdf\Ucdn::SCRIPT_LATIN]);
		$mpdf->WriteHTML('<p>Text</p>');

		$this->assertSame(Ucdn::SCRIPT_LATIN, $mpdf->baseScript);
		$this->assertStringStartsWith('%PDF', $mpdf->OutputBinaryData());
	}
}
