<?php

namespace Snapshots;

/**
 * The angle-bracket-in-attribute cases with autoScriptToLang off, where unsupported tags used to go through
 * strip_tags(), which deleted a "<" or ">" inside a value and read a stray quote as opening one
 *
 * @group snapshot
 */
class AngleBracketInAttributePlainSnapshotTest extends AngleBracketInAttributeSnapshotTest
{

	/**
	 * The fixture's name
	 *
	 * @return string
	 */
	public function getId()
	{
		return 'angle-bracket-in-attribute-plain';
	}

	/**
	 * The configuration the document is drawn with
	 *
	 * @return array
	 */
	protected function config()
	{
		return [];
	}

	/**
	 * What the heading says of the configuration
	 *
	 * @return string
	 */
	protected function configName()
	{
		return 'autoScriptToLang off';
	}

}
