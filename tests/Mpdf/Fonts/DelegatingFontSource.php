<?php

namespace Mpdf\Fonts;

/**
 * A FontSourceInterface that is not a TTFontFile: it hands each call on to one, records it, and can
 * be told to hide tables or to read nothing into memory whole.
 *
 * FontSubsetter is typed against the interface, and a subclass of TTFontFile would still let it reach
 * the parser's properties. This cannot, so a subsetter that builds from it needs nothing else.
 */
class DelegatingFontSource implements FontSourceInterface
{

	/**
	 * @var string[] The interface methods called, in order
	 */
	public $calls = [];

	/**
	 * @var string[] Tags hasTable() answers false for
	 */
	public $hiddenTables = [];

	/**
	 * @var int|null What getMaxStrLenRead() answers instead of the parser's own
	 */
	public $maxStrLenRead;

	private $parser;

	public function __construct(FontSourceInterface $parser)
	{
		$this->parser = $parser;
	}

	public function open($file)
	{
		$this->calls[] = __FUNCTION__;

		return $this->parser->open($file);
	}

	public function selectFont($TTCfontID)
	{
		$this->calls[] = __FUNCTION__;

		$this->parser->selectFont($TTCfontID);
	}

	public function readTableDirectory($debug = false)
	{
		$this->calls[] = __FUNCTION__;

		$this->parser->readTableDirectory($debug);
	}

	public function hasTable($tag)
	{
		$this->calls[] = __FUNCTION__;

		return !in_array($tag, $this->hiddenTables, true) && $this->parser->hasTable($tag);
	}

	public function getTablePosition($tag)
	{
		$this->calls[] = __FUNCTION__;

		return $this->parser->getTablePosition($tag);
	}

	public function getMaxStrLenRead()
	{
		$this->calls[] = __FUNCTION__;

		return $this->maxStrLenRead === null ? $this->parser->getMaxStrLenRead() : $this->maxStrLenRead;
	}

	public function getCMAP4($unicode_cmap_offset, &$glyphToChar, &$charToGlyph)
	{
		$this->calls[] = __FUNCTION__;

		return $this->parser->getCMAP4($unicode_cmap_offset, $glyphToChar, $charToGlyph);
	}

	public function getHMTX($numberOfHMetrics, $numGlyphs, &$glyphToChar, $scale, $maxUniChar)
	{
		$this->calls[] = __FUNCTION__;

		return $this->parser->getHMTX($numberOfHMetrics, $numGlyphs, $glyphToChar, $scale, $maxUniChar);
	}

}
