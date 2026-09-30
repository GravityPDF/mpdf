<?php

namespace Mpdf\Css;

use Mpdf\Mpdf;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Filing compiled rules under the rightmost compound of their selector, and finding the ones an element could match
 */
class RuleSetTest extends TestCase
{

	/**
	 * @var RuleSet
	 */
	private $rules;

	/**
	 * @var SelectorCompiler
	 */
	private $compiler;

	/**
	 * A rule set holding rules filed each way a rule is filed, with a declaration naming each one's position
	 */
	protected function set_up()
	{
		parent::set_up();

		$this->compiler = new SelectorCompiler(new Mpdf());
		$this->rules = new RuleSet();

		$selectors = [
			'div > p', // under P
			'p.a#main', // under #MAIN
			'div + .b.c', // under .B
			':first-child', // for any element
			'p ~ span', // under SPAN
			'.a > p', // under P: only the rightmost compound counts
		];
		foreach ($selectors as $position => $selector) {
			$this->rules->add($this->compiler->compile($selector), ['COLOR' => 'rule ' . $position]);
		}
	}

	/**
	 * Lets go of the rule set and compiler
	 */
	protected function tear_down()
	{
		unset($this->rules, $this->compiler);

		parent::tear_down();
	}

	/**
	 * An element's candidates are the rules filed under its tag, its id, each of its classes, and those for any
	 * element
	 *
	 * @dataProvider elements
	 *
	 * @param string $tag
	 * @param string $id
	 * @param string[] $classes
	 * @param int[] $expected The positions of the candidate rules
	 */
	public function testFindsTheRulesFiledUnderAnElement($tag, $id, array $classes, array $expected)
	{
		$candidates = $this->rules->candidates($tag, $id, $classes);
		sort($candidates);

		$this->assertSame($expected, $candidates);
	}

	/**
	 * An element's tag, id and classes, and the rules it could match
	 *
	 * @return array[]
	 */
	public function elements()
	{
		return [
			'a paragraph' => ['P', '', [], [0, 3, 5]],
			'a paragraph with the id' => ['P', 'MAIN', [], [0, 1, 3, 5]],
			'a class the rule is filed under' => ['DIV', '', ['B'], [2, 3]],
			'only the other class of the rule' => ['DIV', '', ['C'], [3]],
			'a class written twice' => ['DIV', '', ['B', 'B'], [2, 3]],
			'a span' => ['SPAN', '', [], [3, 4]],
			'a tag no rule names' => ['LI', '', [], [3]],
			'the document, matched as body' => ['', '', [], [3]],
			"mPDF's barcode" => ['BARCODE', '', [], []],
			"mPDF's dot tab" => ['DOTTAB', '', [], []],
			"mPDF's text circle" => ['TEXTCIRCLE', '', [], []],
			"mPDF's barcode with a class a rule is filed under" => ['BARCODE', '', ['B'], [2]],
			"mPDF's barcode with an id a rule is filed under" => ['BARCODE', 'MAIN', [], [1]],
		];
	}

	/**
	 * Rules keep the position they were added at, whatever they are filed under
	 */
	public function testKeepsEachRuleAtItsPosition()
	{
		$this->assertSame($this->compiler->compile('p ~ span'), $this->rules->rule(4)[0]);
		$this->assertSame(['COLOR' => 'rule 4'], $this->rules->rule(4)[1]);
	}

	/**
	 * An empty rule set has no candidates for any element
	 */
	public function testHasNoCandidatesUntilARuleIsAdded()
	{
		$rules = new RuleSet();
		$this->assertSame([], $rules->candidates('P', 'MAIN', ['A']));

		$rules->add($this->compiler->compile('div > p'), ['COLOR' => 'red']);
		$this->assertSame([0], $rules->candidates('P', 'MAIN', ['A']));
	}

	/**
	 * The declarations of the rules an element matches come in the order they apply: by specificity, and rules of
	 * the same specificity by position
	 */
	public function testGivesTheMatchingDeclarationsBySpecificityThenPosition()
	{
		$rules = new RuleSet();
		foreach (['.a > p', 'div > p', 'p:first-child', 'body > div > p', 'div > p', '#main > p', 'ul > p'] as $position => $selector) {
			$rules->add($this->compiler->compile($selector), ['COLOR' => 'rule ' . $position]);
		}

		$path = function () {
			return $this->path();
		};

		$this->assertSame(
			[['COLOR' => 'rule 1'], ['COLOR' => 'rule 4'], ['COLOR' => 'rule 3'], ['COLOR' => 'rule 0'], ['COLOR' => 'rule 2'], ['COLOR' => 'rule 5']],
			$rules->matchingDeclarations('P', '', [], $path)[0]
		);
	}

	/**
	 * The !important declarations of the rules an element matches come apart from the others, in the same order, and
	 * only from the rules that have any
	 */
	public function testGivesTheImportantDeclarationsApartInTheSameOrder()
	{
		$rules = new RuleSet();
		$rules->add($this->compiler->compile('#main > p'), ['COLOR' => 'id'], ['FONT-SIZE' => 'id']);
		$rules->add($this->compiler->compile('div > p'), ['COLOR' => 'first tag'], ['FONT-SIZE' => 'first tag']);
		$rules->add($this->compiler->compile('p:first-child'), ['COLOR' => 'pseudo-class']);
		$rules->add($this->compiler->compile('div > p'), [], ['FONT-SIZE' => 'second tag']);
		$rules->add($this->compiler->compile('ul > p'), ['COLOR' => 'no match'], ['FONT-SIZE' => 'no match']);

		$path = function () {
			return $this->path();
		};

		$this->assertSame(
			[
				[['COLOR' => 'first tag'], [], ['COLOR' => 'pseudo-class'], ['COLOR' => 'id']],
				[['FONT-SIZE' => 'first tag'], ['FONT-SIZE' => 'second tag'], ['FONT-SIZE' => 'id']],
			],
			$rules->matchingDeclarations('P', '', [], $path)
		);
	}

