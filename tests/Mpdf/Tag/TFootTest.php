<?php

namespace Mpdf\Tag;

class TFootTest extends BaseTagTestCase
{
	private $tag;

	protected function set_up()
	{
		parent::set_up();

		$this->tag = $this->createTag(TFoot::class);
	}

	public function testOpen_TFoot()
	{
		$attr = [];
		$ahtml = [];
		$ihtml = 0;

		$this->tag->open($attr, $ahtml, $ihtml);

		$this->assertEquals('TFOOT', $this->mpdf->lastoptionaltag);
	}

	/**
	 * Under the legacy cascade the row group's font weight is kept for its cells, as well as its vertical-align
	 */
	public function testOpen_TFoot_WithInlineStyle()
	{
		$this->mpdf->cssMode = \Mpdf\CssMode::LEGACY;
		$attr = ['STYLE' => 'font-weight: bold; vertical-align: top;'];
		$ahtml = [];
		$ihtml = 0;

		$this->tag->open($attr, $ahtml, $ihtml);

		$this->assertEquals('TFOOT', $this->mpdf->lastoptionaltag);
		// Verify CSS properties were applied to TFoot specific properties
		$this->assertEquals('B', $this->mpdf->tfoot_font_weight);
		$this->assertEquals('top', $this->mpdf->tfoot_valign_default);
	}

	/**
	 * Under the standard cascade the cells inherit the row group's font weight from its frame, so none is kept for them
	 */
	public function testOpen_TFoot_KeepsNoFontWeightUnderTheStandardCascade()
	{
		$attr = ['STYLE' => 'font-weight: bold; vertical-align: top;'];
		$ahtml = [];
		$ihtml = 0;

		$this->tag->open($attr, $ahtml, $ihtml);

		$this->assertEmpty($this->mpdf->tfoot_font_weight);
		$this->assertEquals('top', $this->mpdf->tfoot_valign_default);
	}

	public function testClose_TFoot()
	{
		$ahtml = [];
		$ihtml = 0;

		$this->tag->close($ahtml, $ihtml);

		$this->assertEquals('', $this->mpdf->lastoptionaltag);
	}
}
