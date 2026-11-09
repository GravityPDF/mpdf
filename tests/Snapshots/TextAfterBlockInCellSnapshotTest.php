<?php

namespace Snapshots;

/**
 * @group snapshot
 */
class TextAfterBlockInCellSnapshotTest extends Snapshot
{

	/**
	 * @return string A unique identifier / name for the snapshot
	 */
	public function getId()
	{
		return 'text-after-block-in-cell';
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
		$arabic = '&#x628;&#x650;&#x633;&#x652;&#x645;&#x650; &#x627;&#x644;&#x644;&#x651;&#x64E;&#x647;&#x650;';
		$samples = [
			['Arabic, left to right', 'xbriyaz', $arabic, ''],
			['Arabic, right to left', 'xbriyaz', $arabic, 'rtl'],
			['Arabic marks', 'dejavusans', $arabic, 'rtl'],
			['Latin marks', 'dejavusans', 'x&#x323;y&#x301;q&#x323;', ''],
			['Devanagari', 'freeserif', '&#x915;&#x94D;&#x937;&#x93F; &#x930;&#x94D;&#x915;&#x93F; &#x926;&#x941;', ''],
			['Thai', 'garuda', '&#xE17;&#xE35;&#xE48;&#xE19;&#xE35;&#xE48; &#xE19;&#xE49;&#xE33;', ''],
		];

		ob_start();
		?>
		<style>
			p.note { color: #606060; }
			table.samples { width: 100%; border-collapse: collapse; }
			table.samples th, table.samples td { border: 0.2mm solid #b0b0b0; padding: 2mm; }
			td.shaped { font-size: 20pt; width: 38%; }
			div.block { font-family: dejavusans; font-size: 8pt; color: #606060; }
			p.kerned { font-family: dejavusans; font-kerning: normal; font-size: 20pt; }
		</style>

		<h1>mPDF</h1>
		<h2>Text after a block in a table cell</h2>

		<p class="note">Text that follows a block in a cell is shaped like any other text. In each row
			the right cell draws the same glyphs in the same places as the left one, under a small
			block.</p>

		<table class="samples">
			<tr><th>Script</th><th>Alone in its cell</th><th>After a block</th></tr>
			<?php foreach ($samples as $sample) : ?>
				<?php $style = 'font-family: ' . $sample[1] . ($sample[3] ? '; direction: ' . $sample[3] : ''); ?>
				<tr>
					<td><?= $sample[0] ?></td>
					<td class="shaped" style="<?= $style ?>"><?= $sample[2] ?></td>
					<td class="shaped" style="<?= $style ?>"><div class="block">Block</div><?= $sample[2] ?></td>
				</tr>
			<?php endforeach; ?>
		</table>

		<h3>Kerning is applied once</h3>

		<p class="note">Text with no kerning of its own falls back to the font's kern table, for that
			text only. The pairs in the second line are as close as those in the first, not twice as
			close.</p>

		<p class="kerned"><span>AVAVA To Wa</span></p>
		<p class="kerned">Blk <span>AVAVA To Wa</span></p>
		<?php
		$html = ob_get_clean();

		$this->mpdf = new \Mpdf\Mpdf(['mode' => '', 'useKerning' => true, 'autoLangToFont' => false, 'autoScriptToLang' => false]);
		$this->mpdf->WriteHTML($html);
	}
}
