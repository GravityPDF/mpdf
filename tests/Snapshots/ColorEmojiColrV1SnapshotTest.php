<?php

namespace Snapshots;

/**
 * Colour emoji from a COLR version 1 font, drawn as Type3 fonts: every emoji of TestEmoji-COLRv1,
 * each showing another kind of paint - gradients radial, linear, reflected and repeated, transforms, a
 * clip box, blend and Porter-Duff composites, another glyph drawn inside one, and a sweep gradient
 * drawn in one colour - beside DejaVu text, at two sizes and justified. In the red paragraph the
 * face's mouth, a paint in the colour of the text, is red.
 *
 * @group snapshot
 */
class ColorEmojiColrV1SnapshotTest extends Snapshot
{

	/**
	 * @return string A unique identifier / name for the snapshot
	 */
	public function getId()
	{
		return 'color-emoji-colrv1';
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
			'fontdata' => ['colrv1' => ['R' => 'TestEmoji-COLRv1.ttf', 'useOTL' => 0xFF]],
			'default_font' => 'dejavusans',
			'useSubstitutions' => true,
			'backupSubsFont' => ['colrv1'],
		]);

		$emoji = '&#x1F600; &#x2764;&#xFE0F; &#x1F468; &#x1F469; &#x1F467; &#x1F1E6; &#x1F1FA; &#x1F3F4; &#x1F3FD; &#x1F44D; '
			. '&#x1F468;&#x200D;&#x1F469;&#x200D;&#x1F467; &#x1F1E6;&#x1F1FA; 1&#xFE0F;&#x20E3; &#x1F44D;&#x1F3FD; '
			. '&#x1F3F4;&#xE0067;&#xE0062;&#xE0065;&#xE006E;&#xE0067;&#xE007F;';

		$this->mpdf->WriteHTML(
			'<p style="font-size: 28pt">Emoji ' . $emoji . ' end</p>'
			. '<p style="font-size: 11pt; text-align: justify">' . str_repeat('Text with emoji ' . $emoji . ' in it. ', 4) . '</p>'
			. '<p style="font-size: 16pt; color: #c00">Red text keeps its emoji in colour: ' . $emoji . '</p>'
		);
	}
}
