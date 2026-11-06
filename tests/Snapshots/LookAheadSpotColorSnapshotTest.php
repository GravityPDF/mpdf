<?php

namespace Snapshots;

/**
 * A spot colour first met in a pass that is later unwound is still defined, and each text prints in its own ink: the
 * contents, the body after an index that painted the contents ahead of time, and a page-break-inside:avoid block that
 * moves to the next page.
 *
 * @group snapshot
 */
class LookAheadSpotColorSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'look-ahead-spot-color';
	}

	/**
	 * Each case under a caption saying what should be seen
	 */
	public function generatePdf()
	{
		ob_start();
		?>
		<style>
			.mpdf_toc_t_level_0, .mpdf_toc_p_level_0 { color: spot(CONTENTSGREEN,100,100,0,100,0); }
			.later { color: spot(BODYMAGENTA,100,0,100,0,0); }
			.kept { color: spot(KEPTCYAN,100,100,0,0,0); }
		</style>

		<h1>Spot colours through a pass that is unwound</h1>
		<p>The next page is the contents. Its entry has to be green.</p>

		<tocpagebreak toc-preHTML="&lt;h2&gt;Contents&lt;/h2&gt;&lt;p&gt;The entry below, with its page number, is in the green spot colour.&lt;/p&gt;" />

		<tocentry content="The index" />
		<indexentry content="Ink" />
		<p>This page is here to be indexed.</p>

		<pagebreak />
		<indexinsert />

		<p class="later">This paragraph after the index is in the magenta spot colour, not the contents' green.</p>

		<pagebreak />
		<div style="height: 235mm; background: #eeeeee;">A tall block, so that the next one does not fit under it.</div>
		<div class="kept" style="page-break-inside: avoid;">
			<p>This block moved to a page of its own, and is in the cyan spot colour.</p>
			<p>It runs to several paragraphs.</p>
			<p>So that it does not fit under the block above.</p>
			<p>And has to move.</p>
		</div>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf();
		$this->mpdf->WriteHTML($html);
	}
}
