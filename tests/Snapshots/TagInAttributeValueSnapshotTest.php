<?php

namespace Snapshots;

/**
 * A tag written inside a quoted attribute value is part of the value: comments and scripts are removed, and tags are
 * rewritten, only outside tags, so the value and the text after it are drawn as written
 *
 * @group snapshot
 */
class TagInAttributeValueSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'tag-in-attribute-value';
	}

	/**
	 * Each case under a caption saying what it should show
	 */
	public function generatePdf()
	{
		$html = '<style>
				body { font-size: 10pt; }
				h1 { font-size: 13pt; margin: 0 0 2mm 0; }
				p.caption { font-size: 8pt; color: #666666; margin: 3mm 0 1mm 0; }
				p { margin: 0; }
				input { width: 70mm; }
			</style>
			<h1>A tag inside an attribute value</h1>

			<p class="caption">A title holding "&lt;!--", then a real comment: three lines, "Before", "Middle" and "After".</p>
			<p title="a <!-- b">Before</p><p>Middle</p><!-- real comment --><p>After</p>

			<p class="caption">A title holding "&lt;script&gt;", then a real script: three lines, "Before", "Middle" and "After".</p>
			<p title="see <script> tag">Before</p><p>Middle</p><script>x()</script><p>After</p>

			<p class="caption">Text fields, each showing the tag in its value as written, after the label:</p>
			<p>comment: <input type="text" value="a <!-- b --> c"></p>
			<p>br: <input type="text" value="a<br>b"></p>
			<p>wbr: <input type="text" value="x<wbr>y"></p>
			<p>dottab: <input type="text" value="a <dottab> b"></p>
			<p>empty row: <input type="text" value="<tr></tr>"></p>
			<p>caption: <input type="text" value="<table><caption>c</caption></table>"></p>
			<p>symbol runs: <input type="text" value="</tts><tts>"></p>

			<p class="caption">Real tags still rewritten: "Line one" and "Line two" on two lines, then a table with the caption "The caption" above it.</p>
			<p>Line one<br>Line two</p>
			<table border="1"><caption>The caption</caption><tr><td>cell</td></tr></table>';

		$this->mpdf = $this->createMpdf();

		$this->mpdf->WriteHTML($html);
	}

}
