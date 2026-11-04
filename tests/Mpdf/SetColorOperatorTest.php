<?php

namespace Mpdf;

use Mpdf\Color\ColorConverter;
use Mpdf\Color\ColorModeConverter;
use Mpdf\Color\ColorSpaceRestrictor;

/**
 * SetColor() keeps the operator it builds for a colour, so that the next time the colour is set the
 * operator is not built again. What it keeps is told apart by the type of operator asked for.
 */
class SetColorOperatorTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * @var Mpdf
	 */
	private $mpdf;

	/**
	 * @var ColorConverter
	 */
	private $colorConverter;

	/**
	 * A document the colours are set in, and a converter for the colours set in it.
	 */
	protected function set_up()
	{
		parent::set_up();

		$this->mpdf = new Mpdf(['mode' => 'utf-8']);
		$colorModeConverter = new ColorModeConverter();
		$this->colorConverter = new ColorConverter($this->mpdf, $colorModeConverter, new ColorSpaceRestrictor($this->mpdf, $colorModeConverter));
	}

	/**
	 * Closes the document; ReleaseFinishedTests lets go of the properties.
	 */
	protected function tear_down()
	{
		$this->mpdf->cleanup();

		parent::tear_down();
	}

	/**
	 * The same colour set for filling, stroking, text and as bare components gives each its own operator,
	 * whichever was asked for first and however often.
	 */
	public function testEachTypeOfOperatorIsKeptApart()
	{
		$red = $this->colorConverter->convert('#ff0000');
		$expected = [
			'Fill' => '1.000 0.000 0.000 rg',
			'Draw' => '1.000 0.000 0.000 RG',
			'Text' => '1.000 0.000 0.000 rg',
			'CodeOnly' => '1.000 0.000 0.000',
		];

		foreach ([1, 2] as $round) {
			foreach ($expected as $type => $operator) {
				$this->assertSame($operator, $this->mpdf->SetColor($red, $type), $type . ', round ' . $round);
			}
		}
	}

	/**
	 * Two colours that differ in one component, and the same components in two colour spaces, each keep their
	 * own operator.
	 */
	public function testEachColourIsKeptApart()
	{
		$colours = [
			'#ff0000' => '1.000 0.000 0.000 rg',
			'#ff0001' => '1.000 0.000 0.004 rg',
			'#808080' => '0.502 0.502 0.502 rg',
			'cmyk(0, 100, 100, 0)' => '0.000 1.000 1.000 0.000 k',
			'0' => '0.000 g',
		];

		foreach ([1, 2] as $round) {
			foreach ($colours as $css => $operator) {
				$colour = $this->colorConverter->convert($css);
				$this->assertSame($operator, $this->mpdf->SetColor($colour, 'Fill'), $css . ', round ' . $round);
			}
		}
	}

	/**
	 * A spot colour keeps an operator for each type and each tint, naming the colour space it was given.
	 */
	public function testEachSpotColourAndTintIsKeptApart()
	{
		$expected = [
			['Fill', "2\x01\x32\x00\x00\x00", '/CS1 cs 0.500 scn'],
			['Draw', "2\x01\x32\x00\x00\x00", '/CS1 CS 0.500 SCN'],
			['Fill', "2\x01\x64\x00\x00\x00", '/CS1 cs 1.000 scn'],
			['Fill', "2\x02\x32\x00\x00\x00", '/CS2 cs 0.500 scn'],
		];

		foreach ([1, 2] as $round) {
			foreach ($expected as $i => $case) {
				$this->assertSame($case[2], $this->mpdf->SetColor($case[1], $case[0]), $i . ', round ' . $round);
			}
		}
	}

}
