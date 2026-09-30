<?php

namespace Mpdf\Css;

use Mpdf\Mpdf;
use Mpdf\SizeConverter;
use Psr\Log\NullLogger;

/**
 * The pass that unwraps, keeps or removes each at-rule before a stylesheet is split into rules at each brace
 */
class AtRuleProcessorTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * @var AtRuleProcessor
	 */
	private $processor;

	/**
	 * A processor matching @media against the default medium, print
	 */
	protected function set_up()
	{
		parent::set_up();

		$mpdf = new Mpdf();
		$sizeConverter = new SizeConverter($mpdf->dpi, $mpdf->default_font_size, $mpdf, new NullLogger());
		$this->processor = new AtRuleProcessor(new MediaQueryProcessor($mpdf, $sizeConverter));
	}

	/**
	 * Release the processor and its document
	 */
	protected function tear_down()
	{
		unset($this->processor);

		parent::tear_down();
	}

	/**
	 * Each at-rule is unwrapped, kept or removed whole, and the rule after it is left as it was
	 *
	 * @dataProvider stylesheets
	 *
	 * @param string $css
	 * @param string $expected The stylesheet left, with its whitespace collapsed
	 */
	public function testProcess($css, $expected)
	{
		$this->assertSame($expected, trim(preg_replace('/\s+/', ' ', $this->processor->process($css))));
	}

	/**
	 * Stylesheets with an at-rule in them, and what is left of each
	 *
	 * @return array[]
	 */
	public function stylesheets()
	{
		return [
			'no at-rule' => ['p { color: red }', 'p { color: red }'],

			'@charset' => ['@charset "UTF-8"; p { color: red }', 'p { color: red }'],
			'@namespace' => ['@namespace svg url(http://www.w3.org/2000/svg); p { color: red }', 'p { color: red }'],
			'@import' => ['@import url("a.css") screen; @import "b.css"; p { color: red }', 'p { color: red }'],
			'@layer statement' => ['@layer base, theme; p { color: red }', 'p { color: red }'],

			'@keyframes' => ['@keyframes spin { from { opacity: 0 } to { opacity: 1 } } p { color: red }', 'p { color: red }'],
			'prefixed @keyframes' => ['@-webkit-keyframes spin { 0% { opacity: 0 } } p { color: red }', 'p { color: red }'],
			'@container' => ['@container (min-width: 1px) { h1 { color: blue } } p { color: red }', 'p { color: red }'],
			'@font-feature-values' => ['@font-feature-values Font { @swash { fancy: 1 } } p { color: red }', 'p { color: red }'],
			'@font-face' => ['@font-face { font-family: x; src: url(x.ttf) } p { color: red }', 'p { color: red }'],
			'an empty unknown block' => ['@unknown {} p { color: red }', 'p { color: red }'],

			'@supports' => ['@supports (display: grid) { h1 { color: blue } } p { color: red }', 'h1 { color: blue } p { color: red }'],
			'@supports not' => ['@supports not (display: grid) { h1 { color: blue } } p { color: red }', 'p { color: red }'],
			'@supports with not inside it' => ['@supports (display: grid) and (not (display: inline-grid)) { h1 { color: blue } }', 'h1 { color: blue }'],
			'@layer block' => ['@layer base { h1 { color: blue } } p { color: red }', 'h1 { color: blue } p { color: red }'],
			'@layer block with no name' => ['@layer { h1 { color: blue } }', 'h1 { color: blue }'],

			'@media for print' => ['@media print { h1 { color: blue } } p { color: red }', 'h1 { color: blue } p { color: red }'],
			'@media for screen' => ['@media screen { h1 { color: blue } } p { color: red }', 'p { color: red }'],
			'@media in upper case' => ['@MEDIA print { h1 { color: blue } }', 'h1 { color: blue }'],
			'an empty @media block' => ['@media print {} p { color: red }', 'p { color: red }'],
			'@media inside @supports' => ['@supports (display: grid) { @media print { h1 { color: blue } } @media screen { h2 { color: blue } } }', 'h1 { color: blue }'],
			'@supports inside @media' => ['@media print { @supports (display: grid) { h1 { color: blue } } h2 { color: blue } }', 'h1 { color: blue } h2 { color: blue }'],
			'@media inside @media' => ['@media print { @media all { h1 { color: blue } } }', 'h1 { color: blue }'],
			'@keyframes inside @media' => ['@media print { @keyframes spin { from { opacity: 0 } } h1 { color: blue } }', 'h1 { color: blue }'],

			'@page' => ['@page { margin: 10mm } p { color: red }', '@page { margin: 10mm } p { color: red }'],
			'@page with a pseudo page' => ['@page :first { margin-top: 10mm }', '@page :first { margin-top: 10mm }'],
			'@page with margin boxes' => [
				'@page { margin: 10mm; @top-center { content: "x" } margin-bottom: 20mm; @bottom-left { content: "y" } } p { color: red }',
				'@page { margin: 10mm; margin-bottom: 20mm;} p { color: red }',
			],
			'@page with an at sign in a declaration' => [
				'@page { background: url(a@2x.png); @top-center { content: "x" } }',
				'@page { background: url(a@2x.png);}',
			],

			'a brace in a string in a prelude' => ['@supports (content: "}") { h1 { color: blue } } p { color: red }', 'h1 { color: blue } p { color: red }'],
			'a brace in a string in a removed block' => ['@keyframes x { from { content: "}" } } p { color: red }', 'p { color: red }'],
			'a brace in a string in an unwrapped block' => ['@layer { h1 { content: \'{\' } } p { color: red }', 'h1 { content: \'{\' } p { color: red }'],
			'a quote escaped in a string' => ['@keyframes x { from { content: "\"}" } } p { color: red }', 'p { color: red }'],
			'a semicolon in a string in a statement' => ['@import "a;b.css"; p { color: red }', 'p { color: red }'],
			'an escaped brace' => ['@keyframes x { from { content: \} } } p { color: red }', 'p { color: red }'],
			'an at sign in a declaration' => ['p { background: url(a@2x.png) } h1 { color: blue }', 'p { background: url(a@2x.png) } h1 { color: blue }'],
			'a string left open at the end of a line' => ["@keyframes x { from { content: \"} } }\n} } p { color: red }", 'p { color: red }'],

			'a block left open' => ['p { color: red } @keyframes x { from { opacity: 0 }', 'p { color: red }'],
			'a statement left open' => ['p { color: red } @import "a.css"', 'p { color: red }'],
		];
	}
}
