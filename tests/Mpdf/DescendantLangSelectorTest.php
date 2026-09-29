<?php

namespace Mpdf;

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Descendant rules whose last part names a language, such as `div :lang(fr)`, `div [lang=fr]` or `div p:lang(fr)`,
 * in block content and in tables.
 *
 * A regional language such as fr-CA falls back to the rule for its short code, fr, when no rule names it in full,
 * as the simple lang rules do.
 */
class DescendantLangSelectorTest extends TestCase
{

	use PageStreams;

	const GREEN = '0.000 0.502 0.000 rg';
	const RED = '1.000 0.000 0.000 rg';
	const BLACK = '0.000 g';

	/**
	 * The text is drawn in the colour the rules leave it in
	 *
	 * @dataProvider rules
	 *
	 * @param string $css
	 * @param string $html
	 * @param string $expected The fill colour the text should be drawn in
	 */
	public function testTheRuleColoursTheText($css, $html, $expected)
	{
		$colours = $this->textColours('<style>' . $css . '</style>' . $html);

		$this->assertArrayHasKey('text', $colours);
		$this->assertSame($expected, $colours['text']);
	}

	/**
	 * A stylesheet, a document for it, and the colour its text should be drawn in
	 *
	 * @return array[]
	 */
	public function rules()
	{
		return [
			':lang() on its own' => [
				'div :lang(fr) { color: green; }',
				'<div><p lang="fr">text</p></div>',
				self::GREEN,
			],
			'[lang] on its own' => [
				'div [lang=fr] { color: green; }',
				'<div><p lang="fr">text</p></div>',
				self::GREEN,
			],
			'a tag with :lang()' => [
				'div p:lang(fr) { color: green; }',
				'<div><p lang="fr">text</p></div>',
				self::GREEN,
			],
			'a tag with [lang]' => [
				'div p[lang=fr] { color: green; }',
				'<div><p lang="fr">text</p></div>',
				self::GREEN,
			],
			'an inline element' => [
				'div :lang(fr) { color: green; }',
				'<div><span lang="fr">text</span></div>',
				self::GREEN,
			],
			'a table cell' => [
				'table :lang(fr) { color: green; }',
				'<table><tr><td lang="fr">text</td></tr></table>',
				self::GREEN,
			],
			'a table cell, with a tag' => [
				'table td:lang(fr) { color: green; }',
				'<table><tr><td lang="fr">text</td></tr></table>',
				self::GREEN,
			],
			'an element in a table cell' => [
				'td :lang(fr) { color: green; }',
				'<table><tr><td><span lang="fr">text</span></td></tr></table>',
				self::GREEN,
			],
			'a regional language, falling back to its short code' => [
				'div :lang(fr) { color: green; }',
				'<div><p lang="fr-CA">text</p></div>',
				self::GREEN,
			],
			'a regional language, falling back to its short code, with a tag' => [
				'div p:lang(fr) { color: green; }',
				'<div><p lang="fr-CA">text</p></div>',
				self::GREEN,
			],
			'a regional language in a table cell, falling back to its short code' => [
				'table td:lang(fr) { color: green; }',
				'<table><tr><td lang="fr-CA">text</td></tr></table>',
				self::GREEN,
			],
			'a regional language named in full, over its short code' => [
				'div :lang(fr-ca) { color: green; } div :lang(fr) { color: red; }',
				'<div><p lang="fr-CA">text</p></div>',
				self::GREEN,
			],
			'a regional language named only by a deeper rule, falling back to its short code' => [
				'div :lang(fr-ca) span { color: red; } div :lang(fr) { color: green; }',
				'<div><p lang="fr-CA">text</p></div>',
				self::GREEN,
			],
			'another language' => [
				'div :lang(fr) { color: red; }',
				'<div><p lang="de">text</p></div>',
				self::BLACK,
			],
			'a short code, against a rule for a regional language' => [
				'div :lang(fr-ca) { color: red; }',
				'<div><p lang="fr">text</p></div>',
				self::BLACK,
			],
			'no language' => [
				'div :lang(fr) { color: red; }',
				'<div><p>text</p></div>',
				self::BLACK,
			],
			'another language in a table cell' => [
				'table :lang(fr) { color: red; }',
				'<table><tr><td lang="de">text</td></tr></table>',
				self::BLACK,
			],
			'a tag with :lang() after a class' => [
				'div .c { color: red; } div p:lang(fr) { color: green; }',
				'<div><p class="c" lang="fr">text</p></div>',
				self::GREEN,
			],
			'an id after :lang()' => [
				'div #i { color: green; } div :lang(fr) { color: red; }',
				'<div><p id="i" lang="fr">text</p></div>',
				self::GREEN,
			],
		];
	}

}
