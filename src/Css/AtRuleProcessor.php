<?php

namespace Mpdf\Css;

/**
 * Settles the at-rules in a stylesheet before it is split into rules at each brace.
 *
 * The rules in a matching @media block, and in @supports and @layer blocks, are unwrapped into the stylesheet. An
 * @supports condition is taken to pass, as its rules are unwrapped, so an @supports not block, the fallback for
 * engines without a feature, is removed. @page rules are kept, less any margin boxes nested in them. Every other
 * at-rule is removed whole, with its block if it has one: splitting at each brace would otherwise end the at-rule at
 * its first inner brace and take the rest of it as the start of the next rule.
 */
class AtRuleProcessor
{

	/**
	 * @var MediaQueryProcessor
	 */
	private $mediaQueryProcessor;

	/**
	 * @param MediaQueryProcessor $mediaQueryProcessor
	 */
	public function __construct(MediaQueryProcessor $mediaQueryProcessor)
	{
		$this->mediaQueryProcessor = $mediaQueryProcessor;
	}

	/**
	 * Unwrap, keep or remove each at-rule in a list of rules, copying the other rules as they are
	 *
	 * @param string $css A stylesheet with its comments removed
	 * @return string The stylesheet with only plain rules and @page rules left
	 */
	public function process($css)
	{
		if (strpos($css, '@') === false) {
			return $css;
		}

		$processed = '';
		$length = strlen($css);
		$pos = 0;

		while ($pos < $length) {
			$at = $this->find($css, $pos, '@');
			if ($at >= $length || $css[$at] === '}') {
				$processed .= substr($css, $pos, $at + 1 - $pos);
				$pos = $at + 1;
				continue;
			}

			$processed .= substr($css, $pos, $at - $pos) . ' ';
			list($name, $prelude, $body, $end) = $this->readAtRule($css, $at);
			if ($body !== null) {
				$processed .= $this->processBlock($name, $prelude, $body) . ' ';
			}

			$pos = $end + 1;
		}

		return $processed;
	}

	/**
	 * What a block at-rule leaves in the stylesheet
	 *
	 * @param string $name The at-rule's name in lower case, without the @
	 * @param string $prelude What comes between the name and the block
	 * @param string $body What is inside the block
	 * @return string
	 */
	private function processBlock($name, $prelude, $body)
	{
		switch ($name) {
			case 'media':
				return $this->mediaQueryProcessor->matches($prelude) ? $this->process($body) : '';

			case 'supports':
				return preg_match('/^not\b/i', $prelude) ? '' : $this->process($body);

			case 'layer':
				return $this->process($body);

			case 'page':
				return '@page ' . $prelude . ' {' . $this->removeNestedAtRules($body) . '}';
		}

		return '';
	}

	/**
	 * Remove the at-rules from a block of declarations, such as the margin boxes in an @page rule
	 *
	 * @param string $declarations
	 * @return string
	 */
	private function removeNestedAtRules($declarations)
	{
		if (strpos($declarations, '@') === false) {
			return $declarations;
		}

		$length = strlen($declarations);
		$kept = '';
		$pos = 0;

		while ($pos < $length) {
			$pos += strspn($declarations, " \t\n\r\f", $pos);
			if ($pos >= $length) {
				break;
			}

			if ($declarations[$pos] === '@') {
				list(, , , $end) = $this->readAtRule($declarations, $pos);
			} else {
				$end = $this->find($declarations, $pos, ';');
				$kept .= ' ' . substr($declarations, $pos, $end + 1 - $pos);
			}

			$pos = $end + 1;
		}

		return $kept;
	}

	/**
	 * Read the at-rule starting at $at
	 *
	 * @param string $css
	 * @param int $at The position of the @
	 * @return array The name in lower case, the prelude, and the block's content or null for a statement at-rule,
	 *               then the position of the semicolon or brace that ends it
	 */
	private function readAtRule($css, $at)
	{
		$open = $this->find($css, $at, ';{');
		if ($open >= strlen($css) || $css[$open] !== '{') {
			return [null, null, null, $open];
		}

		$close = $this->find($css, $open + 1, '');
		preg_match('/^@([-\w]*)(.*)$/s', substr($css, $at, $open - $at), $m);

		return [strtolower($m[1]), trim($m[2]), substr($css, $open + 1, $close - $open - 1), $close];
	}

	/**
	 * The position of the first of $chars at or after $pos that is outside any string or nested block
	 *
	 * A closing brace that is not nested in the search ends it as well, since it closes the block being searched. The
	 * length of the stylesheet is returned if neither is found.
	 *
	 * @param string $css
	 * @param int $pos
	 * @param string $chars
	 * @return int
	 */
	private function find($css, $pos, $chars)
	{
		$length = strlen($css);
		$stops = $chars . '{}"\'\\';
		$depth = 0;

		while ($pos < $length) {
			$pos += strcspn($css, $stops, $pos);
			if ($pos >= $length) {
				break;
			}

			$char = $css[$pos];
			if ($depth === 0 && ($char === '}' || strpos($chars, $char) !== false)) {
				return $pos;
			}

			if ($char === '"' || $char === '\'') {
				$pos = $this->endOfString($css, $pos);
			} elseif ($char === '\\') {
				$pos++;
			} elseif ($char === '{') {
				$depth++;
			} elseif ($char === '}') {
				$depth--;
			}

			$pos++;
		}

		return $length;
	}

	/**
	 * The position of the quote that closes the string opening at $pos, or of the line break or end of the stylesheet
	 * that ends it unclosed
	 *
	 * @param string $css
	 * @param int $pos
	 * @return int
	 */
	private function endOfString($css, $pos)
	{
		$length = strlen($css);
		$quote = $css[$pos];

		while (++$pos < $length) {
			$pos += strcspn($css, $quote . "\\\n\r\f", $pos);
			if ($pos >= $length || $css[$pos] !== '\\') {
				return $pos;
			}

			$pos++;
		}

		return $length;
	}
}
