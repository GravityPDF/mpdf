<?php

namespace Snapshots;

/**
 * Colour emoji from a COLR version 0 font, drawn as Type3 fonts: a face, a heart asking for colour, a
 * ZWJ family, a flag, a keycap, a skin tone and the flag of England, beside DejaVu text, at two sizes
 * and justified. In the red paragraph the face's mouth, a layer in the colour of the text, is red; the
 * last paragraph draws the font's own number sign, which has no layers, in the colour of the text.
 *
 * @group snapshot
 */
class ColorEmojiColrV0SnapshotTest extends Snapshot
{

	/**
	 * @return string A unique identifier / name for the snapshot
	 */
	public function getId()
	{
		return 'color-emoji-colrv0';
	}

	/**
	 * Generate a PDF document by initializing the Mpdf object on $this->mpdf and
	 * loading it with content
	 *
	 * @return   void
	 * @internal Don't call any $this->mpdf->Output*() method
	 */
	public function generatePdf()
	{
		$this->mpdf = $this->createMpdf([
			'fontDir' => [__DIR__ . '/../data/ttf/color'],
			'fontdata' => ['colr' => ['R' => 'TestEmoji-COLRv0.ttf', 'useOTL' => 0xFF]],
			'default_font' => 'dejavusans',
			'useSubstitutions' => true,
			'backupSubsFont' => ['colr'],
		]);

		$emoji = '&#x1F600; &#x2764;&#xFE0F; &#x1F468;&#x200D;&#x1F469;&#x200D;&#x1F467; &#x1F1E6;&#x1F1FA; '
			. '1&#xFE0F;&#x20E3; &#x1F44D;&#x1F3FD; &#x1F3F4;&#xE0067;&#xE0062;&#xE0065;&#xE006E;&#xE0067;&#xE007F;';

		$this->mpdf->WriteHTML(
			'<p style="font-size: 28pt">Emoji ' . $emoji . ' end</p>'
			. '<p style="font-size: 11pt; text-align: justify">' . str_repeat('Text with emoji ' . $emoji . ' in it. ', 5) . '</p>'
			. '<p style="font-size: 16pt; color: #c00">Red text keeps its emoji in colour: ' . $emoji . '</p>'
			. '<p style="font-size: 28pt; color: #06c; font-family: colr">## &#x1F600;</p>'
		);
	}
}
