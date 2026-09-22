<?php

namespace Mpdf\Ua;

/**
 * The text of an inline element (a link, a span with a lang, an abbr, ruby) is marked as that
 * element's content rather than the block's, so its /Lang, /Alt or /E has something to apply to.
 */
class InlineStructContentTest extends PdfUaTestCase
{

	/**
	 * A link holds both its text and its annotation, and a span with a lang inside it holds its own text.
	 *
	 * @return void
	 */
	public function testLinkOwnsTextMcidAndObjrWithNestedLangSpan()
	{
		$output = $this->getOutput(
			$this->makeMpdf(),
			'<p>see <a href="https://example.com">the report '
			. '<span lang="fr">rapport</span></a> today</p>'
		);

		$this->assertStringContainsString('/Link <</MCID', $output);
		$this->assertStringContainsString('/Span <</MCID', $output);
		// "see " and " today" stay with the paragraph
		$this->assertStringContainsString('/P <</MCID', $output);

		$linkBody = $this->firstStructBodyContaining($output, 'Link');
		$this->assertNotNull($linkBody, 'a /S /Link struct element must exist');
		$this->assertStringContainsString('/MCR', $linkBody, 'Link /K must contain a marked-content reference (the link text MCID)');
		$this->assertStringContainsString('/OBJR', $linkBody, 'Link /K must contain the annotation OBJR');

		$spanBody = $this->firstStructBodyContaining($output, 'Span');
		$this->assertNotNull($spanBody, 'a /S /Span struct element must exist');
		$this->assertStringContainsString("\xfe\xff\x00f\x00r", $spanBody, 'lang-Span must carry /Lang "fr"');
		$this->assertStringContainsString('/K', $spanBody, 'lang-Span must own its own content item');

		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * The text of an abbr is held by the Span that carries its /E expansion.
	 *
	 * @return void
	 */
	public function testAbbrExpansionSpanOwnsItsText()
	{
		$output = $this->getOutput(
			$this->makeMpdf(),
			'<p>The <abbr title="World Health Organization">WHO</abbr> said so.</p>'
		);

		$this->assertStringContainsString('/Span <</MCID', $output);

		$spanBody = $this->firstStructBodyContaining($output, 'Span');
		$this->assertNotNull($spanBody, 'a /S /Span struct element must exist for <abbr>');
		$this->assertStringContainsString('/E', $spanBody, 'the abbr Span must carry /E expansion text');
		$this->assertStringContainsString('/K', $spanBody, 'the abbr Span must own its own content item');

		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * The text of an rb and of its rt are each held by their own RB and RT element.
	 *
	 * @return void
	 */
	public function testRubyRbAndRtEachOwnTheirText()
	{
		$output = $this->getOutput(
			$this->makeMpdf(),
			'<p>Read <ruby><rb>KANJI</rb><rt>kan</rt></ruby> now.</p>'
		);

		$this->assertStringContainsString('/S /Ruby', $output);
		$this->assertStringContainsString('/S /RB', $output);
		$this->assertStringContainsString('/S /RT', $output);

		$this->assertStringContainsString('/RB <</MCID', $output);
		$this->assertStringContainsString('/RT <</MCID', $output);

		$rbBody = $this->firstStructBodyContaining($output, 'RB');
		$this->assertNotNull($rbBody, 'a /S /RB struct element must exist');
		$this->assertStringContainsString('/K', $rbBody, 'RB must own its own content item');

		$rtBody = $this->firstStructBodyContaining($output, 'RT');
		$this->assertNotNull($rtBody, 'a /S /RT struct element must exist');
		$this->assertStringContainsString('/K', $rtBody, 'RT must own its own content item');

		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * Ruby base text with no rb around it is held by the Ruby element, and the rt holds its own.
	 *
	 * @return void
	 */
	public function testBareRubyBaseOwnsItsText()
	{
		$output = $this->getOutput(
			$this->makeMpdf(),
			'<p>Read <ruby>KANJI<rt>kan</rt></ruby> now.</p>'
		);

		$this->assertStringContainsString('/Ruby <</MCID', $output);
		$this->assertStringContainsString('/RT <</MCID', $output);
		$this->assertBdcEmcBalanced($output);
	}

	/**
	 * @param string $pdf  The uncompressed document
	 * @param string $type Structure type without its slash, e.g. 'Link'
	 *
	 * @return string|null The body of the first object that is a structure element of that type
	 */
	private function firstStructBodyContaining($pdf, $type)
	{
		if (preg_match_all('/\d+ 0 obj(.*?)endobj/s', $pdf, $m)) {
			foreach ($m[1] as $body) {
				if (strpos($body, '/S /' . $type) !== false) {
					return $body;
				}
			}
		}
		return null;
	}

	/**
	 * Assert every BDC and BMC in the document is closed by an EMC.
	 *
	 * @param string $output
	 *
	 * @return void
	 */
	private function assertBdcEmcBalanced($output)
	{
		$bdcCount = preg_match_all('/\bBDC\b/', $output);
		$bmcCount = preg_match_all('/\bBMC\b/', $output);
		$emcCount = preg_match_all('/\bEMC\b/', $output);
		$this->assertEquals(
			$bdcCount + $bmcCount,
			$emcCount,
			sprintf('BDC(%d)+BMC(%d) must equal EMC(%d)', $bdcCount, $bmcCount, $emcCount)
		);
	}
}
