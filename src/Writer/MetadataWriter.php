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
		} // This bit is specific to PDFA-1b
		elseif ($this->mpdf->PDFA) {

			if (strpos($this->mpdf->PDFAversion, '-') === false) {
				throw new \Mpdf\MpdfException(sprintf('PDFA version (%s) is not valid. (Use: 1-B, 3-B, etc.)', $this->mpdf->PDFAversion));
			}

			list($part, $conformance) = explode('-', strtoupper($this->mpdf->PDFAversion));
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

		// for each file, we create the spec object + the stream object
		foreach ($this->mpdf->associatedFiles as $k => $file) {
			// spec
			$this->writer->object();
			$this->mpdf->associatedFiles[$k]['_root'] = $this->mpdf->n; // we store the root ref of object for future reference (e.g. /EmbeddedFiles catalog)
			$this->writer->write('<</F ' . $this->writer->string($file['name']));
			if (!empty($file['description'])) {
				$this->writer->write('/Desc ' . $this->writer->string($file['description']));
			}
			$this->writer->write('/Type /Filespec');
			$this->writer->write('/EF <<');
			$this->writer->write('/F ' . ($this->mpdf->n + 1) . ' 0 R');
			$this->writer->write('/UF ' . ($this->mpdf->n + 1) . ' 0 R');
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
			$this->writer->object();
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

	public function writeCatalog() //_putcatalog
	{
		$this->writer->write('/Type /Catalog');

		// PDF/A-2 and PDF/A-3 are based on PDF 1.7 (ISO 32000-1).
		// The /Version entry in the catalog overrides the header version.
		if ($this->mpdf->PDFA && strpos($this->mpdf->PDFAversion, '-') !== false) {
			list($part) = explode('-', $this->mpdf->PDFAversion);
			if ((int) $part >= 2) {
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

			$this->writer->write("/OCProperties <</OCGs [$p $v $h $lall] /D <</ON [$p $l] /OFF [$v $h $loff] ");
			$this->writer->write("/Order [$v $p $h $lall] ");

			if ($as) {
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
	 * The number of objects writeAnnotations() writes an annotation as. PageWriter reserves that many numbers
	 * for it before any of the objects exists.
	 *
	 * The annotation is one; the stream of its embedded file or its popup is a second. Never both, so an
	 * annotation asking for both still takes two.
	 *
	 * @param array $annotation An entry of Mpdf::$PageAnnots
	 *
	 * @return int
	 */
	public function countAnnotationObjects(array $annotation)
	{
		if ($this->embedsFileAttachment($annotation) || $this->writesPopup($annotation)) {
			return 2;
		}

		return 1;
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
	 * Load the file of every annotation that embeds one, before PageWriter numbers the objects they take. A file
	 * that fails is cleared from its annotation, which is then written as a text note; each path is loaded once
	 * for the document
	 *
	 * @throws \Mpdf\MpdfAnnotationException Where a file fails with `showAnnotationErrors` or `debug` on
	 */
	public function loadAnnotationFiles()
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

						$this->writer->object();
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

						if (!$fileAttachment && !empty($pl['opt']['file'])) {
							$this->logger->warning('Embedded files for annotations have to be allowed explicitly with "allowAnnotationFiles" config key');
						}

						$this->writer->object();

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

							$annot .= '/FS <</Type /Filespec /F ' . $this->writer->string($f);
							$annot .= '/EF <</F ' . ($this->mpdf->n + 1) . ' 0 R>>';
							$annot .= '>>';

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
							$annot .= ' /CA 1';
						} elseif ($pl['opt']['ca'] > 0) {
							$annot .= ' /CA ' . $pl['opt']['ca'];
						}

						$annotcolor = ' /C [';
						if (isset($pl['opt']['c']) && $pl['opt']['c']) {
							$col = $pl['opt']['c'];
							if ($col[0] == 3 || $col[0] == 5) {
								$annotcolor .= sprintf('%.3F %.3F %.3F', ord($col[1]) / 255, ord($col[2]) / 255, ord($col[3]) / 255);
							} elseif ($col[0] == 1) {
								$annotcolor .= sprintf('%.3F', ord($col[1]) / 255);
							} elseif ($col[0] == 4 || $col[0] == 6) {
								$annotcolor .= sprintf('%.3F %.3F %.3F %.3F', ord($col[1]) / 100, ord($col[2]) / 100, ord($col[3]) / 100, ord($col[4]) / 100);
							} else {
								$annotcolor .= '1 1 0';
							}
						} else {
							$annotcolor .= '1 1 0';
						}
						$annotcolor .= ']';
						$annot .= $annotcolor;

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
							// Subj is PDF 1.5 spec.
							if (!$this->mpdf->PDFA && !$this->mpdf->PDFX && isset($pl['opt']['subj'])) {
								$annot .= ' /Subj ' . $this->writer->utf16BigEndianTextString($pl['opt']['subj']);
							}
							if ($this->writesPopup($pl)) {
								$annot .= ' /Open true';
								$annot .= ' /Popup ' . ($this->mpdf->n + 1) . ' 0 R';
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
							$this->writer->object();
							$this->writer->write('<</Type /EmbeddedFile');
							$this->writer->write('/Subtype /' . $this->writer->escapeSlashes($type));
							$this->writer->write('/Length ' . $this->writer->streamLength($filestream));
							$this->writer->write('/Filter /FlateDecode');
							$this->writer->write('>>');
							$this->writer->stream($filestream);
							$this->writer->write('endobj');

						} elseif ($this->writesPopup($pl)) {
							$this->writer->object();
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
							$annot .= ' /Parent ' . ($this->mpdf->n - 1) . ' 0 R';
							$annot .= '>>';
							$this->writer->write($annot);
							$this->writer->write('endobj');
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
