<?php

namespace Mpdf\Ua\Security;

use Mpdf\Ua\PdfUaTestCase;
use Mpdf\Image\Svg;

/**
 * Reading the title and description of an SVG resolves no external entity.
 *
 * ImageSVG() strips the DOCTYPE first, but extractAccessibleMetadata() is public
 * and can be handed one directly.
 *
 * @group pdfua
 * @group security
 */
class SvgXxeTest extends PdfUaTestCase
{

	/**
	 * @return Svg An SVG parser with no Mpdf behind it, enough for extractAccessibleMetadata()
	 */
	private function newSvgWithoutConstructor()
	{
		$ref = new \ReflectionClass(Svg::class);
		return $ref->newInstanceWithoutConstructor();
	}

	/**
	 * An entity naming a local file is not read into the title.
	 */
	public function testFileSystemEntityIsNotResolved()
	{
		$svg = $this->newSvgWithoutConstructor();

		$payload = '<?xml version="1.0"?>'
			. '<!DOCTYPE svg [<!ENTITY xxe SYSTEM "file:///etc/hosts">]>'
			. '<svg xmlns="http://www.w3.org/2000/svg"><title>&xxe;</title></svg>';

		$result = $svg->extractAccessibleMetadata($payload);

		$this->assertIsArray($result);
		$this->assertArrayHasKey('title', $result);
		$title = (string) $result['title'];

		// A hosts file always names localhost
		$this->assertStringNotContainsStringIgnoringCase('localhost', $title);
		$this->assertStringNotContainsString('127.0.0.1', $title);
	}

	/**
	 * An entity naming a URL is not fetched.
	 */
	public function testHttpEntityIsNotFetched()
	{
		$svg = $this->newSvgWithoutConstructor();
		$payload = '<?xml version="1.0"?>'
			. '<!DOCTYPE svg [<!ENTITY xxe SYSTEM "http://example.invalid/secret">]>'
			. '<svg xmlns="http://www.w3.org/2000/svg"><title>&xxe;</title></svg>';

		$start = microtime(true);
		$result = $svg->extractAccessibleMetadata($payload);
		$elapsed = microtime(true) - $start;

		$this->assertLessThan(5.0, $elapsed, 'Parse must not block on network entity resolution.');
		$this->assertIsArray($result);
	}

	/**
	 * Character entities in the title and description are still decoded.
	 */
	public function testCharacterEntitiesStillFunctionInBodyText()
	{
		$svg = $this->newSvgWithoutConstructor();
		$payload = '<svg xmlns="http://www.w3.org/2000/svg">'
			. '<title>foo &amp; bar</title>'
			. '<desc>caf&#233;</desc>'
			. '</svg>';
		$result = $svg->extractAccessibleMetadata($payload);
		$this->assertSame('foo & bar', $result['title']);
		$this->assertSame('caf' . "\xC3\xA9", $result['desc']);
	}

	/**
	 * A plain title and description are read as written.
	 */
	public function testWellFormedSvgStillProducesTitle()
	{
		$svg = $this->newSvgWithoutConstructor();
		$payload = '<svg xmlns="http://www.w3.org/2000/svg">'
			. '<title>Company logo</title>'
			. '<desc>Blue square</desc>'
			. '</svg>';
		$result = $svg->extractAccessibleMetadata($payload);
		$this->assertSame('Company logo', $result['title']);
		$this->assertSame('Blue square', $result['desc']);
	}
}
