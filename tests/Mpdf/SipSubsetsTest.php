<?php

namespace Mpdf;

use Mpdf\Fonts\FontRegistry;
use Mpdf\Utils\UtfString;

/**
 * How a font past the Basic Multilingual Plane numbers the characters a document draws in it.
 *
 * Such a font is embedded as simple fonts of at most 255 characters each, and every character is
 * written as its code in whichever of them holds it. The subset a character lands in and its code
 * there are what the embedded fonts and the page text both follow, so they are pinned against the
 * search of every subset mPDF used to do per character.
 */
class SipSubsetsTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * A document drawing in Sun-ExtB, whose characters run into Plane 2.
	 *
	 * @return Mpdf
	 */
	private function sunExtB()
	{
		$mpdf = new Mpdf([
			'mode' => 'utf-8',
			'fontRegistry' => new FontRegistry([]),
			'fontDir' => [__DIR__ . '/../../packages/SunExt/fonts'],
			'fontdata' => ['sunextb' => ['R' => 'Sun-ExtB.ttf']],
			'default_font' => 'sunextb',
		]);
		$mpdf->AddPage();
		$mpdf->SetFont('sunextb');

		return $mpdf;
	}

	/**
	 * Enough Plane 2 characters to fill three subset fonts and start a fourth, then the same ones
	 * again backwards so that each is looked up in the subset it was placed in, then the ASCII range
	 * the first subset font is seeded with.
	 *
	 * @return int[]
	 */
	private function characters()
	{
		$new = range(0x20000, 0x20000 + 800);

		return array_merge($new, array_reverse($new), range(0x20, 0x7E));
	}

	/**
	 * Each character, a few at a time, gives the same text and leaves the same subsets as searching
	 * every subset for it did
	 */
	public function testPlacesEachCharacterAsSearchingTheSubsetsDid()
	{
		$mpdf = $this->sunExtB();
		$this->assertTrue($mpdf->CurrentFont['sip']);

		$font = $mpdf->CurrentFont;
		$extraFontSubsets = $mpdf->extraFontSubsets;
		$fontCount = count($mpdf->fonts);

		foreach (array_chunk($this->characters(), 7) as $chunk) {
			// UTF8toSubset draws a character the font has no width for as character 0
			$drawn = [];
			foreach ($chunk as $c) {
				$drawn[] = $mpdf->_charDefined($mpdf->CurrentFont['cw'], $c) ? $c : 0;
			}

			$expected = $this->searchedSubset($font, $extraFontSubsets, $fontCount, $drawn, $mpdf->FontSizePt);

			$this->assertSame($expected, $mpdf->UTF8toSubset($this->text($chunk)));
		}

		$this->assertSame($font['subsets'], $mpdf->CurrentFont['subsets']);
		$this->assertSame($font['subsetfontids'], $mpdf->CurrentFont['subsetfontids']);
		$this->assertSame($extraFontSubsets, $mpdf->extraFontSubsets);
		$this->assertCount(4, $mpdf->CurrentFont['subsets']);
	}

	/**
	 * The fonts the page text names are the ones the document embeds, one per subset
	 */
	public function testEmbedsOneFontPerSubset()
	{
		$mpdf = $this->sunExtB();
		$mpdf->WriteHTML('<p>' . $this->text($this->characters()) . '</p>');
		$pdf = $mpdf->Output('', 'S');

		$subsets = count($mpdf->fonts['sunextb']['subsets']);
		$this->assertSame(4, $subsets);
		$this->assertSame($subsets, preg_match_all('/\/BaseFont \/MPDFA[A-Z]\+/', $pdf));
		$this->assertCount($subsets, $mpdf->fonts['sunextb']['n']);
	}

	/**
	 * @param int[] $characters
	 *
	 * @return string The characters as UTF-8
	 */
	private function text(array $characters)
	{
		return implode('', array_map([UtfString::class, 'code2utf'], $characters));
	}

	/**
	 * UTF8toSubset as it was: each subset searched in turn for the character, and the first with
	 * room given it where none holds it.
	 *
	 * @param array  $font             The font's subsets and subset font ids, updated
	 * @param int    $extraFontSubsets How many subset fonts past the first the document has, updated
	 * @param int    $fontCount        How many fonts the document has
	 * @param int[]  $characters       The characters to write
	 * @param float  $size             The font size the text is set in
	 *
	 * @return string The text, as UTF8toSubset writes it
	 */
	private function searchedSubset(array &$font, &$extraFontSubsets, $fontCount, array $characters, $size)
	{
		$ret = '<';
		$orig_fid = $font['subsetfontids'][0];
		$last_fid = $orig_fid;
		foreach ($characters as $c) {
			for ($i = 0; $i < 99; $i++) {
				$init = array_search($c, $font['subsets'][$i]);
				if ($init !== false) {
					if ($font['subsetfontids'][$i] != $last_fid) {
						$ret .= '> Tj /F' . $font['subsetfontids'][$i] . ' ' . $size . ' Tf <';
						$last_fid = $font['subsetfontids'][$i];
					}
					$ret .= sprintf("%02s", strtoupper(dechex($init)));
					break;
				} elseif (count($font['subsets'][$i]) < 255) {
					$n = count($font['subsets'][$i]);
					$font['subsets'][$i][$n] = $c;
					if ($font['subsetfontids'][$i] != $last_fid) {
						$ret .= '> Tj /F' . $font['subsetfontids'][$i] . ' ' . $size . ' Tf <';
						$last_fid = $font['subsetfontids'][$i];
					}
					$ret .= sprintf("%02s", strtoupper(dechex($n)));
					break;
				} elseif (!isset($font['subsets'][($i + 1)])) {
					$font['subsets'][($i + 1)] = [0 => 0];
					$font['subsetfontids'][($i + 1)] = $fontCount + $extraFontSubsets + 1;
					$extraFontSubsets++;
				}
			}
		}
		$ret .= '>';
		if ($last_fid != $orig_fid) {
			$ret .= ' Tj /F' . $orig_fid . ' ' . $size . ' Tf <> ';
		}

		return $ret;
	}
}
