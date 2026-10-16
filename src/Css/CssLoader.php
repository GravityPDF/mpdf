<?php

namespace Mpdf\Css;

use Mpdf\AssetFetcher;
use Mpdf\Cache;
use Mpdf\CssMode;
use Mpdf\Exception\AssetFetchingException;
use Mpdf\Html\Tags;
use Mpdf\Mpdf;
use Mpdf\MpdfException;
use Mpdf\Utils\Path;

class CssLoader
{

	/**
	 * @var Mpdf
	 */
	private $mpdf;

	/**
	 * @var AssetFetcher
	 */
	private $assetFetcher;

	/**
	 * @var Cache
	 */
	private $cache;

	/**
	 * @var MediaQueryProcessor
	 */
	private $mediaQueryProcessor;

	/**
	 * @var StylesheetTokenizer
	 */
	private $tokenizer;

	public function __construct(Mpdf $mpdf, AssetFetcher $assetFetcher, Cache $cache, MediaQueryProcessor $mediaQueryProcessor)
	{
		$this->mpdf = $mpdf;
		$this->assetFetcher = $assetFetcher;
		$this->cache = $cache;
		$this->mediaQueryProcessor = $mediaQueryProcessor;
		$this->tokenizer = new StylesheetTokenizer();
	}

	/**
	 * Fetch and return the CSS from $path
	 *
	 * @param string $path
	 * @return string
	 * @throws MpdfException If asset fetching issue, is through when $mpdf->debug = true
	 */
	public function loadStylesheet($path)
	{
		$path = preg_replace('/\.css\?.*$/', '.css', $path);

		try {
			$data = $this->assetFetcher->fetchDataFromPath($path);
			if (!$data) {
				$path = !$this->mpdf->basepathIsLocal ? Path::normalizeLocalFilePath($path) : $path;
				$data = $this->assetFetcher->fetchDataFromPath($path);
			}
		} catch (AssetFetchingException $e) {
			$data = ''; // do nothing
			if ($this->mpdf->debug) {
				throw new MpdfException($e->getMessage(), 0, E_ERROR, null, null, $e);
			}
		}

		return $data;
	}

	/**
	 * Take the <style> blocks out of HTML, and find the external stylesheets it loads, in one pass.
	 *
	 * The external stylesheets are those of each <link rel="stylesheet" href="...">, its attributes in any order, then
	 * those of each @import url(...) and @import "..." in the <style> blocks whose media query list matches.
	 *
	 * @param string $html HTML content to scan
	 * @return array The HTML without its <style> blocks, the URLs of the external stylesheets, and the CSS of the
	 *               <style> blocks with their background URLs resolved
	 */
	public function collectStylesheets($html)
	{
		$links = [];
		$styles = [];
		$html = preg_replace_callback('/' . Tags::startTagOrElementPattern('link', 'style') . '/si', function ($m) use (&$links, &$styles) {
			if (!isset($m[2])) {
				$links[] = $m[1];

				return $m[0];
			}

			// A <link> in a <style> block's text links its stylesheet too
			if (stripos($m[2], '<link') !== false) {
				preg_match_all('/' . Tags::startTagPattern('link') . '/i', $m[2], $inner);
				$links = array_merge($links, $inner[1]);
			}

			$styles[] = $m[2];

			return '';
		}, $html);

		$urls = [];
		foreach ($links as $link) {
			list(, $attributes) = Tags::attributes($link);
			if (isset($attributes['REL'], $attributes['HREF']) && strtolower(trim($attributes['REL'])) === 'stylesheet') {
				$urls[] = $attributes['HREF'];
			}
		}

		return [
			$html,
			array_merge($urls, $this->extractImportUrls($styles, true)),
			array_map([$this, 'resolveBackgroundUrls'], $styles),
		];
	}

	/**
	 * The URLs the @import rules in the stylesheets load, less those whose media query list does not match
	 *
	 * Only an @import among a stylesheet's own rules is read, not one in a comment, a string or a block. A layer()
	 * before the media query list is ignored. A supports() condition is taken to pass, as the rules in an @supports
	 * block are unwrapped, so one that starts with not fails.
	 *
	 * @param string[] $stylesheets
	 * @param bool $inHtml Whether the stylesheets are the document's <style> blocks rather than one it loads, which
	 *                     only legacy mode reads
	 * @return string[]
	 */
	private function extractImportUrls(array $stylesheets, $inHtml)
	{
		$preludes = [];
		foreach ($stylesheets as $css) {
			if (stripos($css, '@import') !== false) {
				foreach ($this->tokenizer->rules($this->tokenizer->removeComments($css)) as $rule) {
					if ($rule[0] === 'import' && $rule[2] === null) {
						$preludes[] = $rule[1];
					}
				}
			}
		}

		if ($this->mpdf->cssMode === CssMode::LEGACY) {
			return $this->legacyImportUrls($preludes, $inHtml);
		}

		$url = '(?|url\(\s*"([^"]*)"\s*\)|url\(\s*\'([^\']*)\'\s*\)|url\(\s*([^\s"\')]*)\s*\)|"([^"]*)"|\'([^\']*)\')';
		$layer = '(?:\s*layer(?:\([^)]*\))?)?';
		$supports = '(?:\s*supports\(\s*(not\b)?(?:[^()]|\([^()]*\))*\))?';
		$pattern = '/^' . $url . $layer . $supports . '(.*)$/is';

		$urls = [];
		foreach ($preludes as $prelude) {
			if (preg_match($pattern, $prelude, $import)
				&& $import[1] !== '' && $import[2] === '' && $this->mediaQueryProcessor->matches($import[3])
			) {
				$urls[] = $import[1];
			}
		}

		return $urls;
	}

