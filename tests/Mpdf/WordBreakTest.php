<?php

namespace Mpdf;

use Mpdf\Color\ColorConverter;
use Mpdf\Color\ColorModeConverter;
use Mpdf\Color\ColorSpaceRestrictor;
use Mpdf\Css\NormalizeProperties;
use Mpdf\Css\WordBreak;
use Psr\Log\NullLogger;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * word-break, overflow-wrap and word-wrap under cssMode standard. With neither property set, a word too long for its
 * line is cut at the edge, as before. Once either is set the word overflows the line unless they let it break, as in
 * a browser: overflow-wrap: break-word and anywhere cut it at the edge, word-break: break-all fills every line to the
 * edge, and keep-all keeps CJK letters together. In a table cell anywhere, break-all and word-break: break-word narrow
 * the column to its widest letter. Under cssMode legacy the properties are ignored.
 *
 * Each test reads the lines from the text Cell() drew and where it drew it.
 */
class WordBreakTest extends TestCase
{

	/**
	 * A URL too long for a 30mm line, with no place to break in it
	 */
	const URL = 'https://example.com/documents/annual-report-2026.pdf';

	/**
	 * Japanese text with no spaces, in which the first full stop is past the end of a 30mm line
	 */
	const CJK = '日本語の文章を書きます。これは長い文です。';

	/**
	 * The lines of a URL in a 30mm block, under each value of the properties
	 *
	 * @dataProvider providerBlocks
	 *
	 * @param string $css The block's declarations
	 * @param string $expected overflows, cut or filled
	 */
	public function testAUrlInABlock($css, $expected)
	{
		$lines = $this->lines('<div style="width: 30mm; ' . $css . '">see ' . self::URL . ' now</div>');

		$this->assertUrlLines($expected, $lines);
	}

	/**
	 * @return array[] Declarations, and whether the URL overflows the line, is cut at its edge, or fills the first line
	 */
	public function providerBlocks()
	{
		return [
			'neither set' => ['', 'cut'],
			'word-break: normal' => ['word-break: normal', 'overflows'],
			'word-break: keep-all' => ['word-break: keep-all', 'overflows'],
			'word-break: break-all' => ['word-break: break-all', 'filled'],
			'word-break: break-word' => ['word-break: break-word', 'cut'],
			'overflow-wrap: normal' => ['overflow-wrap: normal', 'overflows'],
			'overflow-wrap: break-word' => ['overflow-wrap: break-word', 'cut'],
			'overflow-wrap: anywhere' => ['overflow-wrap: anywhere', 'cut'],
			'word-wrap: normal' => ['word-wrap: normal', 'overflows'],
			'word-wrap: break-word' => ['word-wrap: break-word', 'cut'],
			'keep-all with overflow-wrap: anywhere' => ['word-break: keep-all; overflow-wrap: anywhere', 'cut'],
			'in capitals' => ['WORD-BREAK: BREAK-ALL', 'filled'],
			'the later of word-wrap and overflow-wrap' => ['overflow-wrap: anywhere; word-wrap: normal', 'overflows'],
			'an invalid value' => ['word-break: sometimes', 'cut'],
			'initial' => ['overflow-wrap: initial', 'overflows'],
		];
	}

	/**
	 * The lines of a URL in a 30mm table cell, under each value of the properties. The table is laid out at 30mm only
	 * when the column may be narrower than the URL
	 *
	 * @dataProvider providerCells
	 *
	 * @param string $css The cell's declarations
	 * @param bool $narrowed Whether the column is narrowed to the table's width
	 */
	public function testAUrlInACell($css, $narrowed)
	{
		$html = '<table style="width: 30mm"><tr><td style="' . $css . '">' . self::URL . '</td></tr></table>';
		$lines = $this->lines($html);

		if ($narrowed) {
			$this->assertGreaterThan(1, count($lines));
			$this->assertSame(self::URL, implode('', array_column($lines, 0)));
			foreach ($lines as $line) {
				$this->assertLessThan(30, $line[1]);
			}
		} else {
			$this->assertSame([self::URL], array_column($lines, 0));
		}
	}

