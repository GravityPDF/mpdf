<?php

namespace Mpdf;

class LinkTest extends BaseMpdfTest
{

	/**
	 * The PDF default border is 1pt, so a link dictionary without /Border gets a box drawn around it by viewers that
	 * honour the default (poppler does; Ghostscript and Chrome do not).
	 *
	 * @dataProvider hrefProvider
	 */
	public function testALinkIsWrittenWithoutABorder($href)
	{
		$this->mpdf->WriteHTML(sprintf('<p><a href="%s">link</a></p>', $href));

		$output = $this->mpdf->Output(null, 'S');

		$this->assertSame(1, preg_match('/<<\/Type \/Annot \/Subtype \/Link.*?endobj/s', $output, $match));

		$link = $match[0];

		/* The entry has to land inside the dictionary: written after the closing `>>` it is ignored. */
		$this->assertStringContainsString(' /Border [0 0 0]', substr($link, 0, strrpos($link, '>>')));
	}

	public function hrefProvider()
	{
		return [
			'page' => ['@1'],
			'named anchor' => ['#target'],
			'uri' => ['https://example.com/page'],
		];
	}
}
