<?php

namespace Mpdf;

use Mpdf\Css\InheritedProperties;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Each inherited text property reaches a descendant's text through every channel mPDF hands inherited values on by:
 * a block to its child blocks, in the flow, in a list, in a header or footer, in a block laid out twice because it is
 * kept together and after a forced page break; an inline element to a block opened inside it; a positioned block to
 * its content; a table, a row group and a row to their cells, wherever the table is; and a cell to the cells of a
 * table nested in it. The descendant's text is read in the same state as when the descendant sets the value itself.
 * Under legacy the channels drop what mPDF v7 dropped.
 */
class InheritedPropertiesTest extends TestCase
{

	use DrawnStyles;

	/**
	 * A declaration of each of InheritedProperties::TEXT that puts the text in a state apart from its initial one
	 */
	const SAMPLES = [
		'COLOR' => 'color: #ff0000',
		'FONT-FAMILY' => 'font-family: monospace',
		'FONT-SIZE' => 'font-size: 14pt',
		'FONT-STYLE' => 'font-style: italic',
		'FONT-WEIGHT' => 'font-weight: bold',
		'FONT-KERNING' => 'font-kerning: normal',
		'FONT-VARIANT-POSITION' => 'font-variant-position: super',
		'FONT-VARIANT-CAPS' => 'font-variant-caps: small-caps',
		'FONT-VARIANT-LIGATURES' => 'font-variant-ligatures: no-common-ligatures',
		'FONT-VARIANT-NUMERIC' => 'font-variant-numeric: oldstyle-nums',
		'FONT-VARIANT-ALTERNATES' => 'font-variant-alternates: historical-forms',
		'FONT-FEATURE-SETTINGS' => "font-feature-settings: 'tnum' 1",
		'FONT-LANGUAGE-OVERRIDE' => 'font-language-override: TRK',
		'LETTER-SPACING' => 'letter-spacing: 2mm',
		'WORD-SPACING' => 'word-spacing: 5mm',
		'TEXT-TRANSFORM' => 'text-transform: uppercase',
		'TEXT-SHADOW' => 'text-shadow: 1px 1px #0000ff',
		'HYPHENS' => 'hyphens: auto',
		'TEXT-OUTLINE' => 'text-outline: none',
		'TEXT-OUTLINE-COLOR' => 'text-outline-color: #00ff00',
		// An outline width with no colour is drawn with none
		'TEXT-OUTLINE-WIDTH' => 'text-outline-color: #00ff00; text-outline-width: 0.1mm',
	];

