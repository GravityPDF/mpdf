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
	 * Mpdf::AddFont() reads a font's metrics, finds them stale, writes them again and reads them back.
	 * The second read has to be of the entry just written, not of the one the first read remembered.
	 */
	public function testAnEntryReadBackAfterItIsWrittenIsTheOneWritten()
	{
		$filename = 'jsonRewriteTest.json';

		$this->fontCache->jsonWrite($filename, ['cacheFormat' => 1]);
		$this->assertSame(['cacheFormat' => 1], $this->fontCache->jsonLoad($filename));

		$this->fontCache->jsonWrite($filename, ['cacheFormat' => 2]);

		try {
			$this->assertSame(['cacheFormat' => 2], $this->fontCache->jsonLoad($filename));
		} finally {
			$this->fontCache->jsonRemove($filename);
		}
	}
}
