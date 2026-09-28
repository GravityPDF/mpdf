<?php

namespace Mpdf\Invoice;

use Mpdf\MpdfException;
use Mpdf\Strict;

/**
 * Reads what a printed invoice shows from invoice XML, as the same array whichever syntax the XML is in
 *
 * It returns what the XML states, totals included, and recalculates nothing. It neither validates the XML nor checks
 * that its totals add up. Anything the XML leaves out, as a MINIMUM invoice leaves out the lines, is null or an empty
 * list.
 *
 * read() gives an array:
 * - id, typeCode (UNTDID 1001), currency, buyerReference, orderReference, paymentReference: strings or null
 * - issueDate, deliveryDate, dueDate: a DateTime, the text of a date the reader cannot read as one, or null
 * - notes: string[]
 * - precedingInvoices: [id, issueDate][]
 * - seller, buyer: a party; deliverTo: a party or null. A party is name, address (as Formatter::address() takes it),
 *   vatId, taxNumber, contact (name, phone, email), electronicAddress and electronicAddressScheme
 * - lines: name, description, quantity, unitCode, unitPrice, basisQuantity, basisQuantityUnit, vatCategory, vatRate,
 *   netAmount and allowanceCharges
 * - allowanceCharges, on the invoice or a line: charge (bool), amount, reason and reasonCode
 * - vatBreakdown: category, rate, basis, amount, exemptionReason and dueDateCode (UNTDID 2475)
 * - totals: lineTotal, chargeTotal, allowanceTotal, taxBasisTotal, taxTotal, roundingAmount, grandTotal, prepaidAmount
 *   and duePayableAmount, each a float or null
 * - paymentTerms: string[], a line each
 * - paymentDiscounts, paymentPenalties: early payment discounts and late payment penalties, from XRechnung's Skonto
 *   lines in the payment terms and, in Cross Industry Invoice XML, EXTENDED's structured terms. Each is percent,
 *   amount, basisAmount and period (floats or null), periodUnit (UN/ECE Recommendation 20, e.g. DAY) and basisDate
 * - paymentMeans: typeCode, information, account, iban (bool), bic, accountName, debitedAccount, mandate, creditorId,
 *   card and cardholder
 */
abstract class AbstractInvoiceReader
{

	use Strict;

	/**
	 * @var \DOMXPath
	 */
	private $xpath;

	/**
	 * The invoice the XML states
	 *
	 * @param string $xml
	 *
	 * @return mixed[] As the class describes
	 *
	 * @throws \Mpdf\MpdfException When the XML does not parse, has a document type declaration, or is not in a syntax
	 *                             the reader reads
	 */
	public function read($xml)
	{
		return $this->readDocument($this->parse($xml));
	}

	/**
	 * The invoice a parsed document states
	 *
	 * @param \DOMDocument $document
	 *
	 * @return mixed[] As the class describes
	 *
	 * @throws \Mpdf\MpdfException When the document is not in a syntax the reader reads
	 */
	abstract protected function readDocument(\DOMDocument $document);

	/**
	 * Parse the XML without reaching the network or taking a DTD
	 *
	 * @param string $xml
	 *
	 * @return \DOMDocument
	 *
	 * @throws \Mpdf\MpdfException
	 */
	private function parse($xml)
	{
		if (!is_string($xml) || trim($xml) === '') {
			throw new MpdfException('The invoice XML must be a non-empty string.');
		}

		$document = new \DOMDocument();
		$useErrors = libxml_use_internal_errors(true);
		$loaded = $document->loadXML($xml, LIBXML_NONET);
		$error = libxml_get_last_error();
		libxml_clear_errors();
		libxml_use_internal_errors($useErrors);

		if (!$loaded) {
			throw new MpdfException(sprintf('The invoice XML does not parse: %s', $error ? trim($error->message) : 'unknown error'));
		}

		// Neither syntax has a document type declaration, and one could only bring in entities
		if ($document->doctype !== null) {
			throw new MpdfException('The invoice XML has a document type declaration, which invoice XML never has.');
		}

		return $document;
	}

