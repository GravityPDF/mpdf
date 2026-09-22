<?php

namespace Mpdf\Ua;

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * The structure type an HTML tag or an mPDF CSS class is tagged as.
 *
 * @group pdfua
 */
class StructTypeTest extends TestCase
{

	/**
	 * Common block and inline tags map to their structure types.
	 */
	public function testFromHtmlTagReturnsCorrectType()
	{
		$this->assertSame('P', StructType::fromHtmlTag('P'));
		$this->assertSame('H1', StructType::fromHtmlTag('H1'));
		$this->assertSame('L', StructType::fromHtmlTag('UL'));
		$this->assertSame('L', StructType::fromHtmlTag('OL'));
		$this->assertSame('Table', StructType::fromHtmlTag('TABLE'));
		$this->assertSame('Figure', StructType::fromHtmlTag('IMG'));
		$this->assertSame('Span', StructType::fromHtmlTag('STRONG'));
	}

	/**
	 * A tag with no structure type maps to null, and opens no element.
	 */
	public function testFromHtmlTagUnknownReturnsNull()
	{
		$this->assertNull(StructType::fromHtmlTag('UNKNOWN'));
		$this->assertNull(StructType::fromHtmlTag('HR'));
		$this->assertNull(StructType::fromHtmlTag('BR'));
	}

	/**
	 * A role that names a standard structure type overrides the tag's own type.
	 */
	public function testFromHtmlTagRoleOverride()
	{
		$this->assertSame('Sect', StructType::fromHtmlTag('DIV', ['ROLE' => 'Sect']));
		$this->assertSame('Art', StructType::fromHtmlTag('DIV', ['ROLE' => 'Art']));
	}

	/**
	 * A role that names no standard structure type is ignored.
	 */
	public function testFromHtmlTagRoleInvalidIgnored()
	{
		$this->assertSame('Div', StructType::fromHtmlTag('DIV', ['ROLE' => 'BadType']));
		$this->assertSame('Div', StructType::fromHtmlTag('DIV', ['ROLE' => 'presentation']));
	}

	/**
	 * A definition list is a list whose terms are labels and whose definitions are bodies.
	 */
	public function testFromHtmlTagDl()
	{
		$this->assertSame('L', StructType::fromHtmlTag('DL'));
		$this->assertSame('Lbl', StructType::fromHtmlTag('DT'));
		$this->assertSame('LBody', StructType::fromHtmlTag('DD'));
	}

	/**
	 * A <figcaption> is a Caption.
	 */
	public function testFromHtmlTagFigcaption()
	{
		$this->assertSame('Caption', StructType::fromHtmlTag('FIGCAPTION'));
	}

	/**
	 * Tag name lookup is case-insensitive.
	 */
	public function testFromHtmlTagCaseInsensitive()
	{
		$this->assertSame('P', StructType::fromHtmlTag('p'));
		$this->assertSame('H1', StructType::fromHtmlTag('h1'));
		$this->assertSame('Table', StructType::fromHtmlTag('table'));
	}

	/**
	 * An <a> is a Link; the tag opens one only when it has an href.
	 */
	public function testFromHtmlTagAnchor()
	{
		$this->assertSame('Link', StructType::fromHtmlTag('A'));
	}

	/**
	 * A fieldset is a Sect captioned by its legend, and a form is a Div, as Form is kept for
	 * the individual fields.
	 */
	public function testFromHtmlTagFormGrouping()
	{
		$this->assertSame('Sect', StructType::fromHtmlTag('FIELDSET'));
		$this->assertSame('Caption', StructType::fromHtmlTag('LEGEND'));
		$this->assertSame('Div', StructType::fromHtmlTag('FORM'));
	}

	/**
	 * The table of contents container is a TOC.
	 */
	public function testFromCssClassToc()
	{
		$this->assertSame('TOC', StructType::fromCssClass('mpdf_toc'));
	}

