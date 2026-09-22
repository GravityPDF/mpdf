<?php

/**
 * Checks mPDF's PDF/X-4 output against utils/verapdf/pdfx4-policy.sch:
 *
 *   php utils/verapdf/pdfx4_policy.php <path to the veraPDF binary> [<path to a CMYK ICC profile>]
 *
 * This is NOT PDF/X-4 validation. veraPDF validates PDF/A, PDF/UA and WTPDF only, and has no PDF/X
 * profile; --policyfile runs a Schematron over the features it extracts from a document, which is a
 * regression test and nothing more. Real conformance still needs a preflight tool.
 *
 * A Schematron that matches nothing passes in silence, which would be worse than no check at all. So
 * this writes a conformant document for each output intent and, beside each, a document that breaks
 * one rule and nothing else, and it holds every one of them to the failures it expects. A rule that
 * stops matching what it is aimed at fails the run as loudly as a document that stops conforming.
 *
 * Without a CMYK profile the cases that need one are skipped, and the run says so.
 */

require __DIR__ . '/../../vendor/autoload.php';

use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\Utils\UtfString;

$verapdf = isset($argv[1]) ? $argv[1] : '';
$cmykProfile = isset($argv[2]) ? $argv[2] : '';

if (!$verapdf) {
	fwrite(STDERR, "Usage: php utils/verapdf/pdfx4_policy.php <veraPDF binary> [<CMYK ICC profile>]\n");
	exit(2);
}

$policy = __DIR__ . '/pdfx4-policy.sch';
$directory = __DIR__ . '/../../tmp/verapdf';

// The features the policy reads. Anything not asked for is missing from the report, and an assertion
// over what is missing passes in silence.
$features = 'actions,annotations,colorSpace,iccProfile,imageXobject,informationDict,lowLevelInfo,metadata,outputIntent,page,shading';

if (!is_dir($directory) && !mkdir($directory, 0777, true)) {
	fwrite(STDERR, sprintf("Cannot write to %s\n", $directory));
	exit(2);
}

/**
 * @param array $config Merged over PDF/X-4 with automatic fixing and no compression, which every
 *                      mutation below reads and rewrites
 *
 * @return Mpdf A titled document, as PDF/X asks for
 */
function document(array $config = [])
{
	$mpdf = new Mpdf($config + ['mode' => 'utf-8', 'PDFX' => '4', 'PDFXauto' => true]);
	$mpdf->SetCompression(false);
	$mpdf->SetTitle('mPDF PDF/X-4 policy check');

	return $mpdf;
}

/**
 * @return string The HTML of the conformant documents: text, a gradient, an RGB image and a palette
 *                image, which between them settle every colour space the policy looks at
 */
function content()
{
	$true = imagecreatetruecolor(8, 8);
	imagefilledrectangle($true, 0, 0, 7, 7, imagecolorallocate($true, 200, 60, 30));
	ob_start();
	imagepng($true);
	$rgb = base64_encode(ob_get_clean());

	$palette = imagecreate(8, 8);
	imagecolorallocate($palette, 10, 120, 240);
	ob_start();
	imagepng($palette);
	$indexed = base64_encode(ob_get_clean());

	return '<h1 style="color: #000000">Heading</h1>'
		. '<p style="color: #cc2222">Body text, and <span style="color: cmyk(10, 20, 30, 40)">a colour given as CMYK</span>.</p>'
		. '<div style="background: linear-gradient(#ff0000, #0000ff); height: 15mm">A gradient</div>'
		. '<img src="data:image/png;base64,' . $rgb . '" /> <img src="data:image/png;base64,' . $indexed . '" />';
}

/**
 * Reads a document back, replaces one string in it with another of the same length - so that every
 * object keeps its offset and the cross-reference table stays true - and writes it out again
 *
 * @param string $from The document to read
 * @param string $to   The document to write
 * @param string $find
 * @param string $with Of the same length as $find
 */
function mutate($from, $to, $find, $with)
{
	if (strlen($find) !== strlen($with)) {
		throw new \RuntimeException(sprintf('"%s" and "%s" are not the same length', $find, $with));
	}

	$pdf = file_get_contents($from);
	if (substr_count($pdf, $find) !== 1) {
		throw new \RuntimeException(sprintf('"%s" is in %s %d times', $find, basename($from), substr_count($pdf, $find)));
	}

	file_put_contents($to, str_replace($find, $with, $pdf));
}

