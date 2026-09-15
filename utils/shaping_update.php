<?php

/**
 * Rewrites the shaper golden master from the current code:
 *
 *   composer shaping:update <font> [<font> ...]
 *   composer shaping:update all
 *
 * The fixtures are what Otl::applyOTL() makes of a set of runs of text - read
 * tests/Mpdf/Fonts/ShapingGoldenMaster.php before trusting a diff. A change here means text is laid
 * out differently, so it wants explaining in the pull request that causes it.
 */

require __DIR__ . '/../vendor/autoload.php';

exit(Mpdf\Fonts\GoldenMasterUpdate::run(new Mpdf\Fonts\ShapingGoldenMaster(), 'shaping:update', $argv));
