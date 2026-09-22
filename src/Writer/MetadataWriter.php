<?php

namespace Mpdf\Writer;

use Mpdf\AssetFetcherInterface;
use Mpdf\File\FileTypeAllowList;
use Mpdf\Strict;
use Mpdf\Form;
use Mpdf\Log\Context as LogContext;
use Mpdf\Mpdf;
use Mpdf\MpdfAnnotationException;
use Mpdf\Pdf\Protection;
use Mpdf\PsrLogAwareTrait\PsrLogAwareTrait;
use Mpdf\Utils\PdfDate;
use Mpdf\Utils\Path;

use Psr\Log\LoggerInterface;

class MetadataWriter implements \Psr\Log\LoggerAwareInterface
{

	use Strict;
	use PsrLogAwareTrait;

	/**
	 * @var \Mpdf\Mpdf
	 */
	private $mpdf;

	/**
	 * @var \Mpdf\Writer\BaseWriter
	 */
	private $writer;

	/**
	 * @var \Mpdf\Form
	 */
	private $form;

	/**
	 * @var \Mpdf\Pdf\Protection
	 */
	private $protection;

	/**
	 * The file of each annotation path loadAnnotationFiles() has seen, until the annotations are written: its
	 * compressed contents and MIME type, or false where it failed
	 *
	 * @var array
	 */
	private $annotationFiles = [];

	/**
	 * @var \Mpdf\AssetFetcherInterface
	 */
	private $assetFetcher;

	/**
	 * @var int[][] The object number of each link, by page and then by its key in PageLinks
	 */
	private $linkIds = [];

	public function __construct(Mpdf $mpdf, BaseWriter $writer, Form $form, Protection $protection, AssetFetcherInterface $assetFetcher, LoggerInterface $logger)
	{
		$this->mpdf = $mpdf;
		$this->writer = $writer;
		$this->form = $form;
		$this->protection = $protection;
		$this->assetFetcher = $assetFetcher;
		$this->logger = $logger;
	}

	public function writeMetadata() // _putmetadata
	{
		$this->writer->object();
		$this->mpdf->MetadataRoot = $this->mpdf->n;

		$CreationDate = $this->writer->date()->format('Y-m-d\TH:i:sP'); // 2006-03-10T10:47:26-05:00

		// A name-based (version 3) UUID of the document, so the same document carries the same identifier
		$uuid = $this->hash();
		$uuid[12] = '3';
		$uuid[16] = dechex((hexdec($uuid[16]) & 0x3) | 0x8);
		$uuid = preg_replace('/^(.{8})(.{4})(.{4})(.{4})/', '$1-$2-$3-$4-', $uuid);

		$m = '<?xpacket begin="' . chr(239) . chr(187) . chr(191) . '" id="W5M0MpCehiHzreSzNTczkc9d"?>' . "\n"; // begin = FEFF BOM
		$m .= ' <x:xmpmeta xmlns:x="adobe:ns:meta/" x:xmptk="3.1-701">' . "\n";
		$m .= '  <rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">' . "\n";
		$m .= '   <rdf:Description rdf:about="uuid:' . $uuid . '" xmlns:pdf="http://ns.adobe.com/pdf/1.3/">' . "\n";
		$m .= '    <pdf:Producer>' . htmlspecialchars($this->getProducerString(), ENT_QUOTES | ENT_XML1) . '</pdf:Producer>' . "\n";
		if (!empty($this->mpdf->keywords)) {
			$m .= '    <pdf:Keywords>' . htmlspecialchars($this->mpdf->keywords, ENT_QUOTES | ENT_XML1) . '</pdf:Keywords>' . "\n";
		}
		$m .= '   </rdf:Description>' . "\n";

		$m .= '   <rdf:Description rdf:about="uuid:' . $uuid . '" xmlns:xmp="http://ns.adobe.com/xap/1.0/">' . "\n";
		$m .= '    <xmp:CreateDate>' . $CreationDate . '</xmp:CreateDate>' . "\n";
		$m .= '    <xmp:ModifyDate>' . $CreationDate . '</xmp:ModifyDate>' . "\n";
		$m .= '    <xmp:MetadataDate>' . $CreationDate . '</xmp:MetadataDate>' . "\n";
		if (!empty($this->mpdf->creator)) {
			$m .= '    <xmp:CreatorTool>' . htmlspecialchars($this->mpdf->creator, ENT_QUOTES | ENT_XML1) . '</xmp:CreatorTool>' . "\n";
		}
		$m .= '   </rdf:Description>' . "\n";

		// DC elements
		$m .= '   <rdf:Description rdf:about="uuid:' . $uuid . '" xmlns:dc="http://purl.org/dc/elements/1.1/">' . "\n";
		$m .= '    <dc:format>application/pdf</dc:format>' . "\n";
		if (!empty($this->mpdf->title)) {
			$m .= '    <dc:title>
	 <rdf:Alt>
	  <rdf:li xml:lang="x-default">' . htmlspecialchars($this->mpdf->title, ENT_QUOTES | ENT_XML1) . '</rdf:li>
	 </rdf:Alt>
	</dc:title>' . "\n";
		}
		if (!empty($this->mpdf->keywords)) {
			$m .= '    <dc:subject>
	 <rdf:Bag>
	  <rdf:li>' . htmlspecialchars($this->mpdf->keywords, ENT_QUOTES | ENT_XML1) . '</rdf:li>
	 </rdf:Bag>
	</dc:subject>' . "\n";
		}
		if (!empty($this->mpdf->subject)) {
			$m .= '    <dc:description>
	 <rdf:Alt>
	  <rdf:li xml:lang="x-default">' . htmlspecialchars($this->mpdf->subject, ENT_QUOTES | ENT_XML1) . '</rdf:li>
	 </rdf:Alt>
	</dc:description>' . "\n";
		}
		if (!empty($this->mpdf->author)) {
			$m .= '    <dc:creator>
	 <rdf:Seq>
	  <rdf:li>' . htmlspecialchars($this->mpdf->author, ENT_QUOTES | ENT_XML1) . '</rdf:li>
	 </rdf:Seq>
	</dc:creator>' . "\n";
		}
		$m .= '   </rdf:Description>' . "\n";

		if (!empty($this->mpdf->additionalXmpRdf)) {
			$m .= $this->mpdf->additionalXmpRdf;
		}

		// This bit is specific to PDFX-1a
		if ($this->mpdf->PDFX) {
			$m .= '   <rdf:Description rdf:about="uuid:' . $uuid . '" xmlns:pdfx="http://ns.adobe.com/pdfx/1.3/" pdfx:Apag_PDFX_Checkup="1.3" pdfx:GTS_PDFXConformance="PDF/X-1a:2003" pdfx:GTS_PDFXVersion="PDF/X-1:2003"/>' . "\n";
		} elseif ($this->mpdf->PDFA) {
			list($part, $conformance) = $this->mpdf->pdfaConformance();
			$m .= '   <rdf:Description rdf:about="uuid:' . $uuid . '" xmlns:pdfaid="http://www.aiim.org/pdfa/ns/id/" >' . "\n";
			$m .= '    <pdfaid:part>' . $part . '</pdfaid:part>' . "\n";
			$m .= '    <pdfaid:conformance>' . $conformance . '</pdfaid:conformance>' . "\n";
			if ($part === '1' && $conformance === 'B') {
				$m .= '    <pdfaid:amd>2005</pdfaid:amd>' . "\n";
			}
			$m .= '   </rdf:Description>' . "\n";
		}

		$m .= '   <rdf:Description rdf:about="uuid:' . $uuid . '" xmlns:xmpMM="http://ns.adobe.com/xap/1.0/mm/">' . "\n";
		$m .= '    <xmpMM:DocumentID>uuid:' . $uuid . '</xmpMM:DocumentID>' . "\n";
		$m .= '   </rdf:Description>' . "\n";
		$m .= '  </rdf:RDF>' . "\n";
		$m .= ' </x:xmpmeta>' . "\n";
		$m .= str_repeat(str_repeat(' ', 100) . "\n", 20); // 2-4kB whitespace padding required
		$m .= '<?xpacket end="w"?>'; // "r" read only
		$this->writer->write('<</Type/Metadata/Subtype/XML/Length ' . $this->writer->streamLength($m) . '>>');
		$this->writer->stream($m);
		$this->writer->write('endobj');
	}

