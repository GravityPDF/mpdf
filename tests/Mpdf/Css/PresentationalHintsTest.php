<?php

namespace Mpdf\Css;

use Mpdf\Color\ColorConverter;
use Mpdf\Color\ColorModeConverter;
use Mpdf\Color\ColorSpaceRestrictor;
use Mpdf\Mpdf;
use Mpdf\SizeConverter;
use Psr\Log\NullLogger;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * The presentational attributes the standard cascade reads, each on the elements HTML gives it to and on no other
 */
class PresentationalHintsTest extends TestCase
{

	/**
	 * @var \Mpdf\Css\PresentationalHints
	 */
	private $hints;

	/**
	 * Builds the hints over a document's own normaliser
	 */
	protected function set_up()
	{
		parent::set_up();

		$mpdf = new Mpdf();
		$colorModeConverter = new ColorModeConverter();
		$colorConverter = new ColorConverter($mpdf, $colorModeConverter, new ColorSpaceRestrictor($mpdf, $colorModeConverter));
		$normalizeProperties = new NormalizeProperties($mpdf, new SizeConverter(96, 11, $mpdf, new NullLogger()), $colorConverter);

		$this->hints = new PresentationalHints($mpdf, $normalizeProperties);
	}

	/**
	 * An attribute sets its property on an element HTML gives it to, and nothing on one it does not
	 *
	 * @dataProvider elements
	 *
	 * @param string $tag
	 * @param array $attr
	 * @param array $expected
	 */
	public function testReadsEachAttributeOnTheElementsHtmlGivesItTo($tag, array $attr, array $expected)
	{
		$this->assertEquals($expected, $this->hints->of($tag, $attr));
	}

