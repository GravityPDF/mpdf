<?php

namespace Snapshots;

/**
 * An SVG first drawn in a pass that is later unwound keeps its gradient and opacity: in the contents, after an index
 * that painted the contents ahead of time, and in a page-break-inside:avoid block that moves to the next page.
 *
 * @group snapshot
 */
class LookAheadSvgGradientSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'look-ahead-svg-gradient';
	}

	/**
	 * Each case under a caption saying what should be seen
	 */
	public function generatePdf()
	{
		$contentsSvg = $this->svg('#ff00ff', '#00ffff');
		$keptSvg = $this->svg('#ff8000', '#0000ff');
		$preHtml = htmlspecialchars('<h2>Contents</h2><p>A band fading from magenta, half transparent, to cyan:</p><img src="' . $contentsSvg . '" />', ENT_QUOTES);

		ob_start();
		?>
		<h1>SVG gradients through a pass that is unwound</h1>
		<p>The next page is the contents. Its band has to show a gradient, not a blank space.</p>

		<tocpagebreak toc-preHTML="<?= $preHtml ?>" />

		<tocentry content="The index" />
		<indexentry content="Gradient" />
		<p>This page is here to be indexed.</p>

		<pagebreak />
		<indexinsert />

		<p>The same band after the index, fading from magenta to cyan:</p>
		<img src="<?= $contentsSvg ?>" />

		<pagebreak />
		<div style="height: 235mm; background: #eeeeee;">A tall block, so that the next one does not fit under it.</div>
		<div style="page-break-inside: avoid;">
			<p>This block moved to a page of its own. Its band fades from orange to blue:</p>
			<img src="<?= $keptSvg ?>" />
		</div>
		<?php
		$html = ob_get_clean();

		$this->mpdf = new \Mpdf\Mpdf();
		$this->mpdf->WriteHTML($html);
	}

	/**
	 * A wide band with a gradient between two colours, the first half transparent
	 *
	 * @param string $from
	 * @param string $to
	 *
	 * @return string
	 */
	private function svg($from, $to)
	{
		return 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="400" height="100"><defs><linearGradient id="g"><stop offset="0" stop-color="' . $from . '" stop-opacity="0.5"/><stop offset="1" stop-color="' . $to . '"/></linearGradient></defs><rect width="400" height="100" fill="url(#g)"/></svg>');
	}
}
