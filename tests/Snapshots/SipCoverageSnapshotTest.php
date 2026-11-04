<?php

namespace Snapshots;

/**
 * Characters above U+1FFFF go to Sun-ExtB only where Sun-ExtB has them (#837).
 *
 * @group snapshot
 */
class SipCoverageSnapshotTest extends Snapshot
{

	/**
	 * @return string A unique identifier / name for the snapshot
	 */
	public function getId()
	{
		return 'sip-coverage';
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
		$this->mpdf = new \Mpdf\Mpdf([
			'mode' => 'utf-8',
			'default_font' => 'dejavusans',
			'useSubstitutions' => true,
		]);
		$this->mpdf->backupSubsFont = ['sun-exta'];

		ob_start();
		?>
		<style>
			p.note { color: #606060; }
			.sample { font-size: 28pt; margin-bottom: 4mm; }
		</style>

		<h1>mPDF</h1>
		<h2>Characters above U+1FFFF go to Sun-ExtB only where it has them</h2>

		<p class="note">The page is set in DejaVu Sans, which has none of the characters below. Sun-ExtB is the
			SIP font and Sun-ExtA the backup font. Sun-ExtB has CJK Extensions B to D and the compatibility
			supplement; no font here has Extension E.</p>

		<h3>U+20BB7, Extension B: in Sun-ExtB</h3>
		<p class="note">Between the brackets is a form of 吉 with a longer lower stroke.</p>
		<p class="sample">[&#x20BB7;]</p>

		<h3>U+2B820, Extension E: left in DejaVu Sans</h3>
		<p class="note">Between the brackets is DejaVu Sans's missing-character box: a tall rectangle.</p>
		<p class="sample">[&#x2B820;]</p>

		<h3>U+20BB7 then U+2B820: split between Sun-ExtB and DejaVu Sans</h3>
		<p class="note">Between the brackets are the form of 吉 in Sun-ExtB, then the same rectangle as above.</p>
		<p class="sample">[&#x20BB7;&#x2B820;]</p>

		<h3>The same in a Sun-ExtA paragraph: split between Sun-ExtB and Sun-ExtA</h3>
		<p class="note">Sun-ExtA names Sun-ExtB as its sip-ext font. Between the brackets are the form of 吉 in
			Sun-ExtB, then a blank space: Sun-ExtA's missing-character glyph is empty.</p>
		<p class="sample" style="font-family: sun-exta">[&#x20BB7;&#x2B820;]</p>
		<?php

		$this->mpdf->WriteHTML(ob_get_clean());
	}

}
