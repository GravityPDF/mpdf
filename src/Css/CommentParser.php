<?php

namespace Mpdf\Css;

class CommentParser
{
	/**
	 * A <script> element, for a regular expression with the s and i modifiers
	 */
	const SCRIPT = '<script\b.*?<\/script\s*>';

	/**
	 * Remove mPDF-specific and general HTML comments from content.
	 *
	 * Removes the <!--mpdf and mpdf--> markers, keeping what is between them, then every HTML comment, leaving nothing
	 * in its place, as a browser does. A <script> element's content is text, so a comment marker in it opens no comment.
	 *
	 * @param string $html HTML content to clean
	 * @return string HTML with comments removed
	 */
	public function removeHtmlComments($html)
	{
		$html = preg_replace('/<!--mpdf|mpdf-->/i', '', $html);

		if (!preg_match('/<!--/', $html)) {
			return $html;
		}

		// (*SKIP)(*F) steps over a script whole, so the search for a comment carries on after it
		return preg_replace('/' . self::SCRIPT . '(*SKIP)(*F)|<!--.*?-->/si', '', $html);
	}

	/**
	 * Remove the HTML comment markers from style blocks.
	 *
	 * Replaces each <!-- and --> in <style> tag contents with a space, so removeHtmlComments() does not take the
	 * stylesheet for a comment. CSS comments are left for StylesheetTokenizer::removeComments(), which knows a comment
	 * marker inside a string is not one.
	 *
	 * @param string $html HTML content with style tags
	 * @return string HTML with cleaned style blocks
	 */
	public function removeCommentsFromStyleBlocks($html)
	{
		preg_match_all('/<style.*?>(.*?)<\/style>/si', $html, $m);
		if (count($m[1]) === 0) {
			return $html;
		}

		foreach ($m[1] as $style) {
			$sub = '>' . str_replace(['<!--', '-->'], ' ', $style) . '</style>';
			$html = str_replace('>' . $style . '</style>', $sub, $html);
		}

		return $html;
	}
}