	/**
	 * An entry at any level of the table of contents is a TOCI.
	 */
	public function testFromCssClassTociLevel()
	{
		$this->assertSame('TOCI', StructType::fromCssClass('mpdf_toc_level_2'));
		$this->assertSame('TOCI', StructType::fromCssClass('mpdf_toc_level_0'));
	}

	/**
	 * A table of contents link is a Link, not a Reference, as it has a link annotation behind it.
	 */
	public function testFromCssClassTocA()
	{
		$this->assertSame('Link', StructType::fromCssClass('mpdf_toc_a'));
	}

	/**
	 * A table of contents page number at any level is a Lbl.
	 */
	public function testFromCssClassTocPLevel()
	{
		$this->assertSame('Lbl', StructType::fromCssClass('mpdf_toc_p_level_0'));
		$this->assertSame('Lbl', StructType::fromCssClass('mpdf_toc_p_level_3'));
	}

	/**
	 * Any other class maps to null.
	 */
	public function testFromCssClassUnknownReturnsNull()
	{
		$this->assertNull(StructType::fromCssClass('mpdf_other'));
		$this->assertNull(StructType::fromCssClass('some-random-class'));
		$this->assertNull(StructType::fromCssClass(''));
	}

	/**
	 * Standard structure types are valid.
	 */
	public function testIsValidKnownType()
	{
		$this->assertTrue(StructType::isValid('P'));
		$this->assertTrue(StructType::isValid('Table'));
		$this->assertTrue(StructType::isValid('Link'));
		$this->assertTrue(StructType::isValid('Document'));
		$this->assertTrue(StructType::isValid('Figure'));
		$this->assertTrue(StructType::isValid('H6'));
		$this->assertTrue(StructType::isValid('TOCI'));
	}

	/**
	 * Non-standard names are not valid, and case matters.
	 */
	public function testIsValidUnknownType()
	{
		$this->assertFalse(StructType::isValid('BadType'));
		$this->assertFalse(StructType::isValid('p'));
		$this->assertFalse(StructType::isValid('PARAGRAPH'));
	}

	/**
	 * The grouping types of ISO 32000-1 Table 333 are grouping.
	 */
	public function testIsGroupingForGroupingTypes()
	{
		$this->assertTrue(StructType::isGrouping('TOC'));
		$this->assertTrue(StructType::isGrouping('L'));
		$this->assertTrue(StructType::isGrouping('Table'));
		$this->assertTrue(StructType::isGrouping('Document'));
		$this->assertTrue(StructType::isGrouping('Div'));
		$this->assertTrue(StructType::isGrouping('Sect'));
	}

	/**
	 * Block-level and inline types are not grouping.
	 */
	public function testIsGroupingForLeafTypes()
	{
		$this->assertFalse(StructType::isGrouping('P'));
		$this->assertFalse(StructType::isGrouping('TD'));
		$this->assertFalse(StructType::isGrouping('Span'));
		$this->assertFalse(StructType::isGrouping('H1'));
		$this->assertFalse(StructType::isGrouping('Link'));
	}

	/**
	 * Ruby tags map to the ruby structure types; <rtc>, which has none, is a Span.
	 */
	public function testFromHtmlTagRuby()
	{
		$this->assertSame('Ruby', StructType::fromHtmlTag('RUBY'));
		$this->assertSame('RB', StructType::fromHtmlTag('RB'));
		$this->assertSame('RT', StructType::fromHtmlTag('RT'));
		$this->assertSame('RP', StructType::fromHtmlTag('RP'));
		$this->assertSame('Span', StructType::fromHtmlTag('RTC'));
	}

	/**
	 * The ruby and warichu structure types are valid, so a role can name them.
	 */
	public function testIsValidRubyAndWarichuTypes()
	{
		$this->assertTrue(StructType::isValid('Ruby'));
		$this->assertTrue(StructType::isValid('RB'));
		$this->assertTrue(StructType::isValid('RT'));
		$this->assertTrue(StructType::isValid('RP'));
		$this->assertTrue(StructType::isValid('Warichu'));
		$this->assertTrue(StructType::isValid('WT'));
		$this->assertTrue(StructType::isValid('WP'));
	}
}
