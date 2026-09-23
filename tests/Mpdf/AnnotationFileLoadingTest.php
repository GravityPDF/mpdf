<?php

namespace Mpdf;

use Mockery;
use Mpdf\Config\ConfigVariables;
use Mpdf\Container\SimpleContainer;
use Mpdf\File\LocalContentLoaderInterface;
use Mpdf\Http\ClientInterface;
use Mpdf\Log\Context as LogContext;
use Mpdf\PsrHttpMessageShim\Response;
use Psr\Http\Message\RequestInterface;

/**
 * An annotation's file is read through the asset fetcher when the document is output, no larger than
 * annotationFileMaxSize, and embedded only when annotationFileAllowList lists its extension with the MIME type
 * detected in it, which becomes its /Subtype. A file that cannot be embedded is left out with a warning, or
 * throws with showAnnotationErrors or debug on. <annotation file=""> in HTML also needs allowHtmlAnnotationFiles
 */
class AnnotationFileLoadingTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use PageStreams;

	const XML = '<?xml version="1.0" encoding="UTF-8"?><report/>';

	/**
	 * The files the tests attach, by name, with their contents
	 */
	const FILES = [
		'report.xml' => self::XML,
		'upper.XML' => self::XML,
		'archive.php.xml' => self::XML,
		'archive.xml.php' => '<?php echo 1;',
		'script.php' => '<?php echo 1;',
		'noextension' => 'None',
		'fake.pdf' => 'Plain text, not a PDF',
		'notes.txt' => 'Plain text notes',
	];

	/**
	 * A directory of its own for each test, holding the files
	 *
	 * @var string
	 */
	private $dir;

	/**
	 * What the stub HTTP client was asked for
	 *
	 * @var string[]
	 */
	private $requested;

	/**
	 * Write the files the tests attach
	 */
	protected function set_up()
	{
		parent::set_up();

		$this->dir = sys_get_temp_dir() . '/mpdf-annotation-files-' . uniqid();
		mkdir($this->dir);
		foreach (self::FILES as $name => $content) {
			file_put_contents($this->dir . '/' . $name, $content);
		}

		$this->requested = [];
	}

	/**
	 * Remove the files and close the mocks
	 */
	protected function tear_down()
	{
		foreach (array_keys(self::FILES) as $name) {
			unlink($this->dir . '/' . $name);
		}
		rmdir($this->dir);

		Mockery::close();

		parent::tear_down();
	}

	/**
	 * Without allowHtmlAnnotationFiles the attribute is ignored, even with allowAnnotationFiles on, and the
	 * warning names the key that enables it
	 */
	public function testTheFileAttributeIsIgnoredByDefault()
	{
		$logger = new TestLogger();
		$pdf = $this->htmlDocument($this->dir . '/report.xml', [], $logger);

		$this->assertWrittenAsNote($pdf);
		$this->assertTrue($logger->hasRecordThatContains('"allowHtmlAnnotationFiles"', 'warning'), 'The warning should name the key that enables the attribute');
	}

	/**
	 * A file whose extension is listed with the type of its content is embedded
	 *
	 * @dataProvider listedFiles
	 *
	 * @param string $name
	 * @param array|null $map The allow list, or null for the default
	 */
	public function testAFileListedWithTheTypeOfItsContentIsEmbedded($name, $map)
	{
		$this->requireFileinfo();

		$pdf = $this->htmlDocument($this->dir . '/' . $name, $this->htmlAllowed($map));

		$this->assertEmbedded($pdf, self::FILES[$name]);
	}

	/**
	 * Files, and allow lists that list their extension with the type of their content
	 *
	 * @return array[]
	 */
	public function listedFiles()
	{
		return [
			'by default' => ['report.xml', null],
			'with an upper-case extension' => ['upper.XML', null],
			'the last of two extensions' => ['archive.php.xml', null],
			'listed as one type' => ['notes.txt', ['txt' => 'text/plain']],
			'listed among several types' => ['notes.txt', ['txt' => ['text/csv', 'text/plain']]],
			'listed in another case, with a dot and spaces' => ['report.xml', ['.XML' => [' Application/XML ', 'TEXT/XML ']]],
		];
	}

	/**
	 * By default a file that cannot be embedded is logged as a warning naming it, and the annotation is written
	 * as a text note. It takes no object number, so a form field on the same page is still listed in /Annots
	 *
	 * @dataProvider failures
	 *
	 * @param bool $html Whether the file comes from an <annotation> tag rather than Mpdf::Annotation()
	 * @param string $file With %s standing for the test's directory
	 * @param array $config
	 * @param array|null $http The body and status the stub HTTP client answers with, if one is needed
	 * @param string $reason What the warning should say
	 */
	public function testAFileThatFailsIsLeftOutWithAWarning($html, $file, array $config, $http, $reason)
	{
		$this->requireFileinfo();

		$file = sprintf($file, $this->dir);
		$logger = new TestLogger();
		$pdf = $this->documentWithAField($html, $file, $config, $logger, $http);

		$this->assertWrittenAsNote($pdf);
		$reported = $this->reportedPath($file);
		$this->assertTrue($logger->hasRecordThatPasses(function ($record) use ($reported, $reason) {
			return $record['context'] === ['context' => LogContext::ANNOTATIONS]
				&& strpos($record['message'], $reported) !== false
				&& strpos($record['message'], $reason) !== false;
		}, 'warning'), 'An annotations warning should name the file and why it is left out');

		$refs = $this->annotationRefs($pdf);
		$this->assertCount(2, $refs[0], 'The page should list the note and the widget');
		foreach ($refs[0] as $number) {
			$this->assertStringContainsString('/Type /Annot', $this->object($pdf, $number), "Object $number is listed in /Annots and should be an annotation");
		}
		$this->assertSame(1, substr_count($this->annotations($pdf)[0], '/Subtype /Widget'), 'The page should list the widget');
	}

	/**
	 * With showAnnotationErrors on, a file that cannot be embedded throws, naming it
	 *
	 * @dataProvider failures
	 *
	 * @param bool $html
	 * @param string $file
	 * @param array $config
	 * @param array|null $http
	 * @param string $reason
	 */
	public function testAFileThatFailsThrowsWithShowAnnotationErrors($html, $file, array $config, $http, $reason)
	{
		$this->assertFailureThrows($html, $file, $config + ['showAnnotationErrors' => true], $http, $reason);
	}

	/**
	 * With debug on, a file that cannot be embedded throws as it does with showAnnotationErrors
	 *
	 * @dataProvider failures
	 *
	 * @param bool $html
	 * @param string $file
	 * @param array $config
	 * @param array|null $http
	 * @param string $reason
	 */
	public function testAFileThatFailsThrowsWithDebug($html, $file, array $config, $http, $reason)
	{
		$this->assertFailureThrows($html, $file, $config + ['debug' => true], $http, $reason);
	}

	/**
	 * Every way an annotation's file can fail to embed: where it comes from, the file, the configuration, what
	 * the stub HTTP client answers, and what the message says
	 *
	 * @return array[]
	 */
	public function failures()
	{
		return [
			'an extension not listed, from HTML' => [true, '%s/script.php', [], null, 'does not list its extension "php"'],
			'an extension not listed' => [false, '%s/script.php', [], null, 'does not list its extension "php"'],
			'no extension' => [false, '%s/noextension', [], null, 'does not list its extension ""'],
			'a listed extension before the last' => [false, '%s/archive.xml.php', [], null, 'does not list its extension "php"'],
			'an empty allow list, from HTML' => [true, '%s/report.xml', ['annotationFileAllowList' => []], null, '"annotationFileAllowList" is empty'],
			'an empty allow list' => [false, '%s/report.xml', ['annotationFileAllowList' => []], null, '"annotationFileAllowList" is empty'],
			'content of another type' => [false, '%s/fake.pdf', [], null, 'holds text/plain, which "annotationFileAllowList" does not list for the extension "pdf"'],
			'fetched content of another type' => [false, 'https://example.com/files/report.pdf', [], ['Plain text', 200], 'holds text/plain'],
			'a stream outside whitelistStreamWrappers' => [false, 'data://text/plain,notes.txt', [], null, 'invalid stream'],
			'a local file over the size limit' => [false, '%s/notes.txt', ['annotationFileMaxSize' => 5], null, 'larger than the 5 bytes "annotationFileMaxSize" allows'],
			'fetched content over the size limit' => [false, 'https://example.com/files/report.txt', ['annotationFileMaxSize' => 5], ['Fetched', 200], 'larger than the 5 bytes'],
			'a missing file' => [false, '%s/missing.txt', [], ['', 404], 'cannot be read'],
			'a URL that cannot be fetched' => [false, 'https://example.com/files/report.txt', [], ['', 404], 'cannot be read'],
		];
	}

	/**
	 * A URL's extension is taken from its path alone, and a URL without a listed one is never requested
	 *
	 * @dataProvider urls
	 *
	 * @param string $url
	 * @param bool $allowed
	 */
	public function testAUrlIsAttachedOnlyWithAListedExtension($url, $allowed)
	{
		$this->requireFileinfo();

		$pdf = $this->htmlDocument($url, $this->htmlAllowed(null), null, $this->httpContainer(self::XML));

		if ($allowed) {
			$this->assertEmbedded($pdf, self::XML);
			$this->assertCount(1, $this->requested, 'The URL should be requested once');
		} else {
			$this->assertWrittenAsNote($pdf);
			$this->assertSame([], $this->requested, 'A URL without a listed extension should not be requested');
		}
	}

	/**
	 * URLs, and whether the default list lets each through
	 *
	 * @return array[]
	 */
	public function urls()
	{
		return [
			'a listed extension' => ['https://example.com/files/report.xml', true],
			'a listed extension and a query string' => ['https://example.com/files/report.xml?version=2', true],
			'a listed extension and a fragment' => ['https://example.com/files/report.xml#top', true],
			'the listed extension in the query string only' => ['https://example.com/report.php?name=report.xml', false],
			'the listed extension in the fragment only' => ['https://example.com/report.php#report.xml', false],
			'no path' => ['https://example.com', false],
		];
	}

	/**
	 * A file attached on every page is fetched once for the whole document, and embedded on every page
	 */
	public function testAFileRepeatedOnEveryPageIsFetchedOnce()
	{
		$this->requireFileinfo();

		$pdf = $this->repeatedDocument('https://example.com/files/report.xml', self::XML);

		$this->assertCount(1, $this->requested, 'The file should be fetched once');
		$this->assertSame(3, substr_count($pdf, '/Type /EmbeddedFile'), 'Each page should embed the file');
	}

	/**
	 * A file that fails on every page is fetched, and warned about, once
	 */
	public function testAFileThatFailsOnEveryPageIsFetchedAndWarnedAboutOnce()
	{
		$this->requireFileinfo();

		$logger = new TestLogger();
		$pdf = $this->repeatedDocument('https://example.com/files/report.pdf', 'Plain text', $logger);

		$this->assertCount(1, $this->requested, 'The file should be fetched once');
		$this->assertCount(1, $logger->recordsByLevel['warning'], 'The failure should be warned about once');
		$this->assertSame(3, substr_count($pdf, '/Subtype /Text'), 'Each page should carry a text note');
		$this->assertSame(0, substr_count($pdf, '/Type /EmbeddedFile'), 'No file should be embedded');
	}

	/**
	 * The files are let go once they are written
	 */
	public function testFileContentsAreNotHeldAfterOutput()
	{
		$this->requireFileinfo();

		$mpdf = $this->configured([], null, null);
		$mpdf->WriteHTML('<p>Text</p>');
		$mpdf->Annotation('Note', 0, 0, 'Paperclip', '', '', 0, false, '', $this->dir . '/report.xml');
		$mpdf->Annotation('Note', 0, 0, 'Paperclip', '', '', 0, false, '', $this->dir . '/fake.pdf');
		$this->output($mpdf);

		$this->assertSame([], $this->annotationFiles($mpdf));
	}

	/**
	 * Mpdf::Annotation() does not read the file: that waits for output
	 */
	public function testNothingIsReadBeforeOutput()
	{
		$mpdf = $this->configured([], null, $this->httpContainer(self::XML));
		$mpdf->WriteHTML('<p>Text</p>');
		$mpdf->Annotation('Note', 0, 0, 'Paperclip', '', '', 0, false, '', 'https://example.com/files/report.xml');

		$this->assertSame([], $this->requested, 'Nothing should be fetched before output');
		$this->assertSame([], $this->annotationFiles($mpdf), 'Nothing should be held before output');
	}

	/**
	 * Each default type accepts a sample of its kind, whatever libmagic version ext-fileinfo uses, and the
	 * embedded file's /Subtype is the detected MIME type, written as a PDF name
	 *
	 * @dataProvider defaultExtensions
	 *
	 * @param string $extension
	 */
	public function testEveryDefaultTypeTakesASampleOfItsKind($extension)
	{
		$this->requireFileinfo();

		$path = __DIR__ . '/../data/annotation-files/sample.' . $extension;
		$content = file_get_contents($path);
		$finfo = new \finfo(FILEINFO_MIME_TYPE);

		$pdf = $this->apiDocument($path);

		$this->assertEmbedded($pdf, $content);
		$this->assertSame(1, preg_match('/\/Type \/EmbeddedFile\s*\/Subtype \/(\S+)/', $pdf, $match), 'The embedded file should have a subtype');
		$this->assertSame(str_replace('/', '#2F', $finfo->buffer($content)), $match[1]);
	}

	/**
	 * The extensions the default list names
	 *
	 * @return array[]
	 */
	public function defaultExtensions()
	{
		$extensions = [];
		foreach (array_keys(ConfigVariables::ANNOTATION_FILE_TYPES) as $extension) {
			$extensions[$extension] = [$extension];
		}

		return $extensions;
	}

	/**
	 * A local file larger than annotationFileMaxSize is left out before any of it is read
	 */
	public function testALocalFileOverTheSizeLimitIsLeftOutUnread()
	{
		$this->requireFileinfo();

		$loader = Mockery::mock(LocalContentLoaderInterface::class);
		$loader->shouldNotReceive('load');

		$pdf = $this->apiDocument($this->dir . '/notes.txt', ['annotationFileMaxSize' => 5], null, new SimpleContainer(['localContentLoader' => $loader]));

		$this->assertWrittenAsNote($pdf);
	}

	/**
	 * A limit of 0 lets a file of any size through
	 */
	public function testASizeLimitOfZeroIsNoLimit()
	{
		$this->requireFileinfo();

		$pdf = $this->apiDocument($this->dir . '/notes.txt', ['annotationFileMaxSize' => 0]);

		$this->assertEmbedded($pdf, self::FILES['notes.txt']);
	}

	/**
	 * Build the document for a failure case and expect it to throw an MpdfAnnotationException naming the file
	 *
	 * @param bool $html
	 * @param string $file With %s standing for the test's directory
	 * @param array $config
	 * @param array|null $http
	 * @param string $reason
	 */
	private function assertFailureThrows($html, $file, array $config, $http, $reason)
	{
		$this->requireFileinfo();

		$file = sprintf($file, $this->dir);

		$this->expectException(MpdfAnnotationException::class);
		$this->expectExceptionMessageMatches('/' . preg_quote($this->reportedPath($file), '/') . '.*' . preg_quote($reason, '/') . '/');

		$this->documentWithAField($html, $file, $config, null, $http);
	}

	/**
	 * A path as mPDF names it in a message: resolving it turns every backslash into a forward slash, so a Windows
	 * temporary directory reads C:/Users/... there
	 *
	 * @param string $path
	 *
	 * @return string
	 */
	private function reportedPath($path)
	{
		return str_replace('\\', '/', $path);
	}

	/**
	 * A document with a form field and, on the same page, an annotation attaching a file
	 *
	 * @param bool $html
	 * @param string $file
	 * @param array $config
	 * @param \Mpdf\TestLogger|null $logger
	 * @param array|null $http The body and status a stub HTTP client answers with, or null for none
	 *
	 * @return string
	 */
	private function documentWithAField($html, $file, array $config, $logger, $http)
	{
		$container = $http ? $this->httpContainer($http[0], $http[1]) : null;
		$mpdf = $this->configured($config + ['useActiveForms' => true, 'allowHtmlAnnotationFiles' => $html], $logger, $container);

		$field = '<input type="text" name="field" value="Hello" />';
		if ($html) {
			$mpdf->WriteHTML('<p><annotation content="Note" icon="Paperclip" file="' . $file . '" /> ' . $field . '</p>');
		} else {
			$mpdf->WriteHTML('<p>' . $field . '</p>');
			$mpdf->Annotation('Note', 0, 0, 'Paperclip', '', '', 0, false, '', $file);
		}

		return $this->output($mpdf);
	}

	/**
	 * A three-page document with an annotation on each page attaching the same URL, which the stub HTTP client
	 * answers with the given body
	 *
	 * @param string $url
	 * @param string $body
	 * @param \Mpdf\TestLogger|null $logger
	 *
	 * @return string
	 */
	private function repeatedDocument($url, $body, $logger = null)
	{
		$mpdf = $this->configured([], $logger, $this->httpContainer($body));
		for ($page = 1; $page <= 3; $page++) {
			if ($page > 1) {
				$mpdf->AddPage();
			}
			$mpdf->WriteHTML('<p>Page ' . $page . '</p>');
			$mpdf->Annotation('Note', 0, 0, 'Paperclip', '', '', 0, false, '', $url);
		}

		return $this->output($mpdf);
	}

	/**
	 * What the document's metadata writer holds about annotation files
	 *
	 * @param \Mpdf\Mpdf $mpdf
	 *
	 * @return array
	 */
	private function annotationFiles(Mpdf $mpdf)
	{
		$writer = new \ReflectionProperty(Mpdf::class, 'metadataWriter');
		$files = new \ReflectionProperty(Writer\MetadataWriter::class, 'annotationFiles');
		if (PHP_VERSION_ID < 80100) {
			$writer->setAccessible(true);
			$files->setAccessible(true);
		}

		return $files->getValue($writer->getValue($mpdf));
	}

	/**
	 * Skips a test that needs MIME detection when ext-fileinfo is missing
	 */
	private function requireFileinfo()
	{
		if (!class_exists('finfo')) {
			$this->markTestSkipped('ext-fileinfo is needed to detect the type of an annotation file');
		}
	}

	/**
	 * The configuration that lets the file attribute attach a file
	 *
	 * @param array|null $map The allow list, or null for the default
	 *
	 * @return array
	 */
	private function htmlAllowed($map)
	{
		$config = ['allowHtmlAnnotationFiles' => true];
		if ($map !== null) {
			$config['annotationFileAllowList'] = $map;
		}

		return $config;
	}

	/**
	 * A document holding an <annotation> tag that attaches a file, with allowAnnotationFiles on
	 *
	 * @param string $file The file attribute
	 * @param array $config
	 * @param \Mpdf\TestLogger|null $logger
	 * @param \Mpdf\Container\SimpleContainer|null $container
	 *
	 * @return string
	 */
	private function htmlDocument($file, array $config = [], $logger = null, $container = null)
	{
		$mpdf = $this->configured($config, $logger, $container);
		$mpdf->WriteHTML('<p><annotation content="Note" icon="Paperclip" file="' . $file . '" /> Text</p>');

		return $this->output($mpdf);
	}

	/**
	 * A document holding an annotation Mpdf::Annotation() attaches a file to, with allowAnnotationFiles on
	 *
	 * @param string $file
	 * @param array $config
	 * @param \Mpdf\TestLogger|null $logger
	 * @param \Mpdf\Container\SimpleContainer|null $container
	 *
	 * @return string
	 */
	private function apiDocument($file, array $config = [], $logger = null, $container = null)
	{
		$mpdf = $this->configured($config, $logger, $container);
		$mpdf->WriteHTML('<p>Text</p>');
		$mpdf->Annotation('Note', 0, 0, 'Paperclip', '', '', 0, false, '', $file);

		return $this->output($mpdf);
	}

	/**
	 * An uncompressed document with allowAnnotationFiles on
	 *
	 * @param array $config
	 * @param \Mpdf\TestLogger|null $logger
	 * @param \Mpdf\Container\SimpleContainer|null $container
	 *
	 * @return \Mpdf\Mpdf
	 */
	private function configured(array $config, $logger, $container)
	{
		$mpdf = $this->mpdf($config + ['allowAnnotationFiles' => true], $container);
		if ($logger) {
			$mpdf->setLogger($logger);
		}

		return $mpdf;
	}

	/**
	 * A container whose HTTP client answers every request with the given body and status, and records what it
	 * was asked for
	 *
	 * @param string $body
	 * @param int $status
	 *
	 * @return \Mpdf\Container\SimpleContainer
	 */
	private function httpContainer($body, $status = 200)
	{
		$http = Mockery::mock(ClientInterface::class);
		$http->shouldReceive('sendRequest')->andReturnUsing(function (RequestInterface $request) use ($body, $status) {
			$this->requested[] = (string) $request->getUri();

			return new Response($status, [], $body);
		});

		return new SimpleContainer(['httpClient' => $http]);
	}

	/**
	 * The annotation carries the file, and its one embedded stream holds the given content
	 *
	 * @param string $pdf
	 * @param string $content
	 */
	private function assertEmbedded($pdf, $content)
	{
		$this->assertStringContainsString('/Subtype /FileAttachment', $this->annotations($pdf)[0]);
		$this->assertSame(1, preg_match_all('/\/Type \/EmbeddedFile.*?stream\n(.*?)\nendstream/s', $pdf, $streams), 'One file should be embedded');
		$this->assertSame($content, gzuncompress($streams[1][0]));
	}

	/**
	 * The annotation is a text note and nothing is embedded
	 *
	 * @param string $pdf
	 */
	private function assertWrittenAsNote($pdf)
	{
		$this->assertStringContainsString('/Subtype /Text', $this->annotations($pdf)[0]);
		$this->assertSame(0, substr_count($pdf, '/Type /EmbeddedFile'), 'No file should be embedded');
	}

}
