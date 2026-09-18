<?php

namespace Mpdf\Fonts;

use Mpdf\Mpdf;
use Mpdf\Cache;

class FontCacheTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * @var \Mpdf\Mpdf
	 */
	private $mpdf;

	/**
	 * @var \Mpdf\Fonts\FontCache
	 */
	private $fontCache;

	protected function set_up()
	{
		parent::set_up();

		$this->mpdf = new Mpdf();
		$this->fontCache = new FontCache(new Cache($this->mpdf->tempDir . '/ttfontdata'));
	}

	public function testJson()
	{
		$filename = 'jsonCallTest.json';
		$this->fontCache->jsonWrite($filename, 'Output Text');
		$this->assertEquals('Output Text', $this->fontCache->jsonLoad($filename));

		/* Test loading from the in-memory cache now the file is loaded */
		$this->fontCache->remove($filename);
		$this->assertFalse($this->fontCache->has($filename));
		$this->assertTrue($this->fontCache->jsonHas($filename));
		$this->assertEquals('Output Text', $this->fontCache->jsonLoad($filename));

		/* Test the deletion of the JSON cache */
		$this->fontCache->write($filename, '');
		$this->assertTrue($this->fontCache->has($filename));
		$this->fontCache->jsonRemove($filename);
		$this->assertFalse($this->fontCache->jsonHas($filename));
	}

	/**
	 * A read that found nothing is not remembered. Remembering it is what turns one entry expiring
	 * mid-render into a whole document drawn without the font's metrics: the caller answers the miss by
	 * generating the entry again, and is handed the nothing from before it instead of what it wrote.
	 */
	public function testAnEntryThatWasNotThereIsNotRememberedAsEmpty()
	{
		$filename = 'jsonMissTest.json';

		$this->assertNull($this->fontCache->jsonLoadIfPresent($filename));
		$this->assertFalse($this->fontCache->jsonHas($filename));

		$this->fontCache->jsonWrite($filename, 'Output Text');

		try {
			$this->assertSame('Output Text', $this->fontCache->jsonLoadIfPresent($filename));
		} finally {
			$this->fontCache->jsonRemove($filename);
		}
	}
}
