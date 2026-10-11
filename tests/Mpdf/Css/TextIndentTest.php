<?php

namespace Mpdf\Css;

use Mpdf\Mpdf;
use Mpdf\SizeConverter;
use Psr\Log\NullLogger;

class TextIndentTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * A value is one length or percentage with at most one of hanging and each-line, in any order and case
	 *
	 * @dataProvider providerParse
	 *
	 * @param string $value
	 * @param array|null $expected
	 */
	public function testParse($value, $expected)
	{
		$sizeConverter = new SizeConverter(96, 11, new Mpdf(['mode' => 'c']), new NullLogger());

		$this->assertSame($expected, TextIndent::parse($value, $sizeConverter));
	}

	/**
	 * @return array[] Values and what they are read as
	 */
	public function providerParse()
	{
		return [
			'a length' => ['10mm', ['length' => '10mm', 'hanging' => false, 'each-line' => false]],
			'a negative length' => ['-2em', ['length' => '-2em', 'hanging' => false, 'each-line' => false]],
			'a percentage' => ['10%', ['length' => '10%', 'hanging' => false, 'each-line' => false]],
			'hanging' => ['10mm hanging', ['length' => '10mm', 'hanging' => true, 'each-line' => false]],
			'each-line' => ['10mm each-line', ['length' => '10mm', 'hanging' => false, 'each-line' => true]],
			'both, keywords first' => ['each-line  hanging 0', ['length' => '0', 'hanging' => true, 'each-line' => true]],
			'upper case' => ['5PT HANGING', ['length' => '5PT', 'hanging' => true, 'each-line' => false]],
			'no length' => ['hanging', null],
			'two lengths' => ['10mm 5mm', null],
			'a keyword twice' => ['10mm hanging hanging', null],
			'an unknown keyword' => ['10mm inherit', null],
			'not a length' => ['5foo hanging', null],
			'empty' => ['', null],
		];
	}
}
