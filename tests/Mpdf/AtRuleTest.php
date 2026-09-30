<?php

namespace Mpdf;

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * At-rules other than @media in a stylesheet: statement at-rules such as @charset and @import, and block at-rules such
 * as @supports, @layer and @keyframes.
 *
 * Each is unwrapped or removed whole, so the rule after it still applies rather than being taken as the end of it.
 */
class AtRuleTest extends TestCase
{

	use PageStreams;

	const GREEN = '0.000 1.000 0.000 rg';
	const BLACK = '0.000 g';

	/**
	 * The rule after an at-rule colours the paragraph
	 *
	 * @dataProvider atRules
	 *
	 * @param string $atRule
	 */
	public function testTheRuleAfterAnAtRuleApplies($atRule)
	{
		$this->assertSame(['after' => self::GREEN], $this->textColours('<style>' . $atRule . ' p { color: #00ff00; }</style><p>after</p>'));
	}

	/**
	 * At-rules that took the rule after them as part of themselves
	 *
	 * @return array[]
	 */
	public function atRules()
	{
		return [
			'@charset' => ['@charset "UTF-8";'],
			'@namespace' => ['@namespace svg url(http://www.w3.org/2000/svg);'],
			'@import' => ['@import url(missing.css);'],
			'@layer statement' => ['@layer base, theme;'],
			'@supports' => ['@supports (display: grid) { h1 { color: #ff0000; } }'],
			'@supports not' => ['@supports not (display: grid) { h1 { color: #ff0000; } }'],
			'@layer' => ['@layer base { h1 { color: #ff0000; } }'],
			'@keyframes' => ['@keyframes spin { from { opacity: 0; } to { opacity: 1; } }'],
			'@container' => ['@container (min-width: 1px) { h1 { color: #ff0000; } }'],
			'@font-feature-values' => ['@font-feature-values Font { @swash { fancy: 1; } }'],
			'@page with a margin box' => ['@page { @top-center { content: "x"; } }'],
			'a brace in a string' => ['@supports (content: "}") { h1 { content: "{"; } }'],
		];
	}

	/**
	 * The rules in @supports and @layer blocks apply, including those in an @media block for print inside them
	 *
	 * @dataProvider unwrappedBlocks
	 *
	 * @param string $css
	 */
	public function testTheRulesInsideAnUnwrappedBlockApply($css)
	{
		$this->assertSame(['inside' => self::GREEN], $this->textColours('<style>' . $css . '</style><p>inside</p>'));
	}

	/**
	 * Blocks whose rules apply
	 *
	 * @return array[]
	 */
	public function unwrappedBlocks()
	{
		return [
			'@supports' => ['@supports (display: block) { p { color: #00ff00; } }'],
			'@layer' => ['@layer base { p { color: #00ff00; } }'],
			'@media inside @supports' => ['@supports (display: block) { @media print { p { color: #00ff00; } } }'],
			'@supports inside @media' => ['@media print { @supports (display: block) { p { color: #00ff00; } } }'],
		];
	}

	/**
	 * The rules in an @supports not block, a fallback for engines without a feature, and in an @media block for
	 * another medium inside an @supports block, do not apply
	 *
	 * @dataProvider droppedBlocks
	 *
	 * @param string $css
	 */
	public function testTheRulesInsideADroppedBlockDoNotApply($css)
	{
		$this->assertSame(['inside' => self::BLACK], $this->textColours('<style>' . $css . '</style><p>inside</p>'));
	}

	/**
	 * Blocks whose rules do not apply
	 *
	 * @return array[]
	 */
	public function droppedBlocks()
	{
		return [
			'@supports not' => ['@supports not (display: grid) { p { color: #00ff00; } }'],
			'@media for screen inside @supports' => ['@supports (display: block) { @media screen { p { color: #00ff00; } } }'],
			'@keyframes' => ['@keyframes p { from { color: #00ff00; } }'],
		];
	}
}
