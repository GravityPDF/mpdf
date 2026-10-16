<?php

namespace Snapshots;

use Mpdf\CssMode;

/**
 * The text-decoration longhands and the style, colour and thickness of the shorthand, text-underline-offset and
 * text-underline-position, and text-align: start, end and match-parent in blocks and cells, each under a caption
 * saying what it should look like (#607)
 *
 * @group snapshot
 */
class TextDecorationAlignSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'text-decoration-align';
	}

	/**
	 * The decorations, each a style for a line of text and a caption saying how it should be drawn
	 *
	 * @return string[][]
	 */
	protected function decorations()
	{
		return [
			['text-decoration-line: underline', 'Underlined in black, from text-decoration-line alone.'],
			['text-decoration: underline wavy #f00', 'A red wavy underline under black text.'],
			['text-decoration: underline double', 'Two thin black lines under the text.'],
			['text-decoration: underline dotted #00f', 'A row of blue dots under the text.'],
			['text-decoration: underline dashed #080', 'A green dashed line under the text.'],
			['text-decoration: line-through wavy #f00', 'A red wave through the middle of the text.'],
			['text-decoration: overline double #00f', 'Two blue lines over the text.'],
			['text-decoration: underline; text-decoration-thickness: 2pt', 'A thick black underline.'],
			['text-decoration: underline #f00', 'A red underline where the font puts it, for the three below.'],
			['text-decoration: underline #f00; text-underline-offset: 0', 'A red underline touching the bottom of the x, crossing the descenders of g, j, p and q.'],
			['text-decoration: underline #f00; text-underline-offset: 6pt', 'A red underline well clear of the descenders.'],
			['text-decoration: underline #f00; text-underline-position: under', 'A red underline just below the descenders.'],
		];
	}

	/**
	 * The alignments, each an element in a 100mm box and a caption saying where its text should be
	 *
	 * @return string[][]
	 */
	protected function alignments()
	{
		$block = '<div class="box" style="%s">Text</div>';
		$cell = '<table class="box"><tr><td style="%s">Text</td></tr></table>';

		return [
			[sprintf($block, 'text-align: start'), 'Block, start, ltr: at the left.'],
			[sprintf($block, 'text-align: end'), 'Block, end, ltr: at the right.'],
			[sprintf($block, 'direction: rtl; text-align: start'), 'Block, start, rtl: at the right.'],
			[sprintf($block, 'direction: rtl; text-align: end'), 'Block, end, rtl: at the left.'],
			['<div class="box" style="text-align: end"><p style="direction: rtl; text-align: match-parent">Text</p></div>', 'match-parent in an rtl block, inside an ltr block with end: at the right.'],
			[sprintf($cell, 'text-align: end'), 'Cell, end, ltr: at the right.'],
			[sprintf($cell, 'direction: rtl; text-align: end'), 'Cell, end, rtl: at the left.'],
		];
	}

	/**
	 * Each decoration on a line of text with descenders, and each alignment in a bordered box, under its caption
	 */
	public function generatePdf()
	{
		$html = '<style>
			body { font-size: 9pt; }
			h1 { font-size: 13pt; margin: 0 0 1mm 0; }
			h2 { font-size: 10pt; margin: 3mm 0 1mm 0; }
			p.caption { margin: 1.5mm 0 0.5mm 0; color: #555555; }
			p.sample { font-size: 14pt; margin: 0; }
			.box { width: 100mm; border: 0.2mm solid #999999; }
			table.box td { width: 100mm; }
		</style>
		<h1>Text decorations and logical alignment</h1>
		<h2>Decorations</h2>';

		foreach ($this->decorations() as $case) {
			$html .= '<p class="caption">' . $case[1] . '</p><p class="sample" style="' . $case[0] . '">Sample text gjpq</p>';
		}

		$html .= '<h2>Alignment</h2>';
		foreach ($this->alignments() as $case) {
			$html .= '<p class="caption">' . $case[1] . '</p>' . $case[0];
		}

		$this->mpdf = $this->createMpdf(['cssMode' => CssMode::STANDARD]);

		$this->mpdf->WriteHTML($html);
	}
}
