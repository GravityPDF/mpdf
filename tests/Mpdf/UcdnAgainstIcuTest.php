<?php

namespace Mpdf;

/**
 * Every codepoint of the generated table against ICU.
 *
 * UcdnTablesTest reads the tables back through the code that builds them, which cannot tell a table
 * that is wrong from one that is wrong consistently, and it asserts a few dozen characters. This
 * asks something that shares nothing with the generator - a different implementation, reading its
 * own copy of the database - about all 1,114,112 of them, which is the check worth running the once,
 * when the tables are regenerated.
 *
 * It is that regeneration this is here for. ICU carries whichever Unicode version the intl extension
 * on this machine was built against, and against any other version thousands of codepoints differ
 * for reasons that are not a fault, so the test skips unless the two versions are the same. On CI
 * they are not: intl is not installed, and a runner's ICU tracks its image rather than this table.
 */
class UcdnAgainstIcuTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * The record's fields, in the order it holds them
	 */
	private static $fields = ['category', 'combining', 'bidi', 'mirrored', 'width', 'normalization', 'script'];

	/**
	 * @var array[] The name of each property value ICU answers with, by property and enum value
	 */
	private $names = [];

	protected function set_up()
	{
		parent::set_up();

		if (!class_exists('IntlChar')) {
			$this->markTestSkipped('The intl extension is not loaded, so there is nothing to compare against');
		}

		$ours = UcdnTables::DEFAULT_VERSION;
		if ($this->release(\IntlChar::UNICODE_VERSION) !== $this->release($ours)) {
			$this->markTestSkipped(sprintf(
				'ICU %s carries Unicode %s and the tables are built from %s, so every difference between them would be a version and not a fault',
				INTL_ICU_VERSION,
				\IntlChar::UNICODE_VERSION,
				$ours
			));
		}
	}

	public function testEveryCodepointCarriesWhatIcuSaysItDoes()
	{
		list($categories, $bidi, $scripts) = $this->numbering();
		$widths = UcdnTables::widths();

		// The class folds the four isolate classes of Unicode 6.3 into ON, as Bidi resolves them.
		foreach (['LRI', 'RLI', 'FSI', 'PDI'] as $isolate) {
			$bidi[$isolate] = $bidi['ON'];
		}

		$differ = array_fill_keys(self::$fields, 0);
		$first = [];
		$unnamed = [];

		for ($char = 0; $char < 0x110000; $char++) {
			$record = Ucdn::get_ucd_record($char);

			$icu = [
				$categories[$this->nameOf(\IntlChar::PROPERTY_GENERAL_CATEGORY, $char, true)],
				\IntlChar::getCombiningClass($char),
				$bidi[$this->nameOf(\IntlChar::PROPERTY_BIDI_CLASS, $char, true)],
				\IntlChar::isMirrored($char) ? 1 : 0,
				$widths[$this->nameOf(\IntlChar::PROPERTY_EAST_ASIAN_WIDTH, $char, true)],
				0, // normalization_check, which the generator does not take from the database
				$this->scriptOf($char, $scripts, $record[6], $unnamed),
			];

			foreach (self::$fields as $field => $name) {
				if ($record[$field] !== $icu[$field]) {
					$differ[$name]++;
					if (!isset($first[$name])) {
						$first[$name] = sprintf('U+%04X is %d and ICU says %d', $char, $record[$field], $icu[$field]);
					}
				}
			}
		}

		$this->assertSame([], $unnamed, 'ICU names a script the class has no constant for');
		$this->assertSame(array_fill_keys(self::$fields, 0), $differ, implode('; ', $first));
	}

	/**
	 * ICU names every script the class does, so a script it answers with that the class cannot name
	 * is a constant missing rather than a codepoint to compare - it is collected and reported once,
	 * and the codepoint is left reading as it does so that it is not counted twice.
	 *
	 * @param string[] $unnamed by reference
	 */
	private function scriptOf($char, $scripts, $ours, &$unnamed)
	{
		$name = 'SCRIPT_' . strtoupper($this->nameOf(\IntlChar::PROPERTY_SCRIPT, $char, false));

		if (!isset($scripts[$name])) {
			$unnamed[$name] = $name;
			return $ours;
		}

		return $scripts[$name];
	}

	/**
	 * ICU answers with an enum of its own, so it is asked for the name of what it answered rather
	 * than its numbering being transcribed here. The answers are held, there being a few dozen of
	 * them over a million codepoints.
	 */
	private function nameOf($property, $char, $short)
	{
		$value = \IntlChar::getIntPropertyValue($char, $property);

		if (!isset($this->names[$property][$value])) {
			$this->names[$property][$value] = \IntlChar::getPropertyValueName(
				$property,
				$value,
				$short ? \IntlChar::SHORT_PROPERTY_NAME : \IntlChar::LONG_PROPERTY_NAME
			);
		}

		return $this->names[$property][$value];
	}

	/**
	 * The numbers the class gives the property values, read from the class rather than written out
	 * again here - a number transcribed wrongly would read as a wrong table.
	 *
	 * @return array[] [$categories, $bidiClasses, $scriptConstants] - the last by constant name,
	 *                 which is how ICU's script names are matched against it
	 */
	private function numbering()
	{
		$tables = new UcdnTables();
		list($categories, $bidi, $constants) = $tables->numbersInUse(file_get_contents(__DIR__ . '/../../src/Ucdn.php'));

		return [$categories, $bidi, array_flip($constants)];
	}

	/**
	 * @return string The major and minor of a Unicode version, which is as far as ICU states it
	 */
	private function release($version)
	{
		$parts = explode('.', $version);

		return $parts[0] . '.' . $parts[1];
	}

}
