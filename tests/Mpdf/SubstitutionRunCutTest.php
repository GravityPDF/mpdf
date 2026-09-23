<?php

namespace Mpdf;

use Mpdf\Fonts\FontRegistry;

/**
 * Where the substitution scan cuts a text token around a run of characters the current font cannot
 * draw: at the run it found, whatever else the token holds.
 */
class SubstitutionRunCutTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * @param string $defaultFont
	 * @param string $backupFont
	 *
	 * @return \Mpdf\Mpdf With the default font selected
	 */
	private function mpdf($defaultFont, $backupFont)
	{
		// No font packages, so nothing the suite installs is put ahead of these
		$mpdf = new Mpdf([
			'mode' => 'utf-8',
			'fontRegistry' => new FontRegistry([]),
			'fontDir' => [
				__DIR__ . '/../../packages/Dejavu-Family/fonts',
				__DIR__ . '/../../packages/Garuda/fonts',
			],
			'fontdata' => [
				'dejavusans' => ['R' => 'DejaVuSans.ttf'],
				'dejavusansmono' => ['R' => 'DejaVuSansMono.ttf'],
				'garuda' => ['R' => 'Garuda.ttf'],
			],
			'default_font' => $defaultFont,
			'useSubstitutions' => true,
			'backupSubsFont' => [$backupFont],
		]);
		$mpdf->SetFont($defaultFont);

		return $mpdf;
	}

	/**
	 * @param int[] $codepoints
	 *
	 * @return string
	 */
	private function text(array $codepoints)
	{
		return implode('', array_map('Mpdf\Utils\UtfString::code2utf', $codepoints));
	}

	/**
	 * DejaVu Sans Mono has the heart but no text selector. The heart and its selector are one emoji
	 * the font draws, so the scan passes over them and stops at the selector after the b, and it is
	 * that one that is cut out: not the copy inside the heart, which comes first in the token.
	 */
	public function testTheRunIsCutWhereTheScanFoundItRatherThanAtAnEarlierCopy()
	{
		$tokens = [$this->text([0x61, 0x2665, 0xFE0E, 0x62, 0xFE0E, 0x63])];
		$i = 0;
		$e = $tokens[0];

		$this->mpdf('dejavusansmono', 'dejavusans')->SubstituteCharsMB($tokens, $i, $e);

		$this->assertSame([
			$this->text([0x61, 0x2665, 0xFE0E, 0x62]),
			'span style="font-family: dejavusans"',
			$this->text([0xFE0E]),
			'/span',
			'c',
		], $tokens);
		$this->assertSame($tokens[0], $e);
	}

	/**
	 * The text either side of a run is kept whole where it holds a newline. Neither font has a glyph
	 * for the first newline, so it is a run of its own that stays where it is; the second comes
	 * after the Thai and is kept with the text it is in.
	 */
	public function testANewlineKeepsTheTextEitherSideOfARun()
	{
		$tokens = ["one\ntwo" . $this->text([0x0E01]) . "three\nfour"];
		$i = 0;
		$e = $tokens[0];

		$this->mpdf('dejavusans', 'garuda')->SubstituteCharsMB($tokens, $i, $e);

		$this->assertSame([
			"one\n",
			'',
			'two',
			'span style="font-family: garuda"',
			$this->text([0x0E01]),
			'/span',
			"three\nfour",
		], $tokens);
	}

	/**
	 * A core font's substitution keeps the text either side of a run whole where it holds a newline
	 */
	public function testANewlineKeepsTheTextEitherSideOfARunInACoreFont()
	{
		$tokens = ["one\ntwo" . $this->text([0x0E01]) . "three\nfour"];
		$i = 0;
		$e = $tokens[0];

		$mpdf = $this->mpdf('chelvetica', 'garuda');
		$this->assertSame(4, $mpdf->SubstituteCharsNonCore($tokens, $i, $e));

		$this->assertSame([
			"one\ntwo",
			'span style="font-family: garuda"',
			$this->text([0x0E01]),
			'/span',
			"three\nfour",
		], $tokens);
		$this->assertSame($tokens[0], $e);
	}

}
