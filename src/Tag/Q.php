<?php

namespace Mpdf\Tag;

use Mpdf\Css\Quotes;
use Mpdf\CssMode;

class Q extends InlineTag
{

	/**
	 * The attribute added to a <q> start tag once its marks are in the text, so that a block laid out again from an
	 * earlier token does not add them twice
	 */
	const QUOTED = 'mpdf-quoted';

	/**
	 * Under the standard CSS mode, puts the opening mark at the start of the quotation's text and the closing mark at
	 * its end, as a browser draws them, so they take the element's style and are copied with the text. The pair is
	 * picked by how deep the <q> is nested in others, from quotes, or from its language for quotes: auto
	 *
	 * @param array $attr
	 * @param array $ahtml
	 * @param int $ihtml
	 */
	public function open($attr, &$ahtml, &$ihtml)
	{
		parent::open($attr, $ahtml, $ihtml);

		if ($this->mpdf->cssMode !== CssMode::STANDARD) {
			return;
		}

		$end = $this->endTagPosition($ahtml, $ihtml);
		if ($end === null) {
			return;
		}

		$depth = $this->mpdf->quoteDepth++;
		if (isset($attr[strtoupper(self::QUOTED)])) {
			return;
		}

		$computed = $this->cssManager->getLastComputed();
		$pairs = Quotes::pairs(isset($computed['QUOTES']) ? $computed['QUOTES'] : null, $this->language($attr));
		if ($pairs) {
			list($open, $close) = $pairs[min($depth, count($pairs) - 1)];
			$ahtml[$ihtml + 1] = $this->asText($open) . $ahtml[$ihtml + 1];
			$ahtml[$end - 1] .= $this->asText($close);
		}

		$ahtml[$ihtml] .= ' ' . self::QUOTED . '="1"';
	}

	/**
	 * The next <q> is one level less deep
	 *
	 * @param array $ahtml
	 * @param int $ihtml
	 */
	public function close(&$ahtml, &$ihtml)
	{
		if ($this->mpdf->cssMode === CssMode::STANDARD && $this->mpdf->quoteDepth > 0) {
			$this->mpdf->quoteDepth--;
		}

		parent::close($ahtml, $ihtml);
	}

	/**
	 * @param array $ahtml The HTML being written, split into text and tags as WriteHTML() splits it
	 * @param int $ihtml Where the <q> start tag is
	 *
	 * @return int|null Where its end tag is, past those of the <q> elements inside it, or null when it has none
	 */
	private function endTagPosition(array &$ahtml, $ihtml)
	{
		$depth = 0;
		$count = count($ahtml);
		for ($i = $ihtml + 2; $i < $count; $i += 2) {
			if ($ahtml[$i] === '') {
				continue;
			}
			if (strtolower(strtok($ahtml[$i], " \t\r\n/>")) !== 'q') {
				continue;
			}
			if ($ahtml[$i][0] !== '/') {
				$depth++;
			} elseif ($depth-- === 0) {
				return $i;
			}
		}

		return null;
	}

	/**
	 * @param array $attr The <q>'s attributes
	 *
	 * @return string Its language: its own lang, else the one its parent has or inherits
	 */
	private function language(array $attr)
	{
		if (!empty($attr['LANG'])) {
			return $attr['LANG'];
		}

		$open = $this->mpdf->getOpenElements();

		return $open ? (string) $open[count($open) - 1]['lang'] : '';
	}

	/**
	 * @param string $mark
	 *
	 * @return string The mark as WriteHTML() reads it back from a text token, which decodes entities
	 */
	private function asText($mark)
	{
		return str_replace('&', '&amp;', $mark);
	}
}
