<?php

namespace Mpdf\Css;

/**
 * The inherited CSS properties mPDF supports, by the names the merged CSS uses for them.
 *
 * An element takes these from its parent unless it sets them itself. Under CssMode::STANDARD each channel that hands
 * them on takes them from here:
 *
 * - a block to its child blocks: CssMerger::mergeInheritedBlockProperties() reads TEXT back from the block's saved
 *   text state through InlinePropertyConverter, and BLOCK from the block's level of the block stack;
 * - inline elements to a block opened inside them: BlockTag sets their text state aside on the enclosing block, and
 *   blockTextState() gives it to mergeInheritedBlockProperties() in place of the enclosing block's own;
 * - a positioned block to its content: Mpdf::WriteFixedPosHTML() puts TEXT and BLOCK from the block's merged CSS on
 *   the <div> that stands in for the block;
 * - a table to its cells: Table::open() puts TEXT from the table's merged CSS in base_table_properties, which each
 *   cell merges under its own CSS. The table hands on text-align, line-height, the line stacking and direction
 *   through its own fields;
 * - a table cell to a table nested in it: Table::open() starts the nested table's base_table_properties and default
 *   font from the cell's text state, read back through InlinePropertyConverter.
 *
 * To carry another property, add it to TEXT when it lives in the text state that Mpdf::saveInlineProperties() saves,
 * and make InlinePropertyConverter::convert() read it back. Otherwise add it to BLOCK, and carry it on the block stack
 * in mergeInheritedBlockProperties().
 * InheritedPropertiesTest checks that each channel carries each TEXT property.
 *
 * Under CssMode::LEGACY the channels carry the lists mPDF v7 did.
 */
final class InheritedProperties
{

	/**
	 * Held in the text state, and set by Mpdf::setCSS() for inline elements, blocks and table cells alike
	 */
	const TEXT = [
		'COLOR',
		'FONT-FAMILY',
		'FONT-SIZE',
		'FONT-STYLE',
		'FONT-WEIGHT',
		'FONT-KERNING',
		'FONT-VARIANT-POSITION',
		'FONT-VARIANT-CAPS',
		'FONT-VARIANT-LIGATURES',
		'FONT-VARIANT-NUMERIC',
		'FONT-VARIANT-ALTERNATES',
		'FONT-FEATURE-SETTINGS',
		'FONT-LANGUAGE-OVERRIDE',
		'LETTER-SPACING',
		'WORD-SPACING',
		'TEXT-TRANSFORM',
		'TEXT-SHADOW',
		'HYPHENS',
		'TEXT-OUTLINE',
		'TEXT-OUTLINE-COLOR',
		'TEXT-OUTLINE-WIDTH',
	];

	/**
	 * Held on the block, and read when its lines are laid out
	 */
	const BLOCK = [
		'TEXT-ALIGN',
		'TEXT-INDENT',
		'LINE-HEIGHT',
		'LINE-STACKING-STRATEGY',
		'LINE-STACKING-SHIFT',
		'DIRECTION',
		'LIST-STYLE-TYPE',
		'LIST-STYLE-IMAGE',
		'LIST-STYLE-POSITION',
	];

	/**
	 * What a positioned block handed its content under CssMode::LEGACY, in the order it was written. Text decorations
	 * and the stacking order are not inherited, but the <div> that stands in for the block takes them too
	 */
	const LEGACY_POSITIONED = [
		'TEXT-ALIGN',
		'TEXT-TRANSFORM',
		'TEXT-INDENT',
		'TEXT-DECORATION',
		'FONT-FAMILY',
		'FONT-STYLE',
		'FONT-WEIGHT',
		'FONT-SIZE',
		'LINE-HEIGHT',
		'TEXT-SHADOW',
		'LETTER-SPACING',
		'FONT-VARIANT-POSITION',
		'FONT-VARIANT-CAPS',
		'FONT-VARIANT-LIGATURES',
		'FONT-VARIANT-NUMERIC',
		'FONT-VARIANT-ALTERNATES',
		'FONT-FEATURE-SETTINGS',
		'FONT-LANGUAGE-OVERRIDE',
		'FONT-KERNING',
		'COLOR',
		'Z-INDEX',
	];

	/**
	 * @return string[] Every inherited property mPDF supports: TEXT, then BLOCK
	 */
	public static function names()
	{
		return array_merge(self::TEXT, self::BLOCK);
	}

	/**
	 * The entries of a set of merged CSS properties that are inherited
	 *
	 * @param array $properties Merged CSS, keyed by uppercased property name
	 * @param string[] $names The properties to take, such as TEXT
	 *
	 * @return array The entries of $properties named in $names, in the order of $names
	 */
	public static function of(array $properties, array $names)
	{
		$inherited = [];
		foreach ($names as $name) {
			if (isset($properties[$name])) {
				$inherited[$name] = $properties[$name];
			}
		}

		return $inherited;
	}

	/**
	 * The text state a block opened inside the block at a level inherits: that of the inline elements it opened in,
	 * which BlockTag sets aside on the enclosing block under CssMode::STANDARD, or else the enclosing block's own
	 *
	 * @param array $blocks The block stack, as Mpdf::$blk
	 * @param int $level
	 *
	 * @return array|null As Mpdf::saveInlineProperties() saves it, or null when the block at that level has none
	 */
	public static function blockTextState(array $blocks, $level)
	{
		if (isset($blocks[$level]['openInline'])) {
			return $blocks[$level]['openInline']['state'];
		}

		return isset($blocks[$level]['InlineProperties']) ? $blocks[$level]['InlineProperties'] : null;
	}
}
