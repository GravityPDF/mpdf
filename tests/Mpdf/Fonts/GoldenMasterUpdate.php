<?php

namespace Mpdf\Fonts;

/**
 * The command line the three golden-master update utilities share.
 *
 * Each of them is one call to a master's update(), wrapped in the same argument handling: a list of
 * font names or "all", a usage message naming every font in the corpus when the arguments do not
 * resolve, and a line per fixture written. Written once here so that a fourth master is a utility of
 * four lines, and so that the three read alike at the prompt.
 */
class GoldenMasterUpdate
{

	/**
	 * @param GoldenMaster $master  The master to rewrite the fixtures of
	 * @param string       $command The composer script that runs this, for the usage message
	 * @param array        $argv    The script's own $argv, including the script name
	 *
	 * @return int An exit status
	 */
	public static function run(GoldenMaster $master, $command, array $argv)
	{
		$fonts = array_keys($master->fonts());
		$names = array_slice($argv, 1);

		if ($names === ['all']) {
			$names = $fonts;
		}

		$unknown = array_diff($names, $fonts);
		if ($unknown) {
			fwrite(STDERR, 'There is no font called ' . implode(', ', $unknown) . ".\n\n");
		}

		if (!$names || $unknown) {
			fwrite(STDERR, sprintf(
				"Usage: composer %s <font> [<font> ...] | all\n\nThe fonts are:\n  %s\n",
				$command,
				implode("\n  ", $fonts)
			));

			return 1;
		}

		foreach ($names as $name) {
			$file = $master->update($name);

			if ($file === null) {
				printf("%s: no OTL tables, no fixture\n", $name);
				continue;
			}

			printf("%s: %s written, %d bytes\n", $name, realpath($file), filesize($file));
		}

		return 0;
	}
}
