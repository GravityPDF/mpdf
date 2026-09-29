<?php

namespace Mpdf\Css;

use Mpdf\Color\ColorConverter;
use Mpdf\Mpdf;
use Mpdf\MpdfException;
use Mpdf\PageFormat;
use Mpdf\SizeConverter;
use Mpdf\Utils\Arrays;
use Mpdf\Utils\UtfString;

class NormalizeProperties
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
	 * @var ColorConverter
	 */
	private $colorConverter;

	/**
	 * @var array
	 */
	private $properties = [];

	/**
	 * Properties whose value is made only of lengths and keywords, as keys
	 *
	 * @var bool[]
	 */
	private static $lengthProperties = [
		'MARGIN' => true, 'MARGIN-TOP' => true, 'MARGIN-RIGHT' => true, 'MARGIN-BOTTOM' => true, 'MARGIN-LEFT' => true,
		'MARGIN-HEADER' => true, 'MARGIN-FOOTER' => true,
		'PADDING' => true, 'PADDING-TOP' => true, 'PADDING-RIGHT' => true, 'PADDING-BOTTOM' => true, 'PADDING-LEFT' => true,
		'WIDTH' => true, 'HEIGHT' => true, 'MIN-WIDTH' => true, 'MAX-WIDTH' => true, 'MIN-HEIGHT' => true, 'MAX-HEIGHT' => true,
		'TOP' => true, 'RIGHT' => true, 'BOTTOM' => true, 'LEFT' => true,
		'FONT-SIZE' => true, 'LINE-HEIGHT' => true, 'TEXT-INDENT' => true, 'LETTER-SPACING' => true, 'WORD-SPACING' => true,
		'BORDER-WIDTH' => true, 'BORDER-TOP-WIDTH' => true, 'BORDER-RIGHT-WIDTH' => true, 'BORDER-BOTTOM-WIDTH' => true,
		'BORDER-LEFT-WIDTH' => true, 'BORDER-SPACING' => true,
		'BORDER-RADIUS' => true, 'BORDER-TOP-LEFT-RADIUS' => true, 'BORDER-TOP-RIGHT-RADIUS' => true,
		'BORDER-BOTTOM-LEFT-RADIUS' => true, 'BORDER-BOTTOM-RIGHT-RADIUS' => true,
	];

	/**
	 * Properties whose whole value is one colour, as keys
	 *
	 * @var bool[]
	 */
	private static $colorProperties = [
		'COLOR' => true, 'BACKGROUND-COLOR' => true,
		'BORDER-TOP-COLOR' => true, 'BORDER-RIGHT-COLOR' => true, 'BORDER-BOTTOM-COLOR' => true, 'BORDER-LEFT-COLOR' => true,
	];

	public function __construct(Mpdf $mpdf, SizeConverter $sizeConverter, ColorConverter $colorConverter)
	{
		$this->mpdf = $mpdf;
		$this->sizeConverter = $sizeConverter;
		$this->colorConverter = $colorConverter;
	}

	/**
	 * Process and expand CSS shorthand properties.
	 *
	 * Takes an array of CSS properties and expands shorthand properties
	 * into their individual components (e.g., margin -> margin-top, margin-right,
	 * margin-bottom, margin-left). Handles font, background, border, padding,
	 * margin, and other composite properties.
	 *
	 * @param array $prop CSS properties array
	 * @return array Expanded CSS properties array
	 */
	public function normalize($prop)
	{
		if (!is_array($prop) || count($prop) === 0) {
			return [];
		}

		$this->properties = [];

		foreach ($prop as $k => $v) {
			if ($k !== 'BACKGROUND-IMAGE' && $k !== 'BACKGROUND' && $k !== 'ODD-HEADER-NAME' && $k !== 'EVEN-HEADER-NAME' && $k !== 'ODD-FOOTER-NAME' && $k !== 'EVEN-FOOTER-NAME' && $k !== 'HEADER' && $k !== 'FOOTER' && $k !== 'LIST-STYLE' && $k !== 'LIST-STYLE-IMAGE') {
				$v = strtolower($v);
			}

			$v = $this->compactColorFunctions($v);

			if (isset(self::$lengthProperties[$k]) && strpos($v, '+') !== false) {
				// "+5mm" is 5mm; the code reading font sizes and unitless line heights looks for a digit first
				$v = preg_replace('/(^|[\s\/])\+(?=[\d.])/', '$1', $v);
			}

			if ($k === 'FONT') {
				$this->processFontProperty($v);
			} elseif ($k === 'FONT-FAMILY') {
				$this->processFontFamilyProperty($k, $v);
			} elseif ($k === 'FONT-VARIANT') {
				$this->processFontVariantProperty($v);
			} elseif ($k === 'MARGIN') {
				$tmp = $this->expandShorthandProperty($v);

				$this->properties['MARGIN-TOP'] = $tmp['T'];
				$this->properties['MARGIN-RIGHT'] = $tmp['R'];
				$this->properties['MARGIN-BOTTOM'] = $tmp['B'];
				$this->properties['MARGIN-LEFT'] = $tmp['L'];
			} elseif ($k === 'BORDER-RADIUS' || $k === 'BORDER-TOP-LEFT-RADIUS' || $k === 'BORDER-TOP-RIGHT-RADIUS' || $k === 'BORDER-BOTTOM-LEFT-RADIUS' || $k === 'BORDER-BOTTOM-RIGHT-RADIUS') {
				$this->processBorderRadiusProperty($k, $v);
			} elseif ($k === 'PADDING') {
				$tmp = $this->expandShorthandProperty($v);

				$this->properties['PADDING-TOP'] = $tmp['T'];
				$this->properties['PADDING-RIGHT'] = $tmp['R'];
				$this->properties['PADDING-BOTTOM'] = $tmp['B'];
				$this->properties['PADDING-LEFT'] = $tmp['L'];
			} elseif (in_array($k, ['BORDER', 'BORDER-TOP', 'BORDER-RIGHT', 'BORDER-BOTTOM', 'BORDER-LEFT'], true)) {
				$this->processBorderProperty($k, $v);
			} elseif (in_array($k, ['BORDER-STYLE', 'BORDER-WIDTH', 'BORDER-COLOR', 'BORDER-SPACING'], true)) {
				$this->processBorderShorthandProperty($k, $v);
			} elseif ($k === 'TEXT-OUTLINE') {
				$this->processTextOutlineProperty($v);
			} elseif ($k === 'SIZE' || $k === 'SHEET-SIZE') {
				$this->processPageSizeProperty($k, $v);
			} elseif (in_array($k, ['BACKGROUND', 'BACKGROUND-IMAGE', 'BACKGROUND-REPEAT', 'BACKGROUND-POSITION'], true)) {
				$this->processBackgroundProperty($k, $v);
			} elseif ($k === 'IMAGE-ORIENTATION') {
				$this->processImageOrientationProperty($v);
			} elseif ($k === 'TEXT-ALIGN') {
				$this->processTextAlignProperty($k, $v);
			} elseif ($k === 'LIST-STYLE') {
				$this->processListStyleProperty($v);

				if (preg_match('/(inside|outside)/i', $v, $m)) {
					$this->properties['LIST-STYLE-POSITION'] = strtolower(trim($m[1]));
				}
			} else {
				$this->properties[$k] = $v;
			}
		}

		// A negative line-height is invalid, so the declaration is dropped
		if (isset($this->properties['LINE-HEIGHT']) && (float) $this->properties['LINE-HEIGHT'] < 0) {
			unset($this->properties['LINE-HEIGHT']);
		}

		return $this->properties;
	}

	/**
	 * Writes the arguments of each rgb(), hsl() and cmyk() with commas and no whitespace, so the parsers
	 * that split a value on whitespace (border, border-color, shadows, gradients) keep the colour whole:
	 * rgb(255 0 0 / 50%) becomes rgb(255,0,0,50%). A spot colour's name can contain spaces, so spot()
	 * is left alone.
	 *
	 * @param string $value Property value
	 * @return string
	 */
	private function compactColorFunctions($value)
	{
		if (stripos($value, 'rgb') === false && stripos($value, 'hsl') === false && stripos($value, 'cmyk') === false) {
			return $value;
		}

		return preg_replace_callback('/\b(rgba?|hsla?|(?:device-)?cmyka?)\(([^()]*)\)/i', static function ($m) {
			return $m[1] . '(' . preg_replace(ColorConverter::ARGUMENT_SEPARATOR, ',', trim($m[2])) . ')';
		}, $value);
	}

	/**
	 * Whether mPDF can read the value of a declaration.
	 *
	 * A browser drops a declaration it cannot parse, so the value it would have replaced still applies. mPDF drops
	 * a value that uses a CSS function it does not implement, a length it cannot read or in a unit it does not
	 * know, and a colour it does not recognise where the colour is the whole value. Keywords are not checked.
	 *
	 * @param string $property The property name, uppercased
	 * @param string $value
	 *
	 * @return bool
	 */
	public function canParse($property, $value)
	{
		if (strpos($value, '!') !== false) {
			$value = preg_replace('/\s*!\s*important\s*$/i', '', $value);
		}
		$value = strtolower(trim($value));

		if (strpos($value, '(') !== false) {
			// A function name inside a string or a url() is not a call
			$code = preg_replace('/"[^"]*"|\'[^\']*\'|url\([^)]*\)/', '', $value);
			if (preg_match('/(?<![\w-])(calc|min|max|clamp|var|env|attr)\(/', $code)) {
				return false;
			}
		}

		if (isset(self::$lengthProperties[$property])) {
			foreach (preg_split('/[\s\/]+/', $value, -1, PREG_SPLIT_NO_EMPTY) as $part) {
				if (!preg_match('/^-?[a-z_][\w-]*$/', $part) && !$this->sizeConverter->isLength($part)) {
					return false;
				}
			}
		} elseif (isset(self::$colorProperties[$property])) {
			return in_array($value, ['transparent', 'currentcolor', 'inherit', 'initial', 'unset'], true)
				|| $this->colorConverter->isColor($value);
		}

		return true;
	}

	/**
	 * Process FONT shorthand property.
	 *
	 * Expands the CSS font shorthand into individual components:
	 * font-family, font-size, line-height, font-style, font-weight, text-transform.
	 *
	 * @param string $value Font property value
	 * @return void
	 */
	protected function processFontProperty($value)
	{
		// The size, with any /line-height, is the first word that is not a style, variant, weight or stretch keyword,
		// and everything after it is the family list
		$value = preg_replace('/\s*\/\s*/', '/', trim($value));
		if (!preg_match('/^((?:(?:normal|italic|oblique|small-caps|bold|bolder|lighter|\d+|[a-z-]*condensed|[a-z-]*expanded)\s+)*)(\S+?)(?:\/(\S+))?\s+(\S.*)$/s', $value, $m)) {
			return;
		}

		list(, $keywords, $size, $lineHeight, $family) = $m;

		$this->processFontFamilyProperty('FONT-FAMILY', $family);

		$this->properties['FONT-SIZE'] = $size;
		if ($lineHeight !== '') {
			$this->properties['LINE-HEIGHT'] = $lineHeight;
		}

		// Check for font-style
		if (preg_match('/(italic|oblique)/i', $keywords)) {
			$this->properties['FONT-STYLE'] = 'italic';
		} else {
			$this->properties['FONT-STYLE'] = 'normal';
		}

		// Check for font-weight
		if (stripos($keywords, 'bold') !== false) {
			$this->properties['FONT-WEIGHT'] = 'bold';
		} else {
			$this->properties['FONT-WEIGHT'] = 'normal';
		}

		// Check for small-caps
		if (stripos($keywords, 'small-caps') !== false) {
			$this->properties['TEXT-TRANSFORM'] = 'uppercase';
		}
	}

	/**
	 * Process FONT-VARIANT property.
	 *
	 * @param string $value Property value
	 * @return void
	 */
	protected function processFontVariantProperty($value)
	{
		if (preg_match('/(normal|none)/', $value, $m)) {
			$this->properties['FONT-VARIANT-LIGATURES'] = $m[1];
			$this->properties['FONT-VARIANT-CAPS'] = $m[1];
			$this->properties['FONT-VARIANT-NUMERIC'] = $m[1];
			$this->properties['FONT-VARIANT-ALTERNATES'] = $m[1];

			return;
		}

		if (preg_match_all('/(no-common-ligatures|\bcommon-ligatures|no-discretionary-ligatures|\bdiscretionary-ligatures|no-historical-ligatures|\bhistorical-ligatures|no-contextual|\bcontextual)/i', $value, $m)) {
			$this->properties['FONT-VARIANT-LIGATURES'] = implode(' ', $m[1]);
		}

		if (preg_match('/(all-small-caps|\bsmall-caps|all-petite-caps|\bpetite-caps|unicase|titling-caps)/i', $value, $m)) {
			$this->properties['FONT-VARIANT-CAPS'] = $m[1];
		}

		if (preg_match_all('/(lining-nums|oldstyle-nums|proportional-nums|tabular-nums|diagonal-fractions|stacked-fractions)/i', $value, $m)) {
			$this->properties['FONT-VARIANT-NUMERIC'] = implode(' ', $m[1]);
		}

		if (preg_match('/(historical-forms)/i', $value, $m)) {
			$this->properties['FONT-VARIANT-ALTERNATES'] = $m[1];
		}
	}

	/**
	 * Process FONT-FAMILY property.
	 *
	 * Try and parse the property (including malformed values).
	 * This could take the shape of valid properties like:
	 * - "Times New Roman", Times, serif
	 * - serif
	 * - dejavusans, 'DejaVu Sans Condensed'
	 *
	 * And invalid properties like:
	 * - dejavusans 'DejaVu Sans' (missing comma)
	 * - 'DejaVu Sans' dejavusans (missing comma)
	 * - "DejaVu Sans" dejavusans (missing comma)
	 * - dejavusans sans-serif (missing comma)
	 *
	 * @param string $propertyKey Property key
	 * @param string $value Font family value
	 * @return void
	 */
	protected function processFontFamilyProperty($propertyKey, $value)
	{
		foreach (explode(',', $value) as $entry) {

			// The whole entry is one family name, even unquoted with spaces (`DejaVu Sans Mono`); failing that, try to
			// parse invalid properties
			$candidates = [str_replace(' ', '', trim($entry, " \t\n\r\0\x0B\"'"))];
			if (preg_match_all('/"([^"]*)"|\'([^\']*)\'/', $entry, $matches)) { // `"DejaVu Sans" 'Helvetica Neue'` → ['DejaVu Sans', 'Helvetica Neue']
				foreach ($matches[0] as $i => $_unused) {
					$inner = $matches[1][$i] !== '' ? $matches[1][$i] : $matches[2][$i];
					$candidates[] = str_replace(' ', '', $inner);
				}
			}

			$unquoted = preg_replace('/"[^"]*"|\'[^\']*\'/', ' ', $entry); // `dejavusans 'DejaVu Sans' helvetica` → "dejavusans   helvetica"
			foreach (preg_split('/\s+/', trim($unquoted)) as $word) { // `dejavusans   helvetica` → ['dejavusans', 'helvetica']
				if ($word !== '') {
					$candidates[] = $word;
				}
			}

			foreach ($candidates as $candidate) {
				$candidate = strtolower(trim($candidate, " \t\n\r\0\x0B\"'"));
				if (empty($candidate)) {
					continue;
				}

				if (isset($this->mpdf->fontdata[$candidate]) ||
					in_array($candidate, $this->mpdf->available_unifonts, true) ||
					in_array($candidate, $this->mpdf->sans_fonts, true) ||
					in_array($candidate, $this->mpdf->serif_fonts, true) ||
					in_array($candidate, $this->mpdf->mono_fonts, true) ||
					in_array($candidate, ['ccourier', 'ctimes', 'chelvetica'], true) ||
					($this->mpdf->onlyCoreFonts && in_array($candidate, ['courier', 'times', 'helvetica', 'arial'], true)) ||
					in_array($candidate, ['sjis', 'uhc', 'big5', 'gb'], true)
				) {
					$this->properties[$propertyKey] = $candidate;
					return;
				}
			}
		}
	}

	/**
	 * Process BORDER shorthand and individual border properties.
	 *
	 * Handles BORDER, BORDER-TOP, BORDER-RIGHT, BORDER-BOTTOM, BORDER-LEFT properties
	 * by normalizing them to consistent "width style color" format.
	 *
	 * @param string $propertyKey Property key (BORDER, BORDER-TOP, etc.)
	 * @param string $value Property value
	 * @return void
	 */
	protected function processBorderProperty($propertyKey, $value)
	{
		switch ($propertyKey) {
			case 'BORDER':
				$value = $value !== '1' ? $this->normalizeBorderString($value) : '1px solid #000000';

				$this->properties['BORDER-TOP'] = $value;
				$this->properties['BORDER-RIGHT'] = $value;
				$this->properties['BORDER-BOTTOM'] = $value;
				$this->properties['BORDER-LEFT'] = $value;
				break;

			case 'BORDER-TOP':
				$this->properties['BORDER-TOP'] = $this->normalizeBorderString($value);
				break;

			case 'BORDER-RIGHT':
				$this->properties['BORDER-RIGHT'] = $this->normalizeBorderString($value);
				break;

			case 'BORDER-BOTTOM':
				$this->properties['BORDER-BOTTOM'] = $this->normalizeBorderString($value);
				break;

			case 'BORDER-LEFT':
				$this->properties['BORDER-LEFT'] = $this->normalizeBorderString($value);
				break;
		}
	}

	/**
	 * Process border shorthand properties (style, width, color).
	 *
	 * Handles BORDER-STYLE, BORDER-WIDTH, BORDER-COLOR, BORDER-SPACING.
	 *
	 * @param string $key Property key
	 * @param string $value Property value
	 * @return void
	 */
	protected function processBorderShorthandProperty($key, $value)
	{
		if ($key === 'BORDER-STYLE') {
			$e = $this->expandShorthandProperty($value);
			if (empty($e)) {
				return;
			}

			$this->properties['BORDER-TOP-STYLE'] = $e['T'];
			$this->properties['BORDER-RIGHT-STYLE'] = $e['R'];
			$this->properties['BORDER-BOTTOM-STYLE'] = $e['B'];
			$this->properties['BORDER-LEFT-STYLE'] = $e['L'];
		} elseif ($key === 'BORDER-WIDTH') {
			$e = $this->expandShorthandProperty($value);
			if (empty($e)) {
				return;
			}

			$this->properties['BORDER-TOP-WIDTH'] = $e['T'];
			$this->properties['BORDER-RIGHT-WIDTH'] = $e['R'];
			$this->properties['BORDER-BOTTOM-WIDTH'] = $e['B'];
			$this->properties['BORDER-LEFT-WIDTH'] = $e['L'];

		} elseif ($key === 'BORDER-COLOR') {
			$e = $this->expandShorthandProperty($value);
			if (empty($e)) {
				return;
			}

			$this->properties['BORDER-TOP-COLOR'] = $e['T'];
			$this->properties['BORDER-RIGHT-COLOR'] = $e['R'];
			$this->properties['BORDER-BOTTOM-COLOR'] = $e['B'];
			$this->properties['BORDER-LEFT-COLOR'] = $e['L'];
		} elseif ($key === 'BORDER-SPACING') {
			$prop = preg_split('/\s+/', trim($value));
			if (count($prop) === 1) {
				$this->properties['BORDER-SPACING-H'] = $prop[0];
				$this->properties['BORDER-SPACING-V'] = $prop[0];
			} elseif (count($prop) === 2) {
				$this->properties['BORDER-SPACING-H'] = $prop[0];
				$this->properties['BORDER-SPACING-V'] = $prop[1];
			}
		}
	}

	/**
	 * Parse CSS background shorthand property.
	 *
	 * Extracts background color, image, repeat, and position from the
	 * background shorthand property. Supports  gradients and url() images.
	 *
	 * @param string $s Background property value
	 * @return array Array with keys 'c' (color), 'i' (image), 'r' (repeat), 'p' (position)
	 */
	protected function parseCssBackground($s)
	{
		$background = [
			'c' => false, // color
			'i' => false, // image
			'r' => false, // repeat
			'p' => false, // position
		];

		if (preg_match('/(-moz-)*(repeating-)*(linear|radial)-gradient\(.*\)/i', $s, $m)) {
			$background['i'] = $m[0];
			return $background;
		}

		if (preg_match('/url\(/i', $s)) {
			// If color, set and strip it off
			if (preg_match('/^\s*(#[0-9a-fA-F]{3,6}|(rgba|rgb|device-cmyka|cmyka|device-cmyk|cmyk|hsla|hsl|spot)\(.*?\)|[a-zA-Z]{3,})\s+(url\(.*)/i', $s, $m)) {
				$background['c'] = strtolower($m[1]);
				$s = $m[3];
			}

			if (preg_match('/url\([\'\"]{0,1}(.*?)[\'\"]{0,1}\)\s*(.*)/i', $s, $m)) {
				$background['i'] = $m[1];
				$s = strtolower($m[2]);
				if (preg_match('/(repeat-x|repeat-y|no-repeat|repeat)/', $s, $m)) {
					$background['r'] = $m[1];
				}

				// Remove repeat, attachment (discarded) and also any inherit
				$s = preg_replace('/(repeat-x|repeat-y|no-repeat|repeat|scroll|fixed|inherit)/', '', $s);
				$bits = preg_split('/\s+/', trim($s));

				$normalizedPosition = $this->normalizeBackgroundPosition($bits);
				if ($normalizedPosition !== false) {
					$background['p'] = $normalizedPosition;
				}
			}

			return $background;
		}

		if (preg_match('/^\s*(#[0-9a-fA-F]{3,6}|(rgba|rgb|device-cmyka|cmyka|device-cmyk|cmyk|hsla|hsl|spot)\(.*?\)|[a-zA-Z]{3,})/i', $s, $m)) {
			$background['c'] = strtolower($m[1]);
		}

		return $background;
	}

	/**
	 * Expand 1-4 value CSS property into top/right/bottom/left components.
	 *
	 * Handles CSS properties that can be specified with 1-4 values following
	 * the standard CSS clockwise pattern (top, right, bottom, left).
	 * Used for margin, padding, border-width, border-style, and border-color.
	 *
	 * @param string $value Property value(s) separated by spaces
	 * @return array Associative array with keys 'T', 'R', 'B', 'L'
	 */
	protected function expandShorthandProperty($value)
	{
		$property = preg_split('/\s+/', trim($value));

		switch (count($property)) {
			case 0:
				return [];
			case 1:
				return [
					'T' => $property[0],
					'R' => $property[0],
					'B' => $property[0],
					'L' => $property[0]
				];
			case 2:
				return [
					'T' => $property[0],
					'R' => $property[1],
					'B' => $property[0],
					'L' => $property[1]
				];
			case 3:
				return [
					'T' => $property[0],
					'R' => $property[1],
					'B' => $property[2],
					'L' => $property[1]
				];
			default:
				// Ignore rule parts after first 4 values (most likely !important)
				return [
					'T' => $property[0],
					'R' => $property[1],
					'B' => $property[2],
					'L' => $property[3]
				];
		}
	}

	/**
	 * Expand border-radius properties.
	 *
	 * Processes border-radius CSS properties and expands them into horizontal
	 * and vertical components for each corner (TL, TR, BL, BR).
	 *
	 * @param string $val Border radius value(s)
	 * @param string $k Property name (BORDER-RADIUS or specific corner)
	 * @return array Array with keys like 'TL-H', 'TL-V', etc.
	 */
	protected function expandBorderRadius($val, $k)
	{
		if ($k === 'BORDER-RADIUS') {
			return $this->parseBorderRadiusShorthand($val);
		}

		return $this->parseBorderRadiusCorner($val, $k);
	}

	/**
	 * Parse individual border-radius corner values.
	 *
	 * Helper method for expandBorderRadius to parse values for a specific corner.
	 *
	 * @param string $val Border radius value(s)
	 * @param string $k Property name (specific corner)
	 * @return array Array with keys like 'TL-H', 'TL-V', etc.
	 */
	protected function parseBorderRadiusCorner($val, $k)
	{
		$b = [];
		$prop = preg_split('/\s+/', trim($val));

		if (count($prop) === 1) {
			$h = $v = $val;
		} else {
			$h = $prop[0];
			$v = $prop[1];
		}

		if ($h === 0 || $v === 0) {
			$h = $v = 0;
		}

		if ($k === 'BORDER-TOP-LEFT-RADIUS') {
			$b['TL-H'] = $h;
			$b['TL-V'] = $v;
		} elseif ($k === 'BORDER-TOP-RIGHT-RADIUS') {
			$b['TR-H'] = $h;
			$b['TR-V'] = $v;
		} elseif ($k === 'BORDER-BOTTOM-LEFT-RADIUS') {
			$b['BL-H'] = $h;
			$b['BL-V'] = $v;
		} elseif ($k === 'BORDER-BOTTOM-RIGHT-RADIUS') {
			$b['BR-H'] = $h;
			$b['BR-V'] = $v;
		}

		return $b;
	}

	/**
	 * Parse border-radius shorthand values.
	 *
	 * Parses the slash syntax (horizontal/vertical) and expands 1-4 values
	 * into individual corner components.
	 *
	 * @param string $val Border radius value(s)
	 * @return array Array with keys 'TL-H', 'TR-H', 'BR-H', 'BL-H', 'TL-V', 'TR-V', 'BR-V', 'BL-V'
	 */
	protected function parseBorderRadiusShorthand($val)
	{
		$border = [];
		$radius = explode('/', trim($val));
		$properties = preg_split('/\s+/', trim($radius[0]));

		// single radius
		if (count($properties) === 1) {
			$border['TL-H'] = $border['TR-H'] = $border['BR-H'] = $border['BL-H'] = $properties[0];
		} elseif (count($properties) === 2) {
			$border['TL-H'] = $border['BR-H'] = $properties[0];
			$border['TR-H'] = $border['BL-H'] = $properties[1];
		} elseif (count($properties) === 3) {
			$border['TL-H'] = $properties[0];
			$border['TR-H'] = $border['BL-H'] = $properties[1];
			$border['BR-H'] = $properties[2];
		} elseif (count($properties) === 4) {
			$border['TL-H'] = $properties[0];
			$border['TR-H'] = $properties[1];
			$border['BR-H'] = $properties[2];
			$border['BL-H'] = $properties[3];
		}

		// Check for two radius e.g. 10px / 20px
		if (count($radius) === 2) {
			$properties = preg_split('/\s+/', trim($radius[1]));
			if (count($properties) === 1) {
				$border['TL-V'] = $border['TR-V'] = $border['BR-V'] = $border['BL-V'] = $properties[0];
			} elseif (count($properties) === 2) {
				$border['TL-V'] = $border['BR-V'] = $properties[0];
				$border['TR-V'] = $border['BL-V'] = $properties[1];
			} elseif (count($properties) === 3) {
				$border['TL-V'] = $properties[0];
				$border['TR-V'] = $border['BL-V'] = $properties[1];
				$border['BR-V'] = $properties[2];
			} elseif (count($properties) === 4) {
				$border['TL-V'] = $properties[0];
				$border['TR-V'] = $properties[1];
				$border['BR-V'] = $properties[2];
				$border['BL-V'] = $properties[3];
			}

			return $border;
		}

		$border['TL-V'] = Arrays::get($border, 'TL-H', 0);
		$border['TR-V'] = Arrays::get($border, 'TR-H', 0);
		$border['BL-V'] = Arrays::get($border, 'BL-H', 0);
		$border['BR-V'] = Arrays::get($border, 'BR-H', 0);

		return $border;
	}

	/**
	 * Normalize background position values.
	 *
	 * Converts background position keywords (top, bottom, left, right, center)
	 * to percentage values and validates the format.
	 *
	 * @param array $bits Position components (1 or 2 values)
	 * @return string|false Normalized position string or false if invalid
	 */
	protected function normalizeBackgroundPosition($bits)
	{
		$position = '';

		$numOfBits = count($bits);
		if ($numOfBits === 1) {
			if (false !== strpos($bits[0], 'bottom')) {
				$position = '50% 100%';
			} elseif (false !== strpos($bits[0], 'top')) {
				$position = '50% 0%';
			} else {
				$position = $bits[0] . ' 50%';
			}
		} elseif ($numOfBits === 2) {
			// Can be either right center or center right
			if (preg_match('/(top|bottom)/', $bits[0]) || preg_match('/(left|right)/', $bits[1])) {
				$position = $bits[1] . ' ' . $bits[0];
			} else {
				$position = $bits[0] . ' ' . $bits[1];
			}
		}

		if (empty($position)) {
			return false;
		}

		$position = preg_replace('/(left|top)/', '0%', $position);
		$position = preg_replace('/(right|bottom)/', '100%', $position);
		$position = preg_replace('/(center)/', '50%', $position);

		if (!preg_match('/[\-]{0,1}\d+(in|cm|mm|pt|pc|em|ex|px|%)* [\-]{0,1}\d+(in|cm|mm|pt|pc|em|ex|px|%)*/', $position)) {
			return false;
		}

		return $position;
	}

	/**
	 * Parse and normalize border shorthand property.
	 *
	 * Converts border shorthand syntax into standardized "width style color" format.
	 * Handles various input formats and orders.
	 *
	 * @param string $bd Border property value
	 * @return string Normalized border string in format "width style color"
	 */
	protected function normalizeBorderString($bd)
	{
		preg_match_all("/\((.*?)\)/", $bd, $m);
		foreach ($m[1] as $i => $value) {
			$sub = str_replace(' ', '', $m[1][$i]);
			$bd = str_replace($m[1][$i], $sub, $bd);
		}

		$prop = preg_split('/\s+/', trim($bd));
		if (count($prop) > 3) {
			return '';
		}

		$parts = $this->parseBorderParts($prop);
		$w = $parts['w'];
		$s = $parts['s'];
		$c = $parts['c'];

		$s = strtolower($s);

		return $w . ' ' . $s . ' ' . $c;
	}

	/**
	 * Parse border property parts (width, style, color).
	 *
	 * Helper method for normalizeBorderString to determine width, style, and color
	 * from split border property string.
	 *
	 * @param array $prop Split border property string
	 * @return array Array containing 'w' (width), 's' (style), 'c' (color)
	 */
	protected function parseBorderParts($prop)
	{
		$width = 'medium';
		$color = '#000000';
		$style = 'none';

		switch (count($prop)) {
			case 1:
				if (in_array($prop[0], $this->mpdf->borderstyles, true) || $prop[0] === 'none' || $prop[0] === 'hidden') {
					$style = $prop[0];
				} elseif (is_array($this->colorConverter->convert($prop[0], $this->mpdf->PDFAXwarnings))) {
					$color = $prop[0];
				} else {
					$width = $prop[0];
				}
				break;

			case 2:
				if (in_array($prop[1], $this->mpdf->borderstyles, true) || $prop[1] === 'none' || $prop[1] === 'hidden') {
					$width = $prop[0];
					$style = $prop[1];
				} elseif (in_array($prop[0], $this->mpdf->borderstyles, true) || $prop[0] === 'none' || $prop[0] === 'hidden') {
					$style = $prop[0];
					$color = $prop[1];
				} else {
					$width = $prop[0];
					$color = $prop[1];
				}
				break;

			case 3:
				if (0 === strpos($prop[0], '#')) {
					$color = $prop[0];
					$width = $prop[1];
					$style = $prop[2];
				} elseif (substr($prop[0], 1, 1) === '#') {
					$style = $prop[0];
					$color = $prop[1];
					$width = $prop[2];
				} elseif (in_array($prop[0], $this->mpdf->borderstyles) || $prop[0] === 'none' || $prop[0] === 'hidden') {
					$style = $prop[0];
					$width = $prop[1];
					$color = $prop[2];
				} else {
					$width = $prop[0];
					$style = $prop[1];
					$color = $prop[2];
				}
				break;
		}

		return ['w' => $width, 's' => $style, 'c' => $color];
	}

	/**
	 * Process background related CSS properties.
	 *
	 * Handles BACKGROUND, BACKGROUND-IMAGE, BACKGROUND-REPEAT, and BACKGROUND-POSITION.
	 *
	 * @param string $property Property name
	 * @param string $value Property value
	 * @return void
	 */
	protected function processBackgroundProperty($property, $value)
	{
		switch ($property) {
			case 'BACKGROUND':
				$bg = $this->parseCssBackground($value);
				if ($bg['c']) {
					$this->properties['BACKGROUND-COLOR'] = $bg['c'];
				} else {
					$this->properties['BACKGROUND-COLOR'] = 'transparent';
				}

				if ($bg['i']) {
					$this->properties['BACKGROUND-IMAGE'] = $bg['i'];
					if ($bg['r']) {
						$this->properties['BACKGROUND-REPEAT'] = $bg['r'];
					}
					if ($bg['p']) {
						$this->properties['BACKGROUND-POSITION'] = $bg['p'];
					}
				} else {
					$this->properties['BACKGROUND-IMAGE'] = '';
				}
				break;

			case 'BACKGROUND-IMAGE':
				if (preg_match('/(-moz-)*(repeating-)*(linear|radial)-gradient\(.*\)/i', $value, $m)) {
					$this->properties['BACKGROUND-IMAGE'] = $m[0];
					return;
				}

				if (preg_match('/url\([\'\"]{0,1}(.*?)[\'\"]{0,1}\)/i', $value, $m)) {
					$this->properties['BACKGROUND-IMAGE'] = $m[1];
				} elseif (strtolower($value) === 'none') {
					$this->properties['BACKGROUND-IMAGE'] = '';
				}
				break;

			case 'BACKGROUND-REPEAT':
				if (preg_match('/(repeat-x|repeat-y|no-repeat|repeat)/i', $value, $m)) {
					$this->properties['BACKGROUND-REPEAT'] = strtolower($m[1]);
				}
				break;

			case 'BACKGROUND-POSITION':
				$bits = preg_split('/\s+/', trim($value));
				$normalizedPosition = $this->normalizeBackgroundPosition($bits);
				if ($normalizedPosition !== false) {
					$this->properties['BACKGROUND-POSITION'] = $normalizedPosition;
				}
				break;
		}
	}

	/**
	 * Process border radius property.
	 *
	 * @param string $property Property name
	 * @param string $value Property value
	 * @return void
	 */
	protected function processBorderRadiusProperty($property, $value)
	{
		$borderRadius = $this->expandBorderRadius($value, $property);

		if (isset($borderRadius['TL-H'])) {
			$this->properties['BORDER-TOP-LEFT-RADIUS-H'] = $borderRadius['TL-H'];
		}

		if (isset($borderRadius['TL-V'])) {
			$this->properties['BORDER-TOP-LEFT-RADIUS-V'] = $borderRadius['TL-V'];
		}

		if (isset($borderRadius['TR-H'])) {
			$this->properties['BORDER-TOP-RIGHT-RADIUS-H'] = $borderRadius['TR-H'];
		}

		if (isset($borderRadius['TR-V'])) {
			$this->properties['BORDER-TOP-RIGHT-RADIUS-V'] = $borderRadius['TR-V'];
		}

		if (isset($borderRadius['BL-H'])) {
			$this->properties['BORDER-BOTTOM-LEFT-RADIUS-H'] = $borderRadius['BL-H'];
		}

		if (isset($borderRadius['BL-V'])) {
			$this->properties['BORDER-BOTTOM-LEFT-RADIUS-V'] = $borderRadius['BL-V'];
		}

		if (isset($borderRadius['BR-H'])) {
			$this->properties['BORDER-BOTTOM-RIGHT-RADIUS-H'] = $borderRadius['BR-H'];
		}

		if (isset($borderRadius['BR-V'])) {
			$this->properties['BORDER-BOTTOM-RIGHT-RADIUS-V'] = $borderRadius['BR-V'];
		}
	}

	/**
	 * Process text outline CSS properties.
	 *
	 * Handles TEXT-OUTLINE shorthand.
	 *
	 * @param string $v Property value
	 * @return void
	 */
	protected function processTextOutlineProperty($v)
	{
		$prop = preg_split('/\s+/', trim($v));

		if (strtolower(trim($v)) === 'none') {
			$this->properties['TEXT-OUTLINE'] = 'none';
		} elseif (count($prop) === 2) {
			$this->properties['TEXT-OUTLINE-WIDTH'] = $prop[0];
			$this->properties['TEXT-OUTLINE-COLOR'] = $prop[1];
		} elseif (count($prop) === 3) {
			$this->properties['TEXT-OUTLINE-WIDTH'] = $prop[0];
			$this->properties['TEXT-OUTLINE-COLOR'] = $prop[2];
		}
	}

	/**
	 * Process page size CSS properties.
	 *
	 * Handles SIZE and SHEET-SIZE properties.
	 *
	 * @param string $property Property name
	 * @param string $value Property value
	 * @return void
	 */
	protected function processPageSizeProperty($property, $value)
	{
		$value = preg_split('/\s+/', trim($value));

		switch ($property) {
			case 'SIZE':
				if (count($value) === 1 && in_array($value[0], ['auto', 'portrait', 'landscape'], true)) {
					$this->properties['SIZE'] = strtoupper($value[0]);
				} elseif (preg_grep('/^[a-z]/', $value)) {
					$this->processPageSizeName($value);
				} elseif (count($value) === 1) {
					$this->properties['SIZE']['W'] = $this->sizeConverter->convert($value[0]);
					$this->properties['SIZE']['H'] = $this->sizeConverter->convert($value[0]);
				} elseif (count($value) === 2) {
					$this->properties['SIZE']['W'] = $this->sizeConverter->convert($value[0]);
					$this->properties['SIZE']['H'] = $this->sizeConverter->convert($value[1]);
				}
				break;

			case 'SHEET-SIZE':
				if (count($value) === 2) {
					$this->properties['SHEET-SIZE'] = [
						$this->sizeConverter->convert($value[0]),
						$this->sizeConverter->convert($value[1])
					];
				} else {
					if (preg_match('/([0-9a-zA-Z]*)-L/i', $value[0], $m)) { // e.g. A4-L = A$ landscape
						$ft = PageFormat::getSizeFromName($m[1]);
						$format = [$ft[1], $ft[0]];
					} else {
						$format = PageFormat::getSizeFromName($value[0]);
					}

					if ($format) {
						$this->properties['SHEET-SIZE'] = [$format[0] / Mpdf::SCALE, $format[1] / Mpdf::SCALE];
					}
				}
				break;
		}
	}

	/**
	 * Set the sheet from a page-size name such as "a4", "letter" or "a5 landscape"
	 *
	 * The sheet is portrait unless "landscape" is given. A value that is not one name, with at most one orientation,
	 * is dropped.
	 *
	 * @param string[] $value The words of the size property
	 * @return void
	 */
	private function processPageSizeName(array $value)
	{
		$orientation = array_values(array_intersect($value, ['portrait', 'landscape']));
		$name = array_values(array_diff($value, $orientation));

		if (count($name) !== 1 || count($orientation) > 1) {
			return;
		}

		try {
			$format = PageFormat::getSizeFromName($name[0]);
		} catch (MpdfException $e) {
			return;
		}

		$sheet = [min($format) / Mpdf::SCALE, max($format) / Mpdf::SCALE];

		$this->properties['SHEET-SIZE'] = $orientation === ['landscape'] ? array_reverse($sheet) : $sheet;
		$this->properties['SIZE'] = 'AUTO';
	}

	/**
	 * Process image orientation CSS properties.
	 *
	 * Handles IMAGE-ORIENTATION property.
	 *
	 * @param string $v Property value
	 * @return void
	 */
	protected function processImageOrientationProperty($v)
	{
		if (!preg_match('/([\-]*[0-9.]+)(deg|grad|rad)/i', $v, $m)) {
			return;
		}

		$angle = (float) $m[1];

		if (strtolower($m[2]) === 'grad') {
			$angle *= (360 / 400);
		} elseif (strtolower($m[2]) === 'rad') {
			$angle = rad2deg($angle);
		}

		while ($angle < 0) {
			$angle += 360;
		}

		$angle %= 360;
		$angle /= 90;
		$angle = round($angle) * 90;

		$this->properties['IMAGE-ORIENTATION'] = $angle;
	}

	/**
	 * Process text align CSS properties.
	 *
	 * Handles TEXT-ALIGN property including decimal alignment.
	 *
	 * @param string $k Property name
	 * @param string $v Property value
	 * @return void
	 */
	protected function processTextAlignProperty($k, $v)
	{
		if (preg_match('/["\'](.){1}["\']/i', $v, $m)) {
			$d = array_search($m[1], $this->mpdf->decimal_align);

			if ($d !== false) {
				$this->properties['TEXT-ALIGN'] = $d;
			}
			if (preg_match('/(center|left|right)/i', $v, $m)) {
				$this->properties['TEXT-ALIGN'] .= strtoupper(substr($m[1], 0, 1));
			} else {
				$this->properties['TEXT-ALIGN'] .= 'R';
			} // default = R
		} elseif (preg_match('/["\'](\\\\[a-fA-F0-9]{1,6})["\']/i', $v, $m)) {
			$utf8 = UtfString::codeHex2utf(substr($m[1], 1, 6));
			$d = array_search($utf8, $this->mpdf->decimal_align);

			if ($d !== false) {
				$this->properties['TEXT-ALIGN'] = $d;
			}

			if (preg_match('/(center|left|right)/i', $v, $m)) {
				$this->properties['TEXT-ALIGN'] .= strtoupper(substr($m[1], 0, 1));
			} else {
				$this->properties['TEXT-ALIGN'] .= 'R';
			} // default = R
		} else {
			$this->properties[$k] = $v;
		}
	}

	/**
	 * Process list style CSS properties.
	 *
	 * Handles LIST-STYLE property.
	 *
	 * @param string $v Property value
	 * @return void
	 */
	protected function processListStyleProperty($v)
	{
		if (preg_match('/none/i', $v, $m)) {
			$this->properties['LIST-STYLE-TYPE'] = 'none';
			$this->properties['LIST-STYLE-IMAGE'] = 'none';
		}

		if (preg_match('/(lower-roman|upper-roman|lower-latin|lower-alpha|lower-greek|upper-latin|upper-alpha|decimal|disc|circle|square|arabic-indic|bengali|devanagari|gujarati|gurmukhi|kannada|malayalam|oriya|persian|tamil|telugu|thai|urdu|cambodian|khmer|lao|cjk-decimal|hebrew)/i', $v, $m)) {
			$this->properties['LIST-STYLE-TYPE'] = strtolower(trim($m[1]));
		} elseif (preg_match('/U\+([a-fA-F0-9]+)/i', $v, $m)) {
			$this->properties['LIST-STYLE-TYPE'] = strtolower(trim($m[1]));
		}

		if (preg_match('/url\([\'\"]{0,1}(.*?)[\'\"]{0,1}\)/i', $v, $m)) {
			$this->properties['LIST-STYLE-IMAGE'] = trim($m[1]);
		}
	}
}
