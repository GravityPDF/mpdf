<?php

namespace Mpdf;

/**
 * The scalar and array state of an object, captured and put back whole, for a document look-ahead that has to
 * be unwound. Objects and resources are not put back: what they hold is either a service or state of their own
 *
 * @internal
 */
trait StateSnapshot
{

	/**
	 * Every property, objects included: a snapshot is taken for each block laid out and seldom put back, so objects are
	 * left out on restore instead. A property bound by reference stays bound in the snapshot, so a class with one
	 * copies its value in itself
	 *
	 * @return array<string, mixed>
	 */
	public function getStateSnapshot()
	{
		return get_object_vars($this);
	}

	/**
	 * @param array<string, mixed> $snapshot
	 *
	 * @return void
	 */
	public function restoreStateSnapshot(array $snapshot)
	{
		foreach ($snapshot as $key => $value) {
			if (!is_object($value) && !is_resource($value)) {
				$this->{$key} = $value;
			}
		}
	}
}
