<?php

namespace Mpdf\Tag;

class THeadTest extends BaseTagTestCase
{
	private $tag;

	protected function set_up()
	{
		parent::set_up();

		$this->tag = $this->createTag(THead::class);
	}

	public function testOpen_THead()
	{
		$attr = [];
		$ahtml = [];
		$ihtml = 0;

		$this->tag->open($attr, $ahtml, $ihtml);

		$this->assertEquals('THEAD', $this->mpdf->lastoptionaltag);
	}

	/**
	 * Under the legacy cascade the row group's font weight is kept for its cells, as well as its vertical-align
	 */
	public function testOpen_THead_WithInlineStyle()
	{
		$this->mpdf->cssMode = \Mpdf\CssMode::LEGACY;
		$attr = ['STYLE' => 'font-weight: bold; vertical-align: bottom;'];
		$ahtml = [];
		$ihtml = 0;

		$this->tag->open($attr, $ahtml, $ihtml);

		$this->assertEquals('THEAD', $this->mpdf->lastoptionaltag);
		// Verify CSS properties were applied to THead specific properties
		$this->assertEquals('B', $this->mpdf->thead_font_weight);
		$this->assertEquals('bottom', $this->mpdf->thead_valign_default);
	}

	/**
	 * Under the standard cascade the cells inherit the row group's font weight from its frame, so none is kept for them
	 */
	public function testOpen_THead_KeepsNoFontWeightUnderTheStandardCascade()
	{
		$attr = ['STYLE' => 'font-weight: bold; vertical-align: bottom;'];
		$ahtml = [];
		$ihtml = 0;

		$this->tag->open($attr, $ahtml, $ihtml);

		$this->assertEmpty($this->mpdf->thead_font_weight);
		$this->assertEquals('bottom', $this->mpdf->thead_valign_default);
	}

	public function testClose_THead()
	{
		$ahtml = [];
		$ihtml = 0;

		$this->tag->close($ahtml, $ihtml);

		$this->assertEquals('', $this->mpdf->lastoptionaltag);
		$this->assertTrue($this->mpdf->tabletheadjustfinished);
	}
}
