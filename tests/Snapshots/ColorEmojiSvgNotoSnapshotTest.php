<?php

namespace Snapshots;

/**
 * Real emoji artwork from an SVG font, drawn as Type3 fonts: a subset of Adobe's OpenType-SVG build of
 * Noto Color Emoji, whose documents are gzipped and drawn mostly in linear and radial gradients turned
 * by gradientTransform. Its emoji are those the other colour emoji snapshots draw - a family, a skin
 * tone, flags, a keycap - and a few whose artwork is mostly gradients, beside DejaVu text, at two sizes
 * and justified. subset_noto_svg.py beside the font says how it was made.
 *
 * ColorEmojiSvgSnapshotTest shows the parts of SVG a real font does not use.
 *
 * @group snapshot
 */
class ColorEmojiSvgNotoSnapshotTest extends Snapshot
{

	/**
	 * @return string A unique identifier / name for the snapshot
	 */
	public function getId()
	{
		return 'color-emoji-svg-noto';
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
			'fontdata' => ['notosvg' => ['R' => 'NotoColorEmoji-SVG-Subset.ttf', 'useOTL' => 0xFF]],
			'default_font' => 'dejavusans',
			'useSubstitutions' => true,
			'backupSubsFont' => ['notosvg'],
		]);

		$emoji = '&#x1F600; &#x2764;&#xFE0F; &#x1F468; &#x1F469; &#x1F467; &#x1F1E6; &#x1F1FA; &#x1F3F4; &#x1F3FD; &#x1F44D; '
			. '&#x1F468;&#x200D;&#x1F469;&#x200D;&#x1F467; &#x1F1E6;&#x1F1FA; 1&#xFE0F;&#x20E3; &#x1F44D;&#x1F3FD; '
			. '&#x1F3F4;&#xE0067;&#xE0062;&#xE0065;&#xE006E;&#xE0067;&#xE007F; '
			. '&#x1F308; &#x1F525; &#x1F389; &#x1F355; &#x1F680; &#x2728; &#x1F30D;';

		$this->mpdf->WriteHTML(
			'<p style="font-size: 28pt">Emoji ' . $emoji . ' end</p>'
			. '<p style="font-size: 11pt; text-align: justify">' . str_repeat('Text with emoji ' . $emoji . ' in it. ', 4) . '</p>'
			. '<p style="font-size: 16pt; color: #c00">Red text keeps its emoji in colour: ' . $emoji . '</p>'
		);
	}
}