	/**
	 * The URLs @import rules load in cssMode legacy, as mPDF v7 read them: any URL of a .css file, whatever follows
	 * it. Those in url() come first, then, in <style> blocks only, those written as strings
	 *
	 * @param string[] $preludes
	 * @param bool $inHtml
	 * @return string[]
	 */
	private function legacyImportUrls(array $preludes, $inHtml)
	{
		$urls = [];
		$strings = [];
		foreach ($preludes as $prelude) {
			if (preg_match('/^url\([\'"]?(\S*?\.css(\?[^\s\'"]+)?)[\'"]?\)/i', $prelude, $m)) {
				$urls[] = $m[1];
			} elseif ($inHtml && preg_match('/^(?!url)[\'"]?(\S*?\.css(\?[^\s\'"]+)?)/i', $prelude, $m)) {
				$strings[] = $m[1];
			}
		}

		return array_merge($urls, $strings);
	}

	/**
	 * Locate embedded @import stylesheets in other stylesheets and fix url paths
	 * (including background-images) relative to stylesheet
	 *
	 * @param string $stylesheetCss
	 * @param string $path
	 * @param array $externalCss
	 * @param int $externalCssCount
	 * @return string
	 */
	public function processExternalCssImports($stylesheetCss, $path, &$externalCss, &$externalCssCount)
	{
		$cssBasePath = preg_replace('/\/[^\/]*$/', '', $path) . '/';
		foreach ($this->extractImportUrls([$stylesheetCss], false) as $cxtembedded) {
			// path is relative to original stylesheet!!
			$externalCss[] = Path::relativeToAbsolutePath($cxtembedded, $cssBasePath);
			$externalCssCount++;
		}

		return $this->resolveBackgroundUrls($stylesheetCss, $cssBasePath);
	}

	/**
	 * Resolve background image URLs in CSS.
	 *
	 * Converts relative URLs to absolute paths using Path::relativeToAbsolute.
	 * Skips data URIs which are already absolute.
	 *
	 * @param string $cssStr CSS string potentially containing background URLs
	 * @param string|null $basePath Optional base path for resolving relative URLs
	 * @return string CSS string with resolved URLs
	 */
	public function resolveBackgroundUrls($cssStr, $basePath = null)
	{
		if (!preg_match_all('/(background[^;]*url\s*\(\s*[\'"]{0,1})([^)\'"]*)([\'"]{0,1}\s*\))/si', $cssStr, $cxtem)) {
			return $cssStr;
		}

		$basePath = $basePath ?: $this->mpdf->basepath;

		foreach ($cxtem[0] as $i => $value) {
			$embedded = $cxtem[2][$i];
			if (!preg_match('/^data:image/i', $embedded)) {
				$newPath = Path::relativeToAbsolutePath($embedded, $basePath);
				$cssStr = str_replace($cxtem[0][$i], ($cxtem[1][$i] . $newPath . $cxtem[3][$i]), $cssStr);
			}
		}

		return $cssStr;
	}

	/**
	 * Process data URI images in CSS.
	 *
	 * Converts data URI images to temporary files for processing.
	 * Example: url(data:image/png;base64,...) becomes url("tempfile.png")
	 *
	 * @param string $cssStr CSS string potentially containing data URIs
	 * @return string CSS string with data URIs replaced by temp file references
	 * @throws \Random\RandomException
	 */
	public function processDataUriImages($cssStr)
	{
		preg_match_all("/(url\(data:image\/(jpeg|gif|png);base64,(.*?)\))/si", $cssStr, $idata);
		if (count($idata[0]) === 0) {
			return $cssStr;
		}

		foreach ($idata[0] as $i => $value) {
			$file = $this->cache->write('_tempCSSidata' . random_int(1, 10000) . '_' . $i . '.' . $idata[2][$i], base64_decode($idata[3][$i]));
			$cssStr = str_replace($idata[0][$i], 'url("' . $file . '")', $cssStr);
		}

		return $cssStr;
	}
}
