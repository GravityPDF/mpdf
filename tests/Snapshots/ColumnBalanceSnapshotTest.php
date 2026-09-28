<?php

namespace Snapshots;

/**
 * Balanced columns that close on a block with a background whose last child is a block, left to right and right to
 * left. The background's closing rectangle stays in the last column rather than one column past it (#475).
 *
 * @group snapshot
 */
class ColumnBalanceSnapshotTest extends Snapshot
{

	/**
	 * @return string
	 */
	public function getId()
	{
		return 'column-balance';
	}

	/**
	 * Three two-column sections, the last right to left, each balanced as it closes on a panel that ends in a sign-off block
	 */
	public function generatePdf()
	{
		$this->mpdf = $this->createMpdf();

		$text = str_repeat('Lorem ipsum dolor sit amet, consectetur adipiscing elit. ', 4);
		// No bottom padding or border, so the panel's closing rectangle starts at the foot of the sign-off
		$panel = 'background-color: #9bc4e2; padding: 2mm 2mm 0 2mm';
		$signOff = '<div style="font-weight: bold">Sign-off</div>';

		$this->mpdf->WriteHTML('<h3>Text, then a block</h3>');
		$this->mpdf->WriteHTML($this->balanced('<div style="' . $panel . '">' . $text . $signOff . '</div>'));

		$this->mpdf->WriteHTML('<h3>Indented paragraphs, then a block</h3>');
		$this->mpdf->WriteHTML($this->balanced(
			'<div style="' . $panel . '">'
			. str_repeat('<p style="margin: 0; text-indent: 5mm">' . $text . '</p>', 2)
			. $signOff . '</div>'
		));

		$this->mpdf->WriteHTML('<h3>Right to left</h3>');
		$this->mpdf->SetDirectionality('rtl');
		$this->mpdf->WriteHTML($this->balanced('<div style="' . $panel . '">' . $text . $signOff . '</div>'));
	}

	/**
	 * $block in two columns that are balanced when they close
	 *
	 * @param string $block
	 *
	 * @return string
	 */
	private function balanced($block)
	{
		return '<columns column-count="2" column-gap="6" />' . $block . '<columns column-count="0" />';
	}

}
