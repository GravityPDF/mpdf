<?php

namespace Snapshots;

/**
 * Colour emoji from an SVG font, drawn as Type3 fonts: every emoji of TestEmoji-SVG, each built in
 * build.py to show another part of SVG rather than to look like the emoji it stands for. A table names
 * each one, says what it tests and why it may look unfinished - a clipped half, a notch, a square where
 * a flag would be - then the same emoji run beside DejaVu text, at two sizes and justified. The flag of
 * England, which has no document, is its outline. In the red paragraph the face's mouth, in
 * currentColor, and the flag of England are red.
 *
 * ColorEmojiSvgNotoSnapshotTest draws real emoji artwork from an SVG font.
 *
 * @group snapshot
 */
class ColorEmojiSvgSnapshotTest extends Snapshot
{

	/**
	 * Each emoji of the font, what it is, and what it tests
	 */
	const GLYPHS = [
		['&#x1F600;', 'Grinning face', 'A palette colour through var(), eyes drawn twice with use, and the mouth in currentColor, the colour of the text.'],
		['&#x2764;&#xFE0F;', 'Heart', 'One path using every path command. The arc and short lines squeezed in at the tip leave a notch at the bottom. A gradient fill and a half-transparent shine.'],
		['&#x1F468;', 'Man', 'A radial gradient with an off-centre focus. The eyes are stroked in white, one at half opacity.'],
		['&#x1F469;', 'Woman', 'Clipped by two rectangles 40 units apart, which leaves the white stripe down the middle. The eyes are a group at 80% opacity.'],
		['&#x1F467;', 'Girl', 'An embedded 64-pixel PNG stretched to the em, so it looks pixelated.'],
		['&#x1F1E6;', 'Regional indicator A', 'A reflected linear gradient, its stops\' opacity taken from the style attribute.'],
		['&#x1F1FA;', 'Regional indicator U', 'A repeated radial gradient, turned and squashed by gradientTransform.'],
		['&#x1F3F4;', 'Black flag', 'A black shape with a dashed grey outline. Its text is not drawn, since a font may not use text, and a small square is hidden by a mask.'],
		['&#x1F3FD;', 'Skin tone', 'The modifier on its own, a plain square, filled from the style attribute. A style element\'s rule turning it red is not applied.'],
		['&#x1F44D;', 'Thumbs up', 'Drawn out of place and moved back by the document\'s viewBox.'],
		['&#x1F468;&#x200D;&#x1F469;&#x200D;&#x1F467;', 'Family', 'Three faces drawn through use, taking a transform and colour from the group around them, in a document shared with the keycap.'],
		['&#x1F1E6;&#x1F1FA;', 'Flag of A and U', 'A blue square with a white stripe, from a gzipped document.'],
		['1&#xFE0F;&#x20E3;', 'Keycap 1', 'A frame whose even-odd fill leaves a hole, and the digit from the font\'s outline.'],
		['&#x1F44D;&#x1F3FD;', 'Thumbs up, skin tone', 'Clipped to the top half of its bounding box, so the bottom half is missing on purpose. Its gradient takes its stops from another.'],
		['&#x1F3F4;&#xE0067;&#xE0062;&#xE0065;&#xE006E;&#xE0067;&#xE007F;', 'Flag of England', 'No SVG document, so it is drawn from its plain outline, a black square.'],
	];

	/**
	 * @return string A unique identifier / name for the snapshot
	 */
	public function getId()
	{
		return 'color-emoji-svg';
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
			'fontdata' => ['svg' => ['R' => 'TestEmoji-SVG.ttf', 'useOTL' => 0xFF]],
			'default_font' => 'dejavusans',
			'useSubstitutions' => true,
			'backupSubsFont' => ['svg'],
		]);

		$rows = '';
		foreach (self::GLYPHS as $i => $glyph) {
			$rows .= sprintf(
				'<tr><td style="width: 8mm; color: #666">%d</td><td style="width: 16mm; font-size: 24pt">%s</td><td><b>%s</b><br>%s</td></tr>',
				$i + 1,
				$glyph[0],
				$glyph[1],
				$glyph[2]
			);
		}

		$emoji = implode(' ', array_column(self::GLYPHS, 0));

		$this->mpdf->WriteHTML(
			'<h3>TestEmoji-SVG: each emoji tests part of SVG, and may look unfinished</h3>'
			. '<table style="font-size: 9pt; border-collapse: collapse" cellpadding="3">' . $rows . '</table>'
			. '<p style="font-size: 28pt">Emoji ' . $emoji . ' end</p>'
			. '<p style="font-size: 11pt; text-align: justify">' . str_repeat('Text with emoji ' . $emoji . ' in it. ', 4) . '</p>'
			. '<p style="font-size: 16pt; color: #c00">Red text keeps its emoji in colour: ' . $emoji . '</p>'
		);
	}
}
