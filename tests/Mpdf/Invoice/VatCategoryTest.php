<?php

namespace Mpdf\Invoice;

use Mpdf\MpdfException;

class VatCategoryTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * Each VAT category and what its rules turn on
	 *
	 * @return mixed[]
	 */
	public function categoryProvider()
	{
		return [
			'standard rate' => [VatCategory::STANDARD, false, true, true, 'S'],
			'zero rated, taxed at 0' => [VatCategory::ZERO_RATED, false, true, true, 'Z'],
			'exempt' => [VatCategory::EXEMPT, true, true, true, 'E'],
			'reverse charge' => [VatCategory::REVERSE_CHARGE, true, true, false, 'AE'],
			'intra-community supply' => [VatCategory::INTRA_COMMUNITY, true, true, false, 'IC'],
			'export' => [VatCategory::EXPORT, true, true, false, 'G'],
			'not subject to VAT' => [VatCategory::NOT_SUBJECT_TO_VAT, true, false, false, 'O'],
			'Canary Islands' => [VatCategory::CANARY_ISLANDS, false, true, true, 'AF'],
			'Ceuta and Melilla' => [VatCategory::CEUTA_MELILLA, false, true, true, 'AG'],
		];
	}

	/**
	 * What each category's rules turn on
	 *
	 * @dataProvider categoryProvider
	 *
	 * @param string $category
	 * @param bool $exempt
	 * @param bool $hasRate
	 * @param bool $sellerTaxed Whether the seller needs a VAT identifier or tax registration
	 * @param string $ruleCode
	 */
	public function testKnowsWhatEachCategoryNeeds($category, $exempt, $hasRate, $sellerTaxed, $ruleCode)
	{
		$this->assertSame($exempt, VatCategory::isExempt($category));
		$this->assertSame($hasRate, VatCategory::hasRate($category));
		$this->assertSame($sellerTaxed, VatCategory::needsSellerTaxRegistration($category));
		$this->assertSame($ruleCode, VatCategory::getRuleCode($category));
	}

	/**
	 * A category and rate that cannot go together, and the reason given
	 *
	 * @return string[][]
	 */
	public function refusedProvider()
	{
		return [
			'unknown category' => ['X', 20, 'VAT category "X" is not one of S, Z, E, AE, K, G, O, L, M'],
			'standard rate of 0' => ['S', 0, 'A standard rated (S) VAT category needs a rate above 0; use Z for zero rated'],
			'exempt with a rate' => ['E', 20, 'VAT category E charges no VAT, so its rate is 0'],
		];
	}

	/**
	 * A category and rate that cannot go together are refused
	 *
	 * @dataProvider refusedProvider
	 *
	 * @param string $category
	 * @param float $rate
	 * @param string $message
	 */
	public function testRefusesARateItsCategoryCannotHave($category, $rate, $message)
	{
		$this->expectException(MpdfException::class);
		$this->expectExceptionMessage($message);

		VatCategory::check($category, $rate);
	}

	/**
	 * Canary Islands and Ceuta and Melilla taxes have rates of their own, so take any
	 */
	public function testTakesAnyRateForTheSpanishTerritories()
	{
		VatCategory::check(VatCategory::CANARY_ISLANDS, 7);
		VatCategory::check(VatCategory::CEUTA_MELILLA, 0);

		$this->addToAssertionCount(2);
	}

}