/**
 * The same, where what is replaced is matched rather than named, and what replaces it is padded out
 * to the same length with spaces
 *
 * @param string $from    The document to read
 * @param string $to      The document to write
 * @param string $pattern A regular expression matching one place in the document
 * @param string $with    No longer than what it replaces
 */
function mutateMatch($from, $to, $pattern, $with)
{
	$pdf = file_get_contents($from);
	if (preg_match_all($pattern, $pdf, $matches) !== 1) {
		throw new \RuntimeException(sprintf('%s matches %s other than once', $pattern, basename($from)));
	}

	mutate($from, $to, $matches[0][0], str_pad($with, strlen($matches[0][0])));
}

/**
 * Runs veraPDF over a document with the policy, with validation left on because veraPDF applies no
 * policy without it, and the validation verdict - against PDF/A, the only thing it could validate
 * against - ignored
 *
 * @param string $verapdf  The veraPDF binary
 * @param string $features The features to extract
 * @param string $policy   The Schematron
 * @param string $pdf      The document
 *
 * @return string[] The name of each rule the document broke, as the policy brackets them
 */
function broke($verapdf, $features, $policy, $pdf)
{
	$command = sprintf(
		'%s --off --extract %s --policyfile %s --format xml %s 2>&1',
		escapeshellarg($verapdf),
		escapeshellarg($features),
		escapeshellarg($policy),
		escapeshellarg($pdf)
	);

	$output = (string) shell_exec($command);

	// veraPDF logs to standard error as it reads a document, ahead of the report
	$report = strstr($output, '<?xml');

	$document = new \DOMDocument();
	if (!$report || !@$document->loadXML($report)) {
		throw new \RuntimeException(sprintf("veraPDF wrote no report for %s:\n%s", basename($pdf), $output));
	}

	$xpath = new \DOMXPath($document);
	$reports = $xpath->query('//policyReport');
	if (!$reports->length) {
		throw new \RuntimeException(sprintf('veraPDF applied no policy to %s', basename($pdf)));
	}

	$broke = [];
	foreach ($xpath->query('//policyReport/failedChecks/check/message') as $message) {
		if (preg_match('/^\[([a-z-]+)\]/', trim($message->textContent), $match)) {
			$broke[] = $match[1];
			continue;
		}

		throw new \RuntimeException(sprintf('A policy message names no rule: %s', trim($message->textContent)));
	}

	sort($broke);

	return array_values(array_unique($broke));
}

// A conformant document for each output intent, and the mutations of them that each break one rule
$cases = [];

$srgb = $directory . '/srgb.pdf';
$mpdf = document();
$mpdf->WriteHTML(content());
$mpdf->OutputFile($srgb);
$cases['srgb'] = ['file' => $srgb, 'breaks' => [], 'what' => 'the default document, printing to sRGB'];

// The output intent's profile, made to print to a colour space nothing knows
$unknown = $directory . '/unknown-intent-space.pdf';
mutate($srgb, $unknown, "/N 3\n/Length 3052>>\nstream\n" . substr(file_get_contents(__DIR__ . '/../../data/iccprofiles/sRGB_IEC61966-2-1.icc'), 0, 20), "/N 3\n/Length 3052>>\nstream\n" . substr_replace(substr(file_get_contents(__DIR__ . '/../../data/iccprofiles/sRGB_IEC61966-2-1.icc'), 0, 20), 'FOO ', 16, 4));
$cases['unknown-intent-space'] = ['file' => $unknown, 'breaks' => ['intent-colour-space'], 'what' => 'its output intent printing to a colour space nothing knows'];

// CMYK left in a document that prints to sRGB: the gradient is drawn before PDFX is set, so mPDF
// does not convert its stops
$cmykInSrgb = $directory . '/device-cmyk-in-srgb.pdf';
$mpdf = document(['PDFX' => false]);
$mpdf->WriteHTML('<div style="background: linear-gradient(cmyk(0, 100, 100, 0), cmyk(100, 0, 0, 0)); height: 15mm">A gradient</div>');
$mpdf->PDFX = '4';
$mpdf->PDFXauto = true;
$mpdf->OutputFile($cmykInSrgb);
$cases['device-cmyk-in-srgb'] = ['file' => $cmykInSrgb, 'breaks' => ['no-device-cmyk'], 'what' => 'DeviceCMYK in a document printing to sRGB'];

