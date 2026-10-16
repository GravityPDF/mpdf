<?php

namespace Mpdf\Css;

use Mpdf\Html\Tags;

class CommentParser
{
	/**
	 * A <script> element, for a regular expression with the s and i modifiers
	 */
	const SCRIPT = '<script\b.*?<\/script' . Tags::END_TAG_REST_PATTERN;

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
		$html = Tags::replaceOutsideTags($html, '<!--mpdf|mpdf-->', '', 'i');

		if (!preg_match('/<!--/', $html)) {
			return $html;
		}

		// A script is put back as it was, so the search for a comment carries on after it
		return Tags::replaceOutsideTags($html, '(' . self::SCRIPT . ')|<!--.*?-->', '\\1', 'si');
	}

	/**
	 * Remove the HTML comment markers from the CSS of a style block.
	 *
	 * Replaces each <!-- and --> with a space, so removeHtmlComments() does not take the stylesheet for a comment. CSS
	 * comments are left for StylesheetTokenizer::removeComments(), which knows a comment marker inside a string is not
	 * one.
	 *
	 * @param string $css
	 * @return string
	 */
	public function removeCommentMarkers($css)
	{
		return str_replace(['<!--', '-->'], ' ', $css);
	}

	/**
	 * Remove the HTML comment markers from the CSS of each style block in HTML, as removeCommentMarkers() does
	 *
	 * @param string $html
	 * @return string
	 */
	public function removeCommentsFromStyleBlocks($html)
	{
		if (stripos($html, '<style') === false) {
			return $html;
		}

		return preg_replace_callback('/' . Tags::elementPattern('style') . '/si', function ($m) {
			return '<' . $m[1] . '>' . $this->removeCommentMarkers($m[2]) . '</style>';
		}, $html);
	}
}
