<?php

namespace Mpdf;

use Mpdf\Tag\TableTest;

/**
 * PHPUnit keeps every test object until the run is over, so a document one kept in a property stayed in memory
 * for the rest of the suite. See GravityPDF/mpdf#340.
 */
class ReleaseFinishedTestsTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * Kept across tests, where emptying it would lose what the class set up for all of them
	 *
	 * @var string
	 */
	private static $shared = 'kept';

	/**
	 * @var \stdClass|null
	 */
	private $held;

	/**
	 * The document a tag test sets up in a property its parent declares is let go once the test has run
	 */
	public function testTheDocumentATestKeptInAPropertyIsLetGo()
	{
		$finished = new TableTest('testOpenBasicTable');
		$property = new \ReflectionProperty('Mpdf\Tag\BaseTagTestCase', 'mpdf');
		if (PHP_VERSION_ID < 80100) {
			$property->setAccessible(true);
		}
		$property->setValue($finished, new Mpdf(['mode' => 'c']));

		(new ReleaseFinishedTests())->end_test($finished, 0.0);

		$this->assertNull($property->getValue($finished));
	}

	/**
	 * A property of the test class itself is emptied, while its static properties and what PHPUnit keeps to
	 * report the result are left alone
	 */
	public function testOnlyTheTestsOwnInstancePropertiesAreEmptied()
	{
		$finished = new self('testOnlyTheTestsOwnInstancePropertiesAreEmptied');
		$finished->held = new \stdClass();

		(new ReleaseFinishedTests())->end_test($finished, 0.0);

		$this->assertNull($finished->held);
		$this->assertSame('kept', self::$shared);
		$this->assertSame('testOnlyTheTestsOwnInstancePropertiesAreEmptied', $finished->getName());
	}

	/**
	 * The copy of the declared classes PHPUnit before 8.5 keeps in each suite is let go when the run starts, in
	 * the suite of each class and in the suite of each data provider inside it
	 */
	public function testEverySuiteLetsGoOfItsDeclaredClasses()
	{
		$property = ReleaseFinishedTests::declaredClassesProperty();
		if ($property === null) {
			$this->markTestSkipped('This PHPUnit keeps a count of the declared classes rather than a copy');
		}

		$owner = $property->class;
		$run = new $owner('run');
		$run->addTestSuite(new \ReflectionClass('Mpdf\Color\ColorConverterTest'));
		$classSuite = $run->tests()[0];
		$providerSuite = $classSuite->tests()[0];
		$this->assertInstanceOf($owner, $providerSuite, 'the first test of the class has a data provider');
		$this->assertNotEmpty($property->getValue($providerSuite));

		$listener = new ReleaseFinishedTests();
		$listener->start_test_suite($run);

		$this->assertNull($property->getValue($run));
		$this->assertNull($property->getValue($classSuite));
		$this->assertNull($property->getValue($providerSuite));
	}

}