	/**
	 * @return array[] Declarations, and whether the column is narrowed
	 */
	public function providerCells()
	{
		return [
			'neither set' => ['', false],
			'word-break: normal' => ['word-break: normal', false],
			'word-break: keep-all' => ['word-break: keep-all', false],
			'word-break: break-all' => ['word-break: break-all', true],
			'word-break: break-word' => ['word-break: break-word', true],
			'overflow-wrap: normal' => ['overflow-wrap: normal', false],
			'overflow-wrap: break-word' => ['overflow-wrap: break-word', false],
			'overflow-wrap: anywhere' => ['overflow-wrap: anywhere', true],
			'word-wrap: anywhere' => ['word-wrap: anywhere', true],
		];
	}

	/**
	 * Both properties are inherited: by a child block, an inline element, a table's cells and a cell's child block. A
	 * sibling of the element that sets them keeps the default
	 *
	 * @dataProvider providerInheritance
	 *
	 * @param string $html A document with the URL in a 30mm box, with {URL} for the URL
	 * @param string $expected overflows, cut or filled
	 */
	public function testIsInherited($html, $expected)
	{
		$lines = array_filter($this->lines(strtr($html, ['{URL}' => 'see ' . self::URL . ' now'])), function ($line) {
			return $line[0] !== 'x';
		});

		$this->assertUrlLines($expected, array_values($lines));
	}

	/**
	 * @return array[] Documents, and whether the URL overflows the line, is cut at its edge, or fills the first line
	 */
	public function providerInheritance()
	{
		return [
			'to a child block' => ['<div style="word-break: break-all"><p style="width: 30mm">{URL}</p></div>', 'filled'],
			'to an inline element' => ['<div style="width: 30mm; overflow-wrap: normal"><span>{URL}</span></div>', 'overflows'],
			'to a table\'s cells' => ['<table style="width: 30mm; word-break: break-all"><tr><td>{URL}</td></tr></table>', 'filled'],
			'to a cell\'s child block' => ['<table style="width: 30mm"><tr><td style="word-break: break-all"><div>{URL}</div></td></tr></table>', 'filled'],
			'a child sets its own' => ['<div style="width: 30mm; overflow-wrap: normal"><p style="overflow-wrap: anywhere">{URL}</p></div>', 'cut'],
			'inherit' => ['<div style="width: 30mm; overflow-wrap: normal"><p style="overflow-wrap: anywhere"><span style="overflow-wrap: inherit">{URL}</span></p></div>', 'cut'],
			'not to a sibling' => ['<div style="overflow-wrap: normal">x</div><div style="width: 30mm">{URL}</div>', 'cut'],
		];
	}

	/**
	 * An overflowing line keeps its width: justification does not squeeze it to fit. It starts at the start of the line
	 * whatever the alignment, so it runs out of the right of an ltr block and out of the left of an rtl one, which sits
	 * against the right margin
	 *
	 * @dataProvider providerOverflowingLines
	 *
	 * @param string $css
	 * @param string $edge left or right, the edge of the block the line is drawn from
	 */
	public function testAnOverflowingLineIsDrawnFromItsStart($css, $edge)
	{
		$word = 'Supercalifragilisticexpialidocious';
		$natural = $this->lines('<div>' . $word . '</div>');

		foreach (['last line' => $word, 'line before another' => $word . ' and more'] as $case => $text) {
			$lines = $this->lines('<div style="width: 30mm; overflow-wrap: normal; ' . $css . '">' . $text . '</div>');

			$this->assertSame($word, $lines[0][0], $case);
			$this->assertEqualsWithDelta($natural[0][1], $lines[0][1], 0.01, $case);
			if ($edge === 'left') {
				$this->assertEqualsWithDelta(15, $lines[0][2], 0.01, $case);
			} else {
				$this->assertEqualsWithDelta(195, $lines[0][2] + $lines[0][1], 0.01, $case);
			}
		}
	}