	/**
	 * Documents with {A} for the ancestor's declarations and {D} for those of the descendant, whose text is qq
	 */
	const CONTEXTS = [
		'block to child block' => '<div style="{A}"><p style="{D}">qq</p></div>',
		'block to inline element' => '<div style="{A}">zz <span style="{D}">qq</span></div>',
		'inline element to child block' => '<span style="{A}">zz<div style="{D}">qq</div></span>',
		'list to item' => '<ul style="{A}"><li style="{D}">qq</li></ul>',
		'list item to child block' => '<ul><li style="{A}"><div style="{D}">qq</div></li></ul>',
		'body to block' => '<body style="{A}"><p style="{D}">qq</p></body>',
		'header block to child block' => '<htmlpageheader name="h"><div style="{A}"><p style="{D}">qq</p></div></htmlpageheader><sethtmlpageheader name="h" value="on" show-this-page="1" /><p>body</p>',
		'footer block to child block' => '<htmlpagefooter name="f"><div style="{A}"><p style="{D}">qq</p></div></htmlpagefooter><sethtmlpagefooter name="f" value="on" /><p>body</p>',
		'kept block to child block' => '{FILLER}<div style="page-break-inside: avoid; {A}"><p>zz</p><p style="{D}">qq</p></div>',
		'block to child block after a forced page break' => '<div style="{A}"><p>zz</p><pagebreak /><p style="{D}">qq</p></div>',
		'positioned block to child block' => '<div style="position: absolute; top: 60mm; left: 20mm; width: 150mm; {A}"><p style="{D}">qq</p></div>',
		'table to cell' => '<table style="{A}"><tr><td style="{D}">qq</td></tr></table>',
		'block to table cell' => '<div style="{A}"><table><tr><td style="{D}">qq</td></tr></table></div>',
		'list item to table cell' => '<ul><li style="{A}"><table><tr><td style="{D}">qq</td></tr></table></li></ul>',
		'body to table cell' => '<body style="{A}"><table><tr><td style="{D}">qq</td></tr></table></body>',
		'header block to table cell' => '<htmlpageheader name="h"><div style="{A}"><table><tr><td style="{D}">qq</td></tr></table></div></htmlpageheader><sethtmlpageheader name="h" value="on" show-this-page="1" /><p>body</p>',
		'positioned block to table cell' => '<div style="position: absolute; top: 60mm; left: 20mm; width: 150mm; {A}"><table><tr><td style="{D}">qq</td></tr></table></div>',
		'kept block to table cell' => '{FILLER}<div style="page-break-inside: avoid; {A}"><p>zz</p><table><tr><td style="{D}">qq</td></tr></table></div>',
		'block to table cell after a forced page break' => '<div style="{A}"><p>zz</p><pagebreak /><table><tr><td style="{D}">qq</td></tr></table></div>',
		'row to cell' => '<table><tr style="{A}"><td style="{D}">qq</td></tr></table>',
		'row rule to cell' => '<style>tr.a { {A} }</style><table><tr class="a"><td style="{D}">qq</td></tr></table>',
		'tbody to cell' => '<table><tbody style="{A}"><tr><td style="{D}">qq</td></tr></tbody></table>',
		'thead to cell' => '<table><thead style="{A}"><tr><td style="{D}">qq</td></tr></thead><tbody><tr><td>zz</td></tr></tbody></table>',
		'tfoot to cell' => '<table><tbody><tr><td>zz</td></tr></tbody><tfoot style="{A}"><tr><td style="{D}">qq</td></tr></tfoot></table>',
		'implied tbody to cell of its second row' => '<style>tbody { {A} }</style><table><tr><td>zz</td></tr><tr><td style="{D}">qq</td></tr></table>',
		'row in a header to cell' => '<htmlpageheader name="h"><table><tbody style="{A}"><tr><td style="{D}">qq</td></tr></tbody></table></htmlpageheader><sethtmlpageheader name="h" value="on" show-this-page="1" /><p>body</p>',
		'row in a list item to cell' => '<ul><li><table><thead style="{A}"><tr><td style="{D}">qq</td></tr></thead></table></li></ul>',
		'row in a positioned block to cell' => '<div style="position: absolute; top: 60mm; left: 20mm; width: 150mm"><table><tr style="{A}"><td style="{D}">qq</td></tr></table></div>',
		'row in a kept block to cell' => '{FILLER}<div style="page-break-inside: avoid"><p>zz</p><table><tr style="{A}"><td style="{D}">qq</td></tr></table></div>',
		'row to nested table cell' => '<table><tr><td><table><tr style="{A}"><td style="{D}">qq</td></tr></table></td></tr></table>',
		'outer row to nested table cell' => '<table><tr style="{A}"><td><table><tr><td style="{D}">qq</td></tr></table></td></tr></table>',
		'cell to child block' => '<table><tr><td style="{A}"><div style="{D}">qq</div></td></tr></table>',
		'cell to nested table cell' => '<table><tr><td style="{A}"><table><tr><td style="{D}">qq</td></tr></table></td></tr></table>',
	];