	public function writeInfo() // _putinfo
	{
		$this->writer->write('/Producer ' . $this->writer->utf16BigEndianTextString($this->getProducerString()));

		if (!empty($this->mpdf->title)) {
			$this->writer->write('/Title ' . $this->writer->utf16BigEndianTextString($this->mpdf->title));
		}

		if (!empty($this->mpdf->subject)) {
			$this->writer->write('/Subject ' . $this->writer->utf16BigEndianTextString($this->mpdf->subject));
		}

		if (!empty($this->mpdf->author)) {
			$this->writer->write('/Author ' . $this->writer->utf16BigEndianTextString($this->mpdf->author));
		}

		if (!empty($this->mpdf->keywords)) {
			$this->writer->write('/Keywords ' . $this->writer->utf16BigEndianTextString($this->mpdf->keywords));
		}

		if (!empty($this->mpdf->creator)) {
			$this->writer->write('/Creator ' . $this->writer->utf16BigEndianTextString($this->mpdf->creator));
		}

		foreach ($this->mpdf->customProperties as $key => $value) {
			$this->writer->write('/' . $key . ' ' . $this->writer->utf16BigEndianTextString($value));
		}

		$this->writer->write('/CreationDate ' . $this->writer->dateString());
		$this->writer->write('/ModDate ' . $this->writer->dateString());
		if ($this->mpdf->PDFX) {
			$this->writer->write('/Trapped/False');
			$this->writer->write('/GTS_PDFXVersion(PDF/X-1a:2003)');
		}
	}

	public function writeOutputIntent() // _putoutputintent
	{
		$this->writer->object();
		$this->mpdf->OutputIntentRoot = $this->mpdf->n;
		$this->writer->write('<</Type /OutputIntent');

		$ICCProfile = str_replace('_', ' ', basename($this->mpdf->ICCProfile, '.icc'));

		if ($this->mpdf->PDFA) {
			$this->writer->write('/S /GTS_PDFA1');
			if ($this->mpdf->ICCProfile) {
				$this->writer->write('/Info (' . $ICCProfile . ')');
				$this->writer->write('/OutputConditionIdentifier (Custom)');
				$this->writer->write('/OutputCondition ()');
			} else {
				$this->writer->write('/Info (sRGB IEC61966-2.1)');
				$this->writer->write('/OutputConditionIdentifier (sRGB IEC61966-2.1)');
				$this->writer->write('/OutputCondition ()');
			}
			$this->writer->write('/DestOutputProfile ' . ($this->mpdf->n + 1) . ' 0 R');
		} elseif ($this->mpdf->PDFX) { // always a CMYK profile
			$this->writer->write('/S /GTS_PDFX');
			if ($this->mpdf->ICCProfile) {
				$this->writer->write('/Info (' . $ICCProfile . ')');
				$this->writer->write('/OutputConditionIdentifier (Custom)');
				$this->writer->write('/OutputCondition ()');
				$this->writer->write('/DestOutputProfile ' . ($this->mpdf->n + 1) . ' 0 R');
			} else {
				$this->writer->write('/Info (CGATS TR 001)');
				$this->writer->write('/OutputConditionIdentifier (CGATS TR 001)');
				$this->writer->write('/OutputCondition (CGATS TR 001 (SWOP))');
				$this->writer->write('/RegistryName (http://www.color.org)');
			}
		}
		$this->writer->write('>>');
		$this->writer->write('endobj');

		if ($this->mpdf->PDFX && !$this->mpdf->ICCProfile) {
			return;
		}

		$this->writer->object();

		if ($this->mpdf->ICCProfile) {
			if (!file_exists($this->mpdf->ICCProfile)) {
				throw new \Mpdf\MpdfException(sprintf('Unable to find ICC profile "%s"', $this->mpdf->ICCProfile));
			}
			$s = file_get_contents($this->mpdf->ICCProfile);
		} else {
			$s = file_get_contents(__DIR__ . '/../../data/iccprofiles/sRGB_IEC61966-2-1.icc');
		}

		if ($this->mpdf->compress) {
			$s = gzcompress($s);
		}

		$this->writer->write('<<');

		if ($this->mpdf->PDFX || ($this->mpdf->PDFA && $this->mpdf->restrictColorSpace === 3)) {
			$this->writer->write('/N 4');
		} else {
			$this->writer->write('/N 3');
		}

		if ($this->mpdf->compress) {
			$this->writer->write('/Filter /FlateDecode ');
		}

		$this->writer->write('/Length ' . $this->writer->streamLength($s) . '>>');
		$this->writer->stream($s);
		$this->writer->write('endobj');
	}

