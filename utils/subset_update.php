<?php

/**
 * Rewrites the subsetter golden master from the current code:
 *
 *   composer subset:update <font> [<font> ...]
 *   composer subset:update all
 *
 * The fixtures are the font programs embedded in the PDF, hashed table by table - read
 * tests/Mpdf/Fonts/SubsetGoldenMaster.php before trusting a diff. A change here means every PDF
 * embedding that font changes, so it wants explaining in the pull request that causes it.
 */

require __DIR__ . '/../vendor/autoload.php';

exit(Mpdf\Fonts\GoldenMasterUpdate::run(new Mpdf\Fonts\SubsetGoldenMaster(), 'subset:update', $argv));