	/**
	 * What legacy's channels drop, by context. An inline element hands a block opened inside it only its language
	 * override, which the block does not reset. A block hands its child blocks no text shadow. A positioned block hands
	 * its content no word spacing, hyphenation or outline, and its child blocks no text shadow. A table hands its cells
	 * no font variant, feature setting, language override, transform, shadow or outline. A table starts from the
	 * document's defaults, whatever block it is in. A row group or a row hands its cells nothing. A cell hands the
	 * cells of a table nested in it only its spacing and its language override
	 */
	const LEGACY_DROPPED = [
		'block to child block' => ['TEXT-SHADOW'],
		'inline element to child block' => [
			'COLOR', 'FONT-FAMILY', 'FONT-SIZE', 'FONT-STYLE', 'FONT-WEIGHT', 'FONT-KERNING', 'FONT-VARIANT-POSITION',
			'FONT-VARIANT-CAPS', 'FONT-VARIANT-LIGATURES', 'FONT-VARIANT-NUMERIC', 'FONT-VARIANT-ALTERNATES',
			'FONT-FEATURE-SETTINGS', 'LETTER-SPACING', 'WORD-SPACING', 'TEXT-TRANSFORM', 'TEXT-SHADOW', 'HYPHENS',
			'TEXT-OUTLINE', 'TEXT-OUTLINE-COLOR', 'TEXT-OUTLINE-WIDTH',
		],
		'list to item' => ['TEXT-SHADOW'],
		'list item to child block' => ['TEXT-SHADOW'],
		'body to block' => ['TEXT-SHADOW'],
		'header block to child block' => ['TEXT-SHADOW'],
		'footer block to child block' => ['TEXT-SHADOW'],
		'kept block to child block' => ['TEXT-SHADOW'],
		'block to child block after a forced page break' => ['TEXT-SHADOW'],
		'positioned block to child block' => [
			'WORD-SPACING', 'TEXT-SHADOW', 'HYPHENS', 'TEXT-OUTLINE', 'TEXT-OUTLINE-COLOR', 'TEXT-OUTLINE-WIDTH',
		],
		'table to cell' => [
			'FONT-VARIANT-POSITION', 'FONT-VARIANT-CAPS', 'FONT-VARIANT-LIGATURES', 'FONT-VARIANT-NUMERIC',
			'FONT-VARIANT-ALTERNATES', 'FONT-FEATURE-SETTINGS', 'FONT-LANGUAGE-OVERRIDE', 'TEXT-TRANSFORM', 'TEXT-SHADOW',
			'TEXT-OUTLINE', 'TEXT-OUTLINE-COLOR', 'TEXT-OUTLINE-WIDTH',
		],
		'block to table cell' => self::LEGACY_TABLE_DROPPED,
		'list item to table cell' => self::LEGACY_TABLE_DROPPED,
		'body to table cell' => self::LEGACY_BODY_TABLE_DROPPED,
		'header block to table cell' => self::LEGACY_TABLE_DROPPED,
		'positioned block to table cell' => self::LEGACY_TABLE_DROPPED,
		'kept block to table cell' => self::LEGACY_TABLE_DROPPED,
		'block to table cell after a forced page break' => self::LEGACY_TABLE_DROPPED,
		'row to cell' => InheritedProperties::TEXT,
		'row rule to cell' => InheritedProperties::TEXT,
		'tbody to cell' => InheritedProperties::TEXT,
		'thead to cell' => InheritedProperties::TEXT,
		'tfoot to cell' => InheritedProperties::TEXT,
		'implied tbody to cell of its second row' => InheritedProperties::TEXT,
		'row in a header to cell' => InheritedProperties::TEXT,
		'row in a list item to cell' => InheritedProperties::TEXT,
		'row in a positioned block to cell' => InheritedProperties::TEXT,
		'row in a kept block to cell' => InheritedProperties::TEXT,
		'row to nested table cell' => InheritedProperties::TEXT,
		'outer row to nested table cell' => InheritedProperties::TEXT,
		'cell to nested table cell' => [
			'COLOR', 'FONT-FAMILY', 'FONT-SIZE', 'FONT-STYLE', 'FONT-WEIGHT', 'FONT-KERNING', 'FONT-VARIANT-POSITION',
			'FONT-VARIANT-CAPS', 'FONT-VARIANT-LIGATURES', 'FONT-VARIANT-NUMERIC', 'FONT-VARIANT-ALTERNATES',
			'FONT-FEATURE-SETTINGS', 'TEXT-TRANSFORM', 'TEXT-SHADOW', 'HYPHENS', 'TEXT-OUTLINE', 'TEXT-OUTLINE-COLOR',
			'TEXT-OUTLINE-WIDTH',
		],
	];

	/**
	 * What legacy hands a table's cells from the block the table is in: only a font language override, which the text
	 * state keeps through the table
	 */
	const LEGACY_TABLE_DROPPED = [
		'COLOR', 'FONT-FAMILY', 'FONT-SIZE', 'FONT-STYLE', 'FONT-WEIGHT', 'FONT-KERNING', 'FONT-VARIANT-POSITION',
		'FONT-VARIANT-CAPS', 'FONT-VARIANT-LIGATURES', 'FONT-VARIANT-NUMERIC', 'FONT-VARIANT-ALTERNATES',
		'FONT-FEATURE-SETTINGS', 'LETTER-SPACING', 'WORD-SPACING', 'TEXT-TRANSFORM', 'TEXT-SHADOW', 'HYPHENS',
		'TEXT-OUTLINE', 'TEXT-OUTLINE-COLOR', 'TEXT-OUTLINE-WIDTH',
	];

