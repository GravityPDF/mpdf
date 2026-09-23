<?php

/**
 * Rewrites data/iccprofiles/Gray_sRGB_TRC.icc, the grey ICC profile PDF/X-4 writes grey in under an RGB
 * output intent:
 *
 *   composer grayprofile:update
 *
 * The profile is built by tests/Mpdf/Color/GrayIccProfile.php, and GrayIccProfileTest fails where the
 * file is not what it builds.
 */

require __DIR__ . '/../vendor/autoload.php';

$profile = Mpdf\Color\GrayIccProfile::build();
file_put_contents(Mpdf\Color\GrayIccProfile::FILE, $profile);

printf("%s: %d bytes\n", realpath(Mpdf\Color\GrayIccProfile::FILE), strlen($profile));
