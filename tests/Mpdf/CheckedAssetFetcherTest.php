<?php

namespace Mpdf;

use Mockery;
use Mpdf\Container\SimpleContainer;
use Mpdf\Exception\AssetFetchingException;
use Mpdf\File\StreamWrapperChecker;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\NullLogger;

/**
 * mPDF refuses a stream wrapper that is not allowed before an asset fetcher from the container is asked for the path,
 * whichever of the fetcher's callers it comes through
 */
class CheckedAssetFetcherTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;

	/**
	 * A stream wrapper this test registers with PHP
	 */
	const REGISTERED = 'mpdftest';
	const REGISTERED_BASE = self::REGISTERED . '://host/asset';
	const PHAR_BASE = 'phar://archive.phar/asset';
	const UNREGISTERED_BASE = 'asset://host/asset';

	/**
	 * Register the wrapper. Nothing opens it, so it has no behaviour
	 */
	protected function set_up()
	{
		parent::set_up();

		stream_wrapper_register(self::REGISTERED, \stdClass::class);
	}

	/**
	 * Remove the wrapper, and verify any mock's expectations
	 */
	protected function tear_down()
	{
		stream_wrapper_unregister(self::REGISTERED);
		Mockery::close();

		parent::tear_down();
	}

	/**
	 * The HTML that makes each caller of the fetcher ask for a path, and that path
	 *
	 * @return string[][]
	 */
	public function callers()
	{
		return [
			'a <link>' => ['<link rel="stylesheet" href="%s.css"><p>Text</p>', '%s.css'],
			'an @import' => ['<style>@import url("%s.css");</style><p>Text</p>', '%s.css'],
			'an <img>' => ['<img src="%s.jpg" width="20" height="20">', '%s.jpg'],
			'an <annotation>' => ['<p><annotation content="Note" icon="Paperclip" file="%s.txt" /> Text</p>', '%s.txt'],
		];
	}

	/**
	 * phar:// never reaches the fetcher, even where it is whitelisted
	 *
	 * @dataProvider callers
	 *
	 * @param string $html
	 * @param string $path
	 */
	public function testPharIsRefusedBeforeTheFetcher($html, $path)
	{
		$requested = $this->requestedBy(sprintf($html, self::PHAR_BASE), ['whitelistStreamWrappers' => ['phar']]);

		$this->assertNotContains(sprintf($path, self::PHAR_BASE), $requested);
	}

	/**
	 * A registered wrapper off the whitelist never reaches the fetcher
	 *
	 * @dataProvider callers
	 *
	 * @param string $html
	 * @param string $path
	 */
	public function testARegisteredWrapperOffTheWhitelistIsRefusedBeforeTheFetcher($html, $path)
	{
		$requested = $this->requestedBy(sprintf($html, self::REGISTERED_BASE));

		$this->assertNotContains(sprintf($path, self::REGISTERED_BASE), $requested);
	}

	/**
	 * A registered wrapper on the whitelist reaches the fetcher
	 *
	 * @dataProvider callers
	 *
	 * @param string $html
	 * @param string $path
	 */
	public function testAWhitelistedWrapperReachesTheFetcher($html, $path)
	{
		$requested = $this->requestedBy(sprintf($html, self::REGISTERED_BASE), ['whitelistStreamWrappers' => [self::REGISTERED]]);

		$this->assertContains(sprintf($path, self::REGISTERED_BASE), $requested);
	}

	/**
	 * A scheme not registered with PHP is the fetcher's own to serve
	 *
	 * @dataProvider callers
	 *
	 * @param string $html
	 * @param string $path
	 */
	public function testAnUnregisteredSchemeReachesTheFetcher($html, $path)
	{
		$requested = $this->requestedBy(sprintf($html, self::UNREGISTERED_BASE));

		$this->assertContains(sprintf($path, self::UNREGISTERED_BASE), $requested);
	}

	/**
	 * The path a caller keeps from before it was resolved is checked too
	 */
	public function testTheOriginalSourceIsChecked()
	{
		$fetcher = new CheckedAssetFetcher(new RecordingAssetFetcher([]), new StreamWrapperChecker($this->mpdf()));

		$this->expectException(AssetFetchingException::class);
		$this->expectExceptionMessage('invalid stream');

		$fetcher->fetchDataFromPath('https://assets.test/image.jpg', 'phar://archive.phar/image.jpg');
	}

	/**
	 * The check does not stand between the document's logger and the fetcher
	 */
	public function testALoggerStillReachesTheFetcher()
	{
		$logger = new NullLogger();
		$fetcher = Mockery::mock(AssetFetcherInterface::class, LoggerAwareInterface::class);
		$fetcher->shouldReceive('setLogger')->once()->with($logger);

		$mpdf = $this->mpdf([], new SimpleContainer(['assetFetcher' => $fetcher]));
		$mpdf->setLogger($logger);

		$this->addToAssertionCount(1);
	}

	/**
	 * Render the HTML with a fetcher in place of mPDF's own that holds no assets, and files on <annotation> allowed
	 *
	 * @param string $html
	 * @param array $config
	 *
	 * @return string[] The paths the fetcher was asked for
	 */
	private function requestedBy($html, array $config = [])
	{
		$fetcher = new RecordingAssetFetcher([]);

		$mpdf = $this->mpdf($config + ['allowAnnotationFiles' => true, 'allowHtmlAnnotationFiles' => true], new SimpleContainer(['assetFetcher' => $fetcher]));
		$mpdf->WriteHTML($html);
		$this->output($mpdf);

		return $fetcher->requested;
	}

}