// Everything PDF/X refuses, in a document that is not PDF/X at all
$nonconformant = $directory . '/nonconformant.pdf';
$configVariables = new ConfigVariables();
$fontVariables = new FontVariables();
$defaults = $configVariables->getDefaults() + $fontVariables->getDefaults();
$mpdf = new Mpdf([
	'mode' => 'utf-8',
	'useActiveForms' => true,
	'allowAnnotationFiles' => true,
	'fontDir' => array_merge($defaults['fontDir'], [__DIR__ . '/../../tests/data/ttf/color']),
	'fontdata' => $defaults['fontdata'] + ['cbdt' => ['R' => 'TestEmoji-CBDT.ttf', 'useOTL' => 0xFF]],
]);
$mpdf->SetCompression(false);
$mpdf->SetJS('app.alert("x");');
$mpdf->WriteHTML('<p style="font-family: cbdt">' . UtfString::code2utf(0x1F600) . '</p>'
	. '<p><a href="https://example.com">A link</a></p>');
$mpdf->Annotation('A note', 60, 60, 'Note', '', '', 0, false, false, __FILE__);
$mpdf->WriteHTML('<form><input type="text" name="field" value="A form field" /></form>');
$mpdf->OutputFile($nonconformant);
$cases['nonconformant'] = [
	'file' => $nonconformant,
	'breaks' => [
		'dest-output-profile',
		'info-pdfx-version',
		'info-title',
		'intent-colour-space',
		'no-file-attachment',
		'no-interpolation',
		'no-javascript',
		'no-link',
		'no-widget',
		'output-intent',
		'pdf-version',
		'xmp-pdfx-version',
		'xmp-title',
	],
	'what' => 'a document that is not PDF/X at all',
];

if ($cmykProfile) {
	$cmyk = $directory . '/cmyk.pdf';
	$mpdf = document(['ICCProfile' => $cmykProfile]);
	$mpdf->WriteHTML(content());
	$mpdf->OutputFile($cmyk);
	$cases['cmyk'] = ['file' => $cmyk, 'breaks' => [], 'what' => 'a document printing to a CMYK output intent'];

	// The ICC-based sRGB colour space, made DeviceRGB
	$rgbInCmyk = $directory . '/device-rgb-in-cmyk.pdf';
	mutateMatch($cmyk, $rgbInCmyk, '/\[\/ICCBased \d+ 0 R\]/', '/DeviceRGB');
	$cases['device-rgb-in-cmyk'] = ['file' => $rgbInCmyk, 'breaks' => ['no-device-rgb'], 'what' => 'DeviceRGB in a document printing to CMYK'];

	// The ICC-based sRGB colour space, made to claim four components
	$components = $directory . '/wrong-icc-components.pdf';
	mutate($cmyk, $components, '<</N 3 /Length', '<</N 4 /Length');
	$cases['wrong-icc-components'] = ['file' => $components, 'breaks' => ['icc-components'], 'what' => 'an ICC-based colour space miscounting its components'];
}

echo "veraPDF policy check of mPDF's PDF/X-4 output\n";
echo "This is a policy over extracted features, not PDF/X-4 validation.\n\n";

$failed = 0;
foreach ($cases as $name => $case) {
	$broke = broke($verapdf, $features, $policy, $case['file']);
	$expected = $case['breaks'];
	sort($expected);

	$unexpected = array_diff($broke, $expected);
	$missing = array_diff($expected, $broke);

	if (!$unexpected && !$missing) {
		echo sprintf("  ok    %-22s %s%s\n", $name, $case['what'], $expected ? ' (breaks ' . implode(', ', $expected) . ')' : '');
		continue;
	}

	$failed++;
	echo sprintf("  FAIL  %-22s %s\n", $name, $case['what']);
	if ($missing) {
		echo sprintf("        rules that did not fire: %s\n", implode(', ', $missing));
	}
	if ($unexpected) {
		echo sprintf("        rules that fired unasked: %s\n", implode(', ', $unexpected));
	}
}

if (!$cmykProfile) {
	echo "\n  The cases that print to a CMYK output intent were skipped: no CMYK ICC profile was given.\n";
}

echo "\n";

if ($failed) {
	echo sprintf("%d of %d cases did not come out as expected.\n", $failed, count($cases));
	exit(1);
}

echo sprintf("All %d cases came out as expected.\n", count($cases));
