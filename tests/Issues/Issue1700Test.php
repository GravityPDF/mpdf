<?php

namespace Issues;

use Mpdf\TestLogger;

class Issue1700Test extends \Mpdf\BaseMpdfTest
{

	/**
	 * @var string|null The HTTP_HOST the test found, put back afterwards so later documents do not take localhost as
	 *                  their base path and try to fetch every missing asset from it
	 */
	private $httpHost;

	/**
	 * Remember the HTTP_HOST before the test changes it
	 */
	protected function set_up()
	{
		parent::set_up();

		$this->httpHost = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : null;
	}

	/**
	 * Put the HTTP_HOST back as the test found it
	 */
	protected function tear_down()
	{
		if ($this->httpHost === null) {
			unset($_SERVER['HTTP_HOST']);
		} else {
			$_SERVER['HTTP_HOST'] = $this->httpHost;
		}

		parent::tear_down();
	}

	/**
	 * An absolute path with a space in its file name is read from disk when the base path is a browser session's
	 */
	public function testImageLoadingProblemForAbsolutePathsWithSpaceInTheFilename()
	{
		/* Mimic a browser-based session basepath */
		$_SERVER['HTTP_HOST'] = 'localhost';

		$logger = new TestLogger();
		$mpdf   = new \Mpdf\Mpdf([ 'mode' => 'c' ]);
		$mpdf->setLogger($logger);

		$file = __DIR__ . '/../data/img/bay eux.jpg';
		$mpdf->WriteHTML('<img src="' . $file . '" />');

		$this->assertCount(1, $logger->records);
		$this->assertSame('Fetching content of file "' . $file . '" with local basepath', $logger->records[0]['message']);
	}
}
