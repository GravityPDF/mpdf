<?php

namespace Mpdf;

use PHPUnit\Framework\TestListener;
use Yoast\PHPUnitPolyfills\TestListeners\TestListenerDefaultImplementation;

/**
 * Lets go of whatever a test kept in its properties once it has run.
 *
 * PHPUnit keeps every test object until the whole run is over, and so everything they hold: an Mpdf
 * instance left in $this->mpdf keeps its fonts, pages and output alive for the rest of the run. Across
 * the suite that came to more than half a gigabyte. Registered in phpunit.xml.
 *
 * An Mpdf instance and its services refer to each other, so a released one is only freed when PHP next
 * looks for cycles, and PHP does that by how many objects are waiting rather than how large they are.
 * Collecting once another 32MB is waiting halves the peak of the suite without slowing it.
 */
class ReleaseFinishedTests implements TestListener
{

	use TestListenerDefaultImplementation;

	/**
	 * @var int The memory in use after the cycles were last collected
	 */
	private $collectedAt = 0;

	/**
	 * @var bool Whether the suites have let go of their lists of declared classes
	 */
	private $suitesReleased = false;

	/**
	 * Before the first test runs, empty the list of declared classes each suite took when it was built.
	 *
	 * PHPUnit before 8.5 has every suite, down to the one for each data provider, keep a copy of
	 * get_declared_classes() so it can tell which classes a test file adds. It only reads the copy while it
	 * loads the files, but holds it for the whole run: some 1,500 copies of up to 1,100 names, up to 180MB
	 * on PHP 5.6. Later versions keep a count instead.
	 *
	 * @param \PHPUnit\Framework\TestSuite $suite
	 */
	public function start_test_suite($suite)
	{
		if ($this->suitesReleased) {
			return;
		}

		$this->suitesReleased = true;

		$property = self::declaredClassesProperty();
		if ($property !== null) {
			$this->releaseDeclaredClasses($suite, $property);
		}
	}

	/**
	 * The suite's copy of the declared classes, readable from outside, or null where this PHPUnit keeps a count instead
	 *
	 * @return \ReflectionProperty|null
	 */
	public static function declaredClassesProperty()
	{
		$owner = class_exists('PHPUnit_Framework_TestSuite', false) ? 'PHPUnit_Framework_TestSuite' : 'PHPUnit\Framework\TestSuite';
		if (!property_exists($owner, 'declaredClasses')) {
			return null;
		}

		$property = new \ReflectionProperty($owner, 'declaredClasses');
		if (PHP_VERSION_ID < 80100) {
			$property->setAccessible(true);
		}

		return $property;
	}

	/**
	 * Empty the list of declared classes of $suite and of every suite inside it
	 *
	 * @param \PHPUnit\Framework\TestSuite $suite
	 * @param \ReflectionProperty $property
	 */
	private function releaseDeclaredClasses($suite, \ReflectionProperty $property)
	{
		$property->setValue($suite, null);

		$owner = $property->class;
		foreach ($suite->tests() as $test) {
			if ($test instanceof $owner) {
				$this->releaseDeclaredClasses($test, $property);
			}
		}
	}

	/**
	 * Empty the properties the test class and its parents declare, leaving those of PHPUnit and the
	 * polyfills, which PHPUnit still reads to report the result, then collect the cycles if enough are waiting.
	 *
	 * @param \PHPUnit\Framework\Test $test
	 * @param float $time
	 */
	public function end_test($test, $time)
	{
		for ($class = new \ReflectionClass($test); $class && !$this->isFramework($class->getName()); $class = $class->getParentClass()) {
			foreach ($class->getProperties() as $property) {
				if ($property->isStatic() || $property->getDeclaringClass()->getName() !== $class->getName()) {
					continue;
				}

				if (PHP_VERSION_ID < 80100) {
					$property->setAccessible(true);
				}

				$property->setValue($test, null);
			}
		}

		if (memory_get_usage() - $this->collectedAt > 32 * 1024 * 1024) {
			gc_collect_cycles();
			$this->collectedAt = memory_get_usage();
		}
	}

	/**
	 * Whether the class belongs to PHPUnit or the polyfills rather than to this suite
	 *
	 * @param string $name
	 *
	 * @return bool
	 */
	private function isFramework($name)
	{
		return strpos($name, 'PHPUnit') === 0 || strpos($name, 'Yoast\\') === 0;
	}

}
