<?php

namespace Snapshots;

/**
 * Colour emoji from a COLR version 1 font, drawn as Type3 fonts: every emoji of TestEmoji-COLRv1, each
 * built in build.py to show another kind of paint rather than to look like its emoji, in a table saying
 * what each tests, then beside DejaVu text, at two sizes and justified. In the red paragraph the face's
 * mouth, a paint in the colour of the text, is red.
 *
 * @group snapshot
 */
class ColorEmojiColrV1SnapshotTest extends Snapshot
{

	use EmojiTable;

	/**
	 * Each emoji of the font, what it is, and what it tests
	 */
	const GLYPHS = [
		['&#x1F600;', 'Grinning face', 'A radial gradient from white to yellow, and the mouth in the colour of the text, red in the red paragraph.'],
		['&#x2764;&#xFE0F;', 'Heart', 'A linear gradient over a square, kept to the heart by SRC_IN, and a half-transparent shine.'],
		['&#x1F468;', 'Man', 'The face and eyes scaled to 80% about the face\'s centre.'],
		['&#x1F469;', 'Woman', 'A reflected linear gradient whose alpha varies, which draws the stripes. Preview, which draws no soft mask inside a Type3 glyph, shows the face flat.'],
		['&#x1F467;', 'Girl', 'A repeated radial gradient, which draws the rings.'],
		['&#x1F1E6;', 'Regional indicator A', 'A blue square, turned a little and moved by an affine transform.'],
		['&#x1F1FA;', 'Regional indicator U', 'The A drawn inside it by PaintColrGlyph, with a red stripe over it.'],
		['&#x1F3F4;', 'Black flag', 'The flag skewed, then rotated.'],
		['&#x1F3FD;', 'Skin tone', 'A sweep gradient, which mPDF does not draw yet, so it is a plain square in the colour of its middle stop.'],
		['&#x1F44D;', 'Thumbs up', 'Turned about its centre and cut off by its clip box, so the top of the thumb is missing on purpose.'],
		['&#x1F468;&#x200D;&#x1F469;&#x200D;&#x1F467;', 'Family', 'Three faces multiplied onto a yellow square.'],
		['&#x1F1E6;&#x1F1FA;', 'Flag of A and U', 'A linear gradient from blue to green, with a white stripe over it.'],
		['1&#xFE0F;&#x20E3;', 'Keycap 1', 'The digit cut out of a grey square by DEST_OUT.'],
		['&#x1F44D;&#x1F3FD;', 'Thumbs up, skin tone', 'Moved, then squashed to 80% of its width.'],
		['&#x1F3F4;&#xE0067;&#xE0062;&#xE0065;&#xE006E;&#xE0067;&#xE007F;', 'Flag of England', 'A white square from a Var paint, read as the paint it varies, and a red cross.'],
	];

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

		$this->writeEmojiTable('TestEmoji-COLRv1: each emoji tests a kind of paint, and may look unfinished', self::GLYPHS);
	}
}
