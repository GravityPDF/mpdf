<?php

namespace Mpdf;

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Stylesheet rules that name a block with position: absolute or position: fixed, reaching the content inside it.
 *
 * That content is written as a document of its own once the page is done, so the block has to be put back as an
 * ancestor for descendant selectors to match the way they do in normal flow.
 */
class PositionedBlockSelectorTest extends TestCase
{

	use PageStreams;

	const RED = '1.000 0.000 0.000 rg';
	const GREEN = '0.000 0.667 0.000 rg';
	const BLUE = '0.000 0.000 1.000 rg';
	const BLACK = '0.000 g';

	/**
	 * A descendant rule naming the block's class reaches a paragraph and a table cell inside it
	 */
	public function testClassDescendantRulesReachTheContentOfAnAbsoluteBlock()
	{
		$colours = $this->colours('<style>
			.box td { color: #ff0000; }
			.box p { color: #00aa00; }
			.box { color: #0000ff; }
			</style>
			<div class="box"><p>flow p</p><table><tr><td>flow td</td></tr></table></div>
			<div class="box" style="position: absolute; top: 100mm; left: 20mm; width: 100mm;"><p>abs p</p><table><tr><td>abs td</td></tr></table><span>abs span</span></div>');

		$this->assertSame(self::GREEN, $colours['flow p']);
		$this->assertSame(self::RED, $colours['flow td']);
		$this->assertSame(self::GREEN, $colours['abs p']);
		$this->assertSame(self::RED, $colours['abs td']);
		$this->assertSame(self::BLUE, $colours['abs span'], 'The block\'s own colour should still be inherited');
	}

	/**
	 * A descendant rule naming the block's id reaches the content of a fixed block
	 */
	public function testIdDescendantRulesReachTheContentOfAFixedBlock()
	{
		$colours = $this->colours('<style>#side p { color: #ff0000; }</style>
			<div id="side" style="position: fixed; top: 10mm; left: 20mm; width: 100mm;"><p>fixed p</p></div>');

		$this->assertSame(self::RED, $colours['fixed p']);
	}

	/**
	 * The block's content sits directly inside it, with no other element between them for a selector to count
	 */
	public function testTheContentIsAChildOfTheBlockItself()
	{
		$colours = $this->colours('<style>.box div p { color: #ff0000; }</style>
			<div class="box" style="position: absolute; top: 100mm; left: 20mm; width: 100mm;"><p>direct p</p><div><p>nested p</p></div></div>');

		$this->assertSame(self::BLACK, $colours['direct p']);
		$this->assertSame(self::RED, $colours['nested p']);
	}

	/**
	 * One positioned block's rules stay with its own content, not the next positioned block's
	 */
	public function testTheRulesOfOneBlockDoNotReachTheNext()
	{
		$colours = $this->colours('<style>.box p { color: #00aa00; }</style>
			<div class="box" style="position: absolute; top: 20mm; left: 20mm; width: 100mm;"><p>boxed p</p></div>
			<div style="position: absolute; top: 100mm; left: 20mm; width: 100mm;"><p>plain p</p></div>');

		$this->assertSame(self::GREEN, $colours['boxed p']);
		$this->assertSame(self::BLACK, $colours['plain p']);
	}

	/**
	 * The fill colour each piece of text on the first page is drawn in, keyed by that text
	 *
	 * @param string $html
	 *
	 * @return string[]
	 */
	private function colours($html)
	{
		$pages = $this->pages($this->render($html));
		preg_match_all('/q ([\d. ]+ (?:rg|g)) .*?\((.*?)\) Tj/', $pages[0], $drawn, PREG_SET_ORDER);

		$colours = [];
		foreach ($drawn as $text) {
			$colours[$text[2]] = $text[1];
		}

		return $colours;
	}

}
