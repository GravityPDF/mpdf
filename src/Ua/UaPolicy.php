<?php

namespace Mpdf\Ua;

/**
 * Which link targets a PDF/UA document refuses: script schemes such as javascript: and
 * data: URLs carrying active content, in an <a>, an <area> or a link annotation.
 */
class UaPolicy
{

	/**
	 * Schemes no PDF reader runs, which a screen reader can only announce as a dead link
	 * (Matterhorn 28-002). livescript:, mocha: and vbs: are old aliases for script, and
	 * view-source: means nothing outside a browser.
	 */
	private static $deniedSchemes = [
		'javascript',
		'vbscript',
		'vbs',
		'livescript',
		'mocha',
		'view-source',
	];

	/**
	 * The data: types that carry active content; an image such as data:image/png is allowed
	 */
	private static $deniedDataMimes = [
		'text/html',
		'application/x-javascript',
		'application/javascript',
		'application/ecmascript',
		'application/xhtml+xml',
		'image/svg+xml',
	];

	/**
	 * Whether an href uses a denied scheme or data: type, however it is disguised: by case,
	 * leading whitespace or invisible characters, HTML entities, percent-encoding, or a space
	 * before the colon. file: is left alone, being a question of privacy and not of access.
	 *
	 * @param string|null $href As written in the HTML
	 *
	 * @return bool
	 */
	public static function isPolicyBlockedHref($href)
	{
		if ($href === null || $href === '') {
			return false;
		}

		$normalised = self::normaliseHref((string) $href);
		if ($normalised === '') {
			return false;
		}

		if (strncmp($normalised, 'data:', 5) === 0) {
			$mime = substr($normalised, 5);
			$parts = preg_split('/[;,]/', $mime, 2);
			$mime = trim($parts[0]);
			return in_array($mime, self::$deniedDataMimes, true);
		}

		// Dropping whatever a scheme may not contain (RFC 3986) undoes `java\nscript:`,
		// `j a v a s c r i p t :` and a NUL hidden in the name
		$colonPos = strpos($normalised, ':');
		if ($colonPos === false || $colonPos === 0) {
			return false;
		}
		$schemeRaw = substr($normalised, 0, $colonPos);
		$scheme = preg_replace('/[^a-z0-9+\-.]/', '', $schemeRaw);
		if (!is_string($scheme) || $scheme === '') {
			return false;
		}
		return in_array($scheme, self::$deniedSchemes, true);
	}

	/**
	 * The href as the deny-lists compare it: entities decoded, percent-decoded once (as readers
	 * do), leading invisible characters stripped and lowercased. It is never written to the PDF.
	 *
	 * @param string $href
	 *
	 * @return string
	 */
	private static function normaliseHref($href)
	{
		// htmlspecialchars_decode() knows only five entities; &Tab;, &NewLine; and &#58; need the full table
		$decoded = html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8');

		if (strpos($decoded, '%') !== false) {
			$once = rawurldecode($decoded);
			if (is_string($once)) {
				$decoded = $once;
			}
		}

		// C0 controls and whitespace, DEL, C1 controls, NBSP, ZWSP/ZWNJ/ZWJ and BOM
		$pattern = '/^[\x{0000}-\x{0020}\x{007F}-\x{00A0}\x{200B}-\x{200D}\x{FEFF}]+/u';
		$stripped = preg_replace($pattern, '', $decoded);
		if (!is_string($stripped)) {
			// Not valid UTF-8
			$stripped = ltrim($decoded, " \t\r\n\v\f\0");
		}

		// Before PHP 8 strtolower() follows the locale, which under tr_TR and the like could let
		// a scheme slip past the lowercase lists
		return strtr(
			$stripped,
			'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
			'abcdefghijklmnopqrstuvwxyz'
		);
	}

	/**
	 * An href for an exception or warning, cut short with an ellipsis when it is long
	 *
	 * @param string $href
	 * @param int    $maxLen
	 *
	 * @return string
	 */
	public static function formatHrefForMessage($href, $maxLen = 200)
	{
		$href = (string) $href;
		if (strlen($href) <= $maxLen) {
			return $href;
		}
		return substr($href, 0, $maxLen) . '...';
	}
}
