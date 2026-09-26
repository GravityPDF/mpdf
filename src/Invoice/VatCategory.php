<?php

namespace Mpdf\Invoice;

use Mpdf\MpdfException;

/**
 * The VAT categories EN 16931 knows (UNTDID 5305 codes), and what each one's rules turn on
 *
 * @see https://github.com/ConnectingEurope/eInvoicing-EN16931 The EN 16931 validation artefacts
 */
class VatCategory
{

	/**
	 * Standard rated, at a rate above 0
	 */
	const STANDARD = 'S';

	/**
	 * Zero rated: taxed, at a rate of 0
	 */
	const ZERO_RATED = 'Z';

	/**
	 * Exempt from VAT
	 */
	const EXEMPT = 'E';

	/**
	 * VAT reverse charge, which the buyer accounts for
	 */
	const REVERSE_CHARGE = 'AE';

	/**
	 * An intra-community supply of goods or services within the EU, VAT exempt
	 */
	const INTRA_COMMUNITY = 'K';

	/**
	 * An export outside the EU, free of VAT
	 */
	const EXPORT = 'G';

	/**
	 * A supply outside the scope of VAT, which has no rate
	 */
	const NOT_SUBJECT_TO_VAT = 'O';

	/**
	 * The Canary Islands general indirect tax (IGIC)
	 */
	const CANARY_ISLANDS = 'L';

	/**
	 * The tax for production, services and importation in Ceuta and Melilla (IPSI)
	 */
	const CEUTA_MELILLA = 'M';

	/**
	 * Every category EN 16931 knows
	 *
	 * @var string[]
	 */
	private static $categories = [self::STANDARD, self::ZERO_RATED, self::EXEMPT, self::REVERSE_CHARGE, self::INTRA_COMMUNITY, self::EXPORT, self::NOT_SUBJECT_TO_VAT, self::CANARY_ISLANDS, self::CEUTA_MELILLA];

	/**
	 * The name EN 16931's rules give each category's group of rules, e.g. BR-IC-10 for K, by category
	 *
	 * @var string[]
	 */
	private static $ruleCodes = [
		self::STANDARD => 'S',
		self::ZERO_RATED => 'Z',
		self::EXEMPT => 'E',
		self::REVERSE_CHARGE => 'AE',
		self::INTRA_COMMUNITY => 'IC',
		self::EXPORT => 'G',
		self::NOT_SUBJECT_TO_VAT => 'O',
		self::CANARY_ISLANDS => 'AF',
		self::CEUTA_MELILLA => 'AG',
	];

	/**
	 * The categories that charge no VAT, whose rate EN 16931 requires to be 0
	 *
	 * @var string[]
	 */
	private static $untaxed = [self::ZERO_RATED, self::EXEMPT, self::REVERSE_CHARGE, self::INTRA_COMMUNITY, self::EXPORT, self::NOT_SUBJECT_TO_VAT];

	/**
	 * The categories whose seller needs a VAT identifier or tax registration (BR-S-2, BR-Z-2, BR-E-2, BR-AF-2, BR-AG-2)
	 *
	 * @var string[]
	 */
	private static $sellerTaxed = [self::STANDARD, self::ZERO_RATED, self::EXEMPT, self::CANARY_ISLANDS, self::CEUTA_MELILLA];

	/**
	 * Its methods are static; there is nothing to make
	 */
	private function __construct()
	{
	}

	/**
	 * Refuse a category EN 16931 does not know, or a rate its category cannot have: standard rated (S) above 0, the
	 * categories that charge no VAT at 0
	 *
	 * @param string $category
	 * @param float $rate
	 *
	 * @throws \Mpdf\MpdfException
	 */
	public static function check($category, $rate)
	{
		if (!in_array($category, self::$categories, true)) {
			throw new MpdfException(sprintf('VAT category "%s" is not one of %s', $category, implode(', ', self::$categories)));
		}

		if ($category === self::STANDARD && $rate <= 0) {
			throw new MpdfException('A standard rated (S) VAT category needs a rate above 0; use Z for zero rated');
		}

		if (in_array($category, self::$untaxed, true) && $rate != 0) {
			throw new MpdfException(sprintf('VAT category %s charges no VAT, so its rate is 0', $category));
		}
	}

	/**
	 * Whether the category charges no VAT for a reason the invoice must give (BR-E-10, BR-AE-10…): all but Z of those at 0
	 *
	 * @param string $category
	 *
	 * @return bool
	 */
	public static function isExempt($category)
	{
		return $category !== self::ZERO_RATED && in_array($category, self::$untaxed, true);
	}

	/**
	 * Whether a line, allowance or charge in the category gives its rate: all but O, which has none (BR-O-05 to BR-O-07)
	 *
	 * Plain EN 16931 leaves O's rate out of the VAT breakdown too (BR-48), which a CIUS may change.
	 *
	 * @param string $category
	 *
	 * @return bool
	 */
	public static function hasRate($category)
	{
		return $category !== self::NOT_SUBJECT_TO_VAT;
	}

	/**
	 * Whether a seller charging under the category needs a VAT identifier or tax registration
	 *
	 * @param string $category
	 *
	 * @return bool
	 */
	public static function needsSellerTaxRegistration($category)
	{
		return in_array($category, self::$sellerTaxed, true);
	}

	/**
	 * The name EN 16931's rules give the category's group of rules, e.g. IC for K, as in BR-IC-10
	 *
	 * @param string $category
	 *
	 * @return string
	 */
	public static function getRuleCode($category)
	{
		return self::$ruleCodes[$category];
	}

}