	public function writeAssociatedFiles() // _putAssociatedFiles
	{
		if (!function_exists('gzcompress')) {
			throw new \Mpdf\MpdfException('ext-zlib is required for compression of associated files');
		}

		foreach ($this->mpdf->associatedFiles as $k => $file) {
			// we store the root ref of object for future reference (e.g. /EmbeddedFiles catalog)
			$this->mpdf->associatedFiles[$k]['_root'] = $this->writeAssociatedFile($file, $this->mpdf->n + 1);
		}

		// AF array
		$this->writer->object();
		$refs = [];
		foreach ($this->mpdf->associatedFiles as $file) {
			$refs[] = '' . $file['_root'] . ' 0 R';
		}
		$this->writer->write('[' . implode(' ', $refs) . ']');
		$this->writer->write('endobj');

		$this->mpdf->associatedFilesRoot = $this->mpdf->n;
	}

	/**
	 * Writes an associated file's specification as object $specId, then its embedded file stream as the one after
	 *
	 * @param mixed[] $file name, and optionally description, AFRelationship and mime, with the file's path or content
	 * @param int $specId
	 *
	 * @return int the file specification's object number
	 */
	private function writeAssociatedFile(array $file, $specId)
	{
		$this->beginObject($specId);
		$this->writer->write('<</F ' . $this->writer->string($file['name']));
		if (!empty($file['description'])) {
			$this->writer->write('/Desc ' . $this->writer->string($file['description']));
		}
		$this->writer->write('/Type /Filespec');
		$this->writer->write('/EF <<');
		$this->writer->write('/F ' . ($specId + 1) . ' 0 R');
		$this->writer->write('/UF ' . ($specId + 1) . ' 0 R');
		$this->writer->write('>>');
		if (!empty($file['AFRelationship'])) {
			$this->writer->write('/AFRelationship /' . $file['AFRelationship']);
		}
		$this->writer->write('/UF ' . $this->writer->string($file['name']));
		$this->writer->write('>>');
		$this->writer->write('endobj');

		$fileContent = null;
		if (isset($file['path'])) {
			$fileContent = @file_get_contents($file['path']);
		} elseif (isset($file['content'])) {
			$fileContent = $file['content'];
		}

		if (!$fileContent) {
			throw new \Mpdf\MpdfException(sprintf('Cannot access associated file - %s', isset($file['path']) ? $file['path'] : $file['name']));
		}

		$filestream = gzcompress($fileContent);
		$this->beginObject($specId + 1);
		$this->writer->write('<</Type /EmbeddedFile');
		if (!empty($file['mime'])) {
			$this->writer->write('/Subtype /' . $this->writer->escapeSlashes($file['mime']));
		}
		$this->writer->write('/Length ' . $this->writer->streamLength($filestream));
		$this->writer->write('/Filter /FlateDecode');
		if (isset($file['path'])) {
			// The file's own date, not the document's
			$this->writer->write('/Params <</ModDate '.$this->writer->string('D:' . PdfDate::format(filemtime($file['path']))).' >>');
		} else {
			$this->writer->write('/Params <</ModDate ' . $this->writer->dateString() . ' >>');
		}

		$this->writer->write('>>');
		$this->writer->stream($filestream);
		$this->writer->write('endobj');

		return $specId;
	}

