<?php

namespace Snapshots;

use Mpdf\CssMode;

/**
 * word-break, overflow-wrap and word-wrap: a URL too long for its box is cut at the edge when neither is set,
 * overflows the box when they leave it nowhere to break, is cut at the edge with break-word and anywhere, and fills
 * each line with break-all. In a table cell anywhere and break-all narrow the column; break-word does not. CJK text
 * breaks between any two letters unless keep-all keeps them together.
 *
 * @group snapshot
 */
class WordBreakSnapshotTest extends Snapshot
{

	/**
	 * The URL in each box
	 */
	const URL = 'see https://example.com/documents/annual-report-2026.pdf now';

	/**
	 * Japanese text with no spaces
	 */
	const CJK = '日本語の文章を書きます。これは長い文です。';

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'word-break';
	}

	/**
	 * Each case under a caption saying what it should show, grouped under headings: the declarations of the box, its
	 * text, the caption, and whether the box is a 30mm block or the cell of a 30mm table
	 *
	 * @return array
	 */
	protected function cases()
	{
		$url = self::URL;
		$cjk = self::CJK;

		return [
			'A URL in a 30mm block' => [
				['', $url, 'Neither property set: the URL is cut where each line is full, as before.', 'block'],
				['overflow-wrap: normal', $url, 'overflow-wrap: normal: the URL on a line of its own, running out of the right of the box.', 'block'],
				['word-break: keep-all', $url, 'word-break: keep-all: the same, running out of the box.', 'block'],
				['overflow-wrap: break-word', $url, 'overflow-wrap: break-word: the URL starts on its own line and is cut at the edge.', 'block'],
				['word-wrap: anywhere', $url, 'word-wrap: anywhere: the same, read as overflow-wrap.', 'block'],
				['word-break: break-all', $url, 'word-break: break-all: the URL starts after "see" and every line is full to the edge.', 'block'],
				['overflow-wrap: normal; text-align: right', $url, 'Right-aligned: "see" and "now" at the right, the overflowing URL still starting at the left.', 'block'],
				['overflow-wrap: normal; direction: rtl', 'Supercalifragilisticexpialidocious', 'rtl: the word ends at the right of the box and runs out of its left.', 'block'],
			],
			'A URL in the cell of a 30mm table' => [
				['', $url, 'Neither property set: the table widens to hold the URL on one line.', 'cell'],
				['overflow-wrap: break-word', $url, 'overflow-wrap: break-word: the table widens the same.', 'cell'],
				['overflow-wrap: anywhere', $url, 'overflow-wrap: anywhere: the table stays 30mm wide and the URL is cut at its edge.', 'cell'],
				['word-break: break-all', $url, 'word-break: break-all: the table stays 30mm wide and each line is full.', 'cell'],
			],
			'CJK text in a 30mm block' => [
				['', $cjk, 'Neither property set: broken between letters, each line full.', 'block'],
				['word-break: keep-all', $cjk, 'word-break: keep-all: broken only after the full stop, the first sentence running out of the box.', 'block'],
				['word-break: keep-all; overflow-wrap: anywhere', $cjk, 'keep-all with overflow-wrap: anywhere: the first sentence cut at the edge, the second starting a line.', 'block'],
			],
		];
	}

	/**
	 * Each case's box under its caption, with a grey border to show the box's edges
	 */
	public function generatePdf()
	{
		$html = '<style>
			body { font-size: 8pt; }
			h1 { font-size: 13pt; margin: 0 0 1mm 0; }
			h2 { font-size: 10pt; margin: 3mm 0 1mm 0; }
			p { margin: 1.5mm 0 0.5mm 0; }
			div.box { width: 30mm; font-size: 10pt; border: 0.2mm solid #999999; }
			table { width: 30mm; border-collapse: collapse; }
			td { font-size: 10pt; border: 0.2mm solid #999999; padding: 0; }
		</style>
		<h1>word-break and overflow-wrap</h1>';

		foreach ($this->cases() as $heading => $cases) {
			$html .= '<h2>' . $heading . '</h2>';
			foreach ($cases as $case) {
				list($css, $text, $caption, $box) = $case;
				$html .= '<p>' . htmlspecialchars($caption) . '</p>';
				if ($box === 'cell') {
					$html .= '<table><tr><td style="' . $css . '">' . $text . '</td></tr></table>';
				} else {
					$html .= '<div class="box" style="' . $css . '">' . $text . '</div>';
				}
			}
		}

		$this->mpdf = $this->createMpdf(['cssMode' => CssMode::STANDARD, 'autoScriptToLang' => true, 'autoLangToFont' => true]);

		$this->mpdf->WriteHTML($html);
	}
}
