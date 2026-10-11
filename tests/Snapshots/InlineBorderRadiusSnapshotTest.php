<?php

namespace Snapshots;

/**
 * border-radius on inline elements: the background and border of each piece of an element's text follow the curve,
 * and an element that wraps is rounded only at its two ends, as with box-decoration-break: slice.
 *
 * @group snapshot
 */
class InlineBorderRadiusSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'inline-border-radius';
	}

	/**
	 * Each rounded span under a caption saying what its corners should look like
	 */
	public function generatePdf()
	{
		ob_start();
		?>
		<style>
			body { font-size: 11pt; }
			h1 { font-size: 13pt; margin: 0 0 1mm 0; }
			p.caption { font-size: 8pt; margin: 4mm 0 1.5mm 0; color: #606060; }
			p.sample { line-height: 2.2; }
		</style>

		<h1>border-radius on inline elements</h1>

		<p class="caption">A green background and a 1mm blue border, radius 3mm: all four corners rounded, the border following the curve with the green inside it.</p>
		<p class="sample">Before <span style="border: 1mm solid #0000ff; border-radius: 3mm; background: #00ff00">rounded span</span> after.</p>

		<p class="caption">The same span wrapping over three lines: rounded on the left of the first line and on the right of the last, square where it wraps, and the middle line square at both ends.</p>
		<p class="sample" style="width: 80mm">Start <span style="border: 1mm solid #0000ff; border-radius: 3mm; background: #00ff00">this span goes on long enough to wrap onto a second line, and then it goes on past the end of that line onto a third one</span> end.</p>

		<p class="caption">A bold word inside the span: one box, rounded only at its two ends.</p>
		<p class="sample">Before <span style="border: 1mm solid #0000ff; border-radius: 3mm; background: #00ff00">a <b>bold</b> word</span> after.</p>

		<p class="caption">A yellow background alone, radius 50%: an ellipse as wide and tall as the span.</p>
		<p class="sample">Before <span style="background: #ffcc00; border-radius: 50%">ellipse</span> after.</p>

		<p class="caption">border-radius: 0 3mm on a light blue background: square at the top left and bottom right, rounded 3mm at the top right and bottom left.</p>
		<p class="sample">Before <span style="border-radius: 0 3mm; background: #99ccff">uneven corners</span> after.</p>

		<p class="caption">Red borders, 1mm dashed, 1mm dotted and 1.5mm double, radius 3mm: the dashes and dots run round the curves, and the two lines of the double border both curve.</p>
		<p class="sample">
			<span style="border: 1mm dashed #cc0000; border-radius: 3mm">dashed</span>
			&nbsp; <span style="border: 1mm dotted #cc0000; border-radius: 3mm">dotted</span>
			&nbsp; <span style="border: 1.5mm double #cc0000; border-radius: 3mm">double</span>
		</p>

		<p class="caption">A 1.5mm groove in #3366cc, radius 3mm: dark outside and pale inside along the top and left, the other way round along the bottom and right, both rings curving.</p>
		<p class="sample">Before <span style="border: 1.5mm groove #3366cc; border-radius: 3mm">groove</span> after.</p>

		<p class="caption">A 0.5mm red outline 0.5mm out from a rounded span: the red ring rounds with it.</p>
		<p class="sample">Before <span style="border-radius: 3mm; background: #00ff00; outline: 0.5mm solid #cc0000; outline-offset: 0.5mm">outlined</span> after.</p>

		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf();

		$this->mpdf->WriteHTML($html);
	}

}
