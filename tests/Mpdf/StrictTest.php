<?php

namespace Mpdf;

/**
 * The Strict trait turns a mistyped method or property name into an MpdfException, which is what its
 * docblocks say each magic method throws. Mpdf is the class most code touches, so it stands in for
 * every class that uses the trait.
 */
class StrictTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * @dataProvider mistakes
	 *
	 * @param callable $mistake Given an Mpdf, uses a name the class does not declare
	 * @param string   $message What the exception says
	 */
	public function testAnUndeclaredNameThrowsAnMpdfException($mistake, $message)
	{
		$this->expectException(MpdfException::class);
		$this->expectExceptionMessage($message);

		call_user_func($mistake, new Mpdf());
	}

	/**
	 * @return array[] Each magic method the trait defines: [a use of an undeclared name, the message]
	 */
	public function mistakes()
	{
		return [
			'__call' => [
				function (Mpdf $mpdf) {
					$mpdf->noSuchMethod();
				},
				'Call to undefined method Mpdf\Mpdf::noSuchMethod()',
			],
			'__callStatic' => [
				function () {
					Mpdf::noSuchFunction();
				},
				'Call to undefined static function Mpdf\Mpdf::noSuchFunction()',
			],
			'__get' => [
				function (Mpdf $mpdf) {
					return $mpdf->noSuchProperty;
				},
				'Cannot read an undeclared property Mpdf\Mpdf::$noSuchProperty',
			],
			'__set' => [
				function (Mpdf $mpdf) {
					$mpdf->noSuchProperty = 1;
				},
				'Cannot write to an undeclared property Mpdf\Mpdf::$noSuchProperty',
			],
			'__isset' => [
				function (Mpdf $mpdf) {
					return isset($mpdf->noSuchProperty);
				},
				'Cannot read an undeclared property Mpdf\Mpdf::$noSuchProperty',
			],
			'__unset' => [
				function (Mpdf $mpdf) {
					unset($mpdf->noSuchProperty);
				},
				'Cannot unset the property Mpdf\Mpdf::$noSuchProperty.',
			],
		];
	}

}
