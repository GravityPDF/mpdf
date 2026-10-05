<?php

namespace Snapshots;

/**
 * Stylesheet rules with a descendant selector naming a block with position: absolute or position: fixed, reaching
 * the content inside it the way they reach the same markup in normal flow (#474).
 *
 * @group snapshot
 */
class PositionedBlockSelectorSnapshotTest extends Snapshot
{

	/**
	 * @return string
	 */
	public function getId()
	{
		return 'positioned-block-selector';
	}

	/**
	 * A card in normal flow beside the same card positioned absolutely, then fixed, nested, rotated and neighbouring
	 * positioned blocks that each rely on rules naming the block
	 */
	public function generatePdf()
	{
		$this->mpdf = $this->createMpdf();

		$this->mpdf->WriteHTML('<style>
			table { line-height: 1.2; }
			.card { color: #1f3a93; border: 0.3mm solid #1f3a93; padding: 3mm; width: 80mm; }
			.card h4 { color: #ffffff; background-color: #1f3a93; margin: 0 0 2mm 0; padding: 1mm 2mm; }
			.card p { color: #0a7d32; font-style: italic; }
			.card li { color: #b3006b; }
			.card td { color: #c0392b; border: 0.2mm solid #c0392b; padding: 1mm; }
			.card tr:nth-child(even) td { background-color: #fbe3e0; }
			div.card .note { color: #7a4a00; font-weight: bold; }

			#side p { color: #8e44ad; font-size: 14pt; }
			#side td { background-color: #e8daef; }

			.nest div p { color: #d35400; text-decoration: underline; }

			.turned p { color: #16a085; font-weight: bold; }
			</style>');

		$card = '<h4>Heading</h4>'
			. '<p>Paragraph</p>'
			. '<ul><li>First item</li><li>Second item</li></ul>'
			. '<table><tr><td>Cell one</td><td>Cell two</td></tr><tr><td>Cell three</td><td>Cell four</td></tr></table>'
			. '<div class="note">Note</div>'
			. '<span>Inline span in the card colour</span>';

		// The same card in normal flow and positioned absolutely beside it
		$this->mpdf->WriteHTML('<div class="card">' . $card . '</div>');
		$this->mpdf->WriteHTML('<div class="card" style="position: absolute; top: 16mm; left: 110mm;">' . $card . '</div>');

		// An id rule inside a fixed block
		$this->mpdf->WriteHTML('<div id="side" style="position: fixed; top: 110mm; left: 10mm; width: 70mm; border: 0.3mm dashed #8e44ad; padding: 2mm;">'
			. '<p>Fixed paragraph</p><table><tr><td>Fixed cell</td></tr></table></div>');

		// A rule with an element between the block and the paragraph matches only the nested paragraph
		$this->mpdf->WriteHTML('<div class="nest" style="position: absolute; top: 126mm; left: 110mm; width: 80mm; border: 0.3mm dashed #d35400; padding: 2mm;">'
			. '<p>Directly in the box, plain</p><div><p>Nested in a div, orange</p></div></div>');

		// A rotated positioned block
		$this->mpdf->WriteHTML('<div class="turned" style="position: absolute; top: 170mm; left: 10mm; width: 60mm; rotate: 90; border: 0.3mm solid #16a085; padding: 2mm;">'
			. '<p>Rotated paragraph</p></div>');

		// Neighbouring positioned blocks: the card rules stay in the card
		$this->mpdf->WriteHTML('<div class="card" style="position: absolute; top: 200mm; left: 60mm;"><h4>Card</h4><p>Card paragraph</p></div>');
		$this->mpdf->WriteHTML('<div style="position: absolute; top: 240mm; left: 60mm; width: 80mm; border: 0.3mm solid #999999; padding: 3mm;">'
			. '<h4>Plain heading</h4><p>Plain paragraph, not a card</p></div>');
	}

}