	/**
	 * Each attribute on an element it is a hint on, and on elements it is not
	 *
	 * @return array[] Each [tag, attributes, the properties they set]
	 */
	public function elements()
	{
		$black = function ($width) {
			$border = [];
			foreach (['TOP', 'RIGHT', 'BOTTOM', 'LEFT'] as $side) {
				$border['BORDER-' . $side] = $width . ' solid #000000';
				$border['BORDER-' . $side . '-WIDTH'] = $width;
				$border['BORDER-' . $side . '-STYLE'] = 'solid';
				$border['BORDER-' . $side . '-COLOR'] = '#000000';
			}

			return $border;
		};

		return [
			'dir on any element' => ['SPAN', ['DIR' => 'rtl'], ['DIRECTION' => 'rtl']],
			'lang on any element' => ['P', ['LANG' => 'fr'], ['LANG' => 'fr']],

			'color on font' => ['FONT', ['COLOR' => '#f00'], ['COLOR' => '#f00']],
			'color on hr' => ['HR', ['COLOR' => '#f00'], ['COLOR' => '#f00']],
			'color on a' => ['A', ['COLOR' => '#f00'], []],
			'color on span' => ['SPAN', ['COLOR' => '#f00'], []],
			'color on p' => ['P', ['COLOR' => '#f00'], []],
			'color on td' => ['TD', ['COLOR' => '#f00'], []],
			'color on input' => ['INPUT', ['COLOR' => '#f00'], []],

			'face and size on font' => ['FONT', ['FACE' => 'serif', 'SIZE' => '5'], ['FONT-FAMILY' => 'serif', 'FONT-SIZE' => 'LARGE']],
			'a size a step larger on font' => ['FONT', ['SIZE' => '+1'], ['FONT-SIZE' => '120%']],
			'a size mPDF has no size for on font' => ['FONT', ['SIZE' => '9'], []],
			'face on span' => ['SPAN', ['FACE' => 'serif'], []],

			'width on img' => ['IMG', ['WIDTH' => '20'], ['WIDTH' => '20']],
			'width on table' => ['TABLE', ['WIDTH' => '50%'], ['WIDTH' => '50%']],
			'width on td' => ['TD', ['WIDTH' => '30mm'], ['WIDTH' => '30mm']],
			'width on th' => ['TH', ['WIDTH' => '30mm'], ['WIDTH' => '30mm']],
			'width on hr' => ['HR', ['WIDTH' => '50%'], ['WIDTH' => '50%']],
			'width on meter' => ['METER', ['WIDTH' => '40mm'], ['WIDTH' => '40mm']],
			'width on progress' => ['PROGRESS', ['WIDTH' => '40mm'], ['WIDTH' => '40mm']],
			'width on div' => ['DIV', ['WIDTH' => '50mm'], []],
			'width on p' => ['P', ['WIDTH' => '50mm'], []],
			'width on textarea' => ['TEXTAREA', ['WIDTH' => '50mm'], []],
			'width on input' => ['INPUT', ['WIDTH' => '50mm'], []],
			'width of zero on table' => ['TABLE', ['WIDTH' => '0'], []],

			'height on img' => ['IMG', ['HEIGHT' => '20'], ['HEIGHT' => '20']],
			'height on td' => ['TD', ['HEIGHT' => '30mm'], ['HEIGHT' => '30mm']],
			'height on tr' => ['TR', ['HEIGHT' => '30mm'], ['HEIGHT' => '30mm']],
			'height on thead' => ['THEAD', ['HEIGHT' => '30mm'], ['HEIGHT' => '30mm']],
			'height on div' => ['DIV', ['HEIGHT' => '30mm'], []],
			'height on hr' => ['HR', ['HEIGHT' => '3mm'], []],

			'valign on td' => ['TD', ['VALIGN' => 'bottom'], ['VERTICAL-ALIGN' => 'bottom']],
			'valign on th' => ['TH', ['VALIGN' => 'top'], ['VERTICAL-ALIGN' => 'top']],
			'valign on tr' => ['TR', ['VALIGN' => 'top'], ['VERTICAL-ALIGN' => 'top']],
			'valign on thead' => ['THEAD', ['VALIGN' => 'top'], ['VERTICAL-ALIGN' => 'top']],
			'valign on tbody' => ['TBODY', ['VALIGN' => 'top'], ['VERTICAL-ALIGN' => 'top']],
			'valign on tfoot' => ['TFOOT', ['VALIGN' => 'top'], ['VERTICAL-ALIGN' => 'top']],
			'valign on table' => ['TABLE', ['VALIGN' => 'top'], []],
			'valign on img' => ['IMG', ['VALIGN' => 'top'], []],
			'valign on span' => ['SPAN', ['VALIGN' => 'top'], []],

			'vspace and hspace on img' => ['IMG', ['VSPACE' => '10', 'HSPACE' => '5'], ['MARGIN-TOP' => '10', 'MARGIN-BOTTOM' => '10', 'MARGIN-LEFT' => '5', 'MARGIN-RIGHT' => '5']],
			'vspace on an image button' => ['INPUT', ['TYPE' => 'image', 'VSPACE' => '10'], ['MARGIN-TOP' => '10', 'MARGIN-BOTTOM' => '10']],
			'vspace on a text field' => ['INPUT', ['TYPE' => 'text', 'VSPACE' => '10'], []],
			'hspace on div' => ['DIV', ['HSPACE' => '10'], []],

			'align on div' => ['DIV', ['ALIGN' => 'center'], ['TEXT-ALIGN' => 'center']],
			'align middle on div' => ['DIV', ['ALIGN' => 'Middle'], ['TEXT-ALIGN' => 'center']],
			'align on p' => ['P', ['ALIGN' => 'right'], ['TEXT-ALIGN' => 'right']],
			'align middle on p' => ['P', ['ALIGN' => 'middle'], []],
			'align on h1' => ['H1', ['ALIGN' => 'justify'], ['TEXT-ALIGN' => 'justify']],
			'align on h6' => ['H6', ['ALIGN' => 'left'], ['TEXT-ALIGN' => 'left']],
			'align on td' => ['TD', ['ALIGN' => 'right'], ['TEXT-ALIGN' => 'right']],
			'align absmiddle on th' => ['TH', ['ALIGN' => 'absmiddle'], ['TEXT-ALIGN' => 'center']],
			'align on tr' => ['TR', ['ALIGN' => 'center'], ['TEXT-ALIGN' => 'center']],
			'align on thead' => ['THEAD', ['ALIGN' => 'right'], ['TEXT-ALIGN' => 'right']],
			'align char on td' => ['TD', ['ALIGN' => 'char'], ['TEXT-ALIGN' => 'DPR']],
			'align char on a comma' => ['TD', ['ALIGN' => 'char', 'CHAR' => ','], ['TEXT-ALIGN' => 'DCR']],
			'align char on a character mPDF cannot align on' => ['TD', ['ALIGN' => 'char', 'CHAR' => 'x'], []],
			'align char on tr' => ['TR', ['ALIGN' => 'char'], []],
			'align on blockquote' => ['BLOCKQUOTE', ['ALIGN' => 'center'], []],
			'align on span' => ['SPAN', ['ALIGN' => 'center'], []],
			'align on ul' => ['UL', ['ALIGN' => 'center'], []],
			'align bottom on caption' => ['CAPTION', ['ALIGN' => 'bottom'], ['CAPTION-SIDE' => 'bottom']],
			'align left on caption' => ['CAPTION', ['ALIGN' => 'left'], []],
			'align left on hr' => ['HR', ['ALIGN' => 'left'], ['MARGIN-LEFT' => '0', 'MARGIN-RIGHT' => 'auto']],
			'align right on hr' => ['HR', ['ALIGN' => 'right'], ['MARGIN-LEFT' => 'auto', 'MARGIN-RIGHT' => '0']],
			'align center on hr' => ['HR', ['ALIGN' => 'center'], ['MARGIN-LEFT' => 'auto', 'MARGIN-RIGHT' => 'auto']],
			'align left on img' => ['IMG', ['ALIGN' => 'left'], ['FLOAT' => 'left']],
			'align right on img' => ['IMG', ['ALIGN' => 'RIGHT'], ['FLOAT' => 'right']],
			'align absmiddle on img' => ['IMG', ['ALIGN' => 'absmiddle'], ['VERTICAL-ALIGN' => 'middle']],
			'align texttop on img' => ['IMG', ['ALIGN' => 'texttop'], ['VERTICAL-ALIGN' => 'text-top']],
			'align bottom on img' => ['IMG', ['ALIGN' => 'bottom'], ['VERTICAL-ALIGN' => 'bottom']],
			'align justify on img' => ['IMG', ['ALIGN' => 'justify'], []],
			'align on table, read where the table is built' => ['TABLE', ['ALIGN' => 'center'], []],

			'nowrap on td' => ['TD', ['NOWRAP' => 'nowrap'], ['WHITE-SPACE' => 'nowrap']],
			'nowrap on th' => ['TH', ['NOWRAP' => 'nowrap'], ['WHITE-SPACE' => 'nowrap']],
			'nowrap on div' => ['DIV', ['NOWRAP' => 'nowrap'], []],

			'border on table' => ['TABLE', ['BORDER' => '1'], $black('1px')],
			'a wider border on table' => ['TABLE', ['BORDER' => '3'], $black('3px')],
			'a border that is not a number on table' => ['TABLE', ['BORDER' => 'yes'], $black('1px')],
			'no border on table' => ['TABLE', ['BORDER' => '0'], []],
			'border on img' => ['IMG', ['BORDER' => '2'], $black('2px')],
			'no border on img' => ['IMG', ['BORDER' => '0'], []],
			'a border that is not a number on img' => ['IMG', ['BORDER' => 'yes'], []],
			'border on td' => ['TD', ['BORDER' => '1'], []],
			'border on div' => ['DIV', ['BORDER' => '1'], []],

			'type on ol' => ['OL', ['TYPE' => 'A'], ['LIST-STYLE-TYPE' => 'upper-latin']],
			'lowercase type on ol' => ['OL', ['TYPE' => 'a'], ['LIST-STYLE-TYPE' => 'lower-latin']],
			'roman type on li' => ['LI', ['TYPE' => 'I'], ['LIST-STYLE-TYPE' => 'upper-roman']],
			'type on ul, in capitals' => ['UL', ['TYPE' => 'SQUARE'], ['LIST-STYLE-TYPE' => 'square']],
			'type none on li' => ['LI', ['TYPE' => 'none'], ['LIST-STYLE-TYPE' => 'none']],
			'a type HTML does not define on ol' => ['OL', ['TYPE' => 'lower-greek'], []],
			'type on input' => ['INPUT', ['TYPE' => 'text'], []],

			'size on hr' => ['HR', ['SIZE' => '5'], ['HEIGHT' => '5px']],
			'size of zero on hr' => ['HR', ['SIZE' => '0'], []],
			'size on span' => ['SPAN', ['SIZE' => '5'], []],

			'bgcolor, read where the table is built' => ['TD', ['BGCOLOR' => '#f00'], []],
			'cellpadding, read where the table is built' => ['TABLE', ['CELLPADDING' => '5'], []],
		];
	}

	/**
	 * The width of the border `border` gives: a table's is 1px where the value is not a number, an image has none
	 *
	 * @dataProvider borderWidths
	 *
	 * @param string $tag
	 * @param string $value
	 * @param int $expected
	 */
	public function testReadsTheWidthOfABorderAsANonNegativeInteger($tag, $value, $expected)
	{
		$this->assertSame($expected, PresentationalHints::borderWidth($tag, $value));
	}

	/**
	 * Values of `border`, read as HTML reads them
	 *
	 * @return array[] Each [tag, value, width in pixels]
	 */
	public function borderWidths()
	{
		return [
			'a number' => ['TABLE', '2', 2],
			'zero' => ['TABLE', '0', 0],
			'with leading space and a plus sign' => ['TABLE', ' +4', 4],
			'digits then other characters' => ['TABLE', '3px', 3],
			'not a number, on a table' => ['TABLE', 'border', 1],
			'empty, on a table' => ['TABLE', '', 1],
			'not a number, on an image' => ['IMG', 'border', 0],
			'a negative number, on an image' => ['IMG', '-2', 0],
		];
	}
}
