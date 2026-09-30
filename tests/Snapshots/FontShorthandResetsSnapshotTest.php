<?php

namespace Snapshots;

/**
 * The font shorthand drawing small-caps as small capitals, resetting the line-height, style, weight and variant it does
 * not name, and being dropped when it names a system font.
 *
 * @group snapshot
 */
class FontShorthandResetsSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'font-shorthand-resets';
	}

	/**
	 * Boxes each styled with the font shorthand, under a caption saying how the text should look
	 */
	public function generatePdf()
	{
		ob_start();
		?>
		<style>
			body { font-size: 9pt; }
			h1 { font-size: 15pt; margin: 0 0 2mm 0; }
			p.caption { margin: 4mm 0 1mm 0; font-size: 8pt; color: #606060; }
			div.case { border: 0.2mm solid #999999; padding: 1mm 2mm; font-family: dejavuserif; }
			div.case p { margin: 1mm 0; }

			p.small-caps { font: small-caps 14pt dejavuserif; }

			div.tall { line-height: 3; }
			p.shorthand { font: 12pt dejavuserif; }
			p.size-only { font-size: 12pt; }
			p.given { font: 12pt/2 dejavuserif; }

			div.styled { font-style: italic; font-weight: bold; font-variant: small-caps; }
			span.shorthand { font: 12pt dejavuserif; }

			p.system { font: italic 14pt dejavuserif; }
			p.system { font: caption; }
		</style>

		<h1>What the font shorthand sets</h1>

		<p class="caption">small-caps: the lowercase letters drawn as small capitals, the first letter of each word a full-size capital.</p>
		<div class="case"><p class="small-caps">Small capitals, not full capitals</p></div>

		<p class="caption">The box sets line-height: 3. The shorthand names no line height, so these lines are closely spaced.</p>
		<div class="case tall"><p class="shorthand">This paragraph is long enough to wrap onto more than one line, which shows how far apart the lines are drawn once the shorthand has reset the line height.</p></div>

		<p class="caption">For comparison, the same box with font-size: 12pt in place of the shorthand: widely spaced lines.</p>
		<div class="case tall"><p class="size-only">This paragraph is long enough to wrap onto more than one line, which shows how far apart the lines are drawn with the line height the box sets.</p></div>

		<p class="caption">The box sets line-height: 3. The shorthand gives 12pt/2, so these lines are twice the font size apart.</p>
		<div class="case tall"><p class="given">This paragraph is long enough to wrap onto more than one line, which shows how far apart the lines are drawn with the line height the shorthand gives.</p></div>

		<p class="caption">The box sets italic, bold and small-caps. The first line keeps them; the second, set with the shorthand, is upright, regular-weight lowercase.</p>
		<div class="case styled">
			<p>Italic bold small caps from the box</p>
			<p class="shorthand">upright regular lowercase from the shorthand</p>
		</div>

		<p class="caption">The same on an inline element: only the middle words are upright, regular-weight lowercase.</p>
		<div class="case styled"><p>Italic bold small caps, <span class="shorthand">upright regular lowercase,</span> italic bold small caps</p></div>

		<p class="caption">font: caption names a system font, which mPDF has none of, so it is dropped: italic 14pt serif from the rule before it.</p>
		<div class="case"><p class="system">Italic 14pt serif</p></div>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf(['cssMode' => \Mpdf\CssMode::STANDARD]);

		$this->mpdf->WriteHTML($html);
	}

}