	/**
	 * The document's own frame is matched as body, by the rules filed for any element alone: the universal selector
	 * and the other rules whose subject names no tag, id or class, when they match the root
	 */
	public function testMatchesTheDocumentAsBodyWithTheRulesForAnyElement()
	{
		$rules = new RuleSet();
		foreach (['*', 'body', '* > *', ':not(p)', ':not(body)', '*:first-child', '*.a', '*:lang(fr)'] as $position => $selector) {
			$rules->add($this->compiler->compile($selector), ['COLOR' => 'rule ' . $position]);
		}

		$path = function () {
			return array_slice($this->path(), 0, 1);
		};

		$this->assertSame([['COLOR' => 'rule 0'], ['COLOR' => 'rule 3']], $rules->matchingDeclarations('', '', [], $path));
	}

	/**
	 * The open elements are only asked for when a rule is filed under the element
	 */
	public function testOnlyAsksForThePathWhenARuleIsFiledUnderTheElement()
	{
		$asked = false;
		$path = function () use (&$asked) {
			$asked = true;

			return null;
		};

		$this->assertSame([[], []], $this->rules->matchingDeclarations('LI', '', [], $path));
		$this->assertTrue($asked, 'The rule for any element should ask');

		$rules = new RuleSet();
		$rules->add($this->compiler->compile('div > p'), ['COLOR' => 'red']);
		$asked = false;
		$this->assertSame([[], []], $rules->matchingDeclarations('LI', '', [], $path));
		$this->assertFalse($asked);
	}

	/**
	 * A path that ends in another element than the one being styled matches nothing
	 */
	public function testMatchesNothingForThePathOfAnotherElement()
	{
		$rules = new RuleSet();
		$rules->add($this->compiler->compile('div > span'), ['COLOR' => 'red']);

		$path = function () {
			return $this->path();
		};

		$this->assertSame([[], []], $rules->matchingDeclarations('SPAN', '', [], $path));
	}

	/**
	 * html and body are matched from the document's frame alone, html as body's parent. Each gets the declarations of
	 * the rules it matches, by specificity and then position, and rules for any element are among them
	 */
	public function testGivesTheDeclarationsOfHtmlAndBody()
	{
		$rules = new RuleSet();
		$selectors = [':root', 'html', 'body', 'html > body', ':root body', 'body:not(:root)', ':lang(fr)', 'div > body', 'html body p'];
		foreach ($selectors as $position => $selector) {
			$rules->add($this->compiler->compile($selector), ['COLOR' => 'rule ' . $position]);
		}

		$path = $this->path();
		$path[0]['lang'] = 'fr';
		$document = [$path[0]];

		$this->assertSame(
			[[['COLOR' => 'rule 1'], ['COLOR' => 'rule 0'], ['COLOR' => 'rule 6']], []],
			$rules->documentDeclarations(true, $document)
		);
		$this->assertSame(
			[[['COLOR' => 'rule 2'], ['COLOR' => 'rule 3'], ['COLOR' => 'rule 6'], ['COLOR' => 'rule 4'], ['COLOR' => 'rule 5']], []],
			$rules->documentDeclarations(false, $document)
		);
	}

	/**
	 * A rule whose subject names no id, class or tag is filed for any element, unless it names :root, which only html
	 * matches, or :link, which only a and area match
	 */
	public function testFilesRootUnderHtmlAndLinkUnderItsTags()
	{
		$rules = new RuleSet();
		foreach ([':root', ':link', ':any-link:not(.x)', ':first-child'] as $selector) {
			$rules->add($this->compiler->compile($selector), ['COLOR' => 'red']);
		}

		$candidates = function ($tag) use ($rules) {
			$positions = $rules->candidates($tag, '', []);
			sort($positions);

			return $positions;
		};

		$this->assertSame([0, 3], $candidates('HTML'));
		$this->assertSame([1, 2, 3], $candidates('A'));
		$this->assertSame([1, 2, 3], $candidates('AREA'));
		$this->assertSame([3], $candidates('P'));
	}

	/**
	 * html is an ancestor of every element, and passes the check a rule naming an ancestor goes through first
	 */
	public function testHasHtmlAmongTheAncestorsOfEveryElement()
	{
		$rules = new RuleSet();
		$rules->add($this->compiler->compile('html p'), ['COLOR' => 'html']);
		$rules->add($this->compiler->compile(':root > body > div p'), ['COLOR' => 'root']);

		$path = function () {
			return $this->path();
		};

		$this->assertSame([['COLOR' => 'html'], ['COLOR' => 'root']], $rules->matchingDeclarations('P', '', [], $path)[0]);
	}

	/**
	 * The open elements a rule is matched against: a `<p>` as the first child of `<div id="main" class="a">`, the
	 * document's first child
	 *
	 * @return array[] The frames from the document down to the paragraph
	 */
	private function path()
	{
		$frame = function ($tag, array $attr) {
			return [
				'tag' => $tag,
				'id' => isset($attr['ID']) ? $attr['ID'] : '',
				'classes' => isset($attr['CLASS']) ? [$attr['CLASS']] : [],
				'lang' => '',
				'attr' => $attr,
				'nthChild' => 1,
				'nthOfType' => 1,
				'children' => [],
				'childTypes' => [],
				'computed' => null,
			];
		};

		return [$frame('', []), $frame('DIV', ['ID' => 'MAIN', 'CLASS' => 'A']), $frame('P', [])];
	}
}
