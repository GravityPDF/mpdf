<?php

/**
 * Rewrites the font parser golden master from the current code:
 *
 *   composer fontcache:update <font> [<font> ...]
 *   composer fontcache:update all
 *
 * The fixtures are everything TTFontFile hands to Otl - read tests/Mpdf/Fonts/ParserGoldenMaster.php
 * before trusting a diff. A change here means a change in what gets shaped, so it wants explaining in
 * the pull request that causes it.
 */

require __DIR__ . '/../vendor/autoload.php';

exit(Mpdf\Fonts\GoldenMasterUpdate::run(new Mpdf\Fonts\ParserGoldenMaster(), 'fontcache:update', $argv));
