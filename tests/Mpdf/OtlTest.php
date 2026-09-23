<?php

namespace Mpdf;

use Mpdf\Fonts\FontCache;
use Mpdf\Shaper\OtlData;

class OtlTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * @var \Mpdf\Otl
	 */
	private $otl;

	/**
	 * @var \Mpdf\Mpdf
	 */
	private $mpdf;

	protected function set_up()
	{
		parent::set_up();

		$this->mpdf = new Mpdf(['mode' => 'c']);
		$this->otl = new Otl($this->mpdf, new FontCache(new Cache(sys_get_temp_dir() . '/mpdf-otl-test')));
	}

	protected function tear_down()
	{
		parent::tear_down();

		$this->mpdf->cleanup();
	}

	public function testSliceOfPopulatedData()
	{
		$OTLdata = [
			'group' => 'SCCSC',
			'GPOSinfo' => [1 => ['GPOSinfo'], 3 => ['other']],
			'char_data' => [['bidi_class' => 0], ['bidi_class' => 1], ['bidi_class' => 2], ['bidi_class' => 3], ['bidi_class' => 4]],
		];

		$slice = $this->otl->sliceOTLdata($OTLdata, 1, 3);

		$this->assertSame('CCS', $slice['group']);
		$this->assertSame([0 => ['GPOSinfo'], 2 => ['other']], $slice['GPOSinfo']);
		$this->assertCount(3, $slice['char_data']);
	}

	/**
	 * applyOTL() resets OTLdata to an empty array for a blank string, and MultiCell() slices
	 * whatever it is handed. See mpdf/mpdf#2158.
	 */
	public function testSliceOfEmptyDataReturnsAnEmptyStructure()
	{
		$slice = $this->otl->sliceOTLdata([], 0, 0);

		$this->assertSame('', $slice['group']);
		$this->assertSame([], $slice['GPOSinfo']);
		$this->assertSame([], $slice['char_data']);
	}

	/**
	 * The six were public on Otl, which Mpdf exposes as $otl, and stay there as delegates.
	 */
	public function testTheDeprecatedRunMethodsGiveWhatOtlDataGives()
	{
		$run = [
			'group' => 'SCCS',
			'GPOSinfo' => [1 => ['XAdvance' => 10], 3 => ['XAdvance' => 20]],
			'char_data' => [['uni' => 0x20], ['uni' => 0x1F600], ['uni' => 0xAD], ['uni' => 0xA0]],
		];
		$text = " \xf0\x9f\x98\x80\xc2\xad\xc2\xa0";
		$this->mpdf->mb_enc = 'UTF-8';

		$expected = $run;
		$actual = $run;
		$this->assertSame(OtlData::split($expected, 2, 3), $this->otl->splitOTLdata($actual, 2, 3));
		$this->assertSame($expected, $actual);

		$this->assertSame(OtlData::slice($run, 1, 2), $this->otl->sliceOTLdata($run, 1, 2));

		$expected = $run;
		$actual = $run;
		OtlData::prependChar($expected, ['uni' => 0x2D], 'C');
		$this->otl->prependOTLchar($actual, ['uni' => 0x2D], 'C');
		$this->assertSame($expected, $actual);

		$expected = [$text, $run];
		$actual = [$text, $run];
		OtlData::removeChar($expected[0], $expected[1], "\xc2\xad", 'UTF-8');
		$this->otl->removeChar($actual[0], $actual[1], "\xc2\xad");
		$this->assertSame($expected, $actual);

		$expected = [$text, $run];
		$actual = [$text, $run];
		OtlData::nbspToSpace($expected[0], $expected[1], 'UTF-8');
		$this->otl->replaceSpace($actual[0], $actual[1]);
		$this->assertSame($expected, $actual);

		$expected = $run;
		$actual = $run;
		OtlData::trim($expected, false, true);
		$this->otl->trimOTLdata($actual, false, true);
		$this->assertSame($expected, $actual);
	}

}