	/**
	 * @return array[] Declarations of direction and alignment, and the edge the line is drawn from
	 */
	public function providerOverflowingLines()
	{
		return [
			'left' => ['', 'left'],
			'justified' => ['text-align: justify', 'left'],
			'right' => ['text-align: right', 'left'],
			'centred' => ['text-align: center', 'left'],
			'rtl' => ['direction: rtl', 'right'],
			'rtl, left' => ['direction: rtl; text-align: left', 'right'],
		];
	}

	/**
	 * CJK text breaks between any two letters, as before, unless word-break is keep-all: then it breaks only after
	 * punctuation, and a run too long for the line overflows it, or is cut at the edge with overflow-wrap: anywhere
	 */
	public function testKeepAllKeepsCjkLettersTogether()
	{
		$html = '<div style="width: 30mm; {CSS}">' . self::CJK . '</div>';

		$default = $this->lineTexts(strtr($html, ['{CSS}' => '']));
		$this->assertSame(['日本語の文章を', '書きます。これ', 'は長い文です。'], $default);
		$this->assertSame($default, $this->lineTexts(strtr($html, ['{CSS}' => 'word-break: break-all'])));
		$this->assertSame($default, $this->lineTexts(strtr($html, ['{CSS}' => 'word-break: keep-all']), CssMode::LEGACY));

		$this->assertSame(['日本語の文章を書きます。', 'これは長い文です。'], $this->lineTexts(strtr($html, ['{CSS}' => 'word-break: keep-all'])));
		$this->assertSame(
			['日本語の文章を', '書きます。', 'これは長い文で', 'す。'],
			$this->lineTexts(strtr($html, ['{CSS}' => 'word-break: keep-all; overflow-wrap: anywhere']))
		);
	}

	/**
	 * keep-all keeps CJK letters together in a table cell, so the cell is as wide as its longest run of them
	 */
	public function testKeepAllWidensACellToItsCjkText()
	{
		$html = '<table><tr><td style="{CSS}">' . self::CJK . '</td><td>' . str_repeat('x ', 200) . '</td></tr></table>';

		$this->assertGreaterThan(1, count($this->lineTexts(strtr($html, ['{CSS}' => '']))));
		$this->assertSame([self::CJK], array_slice($this->lineTexts(strtr($html, ['{CSS}' => 'word-break: keep-all'])), 0, 1));
	}

	/**
	 * Under cssMode legacy the properties are ignored: every line is drawn as without them
	 */
	public function testLegacyIgnoresTheProperties()
	{
		$text = 'see ' . self::URL . ' now ' . self::CJK;
		$plain = '<div style="width: 30mm">' . $text . '</div><table style="width: 30mm"><tr><td>' . $text . '</td></tr></table>';
		$expected = $this->lines($plain, CssMode::LEGACY);

		foreach (['word-break: break-all', 'word-break: keep-all', 'overflow-wrap: normal', 'overflow-wrap: anywhere', 'word-wrap: normal'] as $css) {
			$html = str_replace('style="width: 30mm"', 'style="width: 30mm; ' . $css . '"', $plain);
			$this->assertSame($expected, $this->lines($html, CssMode::LEGACY), $css);
		}
	}

	/**
	 * word-wrap is read as overflow-wrap, and only the values the properties take are kept, under cssMode standard;
	 * under legacy the declarations are left as they are written
	 */
	public function testNormalisesTheDeclarations()
	{
		$standard = $this->normalizer(CssMode::STANDARD);
		$this->assertSame(
			['OVERFLOW-WRAP' => 'anywhere', 'WORD-BREAK' => 'keep-all'],
			$standard->normalize(['WORD-WRAP' => 'Anywhere', 'WORD-BREAK' => 'keep-all', 'OVERFLOW-WRAP' => 'nowhere'])
		);
		$this->assertSame(['OVERFLOW-WRAP' => 'inherit'], $standard->normalize(['WORD-WRAP' => 'inherit']));

		$this->assertSame(['WORD-WRAP' => 'anywhere'], $this->normalizer(CssMode::LEGACY)->normalize(['WORD-WRAP' => 'anywhere']));
	}

