<?php

namespace Snapshots;

use Mpdf\Container\SimpleContainer;
use Mpdf\RecordingFontFileFinder;

/**
 * Text moved into backup fonts chosen from their ranges files, with only the fonts it is drawn in on
 * disk (#827). The finder finds Sun-ExtA, Sun-ExtB and Garuda and nothing else, so a backup font read
 * to see whether it has a character would stop the render.
 *
 * @group snapshot
 */
class CharacterRangesSnapshotTest extends Snapshot
{

	/**
	 * The only font files the finder finds
	 */
	const ON_DISK = ['Sun-ExtA.ttf', 'Sun-ExtB.ttf', 'Garuda.ttf'];

	/**
	 * @return string A unique identifier / name for the snapshot
	 */
	public function getId()
	{
		return 'character-ranges';
	}

	/**
	 * Generate a PDF document by initializing the Mpdf object on $this->mpdf and
	 * loading it with content
	 *
	 * @return   void
	 * @internal Don't call any $this->mpdf->Output*() method
	 */
	public function generatePdf()
	{
		$finder = new RecordingFontFileFinder(__DIR__ . '/../../ttfonts');
		$finder->onDisk = self::ON_DISK;

		$this->mpdf = new \Mpdf\Mpdf(self::config(), new SimpleContainer(['fontFileFinder' => $finder]));
		$this->mpdf->backupSubsFont = self::backupSubsFont();
		$this->mpdf->WriteHTML(self::html());
	}

	/**
	 * @return array The document's config, without the finder
	 */
	public static function config()
	{
		return [
			'mode' => 'utf-8',
			'default_font' => 'chelvetica',
			'useSubstitutions' => true,
		];
	}

	/**
	 * @return string[] DejaVu Sans Condensed and FreeSans first, which have none of the samples'
	 *                  characters, then Garuda ahead of Sun-ExtA so the Thai is drawn in Garuda
	 */
	public static function backupSubsFont()
	{
		return ['dejavusanscondensed', 'freesans', 'garuda', 'sun-exta'];
	}

	/**
	 * @return string
	 */
	public static function html()
	{
		ob_start();
		?>
		<style>
			p.note { color: #606060; }
			.sample { font-size: 18pt; margin-bottom: 4mm; }
		</style>

		<h1>mPDF</h1>
		<h2>Backup fonts chosen from their character ranges</h2>

		<p class="note">The page is set in Helvetica, a PDF core font. The backup fonts are, in order, DejaVu
			Sans Condensed, FreeSans, Garuda and Sun-ExtA. Only Sun-ExtA, Sun-ExtB and Garuda are on disk:
			which characters each backup font has is read from the ranges file mPDF ships for it, so
			DejaVu Sans Condensed and FreeSans are never opened. Each sample shows its script in the font
			named above it, with no question marks and no empty boxes.</p>

		<h3>Japanese, in Sun-ExtA</h3>
		<p class="sample">Tokyo: 東京へようこそ</p>

		<h3>Thai, in Garuda</h3>
		<p class="sample">Bangkok: ยินดีต้อนรับสู่กรุงเทพ</p>

		<h3>A Plane 2 character, in Sun-ExtB</h3>
		<p class="note">This paragraph is set in Sun-ExtA, which hands Plane 2 to Sun-ExtB. The first character
			after the colon is U+20BB7, a form of 吉 with a longer lower stroke; the two after it are in Sun-ExtA.</p>
		<p class="sample" style="font-family: sun-exta">U+20BB7: &#x20BB7;野家</p>
		<?php

		return ob_get_clean();
	}

}
