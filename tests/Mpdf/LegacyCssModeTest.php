<?php

namespace Mpdf;

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Legacy mode parses and applies CSS as mPDF v7 did, so a rule whose selector only the selector matcher reads does
 * nothing: child and sibling combinators, structural pseudo-classes, attribute selectors other than lang, :lang()
 * through an ancestor, descendant rules through inline ancestors, and :not(), :is() and :where(). Standard mode
 * applies them.
 */
class LegacyCssModeTest extends TestCase
{

	use DrawnStyles;

	const RED = '1.000 0.000 0.000 rg';
	const BLACK = '0.000 g';

	/**
	 * In legacy mode the rules of the selector tests are dropped, so each piece of text they name is drawn as it is
	 * with no stylesheet
	 *
	 * @dataProvider selectorCases
	 *
	 * @param string $css
	 * @param string $html
	 * @param array<string, string> $expected Keyed by the pieces of text to look at
	 */
	public function testLegacyModeDropsRulesOnlyTheMatcherReads($css, $html, array $expected)
	{
		$plain = array_intersect_key($this->drawnColours($html, ['cssMode' => CssMode::LEGACY]), $expected);

		$this->assertDrawnInColours($plain, $this->drawnColours('<style>' . $css . '</style>' . $html, ['cssMode' => CssMode::LEGACY]));
	}

	/**
	 * The cases of the selector tests whose rules only the matcher reads, each drawn in standard mode by its own test
	 *
	 * @return array[] Each [css, html, expected colours]
	 */
	public function selectorCases()
	{
		$providers = [
			'structural' => [new StructuralSelectorTest(), 'selectors'],
			'inline ancestor' => [new InlineAncestorSelectorTest(), 'selectors'],
			'attribute' => [new AttributeSelectorTest(), 'selectors'],
			'language' => [new AttributeSelectorTest(), 'languages'],
			'logical' => [new LogicalSelectorTest(), 'selectors'],
		];

		$cases = [];
		foreach ($providers as $prefix => $provider) {
			foreach (call_user_func($provider) as $name => $case) {
				$cases[$prefix . ': ' . $name] = array_slice($case, 0, 3);
			}
		}

		$readable = array_intersect_key($cases, array_flip(self::legacyReadable()));
		$this->assertCount(count(self::legacyReadable()), $readable, 'A case the legacy parser reads was renamed');

		return array_diff_key($cases, $readable);
	}

	/**
	 * The cases above whose rules the legacy parser reads too, which legacy mode applies as it did before
	 *
	 * @return string[] Their names, as selectorCases() prefixes them
	 */
	private static function legacyReadable()
	{
		return [
			'structural: table cells',
			'structural: a tag the legacy parser reads keeps its own rule, the matcher adds to it',
			'inline ancestor: a class on a block ancestor, as before',
			'attribute: a value with a comma and a space in the same list as another selector',
			'language: its own, as before',
			'language: inside an inline element, from a rule the legacy parser reads',
		];
	}

	/**
	 * Each selector only the matcher reads, in each context: standard mode colours the target, legacy mode leaves it
	 * as it is with no stylesheet. The document is closed, so its header and footer are drawn on the page
	 *
	 * @dataProvider selectorsInContexts
	 *
	 * @param string $context
	 * @param string $selector
	 * @param string $html The target, with the elements the selector names around it
	 */
	public function testOnlyStandardModeAppliesTheRule($context, $selector, $html)
	{
		$document = '<style>' . $selector . ' { color: #f00; }</style>' . $this->inContext($context, $html);

		foreach ([CssMode::STANDARD => self::RED, CssMode::LEGACY => self::BLACK] as $mode => $colour) {
			$mpdf = $this->drawDocument($document, ['cssMode' => $mode]);
			$mpdf->OutputBinaryData();

			$this->assertSame($colour, $this->keyedByText($mpdf, $mpdf->drawnColours)['target'], $mode);
			$this->assertDrawnInContext($context, $mpdf, ['target']);
		}
	}

	/**
	 * Every selector in every context
	 *
	 * @return array[]
	 */
	public function selectorsInContexts()
	{
		$selectors = [
			'child' => ['div > p.t', '<div><p class="t">target</p></div>'],
			'adjacent sibling' => ['h2 + p.t', '<div><h2>heading</h2><p class="t">target</p></div>'],
			'general sibling' => ['h2 ~ p.t', '<div><h2>heading</h2><p>between</p><p class="t">target</p></div>'],
			'first-child' => ['p.t:first-child', '<div><p class="t">target</p><p>second</p></div>'],
			'nth-child' => ['p:nth-child(2)', '<div><p>first</p><p>target</p></div>'],
			'nth-of-type' => ['p:nth-of-type(1)', '<div><h2>heading</h2><p>target</p></div>'],
			'an attribute' => ['p[data-x]', '<div><p data-x="1">target</p></div>'],
			'an attribute value' => ['p[title^="ta"]', '<div><p title="tag">target</p></div>'],
			'an inherited language' => ['p:lang(fr)', '<div lang="fr"><p>target</p></div>'],
			'an inline ancestor' => ['span.w b', '<p><span class="w">x <b>target</b></span></p>'],
			':not()' => ['p:not(.x)', '<div><p>target</p></div>'],
			':is()' => [':is(div, section) > p', '<div><p>target</p></div>'],
			':where()' => [':where(div) p', '<div><p>target</p></div>'],
		];

		$data = [];
		foreach (['block', 'table cell', 'header', 'footer', 'positioned block', 'kept block', 'forced page break'] as $context) {
			foreach ($selectors as $name => $selector) {
				$data[$context . ': ' . $name] = [$context, $selector[0], $selector[1]];
			}
		}

		return $data;
	}
}
