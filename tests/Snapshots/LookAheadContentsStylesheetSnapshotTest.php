<?php

namespace Snapshots;

/**
 * A stylesheet in the contents' pre-HTML applies to the contents and not to the body after an index, which painted the
 * contents ahead of time.
 *
 * @group snapshot
 */
class LookAheadContentsStylesheetSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'look-ahead-contents-stylesheet';
	}

	/**
	 * Each case under a caption saying what should be seen
	 */
	public function generatePdf()
	{
		$preHtml = htmlspecialchars('<style>.after { color: #ff0000; font-size: 30pt; }</style><p class="after">Large and red, on the contents page only</p>', ENT_QUOTES);

		ob_start();
		?>
		<h1>A stylesheet in the contents</h1>
		<p>The next page is the contents. Its first line is large and red, from a stylesheet in its own pre-HTML.</p>

		<tocpagebreak toc-preHTML="<?= $preHtml ?>" />

		<tocentry content="The index" />
		<indexentry content="Stylesheet" />
		<p>This page is here to be indexed.</p>

		<pagebreak />
		<indexinsert />

		<p class="after">This paragraph after the index has the same class, and is in the ordinary size and black.</p>
		<?php
		$html = ob_get_clean();

		$this->mpdf = new \Mpdf\Mpdf();
		$this->mpdf->WriteHTML($html);
	}
}
