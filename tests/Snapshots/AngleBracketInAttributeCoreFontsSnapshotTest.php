<?php

namespace Snapshots;

/**
 * The angle-bracket-in-attribute cases in core fonts, where the HTML is also split at its tags to swap characters for
 * the symbol font's
 *
 * @group snapshot
 */
class AngleBracketInAttributeCoreFontsSnapshotTest extends AngleBracketInAttributeSnapshotTest
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'angle-bracket-in-attribute-core-fonts';
	}

	/**
	 * The configuration the document is drawn with
	 *
	 * @return array
	 */
	protected function config()
	{
		return ['mode' => 'c'];
	}

	/**
	 * What the heading says of the configuration
	 *
	 * @return string
	 */
	protected function configName()
	{
		return 'core fonts';
	}

}
