<?php

namespace Mpdf;

use Mpdf\Container\SimpleContainer;
use Psr\Log\NullLogger;

/**
 * A class that implements only AssetFetcherInterface can be passed through the container. Each address here exists
 * only in the fetcher, so whatever the document draws from one came through it
 */
class InterfaceOnlyAssetFetcherTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;

	const STYLESHEET = 'https://assets.test/linked.css';
	const IMAGE = 'https://assets.test/image.jpg';

	/**
	 * A stylesheet is fetched, and its rules are applied
	 */
	public function testAStylesheetComesFromTheFetcher()
	{
		$fetcher = new RecordingAssetFetcher([self::STYLESHEET => 'p { color: #ff0000 }']);

		$pdf = $this->document($fetcher, '<link rel="stylesheet" href="' . self::STYLESHEET . '"><p>Text</p>');

		$this->assertSame([self::STYLESHEET], $fetcher->requested);
		$this->assertStringContainsString('1.000 0.000 0.000 rg', $pdf);
	}

	/**
	 * An image is fetched, and the bytes returned are embedded
	 */
	public function testAnImageComesFromTheFetcher()
	{
		$jpeg = file_get_contents(__DIR__ . '/../data/img/bg.jpg');
		$fetcher = new RecordingAssetFetcher([self::IMAGE => $jpeg]);

		$pdf = $this->document($fetcher, '<img src="' . self::IMAGE . '" width="20" height="20">');

		$this->assertSame([self::IMAGE], $fetcher->requested);
		$this->assertStringContainsString($jpeg, $pdf);
	}

	/**
	 * Setting a logger passes over a fetcher that cannot take one
	 */
	public function testSetLoggerPassesOverAFetcherThatIsNotLoggerAware()
	{
		$mpdf = $this->mpdf([], new SimpleContainer(['assetFetcher' => new RecordingAssetFetcher([])]));

		$mpdf->setLogger(new NullLogger());

		$this->addToAssertionCount(1);
	}

	/**
	 * Render HTML with the fetcher in place of mPDF's own
	 *
	 * @param \Mpdf\AssetFetcherInterface $fetcher
	 * @param string $html
	 *
	 * @return string The document
	 */
	private function document(AssetFetcherInterface $fetcher, $html)
	{
		$mpdf = $this->mpdf([], new SimpleContainer(['assetFetcher' => $fetcher]));
		$mpdf->WriteHTML($html);

		return $this->output($mpdf);
	}

}
