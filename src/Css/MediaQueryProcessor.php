<?php

namespace Mpdf\Css;

use Mpdf\Mpdf;

class MediaQueryProcessor
{
	/**
	 * @var \Mpdf\Mpdf
	 */
	private $mpdf;

	public function __construct(Mpdf $mpdf)
	{
		$this->mpdf = $mpdf;
	}

	/**
	 * Filter HTML elements by media query.
	 *
	 * Removes elements (style or link tags) that don't match the configured media type.
	 *
	 * @param string $html HTML content to filter
	 * @param string $pattern Regex pattern to match elements
	 * @return string Filtered HTML
	 */
	public function filterByMediaQuery($html, $pattern)
	{
		preg_match_all($pattern, $html, $m);
		foreach ($m[0] as $i => $url) {
			// Unlike an @media block, a <style> or <link> for any medium is left out when CSSselectMedia names none
			if (!$this->mpdf->CSSselectMedia || !$this->matches($m[1][$i])) {
				$html = str_replace($m[0][$i], '', $html);
			}
		}
		return $html;
	}

	/**
	 * Whether a media query list applies to the medium CSSselectMedia names. Every list applies when it names none.
	 *
	 * @param string $mediaQueryList
	 * @return bool
	 */
	public function matches($mediaQueryList)
	{
		return !$this->mpdf->CSSselectMedia || preg_match('/(' . trim($this->mpdf->CSSselectMedia) . '|all)/i', $mediaQueryList) === 1;
	}
}
