<?php

namespace Mpdf\Ua;

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * The element stack, MCID allocation, artifact scopes and role map of a StructureTree
 *
 * @group pdfua
 */
class StructureTreeTest extends TestCase
{

	/** @var StructureTree */
	private $tree;

	/**
	 * Start each test with an empty tree.
	 */
	protected function set_up()
	{
		$this->tree = new StructureTree();
	}

	/**
	 * A new tree has a Document root, and it is the current element.
	 */
	public function testOpenCreatesRootDocument()
	{
		$this->assertSame('Document', $this->tree->getRoot()->getType());
		$this->assertSame($this->tree->getRoot(), $this->tree->getCurrent());
	}

	/**
	 * Opening an element adds it under the current one and makes it current.
	 */
	public function testOpenPushesChildElement()
	{
		$this->tree->open('P');
		$current = $this->tree->getCurrent();
		$this->assertSame('P', $current->getType());
		$children = $this->tree->getRoot()->getChildren();
		$this->assertCount(1, $children);
		$this->assertSame($current, $children[0]);
	}

	/**
	 * Closing an element makes its parent current again.
	 */
	public function testClosePoppsStack()
	{
		$this->tree->open('P');
		$this->tree->close();
		$this->assertSame($this->tree->getRoot(), $this->tree->getCurrent());
	}

	/**
	 * The Document root cannot be closed.
	 */
	public function testCloseWhenOnlyRootOnStackIsNoop()
	{
		$this->tree->close();
		$this->assertSame($this->tree->getRoot(), $this->tree->getCurrent());
	}

	/**
	 * Content added to an element is given a non-negative MCID.
	 */
	public function testAddContentReturnsMcid()
	{
		$this->tree->open('P');
		$mcid = $this->tree->addContent(0);
		$this->assertGreaterThanOrEqual(0, $mcid);
	}

	/**
	 * Two pieces of content on one page get different MCIDs.
	 */
	public function testMcidIsUnique()
	{
		$this->tree->open('P');
		$mcid1 = $this->tree->addContent(0);
		$this->tree->open('P');
		$mcid2 = $this->tree->addContent(0);
		$this->assertNotSame($mcid1, $mcid2);
	}

	/**
	 * MCIDs count from 0 on each page, as a content stream's MCIDs must be dense and 0-based
	 * (ISO 32000-1 §14.7.4.4).
	 */
	public function testMcidResetsToZeroPerPage()
	{
		$this->tree->open('P');
		$mcid0a = $this->tree->addContent(0);
		$mcid0b = $this->tree->addContent(0);

		$mcid1a = $this->tree->addContent(1);

		$this->assertSame(0, $mcid0a);
		$this->assertSame(1, $mcid0b);
		$this->assertSame(0, $mcid1a);
	}

	/**
	 * An artifact is given -1 in place of an MCID.
	 */
	public function testAddArtifactReturnsMinusOne()
	{
		$this->assertSame(-1, $this->tree->addArtifact());
	}

	/**
	 * Added content is entered in the ParentTree under its page and MCID.
	 */
	public function testParentTreeIsPopulated()
	{
		$this->tree->open('P');
		$mcid = $this->tree->addContent(0);
		$pt = $this->tree->getParentTree();
		$this->assertArrayHasKey(0, $pt);
		$this->assertArrayHasKey($mcid, $pt[0]);
		$this->assertSame($this->tree->getCurrent(), $pt[0][$mcid]);
	}

	/**
	 * The element records the page and MCID of the content added to it.
	 */
	public function testMcidsRecordedOnElement()
	{
		$this->tree->open('P');
		$elem = $this->tree->getCurrent();
		$mcid = $this->tree->addContent(0);
		$mcids = $elem->getMcids();
		$this->assertCount(1, $mcids);
		$this->assertSame(0, $mcids[0]['page']);
		$this->assertSame($mcid, $mcids[0]['mcid']);
	}

	/**
	 * Inside an artifact no structure element is opened.
	 */
	public function testOpenArtifactSuppressesStructElements()
	{
		$this->tree->openArtifact();
		$this->tree->open('P');
		$this->assertSame($this->tree->getRoot(), $this->tree->getCurrent());
		$this->assertCount(0, $this->tree->getRoot()->getChildren());
	}

	/**
	 * Content added inside an artifact is given -1.
	 */
	public function testAddContentInArtifactContextReturnsMinusOne()
	{
		$this->tree->openArtifact();
		$mcid = $this->tree->addContent(0);
		$this->assertSame(-1, $mcid);
	}

	/**
	 * Elements open again once the artifact is closed.
	 */
	public function testCloseArtifactRestoresNormalBehaviour()
	{
		$this->tree->openArtifact();
		$this->tree->closeArtifact();
		$this->tree->open('P');
		$this->assertSame('P', $this->tree->getCurrent()->getType());
	}

	/**
	 * Inside an artifact a close pops nothing, as the open before it pushed nothing.
	 */
	public function testCloseInArtifactContextIsNoop()
	{
		$this->tree->openArtifact();
		$this->tree->open('P');
		$this->tree->close();
		$this->assertSame($this->tree->getRoot(), $this->tree->getCurrent());
		$this->tree->closeArtifact();
	}

	/**
	 * Closing an artifact that was never opened leaves the tree outside any artifact.
	 */
	public function testCloseArtifactWhenDepthIsZeroIsNoop()
	{
		$this->assertFalse($this->tree->isInArtifact());
		$this->tree->closeArtifact();
		$this->assertFalse($this->tree->isInArtifact());
	}

	/**
	 * Content added for a given element inside an artifact is given -1 and not recorded on it.
	 */
	public function testAddContentForElementInArtifactContextReturnsMinusOne()
	{
		$this->tree->open('P');
		$elem = $this->tree->getCurrent();
		$this->tree->close();

		$this->tree->openArtifact();
		$result = $this->tree->addContentForElement($elem, 0);

		$this->assertSame(-1, $result);
		$this->assertCount(0, $elem->getMcids());
	}

	/**
	 * The first role mapping for a type is the one kept, so the RoleMap never conflicts with itself.
	 */
	public function testAddRoleMappingDuplicateFirstWins()
	{
		$this->tree->addRoleMapping('CustomBox', 'Div');
		$this->tree->addRoleMapping('CustomBox', 'Sect');
		$mappings = $this->tree->getRoleMappings();
		$this->assertSame('Div', $mappings['CustomBox']);
	}

	/**
	 * Nested artifacts are left only when the outermost one closes.
	 */
	public function testIsInArtifactNested()
	{
		$this->assertFalse($this->tree->isInArtifact());
		$this->tree->openArtifact();
		$this->assertTrue($this->tree->isInArtifact());
		$this->tree->openArtifact();
		$this->assertTrue($this->tree->isInArtifact());
		$this->tree->closeArtifact();
		$this->assertTrue($this->tree->isInArtifact());
		$this->tree->closeArtifact();
		$this->assertFalse($this->tree->isInArtifact());
	}
}