	/**
	 * What legacy hands a table's cells from <body style="">: the font family and size, which set the defaults a table
	 * starts from, and a font language override
	 */
	const LEGACY_BODY_TABLE_DROPPED = [
		'COLOR', 'FONT-STYLE', 'FONT-WEIGHT', 'FONT-KERNING', 'FONT-VARIANT-POSITION', 'FONT-VARIANT-CAPS',
		'FONT-VARIANT-LIGATURES', 'FONT-VARIANT-NUMERIC', 'FONT-VARIANT-ALTERNATES', 'FONT-FEATURE-SETTINGS',
		'LETTER-SPACING', 'WORD-SPACING', 'TEXT-TRANSFORM', 'TEXT-SHADOW', 'HYPHENS', 'TEXT-OUTLINE',
		'TEXT-OUTLINE-COLOR', 'TEXT-OUTLINE-WIDTH',
	];

	/**
	 * The text states read for each document, so that each is written once
	 *
	 * @var array[]
	 */
	private static $states = [];

	/**
	 * The descendant's text is read in the state it would be in had it set the value itself, or under legacy in its
	 * initial state where legacy's channel drops the property
	 *
	 * @dataProvider propertiesInContexts
	 *
	 * @param string $mode
	 * @param string $context
	 * @param string $property
	 */
	public function testTheDescendantTakesTheInheritedValue($mode, $context, $property)
	{
		$declaration = self::SAMPLES[$property];

		$initial = $this->textState($mode, $context, '', '');
		$own = $this->textState($mode, $context, '', $declaration);
		$inherited = $this->textState($mode, $context, $declaration, '');

		$this->assertNotEquals($initial, $own, 'The sample value does not change the text state');

		if ($this->carries($mode, $context, $property)) {
			$this->assertEquals($own, $inherited);
		} else {
			$this->assertNotEquals($own, $inherited);
		}
	}

	/**
	 * Every inherited text property in every context, under both modes
	 *
	 * @return array[]
	 */
	public function propertiesInContexts()
	{
		$data = [];
		foreach ([CssMode::STANDARD, CssMode::LEGACY] as $mode) {
			foreach (array_keys(self::CONTEXTS) as $context) {
				foreach (InheritedProperties::TEXT as $property) {
					$data[$mode . ': ' . $context . ': ' . $property] = [$mode, $context, $property];
				}
			}
		}

		return $data;
	}

	/**
	 * The kept block starts on the first page, runs over and is laid out again from the top of the second, and the
	 * forced page break inside the block puts the descendant on the second page
	 */
	public function testTheKeptBlockAndTheForcedBreakMoveTheDescendantToTheNextPage()
	{
		foreach (['kept block to child block' => 2, 'block to child block after a forced page break' => 1] as $context => $firstPage) {
			$html = strtr(self::CONTEXTS[$context], ['{A}' => '', '{D}' => '', '{FILLER}' => str_repeat('<p>filler</p>', 44)]);
			$mpdf = $this->drawDocument($html);
			$pages = $this->keyedByText($mpdf, array_map(function ($box) {
				return $box[0];
			}, $mpdf->drawnBoxes));

			$this->assertSame($firstPage, $pages['zz'], $context);
			$this->assertSame(2, $pages['qq'], $context);
		}
	}

	/**
	 * What the matrix in #539 found missing is drawn: the child block's text shadow, the positioned block's word
	 * spacing, the cell's transform and small capitals, and the nested cell's colour, font and weight
	 *
	 * @dataProvider gapsOnThePage
	 *
	 * @param string $html
	 * @param callable $read Gives what is drawn of the text qq, from the document
	 * @param mixed $standard What is drawn under standard
	 * @param mixed $legacy What is drawn under legacy
	 */
	public function testTheGapsAreClosedOnThePage($html, callable $read, $standard, $legacy)
	{
		foreach ([CssMode::STANDARD => $standard, CssMode::LEGACY => $legacy] as $mode => $expected) {
			$this->assertSame($expected, $read($this->drawDocument($html, ['mode' => 'utf-8', 'cssMode' => $mode])), $mode);
		}
	}