	public function writeCatalog() //_putcatalog
	{
		$this->writer->write('/Type /Catalog');

		// PDF/A-2 and PDF/A-3 are based on PDF 1.7 (ISO 32000-1).
		// The /Version entry in the catalog overrides the header version.
		if ($this->mpdf->PDFA) {
			list($part) = $this->mpdf->pdfaConformance();
			if ($part !== '1') {
				$this->writer->write('/Version /1.7');
			}
		}

		// AES-256 encryption is PDF 2.0, or PDF 1.7 with Adobe's extension level 8
		if ($this->mpdf->encrypted) {
			if (version_compare($this->mpdf->pdf_version, '1.7', '<')) {
				$this->writer->write('/Version /1.7');
			}
			$this->writer->write('/Extensions <</ADBE <</BaseVersion /1.7 /ExtensionLevel 8>>>>');
		}

		$this->writer->write('/Pages 1 0 R');

		if (is_string($this->mpdf->currentLang)) {
			$this->writer->write('/Lang ' . $this->writer->string($this->mpdf->currentLang));
		} elseif (is_string($this->mpdf->default_lang)) {
			$this->writer->write('/Lang ' . $this->writer->string($this->mpdf->default_lang));
		}

		if ($this->mpdf->ZoomMode === 'fullpage') {
			$this->writer->write('/OpenAction [3 0 R /Fit]');
		} elseif ($this->mpdf->ZoomMode === 'fullwidth') {
			$this->writer->write('/OpenAction [3 0 R /FitH null]');
		} elseif ($this->mpdf->ZoomMode === 'real') {
			$this->writer->write('/OpenAction [3 0 R /XYZ null null 1]');
		} elseif (!is_string($this->mpdf->ZoomMode)) {
			$this->writer->write('/OpenAction [3 0 R /XYZ null null ' . ($this->mpdf->ZoomMode / 100) . ']');
		} elseif ($this->mpdf->ZoomMode === 'none') {
			// do not write any zoom mode / OpenAction
		} else {
			$this->writer->write('/OpenAction [3 0 R /XYZ null null null]');
		}

		if ($this->mpdf->LayoutMode === 'single') {
			$this->writer->write('/PageLayout /SinglePage');
		} elseif ($this->mpdf->LayoutMode === 'continuous') {
			$this->writer->write('/PageLayout /OneColumn');
		} elseif ($this->mpdf->LayoutMode === 'twoleft') {
			$this->writer->write('/PageLayout /TwoColumnLeft');
		} elseif ($this->mpdf->LayoutMode === 'tworight') {
			$this->writer->write('/PageLayout /TwoColumnRight');
		} elseif ($this->mpdf->LayoutMode === 'two') {
			if ($this->mpdf->mirrorMargins) {
				$this->writer->write('/PageLayout /TwoColumnRight');
			} else {
				$this->writer->write('/PageLayout /TwoColumnLeft');
			}
		}

		// Bookmarks
		if (count($this->mpdf->BMoutlines) > 0) {
			$this->writer->write('/Outlines ' . $this->mpdf->OutlineRoot . ' 0 R');
		}

		$pageMode = $this->getPageMode();
		if ($pageMode !== null) {
			$this->writer->write('/PageMode /' . $pageMode);
		}

		// Metadata
		if ($this->mpdf->PDFA || $this->mpdf->PDFX) {
			$this->writer->write('/Metadata ' . $this->mpdf->MetadataRoot . ' 0 R');
		}

		// OutputIntents
		if ($this->mpdf->PDFA || $this->mpdf->PDFX || $this->mpdf->ICCProfile) {
			$this->writer->write('/OutputIntents [' . $this->mpdf->OutputIntentRoot . ' 0 R]');
		}

		// Associated files
		if ($this->mpdf->associatedFilesRoot) {
			$this->writer->write('/AF '. $this->mpdf->associatedFilesRoot .' 0 R');

			$names = [];
			foreach ($this->mpdf->associatedFiles as $file) {
				$names[] = $this->writer->string($file['name']) . ' ' . $file['_root'] . ' 0 R';
			}
			$this->writer->write('/Names << /EmbeddedFiles << /Names [' . implode(' ', $names) .  '] >> >>');
		}

		// Forms
		if (count($this->form->forms) > 0) {
			$this->form->_putFormsCatalog();
		}

		if ($this->mpdf->js !== null) {
			$this->writer->write('/Names << /JavaScript ' . $this->mpdf->n_js . ' 0 R >> ');
		}

		if ($this->mpdf->DisplayPreferences || $this->mpdf->directionality === 'rtl' || $this->mpdf->mirrorMargins) {

			$this->writer->write('/ViewerPreferences<<');

			if (is_int(strpos($this->mpdf->DisplayPreferences, 'HideMenubar'))) {
				$this->writer->write('/HideMenubar true');
			}

			if (is_int(strpos($this->mpdf->DisplayPreferences, 'HideToolbar'))) {
				$this->writer->write('/HideToolbar true');
			}

			if (is_int(strpos($this->mpdf->DisplayPreferences, 'HideWindowUI'))) {
				$this->writer->write('/HideWindowUI true');
			}

			if (is_int(strpos($this->mpdf->DisplayPreferences, 'DisplayDocTitle'))) {
				$this->writer->write('/DisplayDocTitle true');
			}

			if (is_int(strpos($this->mpdf->DisplayPreferences, 'CenterWindow'))) {
				$this->writer->write('/CenterWindow true');
			}

			if (is_int(strpos($this->mpdf->DisplayPreferences, 'FitWindow'))) {
				$this->writer->write('/FitWindow true');
			}

			// PrintScaling is PDF 1.6 spec.
			if (!$this->mpdf->PDFA && !$this->mpdf->PDFX && is_int(strpos($this->mpdf->DisplayPreferences, 'NoPrintScaling'))) {
				$this->writer->write('/PrintScaling /None');
			}

			if ($this->mpdf->directionality === 'rtl') {
				$this->writer->write('/Direction /R2L');
			}

			// Duplex is PDF 1.7 spec.
			if ($this->mpdf->mirrorMargins && !$this->mpdf->PDFA && !$this->mpdf->PDFX) {
				// if ($this->mpdf->DefOrientation=='P') $this->writer->write('/Duplex /DuplexFlipShortEdge');
				$this->writer->write('/Duplex /DuplexFlipLongEdge'); // PDF v1.7+
			}

			$this->writer->write('>>');
		}

		if ($this->mpdf->hasOC || count($this->mpdf->layers)) {

			$p = $v = $h = $l = $loff = $lall = $as = '';

			if ($this->mpdf->hasOC) {

				if (($this->mpdf->hasOC & 1) === 1) {
					$p = $this->mpdf->n_ocg_print . ' 0 R';
				}

				if (($this->mpdf->hasOC & 2) === 2) {
					$v = $this->mpdf->n_ocg_view . ' 0 R';
				}

				if (($this->mpdf->hasOC & 4) === 4) {
					$h = $this->mpdf->n_ocg_hidden . ' 0 R';
				}

				$as = "<</Event /Print /OCGs [$p $v $h] /Category [/Print]>> <</Event /View /OCGs [$p $v $h] /Category [/View]>>";
			}

			if (count($this->mpdf->layers)) {
				foreach ($this->mpdf->layers as $k => $layer) {
					if (isset($this->mpdf->layerDetails[$k]) && strtolower($this->mpdf->layerDetails[$k]['state']) === 'hidden') {
						$loff .= $layer['n'] . ' 0 R ';
					} else {
						$l .= $layer['n'] . ' 0 R ';
					}
					$lall .= $layer['n'] . ' 0 R ';
				}
			}

			$this->writer->write("/OCProperties <</OCGs [$p $v $h $lall] /D <</Name " . $this->writer->string('Default') . " /ON [$p $l] /OFF [$v $h $loff] ");
			$this->writer->write("/Order [$v $p $h $lall] ");

			// PDF/A forbids /AS, and so allows only hidden content, which needs no usage application
			if ($as && !$this->mpdf->PDFA) {
				$this->writer->write("/AS [$as] ");
			}

			$this->writer->write('>>>>');
		}
	}

	/**
	 * A catalog carries a single /PageMode, so the modes the document asks for explicitly take
	 * precedence over the outline pane implied by simply having bookmarks.
	 *
	 * @return string|null
	 */
	private function getPageMode()
	{
		if ($this->mpdf->open_layer_pane && ($this->mpdf->hasOC || count($this->mpdf->layers))) {
			return 'UseOC';
		}

		if (is_int(strpos($this->mpdf->DisplayPreferences, 'FullScreen'))) {
			return 'FullScreen';
		}

		// UseAttachments is PDF 1.6 spec.
		if (is_int(strpos($this->mpdf->DisplayPreferences, 'UseAttachments'))) {
			return 'UseAttachments';
		}

		if (count($this->mpdf->BMoutlines) > 0) {
			return 'UseOutlines';
		}

		return null;
	}

	/**
	 * Whether the file an annotation attaches is embedded, which `allowAnnotationFiles` gates. Where it is
	 * not, the annotation is written with subtype /Text and no stream for the file
	 *
	 * @param array $annotation An entry of Mpdf::$PageAnnots
	 *
	 * @return bool
	 */
	public function embedsFileAttachment(array $annotation)
	{
		return $this->mpdf->allowAnnotationFiles && !empty($annotation['opt']['file']);
	}

	/**
	 * Load the file of every annotation that embeds one, for settleAnnotations(). A file
	 * that fails is cleared from its annotation, which is then written as a text note; each path is loaded once
	 * for the document
	 *
	 * @throws \Mpdf\MpdfAnnotationException Where a file fails with `showAnnotationErrors` or `debug` on
	 */
	private function loadAnnotationFiles()
	{
		foreach ($this->mpdf->PageAnnots as $n => $annotations) {
			foreach ($annotations as $k => $annotation) {
				if (!$this->embedsFileAttachment($annotation)) {
					continue;
				}

				$path = $annotation['opt']['file'];
				if (!array_key_exists($path, $this->annotationFiles)) {
					$this->annotationFiles[$path] = $this->loadAnnotationFileOrWarn($path);
				}

				if ($this->annotationFiles[$path] === false) {
					$this->mpdf->PageAnnots[$n][$k]['opt']['file'] = '';
				}
			}
		}
	}

