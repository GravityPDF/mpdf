<?php

namespace Mpdf;

/**
 * The values of the cssMode configuration option, which says how mPDF parses and applies CSS
 */
final class CssMode
{

	/**
	 * Applies CSS as a browser does: by specificity and then source order, with the newer selectors and behaviour.
	 * The default
	 */
	const STANDARD = 'standard';

	/**
	 * Parses and applies CSS as mPDF v7 did
	 */
	const LEGACY = 'legacy';
}