	/**
	 * The gaps, each with a near miss that sets the value on the descendant and one that sets it nowhere
	 *
	 * @return array[]
	 */
	public function gapsOnThePage()
	{
		$shadow = function (TextRecordingMpdf $mpdf) {
			return $this->keyedByText($mpdf, $mpdf->drawnShadows)['qq'] !== '';
		};
		$text = function (TextRecordingMpdf $mpdf) {
			foreach ($mpdf->drawnText as $piece) {
				if (strtolower(trim($piece)) === 'qq') {
					return trim($piece);
				}
			}

			return null;
		};
		$width = function (TextRecordingMpdf $mpdf) {
			$box = $this->keyedByText($mpdf, $mpdf->drawnBoxes)['qq q'];

			return round($box[2] - $box[1], 2) > 20;
		};
		$drawn = function ($property) {
			return function (TextRecordingMpdf $mpdf) use ($property) {
				return $this->keyedByText($mpdf, $mpdf->$property)['qq'];
			};
		};
		$smallCaps = function (TextRecordingMpdf $mpdf) {
			return ($this->keyedByText($mpdf, $mpdf->drawnTextVars)['qq'] & Css\TextVars::FC_SMALLCAPS) !== 0;
		};
		$size = function (TextRecordingMpdf $mpdf) {
			return round($this->keyedByText($mpdf, $mpdf->drawnFontSize)['qq'], 3);
		};
		$nested = '<table><tr><td style="{A}">zz<table><tr><td>aa<br>qq</td></tr></table></td></tr></table>';

		return [
			'a text shadow reaches a child block' => ['<div style="text-shadow: 1px 1px #00f"><p>qq</p></div>', $shadow, true, false],
			'a child block without a shadow above it has none' => ['<div><p>qq</p></div>', $shadow, false, false],
			'word spacing reaches a positioned block\'s content' => ['<div style="position: absolute; top: 20mm; left: 20mm; width: 150mm; word-spacing: 20mm"><p>qq q</p></div>', $width, true, false],
			'a positioned block without word spacing' => ['<div style="position: absolute; top: 20mm; left: 20mm; width: 150mm"><p>qq q</p></div>', $width, false, false],
			'a table\'s transform reaches its cells' => ['<table style="text-transform: uppercase"><tr><td>qq</td></tr></table>', $text, 'QQ', 'qq'],
			'a table without a transform' => ['<table><tr><td>qq</td></tr></table>', $text, 'qq', 'qq'],
			'a cell sets its own transform' => ['<table><tr><td style="text-transform: uppercase">qq</td></tr></table>', $text, 'QQ', 'QQ'],
			'a table\'s small capitals reach its cells' => ['<table style="font-variant: small-caps"><tr><td>qq</td></tr></table>', $smallCaps, true, false],
			'a table\'s text shadow reaches its cells' => ['<table style="text-shadow: 1px 1px #00f"><tr><td>qq</td></tr></table>', $shadow, true, false],
			'a cell\'s colour reaches a nested table' => [strtr($nested, ['{A}' => 'color: #f00']), $drawn('drawnColours'), '1.000 0.000 0.000 rg', '0.000 g'],
			'a cell\'s font reaches a nested table' => [strtr($nested, ['{A}' => 'font-family: dejavusansmono']), $drawn('drawnFontFamily'), 'dejavusansmono', 'dejavuserifcondensed'],
			'a cell\'s weight reaches a nested table' => [strtr($nested, ['{A}' => 'font-weight: bold']), $drawn('drawnFontStyles'), 'B', ''],
			'a cell\'s size reaches a nested table' => [strtr($nested, ['{A}' => 'font-size: 20pt']), $size, 20.0, 11.0],
			'a nested table sets its own colour over the cell\'s' => ['<table><tr><td style="color: #f00"><table style="color: #00f"><tr><td>aa<br>qq</td></tr></table></td></tr></table>', $drawn('drawnColours'), '0.000 0.000 1.000 rg', '0.000 0.000 1.000 rg'],
		];
	}

	/**
	 * Each of InheritedProperties::TEXT has a sample value
	 */
	public function testEveryTextPropertyHasASample()
	{
		$this->assertEqualsCanonicalizing(InheritedProperties::TEXT, array_keys(self::SAMPLES));
	}

	/**
	 * The list names each property once
	 */
	public function testNamesEachPropertyOnce()
	{
		$names = InheritedProperties::names();

		$this->assertSame($names, array_values(array_unique($names)));
	}