	/**
	 * An annotation's file as loadAnnotationFile() gives it, or false with a warning where it fails and
	 * `showAnnotationErrors` and `debug` are off
	 *
	 * @param string $path
	 *
	 * @return string[]|false
	 *
	 * @throws \Mpdf\MpdfAnnotationException
	 */
	private function loadAnnotationFileOrWarn($path)
	{
		try {
			return $this->loadAnnotationFile($path);
		} catch (MpdfAnnotationException $e) {
			if ($this->mpdf->showAnnotationErrors || $this->mpdf->debug) {
				throw $e;
			}

			$this->logger->warning($e->getMessage(), ['context' => LogContext::ANNOTATIONS]);

			return false;
		}
	}

	/**
	 * Read an annotation's file through the asset fetcher and detect its MIME type. `annotationFileAllowList`
	 * must list its extension, and the detected type for it; the file must be no larger than
	 * `annotationFileMaxSize`, and a local file's size is checked before it is read
	 *
	 * @param string $path A full path, as Mpdf::Annotation() leaves it
	 *
	 * @return string[] The compressed contents and the MIME type
	 *
	 * @throws \Mpdf\MpdfAnnotationException Where any of that fails
	 */
	private function loadAnnotationFile($path)
	{
		$allowList = new FileTypeAllowList((array) $this->mpdf->annotationFileAllowList);
		if ($allowList->isEmpty()) {
			throw new MpdfAnnotationException(sprintf('File attachment %s is not embedded: "annotationFileAllowList" is empty, so no extension is allowed', $path));
		}

		$extension = Path::extension($path);
		if (!$allowList->allowsExtension($extension)) {
			throw new MpdfAnnotationException(sprintf('File attachment %s is not embedded: "annotationFileAllowList" does not list its extension "%s"', $path, $extension));
		}

		if (!class_exists('finfo')) {
			throw new MpdfAnnotationException(sprintf('File attachment %s is not embedded: ext-fileinfo is required to detect its type', $path));
		}

		$limit = (int) $this->mpdf->annotationFileMaxSize;
		$tooLarge = sprintf('File attachment %s is not embedded: it is larger than the %d bytes "annotationFileMaxSize" allows', $path, $limit);

		// Avoids reading a local file that is already too large; the length check below enforces the limit
		if ($limit > 0 && Path::isLocal($path) && @filesize($path) > $limit) {
			throw new MpdfAnnotationException($tooLarge);
		}

		try {
			$content = $this->assetFetcher->fetchDataFromPath($path);
		} catch (\Mpdf\MpdfException $e) {
			throw new MpdfAnnotationException(sprintf('File attachment %s is not embedded: it cannot be read (%s)', $path, $e->getMessage()), 0, E_ERROR, null, null, $e);
		}

		if (!$content) {
			throw new MpdfAnnotationException(sprintf('File attachment %s is not embedded: it cannot be read or is empty', $path));
		}

		if ($limit > 0 && strlen($content) > $limit) {
			throw new MpdfAnnotationException($tooLarge);
		}

		$finfo = new \finfo(FILEINFO_MIME_TYPE);
		$type = $finfo->buffer($content);
		if (!$allowList->allowsType($extension, $type)) {
			throw new MpdfAnnotationException(sprintf('File attachment %s is not embedded: it holds %s, which "annotationFileAllowList" does not list for the extension "%s"', $path, $type, $extension));
		}

		return [gzcompress($content), $type];
	}

	/**
	 * Whether a popup is written for an annotation. Only one with no embedded file carries a popup: the file
	 * takes the object the popup would have been
	 *
	 * @param array $annotation An entry of Mpdf::$PageAnnots
	 *
	 * @return bool
	 */
	private function writesPopup(array $annotation)
	{
		return !$this->embedsFileAttachment($annotation) && !empty($annotation['opt']['popup']);
	}