	/**
	 * @param string $mode
	 *
	 * @return NormalizeProperties Over a document in that CSS mode
	 */
	private function normalizer($mode)
	{
		$mpdf = new Mpdf(['cssMode' => $mode]);
		$colorModeConverter = new ColorModeConverter();
		$colorConverter = new ColorConverter($mpdf, $colorModeConverter, new ColorSpaceRestrictor($mpdf, $colorModeConverter));

		return new NormalizeProperties($mpdf, new SizeConverter(96, 11, $mpdf, new NullLogger()), $colorConverter);
	}

	/**
	 * Where each property's values let a line break
	 */
	public function testWhereTheValuesBreak()
	{
		$this->assertTrue(WordBreak::breaksLongWords([]));
		$this->assertTrue(WordBreak::breaksLongWords(null));
		$this->assertFalse(WordBreak::breaksLongWords(['word-break' => 'normal']));
		$this->assertFalse(WordBreak::breaksLongWords(['overflow-wrap' => 'normal', 'hyphens' => 1]));
		$this->assertTrue(WordBreak::breaksLongWords(['overflow-wrap' => 'break-word']));

		$this->assertTrue(WordBreak::narrowsMinContent(['overflow-wrap' => 'anywhere']));
		$this->assertFalse(WordBreak::narrowsMinContent(['overflow-wrap' => 'break-word']));
		$this->assertFalse(WordBreak::narrowsMinContent([]));

		$this->assertTrue(WordBreak::breaksAll(['word-break' => 'break-all']));
		$this->assertFalse(WordBreak::keepsAll(['word-break' => 'break-all']));
		$this->assertTrue(WordBreak::keepsAll(['word-break' => 'keep-all']));
	}

	/**
	 * Asserts how the URL, between "see" and "now", is laid out in a 30mm box starting at the left margin
	 *
	 * @param string $expected overflows: on a line of its own, wider than the box; cut: across lines no wider than the
	 * box, starting on a line of its own; filled: across lines no wider than the box, starting on the line with "see"
	 * @param array[] $lines As lines() gives them
	 */
	private function assertUrlLines($expected, array $lines)
	{
		$texts = array_column($lines, 0);
		$this->assertSame('see' . self::URL . 'now', str_replace(' ', '', implode('', $texts)));

		if ($expected === 'overflows') {
			$this->assertSame(['see', self::URL, 'now'], $texts);
			$this->assertGreaterThan(30, $lines[1][1]);

			return;
		}

		foreach ($lines as $line) {
			$this->assertLessThanOrEqual(30.01, $line[1]);
		}
		if ($expected === 'cut') {
			$this->assertSame('see', $texts[0]);
			$this->assertGreaterThan(3, count($texts));
		} else {
			$this->assertStringStartsWith('see https', $texts[0]);
			$this->assertGreaterThan(25, $lines[0][1]);
		}
	}

	/**
	 * @param string $html
	 * @param string $mode
	 *
	 * @return string[] The text of each line
	 */
	private function lineTexts($html, $mode = CssMode::STANDARD)
	{
		return array_column($this->lines($html, $mode), 0);
	}

	/**
	 * @param string $html
	 * @param string $mode
	 *
	 * @return array[] Each line drawn: its text, its width and its left edge, in mm. CJK text is drawn in pieces, one
	 * for each change of font, which are joined into their line
	 */
	private function lines($html, $mode = CssMode::STANDARD)
	{
		$mpdf = new TextRecordingMpdf(['mode' => 'utf-8', 'cssMode' => $mode, 'autoScriptToLang' => true, 'autoLangToFont' => true]);
		$mpdf->WriteHTML($html);

		$lines = [];
		foreach ($mpdf->drawnText as $i => $text) {
			list($page, $left, $right, $top) = $mpdf->drawnBoxes[$i];
			$key = $page . ':' . round($top, 3);
			if (isset($lines[$key])) {
				$lines[$key][0] .= $text;
				$lines[$key][1] = max($lines[$key][1], $right);
				$lines[$key][2] = min($lines[$key][2], $left);
			} else {
				$lines[$key] = [$text, $right, $left];
			}
		}

		return array_values(array_map(function ($line) {
			return [$line[0], round($line[1] - $line[2], 3), round($line[2], 3)];
		}, $lines));
	}
}
