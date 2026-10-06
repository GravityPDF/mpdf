<?php

namespace Mpdf\Css;

use Mpdf\Mpdf;
use Mpdf\SizeConverter;
use Psr\Log\NullLogger;

class TextSpacingTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * @var Mpdf
	 */
	private $mpdf;

	/**
	 * @var SizeConverter
	 */
	private $sizeConverter;

	/**
	 * Builds a document at a 10mm font size, so that an em is 10mm
	 */
	protected function set_up()
	{
		parent::set_up();
		$this->mpdf = new Mpdf(['mode' => 'c']);
		$this->mpdf->FontSize = 10;
		$this->sizeConverter = new SizeConverter(96, 11, $this->mpdf, new NullLogger());
	}

	/**
	 * A spacing is the length it comes to at the font size, and normal or none is what stands for none
	 */
	public function testLength()
	{
		$this->assertSame(10.0, TextSpacing::length($this->sizeConverter, '1em', 10, false));
		$this->assertSame(2.0, TextSpacing::length($this->sizeConverter, '2mm', 10, 0));
		$this->assertSame(0.0, TextSpacing::length($this->sizeConverter, '0', 10, false));
		$this->assertFalse(TextSpacing::length($this->sizeConverter, 'normal', 10, false));
		$this->assertFalse(TextSpacing::length($this->sizeConverter, '', 10, false));
		$this->assertSame(0, TextSpacing::length($this->sizeConverter, 'NORMAL', 10, 0));
	}

	/**
	 * The text state keeps each spacing as given and as its length at the current font size
	 */
	public function testSet()
	{
		TextSpacing::set($this->mpdf, $this->sizeConverter, '0.5em', '2mm');

		$this->assertSame('0.5em', $this->mpdf->lSpacingCSS);
		$this->assertSame('2mm', $this->mpdf->wSpacingCSS);
		$this->assertSame(5.0, $this->mpdf->fixedlSpacing);
		$this->assertSame(2.0, $this->mpdf->minwSpacing);

		TextSpacing::set($this->mpdf, $this->sizeConverter, 'normal', '');

		$this->assertFalse($this->mpdf->fixedlSpacing);
		$this->assertSame(0, $this->mpdf->minwSpacing);
	}
}