	/**
	 * @since 5.7.2
	 */
	public function writeAnnotations() // _putannots
	{
		$nb = $this->mpdf->page;

		for ($n = 1; $n <= $nb; $n++) {

			if (isset($this->mpdf->PageLinks[$n]) || isset($this->mpdf->PageAnnots[$n]) || count($this->form->forms) > 0) {

				$wPt = $this->mpdf->pageDim[$n]['w'] * Mpdf::SCALE;
				$hPt = $this->mpdf->pageDim[$n]['h'] * Mpdf::SCALE;

				// Links
				if (isset($this->mpdf->PageLinks[$n])) {

					foreach ($this->mpdf->PageLinks[$n] as $key => $pl) {

						$this->beginObject($this->linkIds[$n][$key]);
						$rect = sprintf('%.3F %.3F %.3F %.3F', $pl[0], $pl[1], $pl[0] + $pl[2], $pl[1] - $pl[3]);
						$this->writer->write('<</Type /Annot /Subtype /Link /Rect [' . $rect . ']', false);

						// Removed as causing undesired effects in Chrome PDF viewer https://github.com/mpdf/mpdf/issues/283
						// $this->writer->write(' /Contents ' . $this->writer->utf16BigEndianTextString($pl[4]);
						$this->writer->write(' /NM ' . $this->writer->string(sprintf('%04u-%04u', $n, $key)), false);
						$this->writer->write(' /M ' . $this->writer->dateString(), false);

						// Use this (instead of /Border) to specify border around link
						// $this->writer->write(' /BS <</W 1');	// Width on points; 0 = no line
						// $this->writer->write(' /S /D');		// style - [S]olid, [D]ashed, [B]eveled, [I]nset, [U]nderline
						// $this->writer->write(' /D [3 2]');		// Dash array - if dashed
						// $this->writer->write(' >>');
						// $this->writer->write(' /C [1 0 0]');	// Color RGB

						if ($this->mpdf->PDFA || $this->mpdf->PDFX) {
							$this->writer->write(' /F 28', false);
						}

						// An imported link carries its source annotation's own entries, which may include a border
						if (!isset($pl['importedLink'])) {
							$this->writer->write(' /Border [0 0 0]', false);
						}

						if (strpos($pl[4], '@') === 0) {

							$p = substr($pl[4], 1);
							// $h=isset($this->mpdf->OrientationChanges[$p]) ? $wPt : $hPt;
							$htarg = $this->mpdf->pageDim[$p]['h'] * Mpdf::SCALE;
							$this->writer->write(sprintf(' /Dest [%d 0 R /XYZ 0 %.3F null]>>', 1 + 2 * $p, $htarg));

						} elseif (is_string($pl[4])) {
							/**
							 * This block is from the FPDI library
							 * @copyright Copyright (c) 2024 Setasign GmbH & Co. KG (https://www.setasign.com)
							 * @license http://opensource.org/licenses/mit-license The MIT License
							 */
							if (isset($pl['importedLink'])) {
								$this->writer->write('/A <</S /URI /URI ' . $this->writer->string($pl[4]) . '>>');
								$values = $pl['importedLink']['pdfObject']->value;

								foreach ($values as $name => $entry) {
									$this->writer->write('/' . $name . ' ', false);
									$this->mpdf->writePdfType($entry);
								}

								if (isset($pl['quadPoints'])) {
									$s = '/QuadPoints[';
									foreach ($pl['quadPoints'] as $value) {
										$s .= sprintf('%.3F ', $value);
									}
									$s .= ']';
									$this->writer->write($s);
								}
							} else {
								$this->writer->write(' /A <</S /URI /URI ' . $this->writer->string($pl[4]) . '>>');
							}
							$this->writer->write('>>');
						} else {

							$l = $this->mpdf->links[$pl[4]];
							// may not be set if #link points to non-existent target
							if (isset($this->mpdf->pageDim[$l[0]]['h'])) {
								$htarg = $this->mpdf->pageDim[$l[0]]['h'] * Mpdf::SCALE;
							} else {
								$htarg = $this->mpdf->h * Mpdf::SCALE;
							} // doesn't really matter

							$this->writer->write(sprintf(' /Dest [%d 0 R /XYZ 0 %.3F null]>>', 1 + 2 * $l[0], $htarg - $l[1] * Mpdf::SCALE));
						}

						$this->writer->write('endobj');

					}
				}

				/* -- ANNOTATIONS -- */
				if (isset($this->mpdf->PageAnnots[$n])) {

					foreach ($this->mpdf->PageAnnots[$n] as $key => $pl) {

						$fileAttachment = $this->embedsFileAttachment($pl);
						$ids = $pl['ids'];

						$this->beginObject($ids['annot']);

						$annot = '';
						$pl['opt'] = array_change_key_case($pl['opt'], CASE_LOWER);
						$x = $pl['x'];

						if ($this->mpdf->annotMargin != 0 || $x == 0 || $x < 0) { // Odd page, intentional non-strict comparison
							$x = ($wPt / Mpdf::SCALE) - $this->mpdf->annotMargin;
						}

						$w = $h = 0;
						$a = $x * Mpdf::SCALE;
						$b = $hPt - ($pl['y'] * Mpdf::SCALE);

						$annot .= '<</Type /Annot ';

						if ($fileAttachment) {
							$annot .= '/Subtype /FileAttachment ';
							// Need to set a size for FileAttachment icons
							if ($pl['opt']['icon'] === 'Paperclip') {
								$w = 8.235;
								$h = 20;
							} elseif ($pl['opt']['icon'] === 'Tag') {
								$w = 20;
								$h = 16;
							} elseif ($pl['opt']['icon'] === 'Graph') {
								$w = 20;
								$h = 20;
							} else {
								$w = 14;
								$h = 20;
							}

							// PushPin
							$f = $pl['opt']['file'];
							$f = preg_replace('/^.*\//', '', $f);
							$f = preg_replace('/[^a-zA-Z0-9._]/', '', $f);

							if (isset($ids['filespec'])) {
								$annot .= '/FS ' . $ids['filespec'] . ' 0 R /AF [' . $ids['filespec'] . ' 0 R]';
							} else {
								$annot .= '/FS <</Type /Filespec /F ' . $this->writer->string($f);
								if ($this->mpdf->PDFA) {
									$annot .= ' /UF ' . $this->writer->string($f);
								}
								$annot .= '/EF <</F ' . $ids['file'] . ' 0 R>>';
								$annot .= '>>';
							}

						} else {
							$annot .= '/Subtype /Text';
							$w = 20;
							$h = 20;  // mPDF 6
						}

						$rect = sprintf('%.3F %.3F %.3F %.3F', $a, $b - $h, $a + $w, $b);
						$annot .= ' /Rect [' . $rect . ']';

						// contents = description of file in free text
						$annot .= ' /Contents ' . $this->writer->utf16BigEndianTextString($pl['txt']);

						$annot .= ' /NM ' . $this->writer->string(sprintf('%04u-%04u', $n, 2000 + $key));
						$annot .= ' /M ' . $this->writer->dateString();
						$annot .= ' /CreationDate ' . $this->writer->dateString();
						$annot .= ' /Border [0 0 0]';

						if ($this->mpdf->PDFA || $this->mpdf->PDFX) {
							$annot .= ' /F 28';
						}

						if (!$this->mpdf->transparencyAllowed()) {
							$annot .= ' /CA 1';
						} elseif ($pl['opt']['ca'] > 0) {
							$annot .= ' /CA ' . $pl['opt']['ca'];
						}

						$fill = $this->annotationFill($pl['opt']);
						// PDF/A-1 allows /C only with an RGB output intent; the appearance still carries the colour
						if ($this->mpdf->pdfaPart() !== '1' || $this->mpdf->restrictColorSpace !== 3) {
							$annot .= ' /C [' . preg_replace('/ (rg|g|k)$/', '', $fill) . ']';
						}

						// Kept apart, as a popup reuses $w and $h for its own size
						$appearanceWidth = $w;
						$appearanceHeight = $h;
						if ($this->mpdf->PDFA) {
							$annot .= ' /AP <</N ' . $ids['appearance'] . ' 0 R>>';
						}

						// Usually Author
						// Use as Title for fileattachment
						if (isset($pl['opt']['t']) && is_string($pl['opt']['t'])) {
							$annot .= ' /T ' . $this->writer->utf16BigEndianTextString($pl['opt']['t']);
						}

						if ($fileAttachment) {
							$iconsapp = ['Paperclip', 'Graph', 'PushPin', 'Tag'];
						} else {
							$iconsapp = ['Comment', 'Help', 'Insert', 'Key', 'NewParagraph', 'Note', 'Paragraph'];
						}

						if (isset($pl['opt']['icon']) && in_array($pl['opt']['icon'], $iconsapp)) {
							$annot .= ' /Name /' . $pl['opt']['icon'];
						} elseif ($fileAttachment) {
							$annot .= ' /Name /PushPin';
						} else {
							$annot .= ' /Name /Note';
						}

						if (!$fileAttachment) {
							// Subj is PDF 1.5 spec, after PDF/X-1a (PDF 1.3) and PDF/A-1 (PDF 1.4)
							if (isset($pl['opt']['subj']) && !$this->mpdf->PDFX && $this->mpdf->pdfaPart() !== '1') {
								$annot .= ' /Subj ' . $this->writer->utf16BigEndianTextString($pl['opt']['subj']);
							}
							if ($this->writesPopup($pl)) {
								$annot .= ' /Open true';
								$annot .= ' /Popup ' . $ids['popup'] . ' 0 R';
							} else {
								$annot .= ' /Open false';
							}
						}

						$annot .= ' /P ' . $pl['pageobj'] . ' 0 R';
						$annot .= '>>';
						$this->writer->write($annot);
						$this->writer->write('endobj');

						if ($fileAttachment) {

							list($filestream, $type) = $this->annotationFiles[$pl['opt']['file']];

							if (isset($ids['filespec'])) {
								$this->writeAssociatedFile([
									'name' => $f,
									'AFRelationship' => 'Unspecified',
									'mime' => $type,
									'content' => gzuncompress($filestream),
								], $ids['filespec']);
							} else {
								$this->beginObject($ids['file']);
								$this->writer->write('<</Type /EmbeddedFile');
								$this->writer->write('/Subtype /' . $this->writer->escapeSlashes($type));
								$this->writer->write('/Length ' . $this->writer->streamLength($filestream));
								$this->writer->write('/Filter /FlateDecode');
								$this->writer->write('>>');
								$this->writer->stream($filestream);
								$this->writer->write('endobj');
							}

						} elseif ($this->writesPopup($pl)) {
							$this->beginObject($ids['popup']);
							$annot = '';
							if (is_array($pl['opt']['popup']) && isset($pl['opt']['popup'][0])) {
								$x = $pl['opt']['popup'][0] * Mpdf::SCALE;
							} else {
								$x = $pl['x'] * Mpdf::SCALE;
							}
							if (is_array($pl['opt']['popup']) && isset($pl['opt']['popup'][1])) {
								$y = $hPt - ($pl['opt']['popup'][1] * Mpdf::SCALE);
							} else {
								$y = $hPt - ($pl['y'] * Mpdf::SCALE);
							}
							if (is_array($pl['opt']['popup']) && isset($pl['opt']['popup'][2])) {
								$w = $pl['opt']['popup'][2] * Mpdf::SCALE;
							} else {
								$w = 180;
							}
							if (is_array($pl['opt']['popup']) && isset($pl['opt']['popup'][3])) {
								$h = $pl['opt']['popup'][3] * Mpdf::SCALE;
							} else {
								$h = 120;
							}
							$rect = sprintf('%.3F %.3F %.3F %.3F', $x, $y - $h, $x + $w, $y);
							$annot .= '<</Type /Annot /Subtype /Popup /Rect [' . $rect . ']';
							$annot .= ' /M ' . $this->writer->dateString();
							if ($this->mpdf->PDFA || $this->mpdf->PDFX) {
								$annot .= ' /F 28';
							}
							$annot .= ' /Parent ' . $ids['annot'] . ' 0 R';
							$annot .= '>>';
							$this->writer->write($annot);
							$this->writer->write('endobj');
						}

						if ($this->mpdf->PDFA) {
							$this->writeAnnotationAppearance($ids['appearance'], $appearanceWidth, $appearanceHeight, $fill);
						}
					}
				}

				// Active Forms
				if (count($this->form->forms) > 0) {
					$this->form->_putFormItems($n, $hPt);
				}
			}
		}

		// Active Forms - Radio Button Group entries
		// Output Radio Button Group form entries (radio_on_obj_id already determined)
		if (count($this->form->form_radio_groups)) {
			$this->form->_putRadioItems($n);
		}

		$this->annotationFiles = [];
	}