	/**
	 * of() takes the named properties that are set, in the order they are named, and nothing else
	 */
	public function testOfTakesTheNamedPropertiesInTheirOrder()
	{
		$properties = ['MARGIN-TOP' => '1mm', 'TEXT-SHADOW' => '1px 1px red', 'COLOR' => 'red', 'TEXT-ALIGN' => 'right'];

		$this->assertSame(
			['COLOR' => 'red', 'TEXT-SHADOW' => '1px 1px red'],
			InheritedProperties::of($properties, InheritedProperties::TEXT)
		);
		$this->assertSame(
			['COLOR' => 'red', 'TEXT-SHADOW' => '1px 1px red', 'TEXT-ALIGN' => 'right'],
			InheritedProperties::of($properties, InheritedProperties::names())
		);
		$this->assertSame([], InheritedProperties::of(['MARGIN-TOP' => '1mm'], InheritedProperties::TEXT));
	}

	/**
	 * @param string $mode
	 * @param string $context
	 * @param string $property
	 *
	 * @return bool Whether the context's channel carries the property under the mode
	 */
	private function carries($mode, $context, $property)
	{
		if ($mode === CssMode::STANDARD || !array_key_exists($context, self::LEGACY_DROPPED)) {
			return true;
		}

		return !in_array($property, self::LEGACY_DROPPED[$context], true);
	}

	/**
	 * The inherited parts of the text state the descendant's text qq is read in, the last time it is read
	 *
	 * @param string $mode
	 * @param string $context
	 * @param string $ancestor The ancestor's declarations
	 * @param string $descendant The descendant's declarations
	 *
	 * @return array
	 */
	private function textState($mode, $context, $ancestor, $descendant)
	{
		$html = strtr(self::CONTEXTS[$context], [
			'{A}' => $ancestor,
			'{D}' => $descendant,
			// Too little of the first page is left for the kept block, which is laid out again on the second
			'{FILLER}' => str_repeat('<p>filler</p>', 44),
		]);

		$key = $mode . "\n" . $html;
		if (!isset(self::$states[$key])) {
			$mpdf = new TextRecordingMpdf(['mode' => 'c', 'cssMode' => $mode]);
			$mpdf->recordBufferedStates = true;
			$mpdf->WriteHTML($html);
			$mpdf->Close();

			$state = null;
			foreach ($mpdf->bufferedStates as $buffered) {
				// Read after any text-transform
				if (strtolower(trim($buffered[0])) === 'qq') {
					$state = $buffered[1];
				}
			}

			$this->assertNotNull($state, 'qq is not read');

			$state = array_intersect_key($state, array_flip([
				'family', 'style', 'sizePt', 'I', 'B', 'colorarray', 'textvar', 'OTLtags', 'textshadow', 'textparam',
				'lSpacingCSS', 'wSpacingCSS', 'fontLanguageOverride',
			]));
			$state['OTLtags'] = $this->features((array) $state['OTLtags']);

			self::$states[$key] = $this->rounded($state);
		}

		return self::$states[$key];
	}

	/**
	 * The OpenType features a text state turns on and off. A block hands its font-variant-* values on as
	 * font-feature-settings, which turn on the same features
	 *
	 * @param array $tags The state's OTLtags
	 *
	 * @return array[] The features turned on and those turned off, each sorted
	 */
	private function features(array $tags)
	{
		$features = [];
		foreach (['on' => ['Plus', 'FFPlus'], 'off' => ['Minus', 'FFMinus']] as $setting => $keys) {
			$named = '';
			foreach ($keys as $key) {
				$named .= ' ' . (isset($tags[$key]) ? $tags[$key] : '');
			}

			// A feature turned on by font-feature-settings carries its value, 1 for on
			$named = preg_replace('/\b([a-z0-9]{4})1\b/', '$1', $named);
			$features[$setting] = array_values(array_unique(preg_split('/\s+/', $named, -1, PREG_SPLIT_NO_EMPTY)));
			sort($features[$setting]);
		}

		return $features;
	}

	/**
	 * @param mixed $value
	 *
	 * @return mixed The value with each number in it rounded to six places, since a value read back from its CSS may
	 *               differ from the one it was written from in the last places
	 */
	private function rounded($value)
	{
		if (is_array($value)) {
			return array_map([$this, 'rounded'], $value);
		}

		return is_float($value) ? round($value, 6) : $value;
	}
}
