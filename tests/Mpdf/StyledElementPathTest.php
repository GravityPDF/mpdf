<?php

namespace Mpdf;

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * The open elements the CSS merger matches compiled rules against, for each element it styles: the element opened
 * by a start tag, a block opened again after a forced page break, a positioned block and the content written in it
 */
class StyledElementPathTest extends TestCase
{

	/**
	 * The path to an element being styled is asked for once for each time it is styled, if a compiled rule is filed
	 * under it: once for an element opened, and again for a block opened again after a forced page break and for the
	 * content of a positioned block, which is written twice
	 *
	 * @dataProvider documents
	 *
	 * @param string $html
	 * @param array $expected The tags on each path asked for, joined by >, the document's own frame first with no tag
	 */
	public function testGivesThePathToTheElementBeingStyled($html, array $expected)
	{
		$mpdf = new StyledPathRecordingMpdf(['mode' => 'c', 'cssMode' => CssMode::STANDARD]);
		$mpdf->WriteHTML($html);

		$this->assertSame($expected, $mpdf->styledPaths);
	}

	/**
	 * The position of the element being styled, which the legacy tr, td and th:nth-child rules read without making the
	 * path, is the one its frame on the path has
	 *
	 * @dataProvider documents
	 *
	 * @param string $html
	 */
	public function testGivesThePositionTheElementHasOnItsPath($html)
	{
		$mpdf = new StyledPathRecordingMpdf(['mode' => 'c', 'cssMode' => CssMode::STANDARD]);
		$mpdf->WriteHTML($html . '<style>tr > td, tbody > tr { font-weight: bold; }</style>'
			. '<table><tr><td>a</td><td>b</td></tr></table><table><tbody><tr><td>c</td></tr></tbody><tr><td>d</td></tr></table>');

		$this->assertGreaterThan(5, count($mpdf->nthChildren));
		foreach ($mpdf->nthChildren as $nthChild) {
			$this->assertSame($nthChild[0], $nthChild[1]);
		}
	}

	/**
	 * A document with rules the matcher reads, and the paths asked for while it is written
	 *
	 * @return array[]
	 */
	public function documents()
	{
		return [
			'elements in the flow' => [
				'<style>div > p, p > b { color: red; }</style><div><p>a <b>b</b></p></div><p>c</p>',
				['>DIV>P', '>DIV>P>B', '>P'],
			],
			'a row written straight into a table sits in a tbody' => [
				'<style>tbody > tr { color: red; }</style><table><tr><td>a</td></tr></table>',
				['>TABLE>TBODY>TR'],
			],
			'a row in a tbody of its own' => [
				'<style>tbody > tr { color: red; }</style><table><tbody><tr><td>a</td></tr></tbody></table>',
				['>TABLE>TBODY>TR'],
			],
			'blocks opened again after a forced page break' => [
				'<style>body > div, div > p { color: red; }</style><div><p>a<pagebreak />b</p></div>',
				['>DIV', '>DIV>P', '>DIV', '>DIV>P'],
			],
			'a positioned block and its content, which is written twice, but not the div standing in for it' => [
				'<style>span > div, div > p { color: red; }</style>'
				. '<span><div style="position: absolute; top: 50mm; left: 20mm; width: 50mm"><p>a</p></div></span>',
				['>SPAN>DIV', null, '>SPAN>DIV>P', null, '>SPAN>DIV>P'],
			],
		];
	}

	/**
	 * Once the start tags are read, no element is being styled, and the stack is not held on to
	 */
	public function testLetsGoOfTheElementOnceItsStartTagIsRead()
	{
		$mpdf = new StyledPathRecordingMpdf(['mode' => 'c', 'cssMode' => CssMode::STANDARD]);
		$mpdf->WriteHTML('<style>div > p { color: red; }</style><div><p>a</p><p>b</p></div>');

		$this->assertNull($mpdf->getStyledElementPath());
	}

	/**
	 * The spans autoScriptToLang wraps a run of another script in are not the document's elements
	 */
	public function testGivesNoPathForASpanWrappedAroundARunOfAnotherScript()
	{
		$mpdf = new StyledPathRecordingMpdf(['mode' => '', 'autoScriptToLang' => true, 'cssMode' => CssMode::STANDARD]);
		$mpdf->WriteHTML('<style>p > span { color: red; }</style><p>abc שלום <span>def</span></p>');

		$this->assertSame([null, '>P>SPAN'], $mpdf->styledPaths);
	}
}