	/**
	 * Numbers the objects writeAnnotations() writes, in the order it writes them, starting at $id
	 *
	 * Each page gets its links, then its annotations, then its form widgets. An annotation's ids name the annotation
	 * itself, its embedded file (under PDF/A-3 its file specification, then the file) or else its popup, and its
	 * appearance under PDF/A. The radio groups come last.
	 *
	 * @param int $id the object number after the last page's
	 *
	 * @return int[][] the objects each page lists in /Annots, by page
	 */
	public function numberAnnotations($id)
	{
		$this->settleAnnotations();

		$this->linkIds = [];
		$annots = [];

		for ($n = 1; $n <= $this->mpdf->page; $n++) {
			$annots[$n] = [];

			if (isset($this->mpdf->PageLinks[$n])) {
				foreach ($this->mpdf->PageLinks[$n] as $key => $pl) {
					$this->linkIds[$n][$key] = $annots[$n][] = $id++;
				}
			}

			if (isset($this->mpdf->PageAnnots[$n])) {
				foreach ($this->mpdf->PageAnnots[$n] as $key => $pl) {
					$ids = ['annot' => $annots[$n][] = $id++];

					if ($pl['opt']['file'] && $this->associatesAnnotationFiles()) {
						$ids['filespec'] = $id++;
						$ids['file'] = $id++;
					} elseif ($pl['opt']['file']) {
						$ids['file'] = $id++;
					} elseif (!empty($pl['opt']['popup'])) {
						$ids['popup'] = $annots[$n][] = $id++;
					}

					if ($this->mpdf->PDFA) {
						$ids['appearance'] = $id++;
					}

					$this->mpdf->PageAnnots[$n][$key]['ids'] = $ids;
					$this->mpdf->PageAnnots[$n][$key]['pageobj'] = 1 + 2 * $n;
				}
			}

			$this->form->addFormIds($n, $annots[$n], $id);
		}

		foreach ($this->form->form_radio_groups as $name => $frg) {
			$this->form->form_radio_groups[$name]['obj_id'] = $id++;
		}

		return $annots;
	}

	/**
	 * Loads each annotation's file, and drops it where it fails, or where the configuration or the PDF/A part does
	 * not allow it to be embedded
	 */
	private function settleAnnotations()
	{
		$this->loadAnnotationFiles();

		foreach ($this->mpdf->PageAnnots as $n => $annotations) {
			foreach ($annotations as $key => $pl) {
				$file = $pl['opt']['file'];

				if ($file && !$this->mpdf->allowAnnotationFiles) {
					$this->logger->warning('Embedded files for annotations have to be allowed explicitly with "allowAnnotationFiles" config key');
					$file = '';
				} elseif ($file && !$this->mayEmbed($file)) {
					if (!$this->mpdf->PDFAauto) {
						$this->mpdf->PDFAXwarnings[] = sprintf('PDFA version %s cannot embed the file "%s" (Annotation written without the file)', $this->mpdf->PDFAversion, $file);
					}
					$file = '';
				}

				$this->mpdf->PageAnnots[$n][$key]['opt']['file'] = $file;
			}
		}
	}

