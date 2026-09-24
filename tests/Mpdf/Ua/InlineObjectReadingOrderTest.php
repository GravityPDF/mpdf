<?php

namespace Mpdf\Ua;

/**
 * An image, barcode, list marker or form field in a line is marked where it falls among the
 * line's text, and its marked content does not sit inside the text's.
 */
class InlineObjectReadingOrderTest extends PdfUaTestCase
{

	/**
	 * @var string
	 */
	private $image = __DIR__ . '/../../data/img/bayeux2.jpg';

	/**
	 * An image's Figure is read between the text before and after it.
	 *
	 * @return void
	 */
	public function testImageIsReadBetweenTheTextAroundIt()
	{
		$pdf = $this->getOutput(
			$this->makeMpdf(),
			'<p>Before <img src="' . $this->image . '" alt="A picture" width="20"> after</p>'
		);

		$this->assertSame(['MCID 0', 'Figure', 'MCID 2'], $this->kidsOf($pdf, 'P'));
		$this->assertNoNestedMarkedContent($pdf);
	}

	/**
	 * A line broken off in the middle of a paragraph draws its image in order too.
	 *
	 * @return void
	 */
	public function testImageOnAWrappedLineIsReadBetweenTheTextAroundIt()
	{
		$pdf = $this->getOutput(
			$this->makeMpdf(),
			'<p>Before <img src="' . $this->image . '" alt="A picture" width="20"> after '
			. str_repeat('and then some more words ', 20) . '</p>'
		);

		$kids = $this->kidsOf($pdf, 'P');
		$this->assertSame(['MCID 0', 'Figure', 'MCID 2'], array_slice($kids, 0, 3));
		$this->assertNoNestedMarkedContent($pdf);
	}

	/**
	 * An image in a link is the Link's content, beside its annotation, so a Link with text after it
	 * is not left empty.
	 *
	 * @return void
	 */
	public function testImageInALinkIsTheLinksContent()
	{
		$pdf = $this->getOutput(
			$this->makeMpdf(),
			'<p>x <a href="https://example.com"><img src="' . $this->image . '" alt="Home" width="20"></a> y</p>'
		);

		$this->assertSame(['MCID 0', 'Link', 'MCID 2'], $this->kidsOf($pdf, 'P'));
		$this->assertSame(['Figure', 'OBJR'], $this->kidsOf($pdf, 'Link'));
		$this->assertNoNestedMarkedContent($pdf);
	}

	/**
	 * A checkbox before its label's text is read before it.
	 *
	 * @return void
	 */
	public function testFormFieldIsReadBetweenTheTextAroundIt()
	{
		$pdf = $this->getOutput(
			$this->makeMpdf(['useActiveForms' => true]),
			'<p>Start <label><input type="checkbox" name="c" value="y" title="Agree"> Check</label>'
			. ' and <input type="text" name="t" title="Name"> end</p>'
		);

		$this->assertSame(['MCID 0', 'Form', 'MCID 1', 'Form', 'MCID 2'], $this->kidsOf($pdf, 'P'));
	}

	/**
	 * Images in a table cell, a barcode and list markers open their marked content outside the text's.
	 *
	 * @return void
	 */
	public function testObjectsOpenNoMarkedContentInsideTheTexts()
	{
		$pdf = $this->getOutput(
			$this->makeMpdf(),
			'<table><tr><td>cell <img src="' . $this->image . '" alt="C" width="10"> text</td></tr></table>'
			. '<ul><li>item <barcode code="123" type="C39" /> more</li><li>two</li></ul>'
		);

		$this->assertSame(['MCID 0', 'Figure', 'MCID 2'], $this->kidsOf($pdf, 'TD'));
		$this->assertNoNestedMarkedContent($pdf);
	}

	/**
	 * The kids of the first structure element of a type, each named by the type of the element it
	 * refers to, 'MCID n' or 'OBJR'.
	 *
	 * @param string $pdf
	 * @param string $type
	 *
	 * @return string[]
	 */
	private function kidsOf($pdf, $type)
	{
		preg_match_all('@(\d+) 0 obj\s*<</Type /StructElem\s+/S /(\w+)(.*?)endobj@s', $pdf, $m, PREG_SET_ORDER);
		$types = [];
		$body = null;
		foreach ($m as $elem) {
			$types[$elem[1]] = $elem[2];
			if ($body === null && $elem[2] === $type) {
				$body = $elem[3];
			}
		}
		$this->assertNotNull($body, 'no /' . $type . ' element');
		$this->assertSame(1, preg_match('@/K (\[.*\]|<<[^>]*>>|\d+ 0 R|\d+)\s*>>\s*$@s', $body, $k));

		preg_match_all('@<</Type /MCR[^>]*/MCID (\d+)>>|<</Type /OBJR[^>]*>>|(\d+) 0 R|\d+@', $k[1], $refs, PREG_SET_ORDER);
		$kids = [];
		foreach ($refs as $ref) {
			if (strpos($ref[0], '/OBJR') !== false) {
				$kids[] = 'OBJR';
			} elseif (isset($ref[2]) && $ref[2] !== '') {
				$kids[] = $types[$ref[2]];
			} else {
				$kids[] = 'MCID ' . ($ref[1] !== '' ? $ref[1] : $ref[0]);
			}
		}

		return $kids;
	}
}
