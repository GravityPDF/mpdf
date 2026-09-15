<?php

/**
 * Rewrites the OtlDump golden master from the current code:
 *
 *   composer otldump:update <font> [<font> ...]
 *   composer otldump:update all
 *
 * The fixtures are the dump's own report plus every diagnostic PHP raised producing it - read
 * tests/Mpdf/Fonts/OtlDumpGoldenMaster.php before trusting a diff.
 */

require __DIR__ . '/../vendor/autoload.php';

exit(Mpdf\Fonts\GoldenMasterUpdate::run(new Mpdf\Fonts\OtlDumpGoldenMaster(), 'otldump:update', $argv));
