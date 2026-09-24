<?php

namespace Mpdf\Ua;

/**
 * With useActiveForms off, what a drawn form control holds - the text typed in it, the option
 * chosen, whether it is checked - is read as content: a Form element with PrintField attributes
 * (ISO 32000-1 §14.8.5.6) inside the element the control sits in. Only its box is an artifact.
 *
 * @group pdfua
 */
class StaticFormFieldValueTest extends PdfUaTestCase
{

	/**
	 * A PDF/UA document with useActiveForms off, rendered.
	 *
	 * @param string $html
	 *
	 * @return array{0: \Mpdf\Mpdf, 1: string} The document, and the content stream of its first page
	 */
	private function renderStatic($html)
	{
		$mpdf = $this->makeMpdf(['useActiveForms' => false]);
		$pdf = $this->getOutput($mpdf, $html);

		$this->assertSame(1, preg_match('/<<\/Length \d+>>\s*stream\n(.*?)\nendstream/s', $pdf, $page));

		return [$mpdf, $page[1]];
	}

	/**
	 * The marked-content sequences open where $needle is first drawn, outermost first.
	 *
	 * @param string $stream
	 * @param string $needle
	 *
	 * @return string[] Each sequence's opening operator, such as "/Form <</MCID 1>> BDC"
	 */
	private function openAt($stream, $needle)
	{
		$at = strpos($stream, $needle);
		$this->assertNotFalse($at, 'The page should draw ' . bin2hex($needle));

		preg_match_all('/\/\w+ <<[^>]*>> BDC|\/\w+ BMC|\bEMC\b/', substr($stream, 0, $at), $ops);
		$open = [];
		foreach ($ops[0] as $op) {
			if ($op === 'EMC') {
				array_pop($open);
			} else {
				$open[] = $op;
			}
		}

		return $open;
	}

	/**
	 * $text as the page draws it with an embedded font, where each character is two bytes.
	 *
	 * @param string $text
	 *
	 * @return string
	 */
	private function drawn($text)
	{
		return mb_convert_encoding($text, 'UTF-16BE', 'UTF-8');
	}

	/**
	 * Every Form element under $elem, in document order.
	 *
	 * @param \Mpdf\Ua\StructureElement $elem
	 *
	 * @return \Mpdf\Ua\StructureElement[]
	 */
	private function forms(StructureElement $elem)
	{
		$found = [];
		foreach ($elem->getChildren() as $child) {
			if ($child->getType() === 'Form') {
				$found[] = $child;
			}
			$found = array_merge($found, $this->forms($child));
		}

		return $found;
	}

	/**
	 * The one Form element the document made.
	 *
	 * @param \Mpdf\Mpdf $mpdf
	 *
	 * @return \Mpdf\Ua\StructureElement
	 */
	private function onlyForm(\Mpdf\Mpdf $mpdf)
	{
		$forms = $this->forms($mpdf->getPdfUaStructureTree()->getRoot());
		$this->assertCount(1, $forms);

		return $forms[0];
	}

	/**
	 * $open is a single sequence of $type, so nothing drawn is nested in other marked content.
	 *
	 * @param string   $type
	 * @param string[] $open
	 */
	private function assertOnlyInside($type, array $open)
	{
		$this->assertCount(1, $open, 'Marked content may not nest: ' . implode(' / ', $open));
		$this->assertStringStartsWith('/' . $type . ' ', $open[0]);
	}

	/**
	 * A text input's value is the content of a Form element in the paragraph holding its label,
	 * named by its title; its box is an artifact, and the paragraph's text after it is the paragraph's.
	 */
	public function testTextInputValueIsReadInsideTheParagraph()
	{
		list($mpdf, $page) = $this->renderStatic('<p>Name: <input type="text" name="x" value="Jane" title="Full name" /> end</p>');

		$this->assertOnlyInside('Form', $this->openAt($page, $this->drawn('Jane')));
		$this->assertOnlyInside('Artifact', $this->openAt($page, ' re B'));
		$this->assertOnlyInside('P', $this->openAt($page, $this->drawn('end')));

		$form = $this->onlyForm($mpdf);
		$this->assertSame('P', $form->getParent()->getType());
		$this->assertCount(1, $form->getMcids());
		$this->assertSame(['Role' => 'tv', 'Desc' => 'Full name'], $form->getAttributes());
	}

	/**
	 * A textarea's text is the content of a Form element and its box an artifact.
	 */
	public function testTextareaValueIsContent()
	{
		list($mpdf, $page) = $this->renderStatic('<p>Notes:</p><textarea name="n" rows="3" cols="20">First line</textarea>');

		$this->assertOnlyInside('Form', $this->openAt($page, $this->drawn('First line')));
		$this->assertOnlyInside('Artifact', $this->openAt($page, ' re B'));
		$this->assertSame(['Role' => 'tv'], $this->onlyForm($mpdf)->getAttributes());
	}