	/**
	 * The document's root element, once it is checked to be one the reader reads, with the namespaces its paths use
	 * registered
	 *
	 * @param \DOMDocument $document
	 * @param string $syntax Named in the exception, e.g. "UBL invoice XML"
	 * @param string[] $roots Each as {namespace}name
	 * @param string[] $namespaces By their prefix
	 *
	 * @return \DOMElement
	 *
	 * @throws \Mpdf\MpdfException When the root element is none of $roots
	 */
	protected function root(\DOMDocument $document, $syntax, array $roots, array $namespaces)
	{
		$root = $document->documentElement;
		$name = sprintf('{%s}%s', $root->namespaceURI, $root->localName);
		if (!in_array($name, $roots, true)) {
			throw new MpdfException(sprintf('%s reads %s, not a %s document.', get_class($this), $syntax, $name));
		}

		$this->xpath = new \DOMXPath($document);
		foreach ($namespaces as $prefix => $namespace) {
			$this->xpath->registerNamespace($prefix, $namespace);
		}

		return $root;
	}

	/**
	 * The payment terms as lines of text, and the early payment discounts written among them in XRechnung's Skonto form
	 *
	 * @param string[] $descriptions
	 *
	 * @return mixed[] The lines of text, then the discounts
	 */
	protected function paymentTerms(array $descriptions)
	{
		$terms = [];
		$discounts = [];
		foreach ($descriptions as $description) {
			foreach (explode("\n", $description) as $term) {
				$skonto = $this->skonto($term);
				if ($skonto !== null) {
					$discounts[] = $skonto;
				} elseif (trim($term) !== '') {
					$terms[] = trim($term);
				}
			}
		}

		return [$terms, $discounts];
	}

	/**
	 * An early payment discount in XRechnung's Skonto form, #SKONTO#TAGE=14#PROZENT=2.00# with BASISBETRAG=...# when it
	 * applies to part of the amount, or null for any other line
	 *
	 * @see https://xeinkauf.de/xrechnung/ XRechnung, which defines the Skonto form
	 *
	 * @param string $term
	 *
	 * @return mixed[]|null A discount, as the class describes
	 */
	private function skonto($term)
	{
		if (!preg_match('/^#SKONTO#TAGE=(\d+)#PROZENT=(\d+(?:\.\d+)?)#(?:BASISBETRAG=(-?\d+(?:\.\d+)?)#)?$/', trim($term), $match)) {
			return null;
		}

		return [
			'percent' => (float) $match[2],
			'amount' => null,
			'basisAmount' => isset($match[3]) ? (float) $match[3] : null,
			'period' => (float) $match[1],
			'periodUnit' => 'DAY',
			'basisDate' => null,
		];
	}

	/**
	 * A number, or null when there is none
	 *
	 * @param string $path
	 * @param \DOMNode|null $context
	 *
	 * @return float|null
	 */
	protected function amount($path, $context)
	{
		$text = $this->text($path, $context);

		return $text !== null && is_numeric($text) ? (float) $text : null;
	}

	/**
	 * The trimmed text of the first node the path finds, or null when it finds none or only whitespace
	 *
	 * @param string $path
	 * @param \DOMNode|null $context
	 *
	 * @return string|null
	 */
	protected function text($path, $context)
	{
		$node = $this->node($path, $context);
		$text = $node !== null ? trim($node->textContent) : '';

		return $text !== '' ? $text : null;
	}

	/**
	 * The trimmed text of every node the path finds, leaving out those with none
	 *
	 * @param string $path
	 * @param \DOMNode|null $context
	 *
	 * @return string[]
	 */
	protected function texts($path, $context)
	{
		$texts = [];
		foreach ($this->nodes($path, $context) as $node) {
			$text = trim($node->textContent);
			if ($text !== '') {
				$texts[] = $text;
			}
		}

		return $texts;
	}

	/**
	 * Lines of text joined by a comma, leaving out those that are not set, or null when none are
	 *
	 * @param mixed[] $parts
	 *
	 * @return string|null
	 */
	protected function joined(array $parts)
	{
		// text() gives null for text that is not there, never ''
		$parts = array_filter($parts, 'is_string');

		return $parts ? implode(', ', $parts) : null;
	}

	/**
	 * The first node the path finds, or null
	 *
	 * @param string $path
	 * @param \DOMNode|null $context
	 *
	 * @return \DOMNode|null
	 */
	protected function node($path, $context)
	{
		$nodes = $this->nodes($path, $context);

		return $nodes instanceof \DOMNodeList && $nodes->length ? $nodes->item(0) : null;
	}

	/**
	 * Every node the path finds, none when there is no context to search in
	 *
	 * @param string $path
	 * @param \DOMNode|null $context
	 *
	 * @return \DOMNodeList|\DOMNode[]
	 */
	protected function nodes($path, $context)
	{
		$nodes = $context !== null ? $this->xpath->query($path, $context) : false;

		return $nodes !== false ? $nodes : [];
	}

}
