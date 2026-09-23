<?php

namespace Mpdf;

/**
 * An input position of a class-based context rule (GSUB 5.2, 6.2, GPOS 7.2, 8.2) that names a class
 * the ClassDef does not define matches nothing, while one naming class 0 matches any glyph the
 * ClassDef puts in no other class.
 *
 * NotoSans-UndefinedInputClass-Synthetic is NotoSans-ClassZeroContext-Synthetic with its GSUB
 * replaced and a GPOS added. Each context lookup has one Format 2 subtable, and its second
 * input position names a class above the highest one its ClassDef defines:
 *
 *   calt  5.2 covering B, B in class 1 and C in class 2. Rule #0 names class 3 and substitutes
 *         c.sc for B; rule #1 names class 0 and substitutes b.sc.
 *         6.2 covering C, C in class 1 and B in class 2. Rule #0 names class 3 and substitutes
 *         a.sc for C; rule #1 names class 0 and substitutes c.sc.
 *   dist  7.2 covering A, A alone in class 1. Its one rule names class 2 and moves A by -400.
 *         8.2 covering A, A alone in class 1. Its one rule names class 2 and moves A by -250.
 *
 * `hb-shape` draws what each case expects.
 */
class UndefinedInputClassTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	const FONT = 'NotoSans-UndefinedInputClass-Synthetic';

	/** b.sc and c.sc have no codepoint, so they are mapped into the Private Use Area */
	const B_SMALL_CAP = 0xE002;

	const C_SMALL_CAP = 0xE003;

	/**
	 * The fixture font, registered with OTL fully on.
	 *
	 * @param string $class The Mpdf subclass to build
	 *
	 * @return Mpdf
	 */
	private function mpdf($class)
	{
		return new $class([
			'fontDir' => [__DIR__ . '/../data/ttf'],
			'fontdata' => [strtolower(self::FONT) => [
				'R' => self::FONT . '.ttf',
				'useOTL' => 0xFF,
			]],
			'default_font' => strtolower(self::FONT),
		]);
	}

	/**
	 * A substitution rule naming an undefined class at an input position never applies. The class 0
	 * rule after it applies where the next glyph is in class 0.
	 *
	 * @dataProvider dataSubstitutions
	 *
	 * @param string $text       What the document is written from
	 * @param int[]  $codepoints The codepoints of the line the drawing code is handed
	 */
	public function testAnUndefinedInputClassSubstitutesNothing($text, array $codepoints)
	{
		$mpdf = $this->mpdf(TextRecordingMpdf::class);
		$mpdf->WriteHTML('<p>' . $text . '</p>');

		$this->assertSame($codepoints, $mpdf->drawnCodepoints(0));
	}

	/**
	 * A run, and the codepoints it should draw.
	 *
	 * @return array
	 */
	public function dataSubstitutions()
	{
		return [
			'5.2, A in class 0 after B' => ['BA', [self::B_SMALL_CAP, 0x41]],
			'5.2, C in class 2 after B' => ['BC', [0x42, 0x43]],
			'6.2, A in class 0 after C' => ['CA', [self::C_SMALL_CAP, 0x41]],
			'6.2, B in class 2 after C' => ['CB', [0x43, 0x42]],
		];
	}

	/**
	 * A positioning rule naming an undefined class at an input position never applies, though B
	 * after A is in class 0.
	 */
	public function testAnUndefinedInputClassPositionsNothing()
	{
		$mpdf = $this->mpdf(PositionRecordingMpdf::class);
		$mpdf->WriteHTML('<p>AB</p>');

		$this->assertSame([], $mpdf->drawnPositions);
	}

}
