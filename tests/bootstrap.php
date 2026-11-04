<?php

namespace Mpdf;

/**
 * Setup our unit testing functionality
 *
 * @package    mPDF
 * @author     Blue Liquid Designs <admin@blueliquiddesigns.com.au>
 * @copyright  2015 Blue Liquid Designs
 * @license    GPLv2
 * @since      GPL-2.0
 */

// No Mpdf instance is made here. PHPUnit before 8.5 has every suite it builds keep a copy of the list of declared
// classes, so each class loaded before the suite is built costs memory in all of them
require_once __DIR__ . '/../vendor/autoload.php';
