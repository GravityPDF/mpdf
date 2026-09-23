<?php

namespace Mpdf;

use Mpdf\Unicode\Ucdn;
use Mpdf\Utils\UtfString;

/**
 * Text in a core font is held in Windows-1252, one byte to a character, and its basic bidi data is worked
 * out from those characters rather than from the bytes read as UTF-8.
 */
class CoreFontBidiDataTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * A nested list in a table cell is indented with Windows-1252 no-break spaces, 0xA0, and "Café Â" ends in
	 * 0xC2, which read as UTF-8 would take the byte after it along.
	 */
	public function testAListIndentAndAnAccentedLetterInACoreFontDocument()
	{
		$mpdf = $this->mpdf(['mode' => 'c']);
		$mpdf->WriteHTML('<table><tr><td><ul><li>One<ul><li>Caf&eacute; &Acirc;</li></ul></li></ul></td></tr></table>');

		$this->assertContains(
			[[0xA0, 0xA0, 0xA0, 0xA0, 0x2D, 0x20], [Ucdn::BIDI_CLASS_CS, Ucdn::BIDI_CLASS_CS, Ucdn::BIDI_CLASS_CS, Ucdn::BIDI_CLASS_CS, Ucdn::BIDI_CLASS_ES, Ucdn::BIDI_CLASS_WS]],
			$mpdf->bidiData
		);
		$this->assertContains(
			[[0x43, 0x61, 0x66, 0xE9, 0x20, 0xC2], [Ucdn::BIDI_CLASS_L, Ucdn::BIDI_CLASS_L, Ucdn::BIDI_CLASS_L, Ucdn::BIDI_CLASS_L, Ucdn::BIDI_CLASS_WS, Ucdn::BIDI_CLASS_L]],
			$mpdf->bidiData
		);
	}

	/**
	 * In a UTF-8 document whose default font is a core font, a chunk that names no font is in that core font
	 * and is read as Windows-1252, while the UTF-8 of the TrueType span after it gets its bidi data from OTL.
	 */
	public function testAChunkNamingNoFontInACoreDefaultFont()
	{
		$mpdf = $this->mpdf(['mode' => 'utf-8', 'default_font' => 'chelvetica']);
		$mpdf->WriteHTML('<p>Caf&eacute; <span style="font-family:dejavusans">Caf&eacute;</span></p>');

		$this->assertSame(
			[[[0x43, 0x61, 0x66, 0xE9, 0x20], [Ucdn::BIDI_CLASS_L, Ucdn::BIDI_CLASS_L, Ucdn::BIDI_CLASS_L, Ucdn::BIDI_CLASS_L, Ucdn::BIDI_CLASS_WS]]],
			$mpdf->bidiData
		);
	}

	/**
	 * A chunk in a TrueType font that has not been given bidi data by OTL is still read as UTF-8, after a
	 * chunk in a core font.
	 */
	public function testATrueTypeChunkAfterACoreFontChunk()
	{
		$mpdf = $this->mpdf(['mode' => 'utf-8', 'default_font' => 'chelvetica', 'fontdata' => ['dejavusansnootl' => ['R' => 'DejaVuSans.ttf']]]);
		$mpdf->WriteHTML('<p>Caf&eacute; <span style="font-family:dejavusansnootl">Caf&eacute;</span></p>');

		$this->assertSame(
			[
				[[0x43, 0x61, 0x66, 0xE9, 0x20], [Ucdn::BIDI_CLASS_L, Ucdn::BIDI_CLASS_L, Ucdn::BIDI_CLASS_L, Ucdn::BIDI_CLASS_L, Ucdn::BIDI_CLASS_WS]],
				[[0x43, 0x61, 0x66, 0xE9], [Ucdn::BIDI_CLASS_L, Ucdn::BIDI_CLASS_L, Ucdn::BIDI_CLASS_L, Ucdn::BIDI_CLASS_L]],
			],
			$mpdf->bidiData
		);
	}

	/**
	 * MultiCell() gives text basic bidi data once the document is bidirectional, and a core font's é is its
	 * last byte, which read as UTF-8 would be dropped.
	 */
	public function testMultiCellInACoreFont()
	{
		$mpdf = $this->mpdf(['mode' => 'c']);
		$mpdf->biDirectional = true;
		$mpdf->MultiCell(0, 5, 'Caf' . UtfString::code2utf(0xE9));

		$this->assertSame([[[0x43, 0x61, 0x66, 0xE9], [Ucdn::BIDI_CLASS_L, Ucdn::BIDI_CLASS_L, Ucdn::BIDI_CLASS_L, Ucdn::BIDI_CLASS_L]]], $mpdf->bidiData);
	}

	/**
	 * A document that records the bidi data it gives each chunk.
	 *
	 * @param array $config
	 *
	 * @return BidiDataRecordingMpdf
	 */
	private function mpdf(array $config)
	{
		$mpdf = new BidiDataRecordingMpdf($config);
		$mpdf->AddPage();

		return $mpdf;
	}

}
