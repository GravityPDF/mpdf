<?php

namespace Snapshots;

/**
 * A plain @page rule with important margins against the :first, :left and named page rules that would otherwise set
 * them. Each page is a shaded panel filling its page area, with a caption saying where each cascade puts its
 * margins. Written under each value of cssMode.
 *
 * @group snapshot
 */
abstract class ImportantPageRuleSnapshot extends Snapshot
{

	/**
	 * @return string legacy or standard
	 */
	abstract protected function cascade();

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'important-page-rule-' . $this->cascade();
	}

	/**
	 * Four pages: a first page, a left page, then a named page and its own left page
	 */
	public function generatePdf()
	{
		ob_start();
		?>
		<style>
			@page { margin-top: 30mm !important; margin-left: 30mm !important; margin-right: 20mm; margin-bottom: 20mm; }
			@page :first { margin-top: 10mm; margin-left: 10mm; }
			@page :left { margin-left: 50mm; margin-right: 50mm; }
			@page appendix { margin-left: 10mm !important; margin-top: 15mm; }
			@page appendix :first { margin-left: 50mm; margin-top: 50mm !important; }

			body { font-family: dejavusans; font-size: 9pt; color: #212529; }
			h1 { font-size: 15pt; margin: 0 0 2mm 0; }
			p { margin: 0 0 1.5mm 0; }
			p.caption { font-size: 7.5pt; color: #6c757d; }
			.panel { background-color: #e6eef8; border: 0.5mm solid #336699; padding: 3mm; height: 100mm; }
			.turn { page-break-before: always; }
		</style>

		<div class="panel">
			<h1>!important in @page rules</h1>
			<p class="caption">Written with cssMode set to <?php echo $this->cascade(); ?>. Each panel fills its page area, so its edges are the page's margins.</p>
			<p>Page 1, the first page and a right page. The plain @page rule's top and left margins of 30mm are important; @page :first gives them 10mm.</p>
			<p class="caption">Standard: the panel starts 30mm from the top and the left, the important declarations beating :first. Legacy: 10mm from each, :first coming last. The right margin is 20mm in both.</p>
		</div>

		<div class="panel turn">
			<p>Page 2, a left page. The side margins are mirrored, so the plain rule's important margin-left of 30mm is this page's right margin. @page :left gives both sides 50mm.</p>
			<p class="caption">Standard: the panel ends 30mm from the right, the important margin beating :left. Legacy: 50mm. The left margin is 50mm in both. The top margin is 30mm in both.</p>
		</div>

		<div style="page: appendix">
			<div class="panel">
				<p>Page 3, the first page of the named page appendix and a right page. The named page's margin-left of 10mm is important; its :first gives 50mm. Its :first's margin-top of 50mm is important too; the named page gives 15mm.</p>
				<p class="caption">Standard: the panel starts 50mm from the top, the latest important declaration winning, and 10mm from the left, the named page's important margin beating its :first. Legacy: 50mm from each, :first coming last.</p>
			</div>

			<div class="panel turn">
				<p>Page 4, the named page's left page. Its important margin-left of 10mm is the right margin here. Its margin-top of 15mm is not important.</p>
				<p class="caption">Standard: the panel starts 30mm from the top, the plain rule's important margin beating the named page's. Legacy: 15mm, the named page coming last. The left margin is 50mm and the right 10mm in both.</p>
			</div>
		</div>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf(['cssMode' => $this->cascade()]);

		$this->mpdf->WriteHTML($html);
	}

}