	/**
	 * Begins object $id, numbered by numberAnnotations() or next after the objects already written
	 *
	 * @param int $id
	 */
	private function beginObject($id)
	{
		$this->writer->object($id);
		$this->mpdf->n = $id;
	}

	/**
	 * Whether an annotation's file is written as a PDF/A-3 associated file, with a MIME type, an AFRelationship and
	 * the annotation's /AF naming it
	 *
	 * @return bool
	 */
	private function associatesAnnotationFiles()
	{
		if (!$this->mpdf->PDFA) {
			return false;
		}

		list($part) = $this->mpdf->pdfaConformance();

		return $part === '3';
	}

	/**
	 * Whether the PDF/A part lets the document embed a file: PDF/A-1 embeds none, PDF/A-2 only PDF/A documents
	 *
	 * PDF/A forbids compressing the XMP metadata, so a file's pdfaid:part claim can be read from its raw bytes. The
	 * claim is taken on trust.
	 *
	 * @param string $file A path loadAnnotationFiles() has loaded
	 *
	 * @return bool
	 */
	private function mayEmbed($file)
	{
		if (!$this->mpdf->PDFA) {
			return true;
		}

		list($part) = $this->mpdf->pdfaConformance();

		if ($part === '1') {
			return false;
		}

		if ($part !== '2') {
			return true;
		}

		$content = gzuncompress($this->annotationFiles[$file][0]);

		return strpos($content, '%PDF-') === 0 && strpos($content, 'pdfaid:part') !== false;
	}

	/**
	 * The fill operator for an annotation's colour, yellow if it has none
	 *
	 * A spot colour falls back to yellow too, as neither the /C array nor the appearance can name its colour space.
	 *
	 * @param mixed[] $opt
	 *
	 * @return string
	 */
	private function annotationFill(array $opt)
	{
		if (empty($opt['c']) || $opt['c'][0] == 2) {
			return '1.000 1.000 0.000 rg';
		}

		return $this->mpdf->SetColor($opt['c']);
	}

	/**
	 * Writes the appearance PDF/A-2 requires of an annotation: a note icon in the annotation's colour
	 *
	 * @param int $id
	 * @param float $w
	 * @param float $h
	 * @param string $fill the fill operator for the annotation's colour
	 */
	private function writeAnnotationAppearance($id, $w, $h, $fill)
	{
		$stream = sprintf("q %s 0 G 0.5 w 0.25 0.25 %.3F %.3F re B\n", $fill, $w - 0.5, $h - 0.5);
		for ($line = 1; $line <= 3; $line++) {
			$y = $h * $line / 4;
			$stream .= sprintf("%.3F %.3F m %.3F %.3F l\n", $w * 0.2, $y, $w * 0.8, $y);
		}
		$stream .= "S Q";

		$this->beginObject($id);
		$this->writer->write(sprintf('<</Type /XObject /Subtype /Form /BBox [0 0 %.3F %.3F] /Length %d>>', $w, $h, strlen($stream)));
		$this->writer->stream($stream);
		$this->writer->write('endobj');
	}

	public function writeEncryption() // _putencryption
	{
		$this->writer->write('/Filter /Standard');
		$this->writer->write('/V 5');
		$this->writer->write('/R 6');
		$this->writer->write('/Length 256');
		$this->writer->write('/CF <</StdCF <</AuthEvent /DocOpen /CFM /AESV3 /Length 32>>>>');
		$this->writer->write('/StmF /StdCF');
		$this->writer->write('/StrF /StdCF');
		$this->writer->write('/O (' . $this->writer->escape($this->protection->getOValue()) . ')');
		$this->writer->write('/U (' . $this->writer->escape($this->protection->getUValue()) . ')');
		$this->writer->write('/OE (' . $this->writer->escape($this->protection->getOEValue()) . ')');
		$this->writer->write('/UE (' . $this->writer->escape($this->protection->getUEValue()) . ')');
		$this->writer->write('/P ' . $this->protection->getPValue());
		$this->writer->write('/Perms (' . $this->writer->escape($this->protection->getPermsValue()) . ')');
	}

	/**
	 * The trailer's entries but the file ID, which a cross-reference stream carries in its own dictionary instead
	 *
	 * @param int $root The object number of the catalog
	 *
	 * @return string[]
	 */
	public function trailer($root) // _puttrailer
	{
		$entries = [
			'/Size ' . ($this->mpdf->n + 1),
			'/Root ' . $root . ' 0 R',
			'/Info ' . $this->mpdf->InfoRoot . ' 0 R',
		];

		if ($this->mpdf->encrypted) {
			$entries[] = '/Encrypt ' . $this->mpdf->enc_obj_id . ' 0 R';
		}

		return $entries;
	}

	/**
	 * The trailer's file ID, made of the document as written up to the moment it is asked for: a classic trailer
	 * asks after writing its other entries, so the ID stays what it has always been
	 *
	 * @return string
	 */
	public function fileId()
	{
		$uniqid = $this->hash();

		return '/ID [<' . $uniqid . '> <' . $uniqid . '>]';
	}

	/**
	 * A hash of the document so far and the moment it is dated: what the file ID and the XMP identifier are made
	 * of, so they follow the content rather than the run
	 */
	private function hash()
	{
		return md5($this->writer->date()->getTimestamp() . $this->mpdf->buffer->getHash());
	}

	private function getVersionString()
	{
		$return = Mpdf::VERSION;
		$headFile = __DIR__ . '/../../.git/HEAD';
		if (file_exists($headFile)) {
			$ref = file($headFile);
			$path = explode('/', $ref[0], 3);
			$branch = isset($path[2]) ? trim($path[2]) : '';
			$revFile = __DIR__ . '/../../.git/refs/heads/' . $branch;
			if ($branch && file_exists($revFile)) {
				$rev = file($revFile);
				$rev = substr($rev[0], 0, 7);
				$return .= ' (' . $rev . ')';
			}
		}

		return $return;
	}

	private function getProducerString()
	{
		return 'mPDF' . ($this->mpdf->exposeVersion ? (' ' . $this->getVersionString()) : '');
	}

}
