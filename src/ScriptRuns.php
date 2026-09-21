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
	 * The one character read against the script table rather than out of it.
	 *
	 * Unicode calls the Arabic End of Ayah Common, which would leave a verse number to be read as
	 * whatever script it happened to stand next to - shaped by that script's rules and, where no
	 * Arabic stands beside it, given that script's font, which need carry no glyph for it. Unicode
	 * answers this generally in Script_Extensions, which names the scripts a Common character may be
	 * read as; mPDF has only the Script property, so the character is named here instead.
	 */
	const END_OF_AYAH = 0x06DD;

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
	 * A character's script is the table's, but for END_OF_AYAH, and every caller is handed the same
	 * reading: the script a character is shaped as is the script it is given a font for.
	 *
	 * @param int[] $codepoints The string, as Mpdf::UTF8StringToArray() writes it
	 *
	 * @return array[] One run per entry, in order, each ['script' => int, 'characters' => array[]]
	 *                 where a character is ['uni' => int, 'script' => int, 'record' => int[]]. There
	 *                 is always at least one run; an empty string gives one empty run of no script.
	 */
	public static function split($codepoints)
	{
		$runs = [];
		$runScript = Ucdn::SCRIPT_COMMON;
		$characters = [];

		foreach ($codepoints as $codepoint) {
			$record = Ucdn::get_ucd_record($codepoint);
			$script = $codepoint === self::END_OF_AYAH ? Ucdn::SCRIPT_ARABIC : $record[6];

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
