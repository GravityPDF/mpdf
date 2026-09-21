<?php

namespace Mpdf;

/**
 * Cuts a string into runs of one script each, which is the unit the rest of mPDF reads a script in:
 * Otl::selectShaper() picks a shaping algorithm per run, and markScriptToLang() gives each run the
 * language attribute its script asks for.
 *
 * Where a run ends decides which shaper a character reaches, so a character misjudged here is laid
 * out by the wrong algorithm or by none. That is why the rule lives in one place: it was copied into
 * three classes and restated in a fourth, and the bare script numbers in those copies had to be found
 * and changed together at every Unicode release.
 */
class ScriptRuns
{

	/**
	 * Whether a character of this script begins a run of its own.
	 *
	 * Common and Inherited do not. Punctuation, spaces and combining marks carry no script to shape by,
	 * and a comma between two Arabic words must not end the Arabic. Unknown is folded into the run
	 * around it for a different reason: it is how a codepoint the script table has not caught up with
	 * arrives, and such a character still reaches whichever shaper its neighbours did. That is why a
	 * character Ucdn can name no script for still needs an entry in a shaper's own tables.
	 *
	 * @param int $script
	 *
	 * @return bool
	 */
	public static function startsARun($script)
	{
		return $script !== Ucdn::SCRIPT_COMMON
			&& $script !== Ucdn::SCRIPT_INHERITED
			&& $script !== Ucdn::SCRIPT_UNKNOWN;
	}

	/**
	 * Characters ahead of the first one to start a run open the first run, which then takes that
	 * character's script. Nothing is dropped: the runs hold the whole string, in order.
	 *
	 * @param int[] $codepoints The string, as Mpdf::UTF8StringToArray() writes it
	 * @param int[] $scriptOverrides The script to read a codepoint as, by codepoint, where a caller
	 *                               reads one differently from the table. Otl shapes the Arabic End of
	 *                               Ayah as Arabic although Unicode calls it Common; the callers that
	 *                               pick a language rather than a shaper do not, which is #274 rather
	 *                               than this.
	 *
	 * @return array[] One run per entry, in order, each ['script' => int, 'characters' => array[]]
	 *                 where a character is ['uni' => int, 'script' => int, 'record' => int[]]. There
	 *                 is always at least one run; an empty string gives one empty run of no script.
	 */
	public static function split($codepoints, $scriptOverrides = [])
	{
		$runs = [];
		$runScript = Ucdn::SCRIPT_COMMON;
		$characters = [];

		foreach ($codepoints as $codepoint) {
			$record = Ucdn::get_ucd_record($codepoint);
			$script = isset($scriptOverrides[$codepoint]) ? $scriptOverrides[$codepoint] : $record[6];

			if (self::startsARun($script)) {
				if ($runScript === Ucdn::SCRIPT_COMMON) {
					$runScript = $script;
				} elseif ($runScript !== $script) {
					$runs[] = ['script' => $runScript, 'characters' => $characters];
					$runScript = $script;
					$characters = [];
				}
			}

			$characters[] = ['uni' => $codepoint, 'script' => $script, 'record' => $record];
		}

		$runs[] = ['script' => $runScript, 'characters' => $characters];

		return $runs;
	}

}
