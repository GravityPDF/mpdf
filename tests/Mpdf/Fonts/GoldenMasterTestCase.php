<?php

namespace Mpdf\Fonts;

/**
 * One assertion, made by all four golden masters: what this font captures now is what its committed
 * fixture says.
 */
abstract class GoldenMasterTestCase extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * @var GoldenMaster
	 */
	private $master;

	/**
	 * @return GoldenMaster The master under test
	 */
	abstract protected function newMaster();

	/**
	 * @return string The composer script that rewrites its fixtures, named in the failure message
	 */
	abstract protected function updateCommand();

	/**
	 * Builds the master once per test, so that a capture cannot be told apart by what ran before it.
	 */
	public function set_up()
	{
		parent::set_up();

		$this->master = $this->newMaster();
	}

	/**
	 * @dataProvider fontProvider
	 */
	public function testTheFixtureStillHolds($name)
	{
		$this->assertSame(
			$this->master->loadFixture($name),
			$this->master->capture($name),
			sprintf(
				'%s captures differently than its fixture. If the change is intended, run: composer %s %s',
				$name,
				$this->updateCommand(),
				$name
			)
		);
	}

	/**
	 * @return array Every font in the corpus, as a PHPUnit data provider
	 */
	public function fontProvider()
	{
		return $this->newMaster()->fonts();
	}
}
