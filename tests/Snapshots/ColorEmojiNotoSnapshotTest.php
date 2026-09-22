<?php

namespace Snapshots;

/**
 * Every emoji of Noto Color Emoji's COLRv1 build, as Google Fonts serves it, drawn as Type3 fonts
 * beside DejaVu text: each fully-qualified sequence and component of Unicode's emoji-test.txt, a
 * paragraph per group, then the regional indicators and U+20E3, which the font also draws alone.
 *
 * Noto 2.051 shapes each of those 3953 sequences to one colour glyph. Of its other colour glyphs, the
 * digits, # and * stay in DejaVu, which has them, and one is reached by no character.
 *
 * Unlike the other snapshots its streams are compressed: uncompressed the fixture is 83 MB. The
 * comparison inflates them, so a failure still shows each object's content.
 *
 * @group snapshot
 */
class ColorEmojiNotoSnapshotTest extends Snapshot
{

	/**
	 * @return string A unique identifier / name for the snapshot
	 */
	public function getId()
	{
		return 'color-emoji-noto';
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
			'fontdata' => ['noto' => ['R' => 'Noto-COLRv1.ttf', 'useOTL' => 0xFF]],
			'default_font' => 'dejavusans',
			'useSubstitutions' => true,
			'backupSubsFont' => ['noto'],
		]);
		$this->mpdf->SetCompression(true);

		$html = '';
		foreach ($this->groups() as $group => $emoji) {
			$html .= '<h3>' . htmlspecialchars($group) . '</h3><p style="font-size: 16pt">' . implode(' ', $emoji) . '</p>';
		}

		$alone = [];
		foreach (array_merge(range(0x1F1E6, 0x1F1FF), [0x20E3]) as $codepoint) {
			$alone[] = sprintf('&#x%X;', $codepoint);
		}

		$this->mpdf->WriteHTML($html . '<h3>Drawn alone</h3><p style="font-size: 16pt">' . implode(' ', $alone) . '</p>');
	}

	/**
	 * @return string[][] The fully-qualified sequences and components of emoji-test.txt, as character
	 *                    references, by group
	 */
	private function groups()
	{
		$groups = [];
		$group = null;
		foreach (file(__DIR__ . '/../data/ttf/color/emoji-test.txt') as $line) {
			if (strpos($line, '# group: ') === 0) {
				$group = trim(substr($line, 9));
			} elseif (preg_match('/^([0-9A-F ]+?)\s*;\s*(fully-qualified|component)\s/', $line, $match)) {
				$groups[$group][] = '&#x' . str_replace(' ', ';&#x', $match[1]) . ';';
			}
		}

		return $groups;
	}
}
