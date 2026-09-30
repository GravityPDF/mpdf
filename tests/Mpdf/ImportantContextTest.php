<?php

namespace Mpdf;

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Declarations marked !important, under each value of cssMode, wherever the element is: a block, an inline element or
 * a table cell, in a header or footer, in a positioned block, in a block laid out twice because it is kept together,
 * and after a forced page break inside a block. The standard cascade applies them after the inline style; the legacy
 * cascade reads them as any other declaration.
 */
class ImportantContextTest extends TestCase
{

	use DrawnStyles;

	const RED = '1.000 0.000 0.000 rg';
	const BLUE = '0.000 0.000 1.000 rg';
	const GREEN = '0.000 0.502 0.000 rg';
	const BLACK = '0.000 g';

	/**
	 * Each subject is drawn in the colour the cascade leaves it in: an element the important rule reaches, and a near
	 * miss it does not. The document is closed, so its header and footer are drawn on the page, and each subject is
	 * checked to be drawn in the context the case names
	 *
	 * @dataProvider casesInContexts
	 *
	 * @param string $cascade legacy or standard
	 * @param string $context
	 * @param string $css With {T} for the subjects' tag
	 * @param array[] $subjects As cases() gives them
	 */
	public function testTheImportantDeclarationWinsInItsLayer($cascade, $context, $css, array $subjects)
	{
		$expected = [];
		foreach ($subjects as $text => $subject) {
			$expected[$text] = $subject[$cascade === CssMode::LEGACY ? 1 : 2];
		}

		$mpdf = $this->drawDocument($this->document($context, $css, $subjects), ['cssMode' => $cascade]);
		$mpdf->OutputBinaryData();

		$this->assertDrawnInColours($expected, $this->keyedByText($mpdf, $mpdf->drawnColours));
		$this->assertDrawnInContext($context, $mpdf, array_keys($expected));
	}

	/**
	 * Every case in every context, under each cascade
	 *
	 * @return array[]
	 */
	public function casesInContexts()
	{
		$data = [];
		foreach ([CssMode::LEGACY, CssMode::STANDARD] as $cascade) {
			foreach (['block', 'inline', 'table cell', 'header', 'footer', 'positioned block', 'kept block', 'forced page break'] as $context) {
				foreach ($this->cases() as $name => $case) {
					$data[$cascade . ', ' . $context . ': ' . $name] = [$cascade, $context, $case[0], $case[1]];
				}
			}
		}

		return $data;
	}

	/**
	 * Rules with an important declaration, and the elements they are matched against. Every subject is inside an
	 * element of class w
	 *
	 * @return array[] Each [css, subjects]: each subject keyed by its text, with its attributes, the colour the legacy
	 *                 cascade draws it in, as measured, and the colour the standard cascade draws it in
	 */
	private function cases()
	{
		return [
			'an important class beats an id' => [
				'.c { color: #008000 !important; } #i { color: #f00; }',
				['both' => ['id="i" class="c"', self::RED, self::GREEN], 'id only' => ['id="i"', self::RED, self::RED]],
			],
			'an important rule beats the inline style' => [
				'.c { color: #008000 !important; }',
				['both' => ['class="c" style="color: #f00"', self::RED, self::GREEN], 'inline only' => ['style="color: #f00"', self::RED, self::RED]],
			],
			'an important inline style beats an important rule' => [
				'#i { color: #f00 !important; }',
				[
					'important inline' => ['id="i" style="color: #008000 !important"', self::GREEN, self::GREEN],
					'plain inline' => ['id="i" style="color: #00f"', self::BLUE, self::RED],
				],
			],
			'the more specific of two important rules wins' => [
				'.c.d { color: #008000 !important; } {T}.c { color: #f00 !important; }',
				['both' => ['class="c d"', self::RED, self::GREEN], 'one class' => ['class="c"', self::RED, self::RED]],
			],
			'the later of two important rules as specific wins' => [
				'.b { color: #f00 !important; } .a { color: #008000 !important; } .b { color: #00f; }',
				['b then a' => ['class="b a"', self::BLUE, self::GREEN], 'b alone' => ['class="b"', self::BLUE, self::RED]],
			],
			'an important descendant rule beats an id' => [
				'.w {T}.c { color: #008000 !important; } #i { color: #f00; }',
				['both' => ['id="i" class="c"', self::GREEN, self::GREEN], 'id only' => ['id="i"', self::RED, self::RED]],
			],
			'an important rule in a matching @media' => [
				'@media print { .c { color: #008000 !important; } } @media screen { .c { color: #00f !important; } } #i { color: #f00; }',
				['both' => ['id="i" class="c"', self::RED, self::GREEN], 'id only' => ['id="i"', self::RED, self::RED]],
			],
			'the flag in capitals, spaced from the bang' => [
				'.c { color: #008000 ! IMPORTANT; } #i { color: #f00; }',
				['both' => ['id="i" class="c"', self::RED, self::GREEN], 'id only' => ['id="i"', self::RED, self::RED]],
			],
			'an important declaration beats a later one of its block' => [
				'.c { color: #008000 !important; color: #f00; }',
				['with the class' => ['class="c"', self::RED, self::GREEN], 'without it' => ['', self::BLACK, self::BLACK]],
			],
		];
	}

	/**
	 * A document with each subject inside an element of class w, in a context, under a stylesheet naming the tag the
	 * context gives them
	 *
	 * @param string $context
	 * @param string $css With {T} for the subjects' tag
	 * @param array[] $subjects As cases() gives them
	 *
	 * @return string
	 */
	private function document($context, $css, array $subjects)
	{
		$tag = $context === 'inline' ? 'em' : ($context === 'table cell' ? 'td' : 'p');

		$html = '';
		foreach ($subjects as $text => $subject) {
			$html .= '<' . $tag . ' ' . $subject[0] . '>' . $text . '</' . $tag . '> ';
		}

		if ($context === 'inline') {
			$html = '<p>' . $html . '</p>';
		} elseif ($context === 'table cell') {
			$html = '<table><tr>' . $html . '</tr></table>';
		}

		$html = '<div class="w">' . $html . '</div>';

		// A table cell and an inline element are the subjects' own tags, in the flow
		return '<style>' . str_replace('{T}', $tag, $css) . '</style>' . ($context === 'table cell' ? $html : $this->inContext($context, $html));
	}
}
