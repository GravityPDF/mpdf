<?php

namespace Mpdf;

/**
 * GPOS Lookup Types 7 and 8 match a sequence of glyphs and then hand named positions within it to
 * other lookups, exactly as GSUB Types 5 and 6 do for substitution. Type 7 is a plain context;
 * Type 8 puts a backtrack and a lookahead around it. Format 1 lists the glyphs of each rule one by
 * one, Format 2 matches them against classes, Format 3 against a Coverage table per position.
 *
 * Positioning does not change the shaped text, so the seam TextRecordingMpdf uses cannot see any of
 * this. PositionRecordingMpdf reads the adjustments off the same OTLdata the drawing code is given.
 */
class ContextualPositioningTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	/**
	 * An mPDF that records positioning, with one font registered and OTL fully on.
	 *
	 * @param string $fontkey The name the font is registered and selected under
	 * @param string $file    Its file name within tests/data/ttf
	 *
	 * @return PositionRecordingMpdf
	 */
	private function mpdf($fontkey, $file)
	{
		return new PositionRecordingMpdf([
			'fontDir' => [__DIR__ . '/../data/ttf'],
			'fontdata' => [$fontkey => [
				'R' => $file,
				'useOTL' => 0xFF,
			]],
			'default_font' => $fontkey,
		]);
	}

	/**
	 * Noto Sans Takri's `kern` reaches a pair adjustment through a chained context: the I matra is
	 * pulled across the KA that follows it only when the anusvara closes the cluster.
	 */
	public function testAppliesAGlyphListChainedContextPositioning()
	{
		$mpdf = $this->mpdf('takrigpos', 'NotoSansTakri-GPOS81-Subset.ttf');

		$mpdf->WriteHTML('<p>&#x116AE;&#x1168A;&#x116AB;</p>');

		$this->assertEquals(
			[[1 => ['XAdvanceL' => 107, 'XAdvanceR' => 107, 'XPlacement' => 107]]],
			$mpdf->drawnPositions
		);
	}

	/**
	 * The same matra on its own has nothing for the chain to match, and is left where it was.
	 */
	public function testLeavesAGlyphTheChainedContextDoesNotReach()
	{
		$mpdf = $this->mpdf('takrigpos', 'NotoSansTakri-GPOS81-Subset.ttf');

		$mpdf->WriteHTML('<p>&#x116AE;</p>');

		$this->assertSame([], $mpdf->drawnPositions);
	}

	/**
	 * Noto Sans Gurmukhi UI's `dist` nudges the AU matra + addak ligature by a single unit, but only
	 * where a TTA and an EE matra follow it - a plain context, with no backtrack or lookahead.
	 */
	public function testAppliesAGlyphListPlainContextPositioning()
	{
		$mpdf = $this->mpdf('gurmukhigpos', 'NotoSansGurmukhiUI-GPOS71-Subset.ttf');

		$mpdf->WriteHTML('<p>&#x0A2C;&#x0A4C;&#x0A71;&#x0A1F;&#x0A47;</p>');

		$this->assertEquals([[1 => ['XPlacement' => -1]]], $mpdf->drawnPositions);
	}

	/**
	 * Drop the matra the context begins with and nothing is adjusted, which is what tells the rule
	 * apart from the single positioning it delegates to.
	 */
	public function testLeavesAGlyphThePlainContextDoesNotReach()
	{
		$mpdf = $this->mpdf('gurmukhigpos', 'NotoSansGurmukhiUI-GPOS71-Subset.ttf');

		$mpdf->WriteHTML('<p>&#x0A2C;&#x0A1F;&#x0A47;</p>');

		$this->assertSame([], $mpdf->drawnPositions);
	}

	/**
	 * None of the 701 fonts surveyed for GravityPDF/mpdf#80 carries a Type 7 Format 3 subtable, so
	 * the fixture is written by hand: Noto Sans cut down to A, B and C, with a GPOS of two lookups -
	 * a single positioning that shifts B left by 400 units, and a plain context covering A then B
	 * that runs it at the second position. `dist` is the only feature, on both DFLT and latn.
	 */
	public function testAppliesACoverageBasedPlainContextPositioning()
	{
		$mpdf = $this->mpdf('context73', 'NotoSans-GPOS73-Synthetic.ttf');

		$mpdf->WriteHTML('<p>ABAB</p>');

		$this->assertEquals(
			[[
				1 => ['XPlacement' => -400],
				3 => ['XPlacement' => -400, 'XAdvanceL' => -400, 'XAdvanceR' => -400],
			]],
			$mpdf->drawnPositions
		);
	}

	/**
	 * The B is only moved where the context's first Coverage table matches what precedes it, so the
	 * same glyph after a C, and the same pair the other way round, stay put.
	 */
	public function testLeavesAGlyphTheCoverageBasedContextDoesNotReach()
	{
		$mpdf = $this->mpdf('context73', 'NotoSans-GPOS73-Synthetic.ttf');

		$mpdf->WriteHTML('<p>CB</p><p>BA</p>');

		$this->assertSame([], $mpdf->drawnPositions);
	}

	/**
	 * Type 7 Format 2 matches classes of glyphs rather than naming them, and no font among the 103
	 * installed carries one either - the last of the six contextual positioning layouts without a
	 * font - so this fixture is written by hand the same way: Noto Sans cut down to A, B and C, with
	 * a GPOS of three lookups.
	 *
	 *   #0  single positioning, shifting B left by 400 units
	 *   #1  single positioning, shifting C left by 250
	 *   #2  the context, whose Class Definition table puts A in class 1 and B in class 2, leaving C
	 *       in class 0, and whose set of rules for a context beginning with class 1 is
	 *
	 *         rule 0   class 1, class 2   run lookup #0 at position 1
	 *         rule 1   class 1, class 0   run lookup #1 at position 1
	 *
	 * `dist` is the only feature, on both DFLT and latn, and it runs lookup #2 alone. Two nested
	 * lookups rather than one so that the two rules can be told apart by how far the glyph moved.
	 *
	 * The first rule is the ordinary case: A then a glyph of class 2, which is B.
	 */
	public function testAppliesAClassBasedPlainContextPositioning()
	{
		$mpdf = $this->mpdf('context72', 'NotoSans-GPOS72-Synthetic.ttf');

		$mpdf->WriteHTML('<p>AB</p>');

		$this->assertEquals(
			[[1 => ['XPlacement' => -400, 'XAdvanceL' => -400, 'XAdvanceR' => -400]]],
			$mpdf->drawnPositions
		);
	}

	/**
	 * The second rule is A then class 0, which the spec makes every glyph the Class Definition table
	 * does not name. C is the only one of the three, and it is shifted by a different amount so that
	 * the two rules can be told apart.
	 */
	public function testAppliesAClassBasedContextRuleNamingClassZero()
	{
		$mpdf = $this->mpdf('context72', 'NotoSans-GPOS72-Synthetic.ttf');

		$mpdf->WriteHTML('<p>AC</p>');

		$this->assertEquals(
			[[1 => ['XPlacement' => -250, 'XAdvanceL' => -250, 'XAdvanceR' => -250]]],
			$mpdf->drawnPositions
		);
	}

	/**
	 * AA is what tells class 0 from "anything": the second A is in class 1, so the rule naming class
	 * 0 does not reach it and neither does the rule naming class 2. CB and BA begin with a glyph no
	 * rule starts from.
	 */
	public function testLeavesAGlyphNoClassBasedRuleReaches()
	{
		$mpdf = $this->mpdf('context72', 'NotoSans-GPOS72-Synthetic.ttf');

		$mpdf->WriteHTML('<p>AA</p><p>CB</p><p>BA</p>');

		$this->assertSame([], $mpdf->drawnPositions);
	}

	/**
	 * Type 8 Format 3 is how most fonts write chained kerning and mark positioning, and a lookup
	 * commonly holds dozens of subtables whose first input Coverage tables overlap - Taamey David
	 * CLM's 'mark' lookup 18 has fifty-one. Two of them match a HOLAM followed by a QARNEY PARA and
	 * a MUNAH, and each names the same single adjustment at the first position. Only the first
	 * matching subtable of a lookup applies, which the caller decides by whether the subtable it just
	 * offered the glyph to reports a shift; this format reported none, so the glyph went on to the
	 * rest of the lookup and was moved twice as far as the font asks.
	 */
	public function testAppliesTheNestedLookupOfOnlyTheFirstMatchingChainedContextSubtable()
	{
		$mpdf = new PositionRecordingMpdf();

		$mpdf->WriteHTML('<p style="font-family:taameydavidclm">&#x05B9;&#x05AF;&#x0599;</p>');

		$this->assertEquals([[0 => ['XPlacement' => -190]]], $mpdf->drawnPositions);
	}

	/**
	 * The same in Padauk, whose 'mark' lookup 22 has two subtables matching a DOT BELOW between a KA
	 * and an ASAT. The dot was placed at twice the offset, taking it out from under the letter.
	 */
	public function testAppliesAMarkAdjustmentFromAChainedContextOnce()
	{
		$mpdf = new PositionRecordingMpdf();

		$mpdf->WriteHTML('<p style="font-family:padaukbook">&#x1000;&#x1037;&#x103A;</p>');

		$this->assertEquals(['XPlacement' => -200], $mpdf->drawnPositions[0][1]);
	}

}
