<?php

namespace Mpdf\Ua\Security;

use Mpdf\Ua\PdfUaTestCase;

/**
 * The key of a custom property is written as one escaped name, so it cannot add
 * entries of its own to the /Info dictionary.
 *
 * @group pdfua
 * @group security
 */
class CustomPropertyInjectionTest extends PdfUaTestCase
{

	/**
	 * A key carrying a newline, delimiters and a second name is written as a single escaped name.
	 */
	public function testInjectedKeyIsEscapedToSingleNameToken()
	{
		$mpdf = $this->makeMpdf();
		$mpdf->AddCustomProperty("good\n/Producer (pwned) /Title", 'value');
		$output = $this->getOutput($mpdf, '<p>x</p>');

		$startMarker = "/Producer";
		$start = strpos($output, $startMarker);
		$this->assertNotFalse($start, 'PDF must contain a /Producer line');
		$endMarker = '/CreationDate';
		$end = strpos($output, $endMarker, $start);
		$this->assertNotFalse($end, 'PDF must contain /CreationDate after /Info entries');
		$infoBlock = substr($output, $start, $end - $start);

		$this->assertStringNotContainsString("\n/Producer (pwned)", $infoBlock);
		$this->assertStringNotContainsString("(pwned)", $infoBlock);
		$this->assertStringNotContainsString("/Title (FE", $infoBlock);

		$this->assertStringContainsString('good#0A#2FProducer#20#28pwned#29#20#2FTitle', $infoBlock);
	}

	/**
	 * An empty key is refused in a PDF/UA document.
	 */
	public function testEmptyKeyIsRejected()
	{
		$this->expectException(\Mpdf\MpdfException::class);
		$mpdf = $this->makeMpdf();
		$mpdf->AddCustomProperty('', 'v');
	}

	/**
	 * A key longer than 127 bytes, the limit on a PDF name, is refused in a PDF/UA document.
	 */
	public function testOversizedKeyIsRejected()
	{
		$this->expectException(\Mpdf\MpdfException::class);
		$mpdf = $this->makeMpdf();
		$mpdf->AddCustomProperty(str_repeat('A', 128), 'v');
	}

	/**
	 * A document that is not PDF/UA accepts an empty or oversized key, as it always has.
	 */
	public function testKeyValidationIsScopedToPdfUa()
	{
		$mpdf = new \Mpdf\Mpdf();
		$mpdf->AddCustomProperty('', 'v');
		$mpdf->AddCustomProperty(str_repeat('A', 200), 'v');
		$this->assertTrue(true, 'Non-UA AddCustomProperty accepts empty/oversized keys.');
	}

	/**
	 * A plain key is written unchanged.
	 */
	public function testBenignKeyPassesThrough()
	{
		$mpdf = $this->makeMpdf();
		$mpdf->AddCustomProperty('Department', 'Engineering');
		$output = $this->getOutput($mpdf, '<p>x</p>');

		$this->assertStringContainsString('/Department ', $output);
	}

	/**
	 * A control byte in a key is written as a #XX escape.
	 */
	public function testKeyWithControlBytesIsEscaped()
	{
		$mpdf = $this->makeMpdf();
		$mpdf->AddCustomProperty("Tab\tHere", 'v');
		$output = $this->getOutput($mpdf, '<p>x</p>');

		$this->assertStringContainsString('/Tab#09Here', $output);
		$this->assertStringNotContainsString("/Tab\tHere", $output);
	}
}
