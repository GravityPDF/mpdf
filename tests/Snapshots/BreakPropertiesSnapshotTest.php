<?php

namespace Snapshots;

/**
 * The break-before, break-after and break-inside properties, read as their page-break-* equivalents, and break values
 * that force no page leaving the blocks around them alone.
 *
 * @group snapshot
 */
class BreakPropertiesSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'break-properties';
	}

	/**
	 * Sections each starting on the page their break names, under a caption saying what should be seen
	 */
	public function generatePdf()
	{
		ob_start();
		?>
		<style>
			body { font-size: 9pt; }
			h2 { font-size: 12pt; margin: 0 0 2mm 0; }
			p.caption { margin: 0 0 2mm 0; font-size: 8pt; color: #606060; }
			div.case { border: 0.3mm solid #c0392b; padding: 2mm; margin-bottom: 4mm; }
			div.case p { margin: 1mm 0; }
			div.keep { border: 0.3mm solid #1f3a93; background-color: #eaf2fb; padding: 2mm; }
			div.keep p { margin: 1mm 0; }
			p.filler { margin: 0 0 3mm 0; color: #909090; }
		</style>

		<h2>Page 1: break-after</h2>
		<p class="caption">Each red box has one paragraph with a break value that forces no page. Each box is drawn once, with both paragraphs inside it.</p>
		<div class="case"><p>First paragraph</p><p style="break-before: auto">Second paragraph, break-before: auto</p></div>
		<div class="case"><p>First paragraph</p><p style="break-before: avoid">Second paragraph, break-before: avoid</p></div>
		<div class="case"><p>First paragraph</p><p style="break-before: column">Second paragraph, break-before: column</p></div>
		<p style="break-after: page">This paragraph has break-after: page, so the next section starts on page 2.</p>

		<h2>Page 2: break-before: recto</h2>
		<p class="caption">Margins are mirrored. The next heading has break-before: recto, so it starts on the next odd page, page 3, and page 2 is otherwise empty.</p>

		<h2 style="break-before: recto">Page 3: break-before: verso</h2>
		<p class="caption">The next heading has break-before: verso, so it starts on the next even page, page 4.</p>

		<h2 style="break-before: verso">Page 4: break-inside: avoid-page</h2>
		<p class="caption">The blue box after the grey lines has break-inside: avoid-page. It would start at the foot of this page, so it is moved whole to page 5.</p>
		<?php for ($i = 1; $i <= 32; $i++) { ?>
		<p class="filler">Filler line <?php echo $i; ?></p>
		<?php } ?>
		<div class="keep" style="break-inside: avoid-page">
			<h2>Page 5: the kept box</h2>
			<?php for ($i = 1; $i <= 6; $i++) { ?>
			<p>Line <?php echo $i; ?> of the box, all on page 5.</p>
			<?php } ?>
		</div>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf(['mirrorMargins' => true]);

		$this->mpdf->WriteHTML($html);
	}

}
