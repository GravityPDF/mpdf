<?php

namespace Mpdf\Css;

class InlineStyleParser
{
	/**
	 * @var NormalizeProperties
	 */
	private $normalizeProperties;

	public function __construct(NormalizeProperties $normalizeProperties)
	{
		$this->normalizeProperties = $normalizeProperties;
	}

	/**
	 * Parse inline CSS style attribute.
	 *
	 * Parses a CSS string from an HTML style attribute and returns
	 * an array of CSS properties.
	 *
	 * @param string $html CSS string from style attribute
	 * @return array Parsed CSS properties
	 */
	public function parse($html)
	{
		$html = htmlspecialchars_decode($html); // mPDF 5.7.4 URLs
		// mPDF 5.7.4 URLs
		// Characters "(", ")", and ";" in url() e.g. background-image, cause problems parsing the CSS string
		// URLencode ( and ), but change ";" to a code which can be converted back after parsing (so as not to confuse ;
		// with a segment delimiter in the URI)
		$html = $this->processUrlsInCss($html);

		// Fix incomplete CSS code
		$size = strlen($html) - 1;
		if (substr($html, $size, 1) !== ';') {
			$html .= ';';
		}

		// Make CSS[Name-of-the-class] = array(key => value)
		$regexp = '|\\s*?(\\S+?):(.+?);|i';
		preg_match_all($regexp, $html, $styleinfo);
		$properties = $styleinfo[1];
		$values = $styleinfo[2];

		// Array-properties and Array-values must have the SAME SIZE!
		$classproperties = [];
		$properties_count = count($properties);
		for ($i = 0; $i < $properties_count; $i++) {

			// Ignores -webkit-gradient so doesn't override -moz-
			if ((strtoupper($properties[$i]) === 'BACKGROUND-IMAGE' || strtoupper($properties[$i]) === 'BACKGROUND') && false !== stripos($values[$i], '-webkit-gradient')) {
				continue;
			}

			// Dropped before it can replace an earlier declaration of the property in the same attribute
			if (!$this->normalizeProperties->canParse(strtoupper($properties[$i]), $values[$i])) {
				continue;
			}

			$values[$i] = str_replace('%ZZ', ';', $values[$i]); // mPDF 5.7.4 URLs
			// A repeated property moves to its last place, so it is expanded after a shorthand written before it
			$property = strtoupper($properties[$i]);
			unset($classproperties[$property]);
			$classproperties[$property] = trim(preg_replace('/\s*!important/i', '', $values[$i]));
		}

		return $this->normalizeProperties->normalize($classproperties);
	}

	/**
	 * Rewrite each url() as url('...'), with the characters that would break the parsing after it encoded.
	 *
	 * The URL is read as CSS reads it: in either quote, with the other quote allowed inside, or unquoted, without the
	 * whitespace around it. A backslash before a quote, a parenthesis or whitespace is dropped. Other backslashes
	 * are kept, as a Windows path is full of them. Parentheses and braces are percent-encoded, and ";" becomes the
	 * placeholder %ZZ, which the caller turns back once the declarations are split.
	 *
	 * @param string $css CSS string containing url() references
	 * @return string CSS string with processed URLs
	 */
	public function processUrlsInCss($css)
	{
		if (stripos($css, 'url(') === false) {
			return $css;
		}

		// Possessive, so a long data URI does not run PCRE out of stack one character at a time
		return preg_replace_callback(
			'/url\(\s*(?:"((?:[^"\\\\]++|\\\\.)*+)"|\'((?:[^\'\\\\]++|\\\\.)*+)\'|((?:[^)\\\\]++|\\\\.)*+))\s*\)/is',
			function ($m) {
				$url = isset($m[3]) ? rtrim($m[3]) : (isset($m[2]) ? $m[2] : $m[1]);
				$url = preg_replace('/\\\\(["\'()\s])/', '$1', $url);

				return "url('" . str_replace(['(', ')', '{', '}', ';'], ['%28', '%29', '%7B', '%7D', '%ZZ'], $url) . "')";
			},
			$css
		);
	}
}
