<?php

namespace Snapshots;

use Mpdf\CssMode;

/**
 * Braces, semicolons and comment markers inside strings, url() and escapes, and rules and at-rules nested in a block,
 * each read as a browser reads it, so the rule it is in and the rule after it both apply.
 *
 * @group snapshot
 */
class StylesheetSyntaxSnapshotTest extends Snapshot
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'stylesheet-syntax';
	}

	/**
	 * Pairs of paragraphs, the first styled by a rule with the construct in it and the second by the rule after,
	 * under a caption saying what should be seen
	 */
	public function generatePdf()
	{
		ob_start();
		?>
		<style>
			@import url(missing.css?family=Inter:wght@300;400;500&display=swap);
			p.after-import { color: #0a7d32; font-weight: bold; }

			body { font-size: 9pt; }
			h1 { font-size: 15pt; margin: 0 0 2mm 0; }
			h2 { margin: 3mm 0 1mm 0; font-size: 10pt; }
			p.caption { margin: 0 0 1mm 0; font-size: 8pt; color: #606060; }
			div.case { border: 0.2mm solid #999999; padding: 0 2mm; }
			div.case p { margin: 1mm 0; }

			p.brace-string { quotes: "}" "{"; color: #1f3a93; font-style: italic; }
			p.after-brace-string { color: #0a7d32; font-weight: bold; }

			p.unbalanced-string { quotes: "}" "»"; color: #1f3a93; font-style: italic; }
			p.after-unbalanced-string { color: #0a7d32; font-weight: bold; }

			p.semicolon-string { color: #1f3a93; font-family: 'x;color:#ff0000;y', monospace; }
			p.after-semicolon-string { color: #0a7d32; font-weight: bold; }

			p.escaped-quote { quotes: "\"}" "x"; color: #1f3a93; font-style: italic; }
			p.after-escaped-quote { color: #0a7d32; font-weight: bold; }

			p.comment-string { quotes: "/*" "x"; color: #1f3a93; font-style: italic; }
			p.after-comment-string { color: #0a7d32; font-weight: bold; }
			q { quotes: "*/" "x"; }

			p.comment-url { background-image: url(missing/*.png); color: #1f3a93; font-style: italic; }
			p.after-comment-url { color: #0a7d32; font-weight: bold; } /* a comment */

			p.open-string { color: #1f3a93; quotes: "a}b
			; font-style: italic; }
			p.after-open-string { color: #0a7d32; font-weight: bold; }

			p.md\:italic { color: #1f3a93; font-style: italic; }
			p.a\{b\} { color: #1f3a93; font-style: italic; }
			p.after-escapes { color: #0a7d32; font-weight: bold; }

			p[title="{"] { color: #1f3a93; font-style: italic; }
			p.after-attribute { color: #0a7d32; font-weight: bold; }

			p.url-braces { background-image: url(missing};{.png); background-color: #f9e79f; }
			p.after-url-braces { color: #0a7d32; font-weight: bold; }

			p.nested-rule { color: #1f3a93; & b { color: #ff0000; } font-style: italic; }
			p.nested-at-rule { color: #1f3a93; @media screen { color: #ff0000; } font-style: italic; }
			p.after-nested { color: #0a7d32; font-weight: bold; }
		</style>
		<style>p.left-open { color: #1f3a93; font-style: italic;</style>
		<style>p.after-left-open { color: #0a7d32; font-weight: bold; }</style>
		<style><?php echo "\xEF\xBB\xBF"; ?>@charset "UTF-8"; p.byte-order-mark { color: #0a7d32; font-weight: bold; }</style>

		<h1>Strings, url() and escapes in a stylesheet</h1>

		<h2>An @import with semicolons in its url()</h2>
		<p class="caption">The @import ends at the semicolon after its url(): the paragraph is green and bold.</p>
		<div class="case"><p class="after-import">After the @import</p></div>

		<h2>Braces in a string</h2>
		<p class="caption">The first paragraph is blue and italic, the second green and bold.</p>
		<div class="case"><p class="brace-string">Braces in quotes</p><p class="after-brace-string">After the rule</p></div>

		<h2>A brace in a string with no brace to pair with</h2>
		<p class="caption">The first paragraph is blue and italic, the second green and bold.</p>
		<div class="case"><p class="unbalanced-string">One brace in quotes</p><p class="after-unbalanced-string">After the rule</p></div>

		<h2>A declaration in a string</h2>
		<p class="caption">The string is a font name, not a color: the first paragraph is blue, in a monospace font; the second green and bold. None is red.</p>
		<div class="case"><p class="semicolon-string">Semicolons in quotes</p><p class="after-semicolon-string">After the rule</p></div>

		<h2>An escaped quote in a string</h2>
		<p class="caption">The first paragraph is blue and italic, the second green and bold.</p>
		<div class="case"><p class="escaped-quote">An escaped quote before a brace</p><p class="after-escaped-quote">After the rule</p></div>

		<h2>Comment markers in strings and url()</h2>
		<p class="caption">A /* in a string, closed by a */ in a later string, and a /* in an unquoted url() before a real comment, start no comment: each first paragraph is blue and italic, each second green and bold.</p>
		<div class="case">
			<p class="comment-string">A comment opener in quotes</p><p class="after-comment-string">After the rule</p>
			<p class="comment-url">A comment opener in url()</p><p class="after-comment-url">After the rule</p>
		</div>

		<h2>A string left open</h2>
		<p class="caption">The string ends at the line break, taking the brace with it, and the declaration on the next line applies: the first paragraph is blue and italic, the second green and bold.</p>
		<div class="case"><p class="open-string">A string with no closing quote</p><p class="after-open-string">After the rule</p></div>

		<h2>Escapes in selectors</h2>
		<p class="caption">.md\:italic and .a\{b\} match the classes md:italic and a{b}: the first two paragraphs are blue and italic, the third green and bold.</p>
		<div class="case"><p class="md:italic">class="md:italic"</p><p class="a{b}">class="a{b}"</p><p class="after-escapes">After the rules</p></div>

		<h2>A brace in an attribute selector</h2>
		<p class="caption">p[title="{"] matches the first paragraph, which is blue and italic; the second is green and bold.</p>
		<div class="case"><p title="{">title="{"</p><p class="after-attribute">After the rule</p></div>

		<h2>Braces and a semicolon in an unquoted url()</h2>
		<p class="caption">The url() is one value: the first paragraph has a yellow background, the second is green and bold.</p>
		<div class="case"><p class="url-braces">Braces in url()</p><p class="after-url-braces">After the rule</p></div>

		<h2>A rule and an at-rule nested in a block</h2>
		<p class="caption">Each is left out and the declarations after it apply: the first two paragraphs are blue and italic, with no red, and the third green and bold.</p>
		<div class="case"><p class="nested-rule">A rule nested in the block, with <b>bold</b> text</p><p class="nested-at-rule">An @media screen block nested in the block</p><p class="after-nested">After the rules</p></div>

		<h2>Each stylesheet on its own</h2>
		<p class="caption">A block left open ends with its &lt;style&gt;, and the next one starts afresh: the first paragraph is blue and italic, the second green and bold.</p>
		<div class="case"><p class="left-open">In a block left open</p><p class="after-left-open">In the next &lt;style&gt;</p></div>

		<h2>A byte order mark</h2>
		<p class="caption">A stylesheet starting with a byte order mark and @charset: the paragraph is green and bold.</p>
		<div class="case"><p class="byte-order-mark">After the byte order mark</p></div>
		<?php
		$html = ob_get_clean();

		$this->mpdf = $this->createMpdf(['cssMode' => CssMode::STANDARD]);

		$this->mpdf->WriteHTML($html);
	}

}
