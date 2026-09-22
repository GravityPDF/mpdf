<?php

namespace Snapshots;

/**
 * What the colour font documents of made-up emoji write: a table naming each emoji and saying what it
 * tests, then the same emoji beside DejaVu text, at two sizes, justified, and in red text
 */
trait EmojiTable
{
	/**
	 * @param string     $heading What the table is headed
	 * @param string[][] $glyphs  Each emoji, its name, and what it tests
	 */
	private function writeEmojiTable($heading, array $glyphs)
	{
		$rows = '';
		foreach ($glyphs as $i => $glyph) {
			$rows .= sprintf(
				'<tr><td style="width: 8mm; color: #666">%d</td><td style="width: 16mm; font-size: 24pt">%s</td><td><b>%s</b><br>%s</td></tr>',
				$i + 1,
				$glyph[0],
				$glyph[1],
				$glyph[2]
			);
		}

		$emoji = implode(' ', array_column($glyphs, 0));

		$this->mpdf->WriteHTML(
			'<h3>' . $heading . '</h3>'
			. '<table style="font-size: 9pt; border-collapse: collapse" cellpadding="3">' . $rows . '</table>'
			. '<p style="font-size: 28pt">Emoji ' . $emoji . ' end</p>'
			. '<p style="font-size: 11pt; text-align: justify">' . str_repeat('Text with emoji ' . $emoji . ' in it. ', 4) . '</p>'
			. '<p style="font-size: 16pt; color: #c00">Red text keeps its emoji in colour: ' . $emoji . '</p>'
		);
	}
}