	/**
	 * A select's chosen option is the content of a Form element; its box and arrow are artifacts.
	 */
	public function testSelectChosenOptionIsContent()
	{
		list($mpdf, $page) = $this->renderStatic('<p>Pick: <select name="s"><option>Alpha</option><option selected>Beta</option></select></p>');

		$this->assertOnlyInside('Form', $this->openAt($page, $this->drawn('Beta')));
		$this->assertOnlyInside('Artifact', $this->openAt($page, ' re B'));
		$this->assertSame(1, substr_count($page, '/Form <<'), 'The arrow is not part of the value');
		$this->assertSame(['Role' => 'tv'], $this->onlyForm($mpdf)->getAttributes());
	}

	/**
	 * A submit button's label is the content of a Form element with the push button role.
	 */
	public function testButtonLabelIsContent()
	{
		list($mpdf, $page) = $this->renderStatic('<p><input type="submit" name="b" value="Send" /></p>');

		$this->assertOnlyInside('Form', $this->openAt($page, $this->drawn('Send')));
		$this->assertSame(['Role' => 'pb'], $this->onlyForm($mpdf)->getAttributes());
	}

	/**
	 * The expected attributes of a drawn check box or radio button, by whether it is checked.
	 *
	 * @return array[]
	 */
	public function checkableProvider()
	{
		return [
			'checked check box' => ['<input type="checkbox" name="c" checked />', 'cb', 'on', "\xe2\x98\x92"],
			'unchecked check box' => ['<input type="checkbox" name="c" />', 'cb', 'off', "\xe2\x98\x90"],
			'checked radio button' => ['<input type="radio" name="r" value="a" checked />', 'rb', 'on', "\xe2\x97\x89"],
			'unchecked radio button' => ['<input type="radio" name="r" value="a" />', 'rb', 'off', "\xe2\x97\x8b"],
		];
	}

	/**
	 * A check box or radio button is drawn as the content of a Form element saying whether it is
	 * checked, as a PrintField attribute and as the character its drawing stands for.
	 *
	 * @dataProvider checkableProvider
	 *
	 * @param string $control
	 * @param string $role
	 * @param string $checked
	 * @param string $actualText
	 */
	public function testCheckableStateIsContent($control, $role, $checked, $actualText)
	{
		list($mpdf, $page) = $this->renderStatic('<p>' . $control . ' agree</p>');

		$this->assertOnlyInside('Form', $this->openAt($page, $role === 'cb' ? ' re B' : ' c S'));
		$this->assertSame(
			['Role' => $role, 'checked' => $checked, 'ActualText' => $actualText],
			$this->onlyForm($mpdf)->getAttributes()
		);
	}

	/**
	 * A field in a table cell is read in that cell.
	 */
	public function testFieldInTableCellIsReadInTheCell()
	{
		list($mpdf, $page) = $this->renderStatic('<table><tr><td>Name</td><td><input type="text" name="t" value="Jo" /></td></tr></table>');

		$this->assertOnlyInside('Form', $this->openAt($page, $this->drawn('Jo')));
		$this->assertSame('TD', $this->onlyForm($mpdf)->getParent()->getType());
	}

	/**
	 * The PrintField attributes are written as an attribute object, with the title as a text string.
	 */
	public function testPrintFieldAttributesAreWritten()
	{
		$mpdf = $this->makeMpdf(['useActiveForms' => false]);
		$pdf = $this->getOutput($mpdf, '<p><input type="checkbox" name="c" title="Agree" checked /></p>');

		$this->assertStringContainsString(
			'/A <</O /PrintField /Role /cb /checked /on /Desc (' . "\xfe\xff" . $this->drawn('Agree') . ') >>',
			$pdf
		);
	}

	/**
	 * Without PDF/UA a drawn field makes no marked content.
	 */
	public function testNoMarkedContentWithoutPdfUa()
	{
		$mpdf = new \Mpdf\Mpdf(['mode' => 'en-GB', 'useActiveForms' => false]);
		$mpdf->compress = false;
		$mpdf->WriteHTML('<p>Name: <input type="text" name="x" value="Jane" /> <input type="checkbox" name="c" checked /></p>');
		$pdf = $mpdf->Output(null, 'S');

		$this->assertStringNotContainsString('BDC', $pdf);
		$this->assertStringNotContainsString('BMC', $pdf);
	}
}
