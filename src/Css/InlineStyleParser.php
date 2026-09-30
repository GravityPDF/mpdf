<?php

namespace Mpdf\Css;

class InlineStyleParser
{
	/**
	 * @var NormalizeProperties
	 */
	private $normalizeProperties;

	/**
	 * @var StylesheetTokenizer
	 */
	private $tokenizer;

	public function __construct(NormalizeProperties $normalizeProperties)
	{
		$this->normalizeProperties = $normalizeProperties;
		$this->tokenizer = new StylesheetTokenizer();
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
		return $this->parseDeclarations($this->tokenizer->declarations(htmlspecialchars_decode($html)));
	}

	/**
	 * The properties a list of declarations sets, whether from a style attribute or a stylesheet's block
	 *
	 * @param string[][] $declarations Each [name, value], as StylesheetTokenizer::declarations() gives them
	 * @return array Parsed CSS properties
	 */
	public function parseDeclarations(array $declarations)
	{
		$classproperties = [];
		foreach ($declarations as $declaration) {
			$property = strtoupper(trim($declaration[0], " \t\n\r\0\x0B\f"));
			$value = trim(preg_replace('/\s*!important/i', '', $this->processUrlsInCss($declaration[1])));

			if (empty($property) || $value === '') {
				continue;
			}

			// Ignores -webkit-gradient so doesn't override -moz-
			if (($property === 'BACKGROUND-IMAGE' || $property === 'BACKGROUND') && false !== stripos($value, '-webkit-gradient')) {
				continue;
			}

			// Dropped before it can replace an earlier declaration of the property in the same list
			if (!$this->normalizeProperties->canParse($property, $value)) {
				continue;
			}

			// A repeated property moves to its last place, so it is expanded after a shorthand written before it
			unset($classproperties[$property]);
			$classproperties[$property] = $value;
		}

		return $this->normalizeProperties->normalize($classproperties);
	}

	/**
	 * Rewrite each url() as url('...'), with the characters that would break the parsing after it encoded.
	 *
	 * The URL is read as CSS reads it: in either quote, with the other quote allowed inside, or unquoted, without the
	 * whitespace around it. A backslash before a quote, a parenthesis or whitespace is dropped. Other backslashes
	 * are kept, as a Windows path is full of them. Parentheses and braces are percent-encoded.
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

				return "url('" . str_replace(['(', ')', '{', '}'], ['%28', '%29', '%7B', '%7D'], $url) . "')";
			},
			$css
		);
	}
}
