<?php

namespace Mpdf;

/**
 * An active select is a combo box when HTML would draw it as a drop-down: neither multiple nor given a size of two
 * or more rows. A select with no size attribute has a size of one (GravityPDF/mpdf#396)
 */
class SelectFieldFlagsTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{
	use PageStreams;

	/**
	 * The attributes of a select and the /Ff bits its field should carry
	 *
	 * @return mixed[]
	 */
	public function selects()
	{
		return [
			'no size' => ['', [Form::FLAG_COMBOBOX, Form::FLAG_NO_SPELLCHECK]],
			'a size of one' => ['size="1"', [Form::FLAG_COMBOBOX, Form::FLAG_NO_SPELLCHECK]],
			'a size of four' => ['size="4"', [Form::FLAG_NO_SPELLCHECK]],
			'multiple with no size' => ['multiple', [Form::FLAG_MULTISELECT, Form::FLAG_NO_SPELLCHECK]],
			'multiple with a size of three' => ['multiple size="3"', [Form::FLAG_MULTISELECT, Form::FLAG_NO_SPELLCHECK]],
			'editable' => ['editable', [Form::FLAG_COMBOBOX, Form::FLAG_EDITABLE, Form::FLAG_NO_SPELLCHECK]],
			'editable and spellchecked' => ['editable spellcheck="true"', [Form::FLAG_COMBOBOX, Form::FLAG_EDITABLE]],
			'spellchecked but not editable' => ['spellcheck="true"', [Form::FLAG_COMBOBOX, Form::FLAG_NO_SPELLCHECK]],
			'editable and spellchecked with a size of four' => ['editable spellcheck="true" size="4"', [Form::FLAG_NO_SPELLCHECK]],
		];
	}

	/**
	 * @dataProvider selects
	 *
	 * @param string $attributes
	 * @param int[] $bits
	 */
	public function testTheFieldFlagsFollowTheSelectsAttributes($attributes, $bits)
	{
		$pdf = $this->render(
			'<form><select name="choice" ' . $attributes . '><option>A</option><option selected>B</option></select></form>',
			['useActiveForms' => true]
		);

		$this->assertSame(1, preg_match('/\/FT \/Ch\b.*?\/Ff (\d+)/s', $pdf, $match), 'The select should be written as a choice field');
		$this->assertSame($bits, $this->bits((int) $match[1]));
	}

	/**
	 * The 1-based positions of the bits set in $flags, lowest first
	 *
	 * @param int $flags
	 *
	 * @return int[]
	 */
	private function bits($flags)
	{
		$bits = [];
		for ($bit = 1; $flags; $bit++, $flags >>= 1) {
			if ($flags & 1) {
				$bits[] = $bit;
			}
		}

		return $bits;
	}
}
