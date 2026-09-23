<?php

namespace Mpdf;

use Mpdf\Output\Destination;
use Mpdf\Pdf\Aes256Reader;
use Mpdf\Pdf\Protection\PasswordHash;
use setasign\Fpdi\PdfParser\PdfParser;
use setasign\Fpdi\PdfParser\StreamReader;
use setasign\Fpdi\PdfParser\Type\PdfString;
use setasign\Fpdi\PdfReader\PdfReader;

/**
 * A document SetProtection() encrypts, opened the way a reader opens it
 */
class EncryptionTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{

	use Aes256Reader;

	/**
	 * @var string[]
	 */
	private $files = [];

	/**
	 * Removes the documents written for OverWrite()
	 */
	protected function tear_down()
	{
		foreach ($this->files as $file) {
			if (is_file($file)) {
				unlink($file);
			}
		}

		parent::tear_down();
	}

	/**
	 * The encryption dictionary names AES-256 and the catalog the extension level that brings it to PDF 1.7
	 */
	public function testTheDocumentDeclaresAes256()
	{
		$pdf = $this->render($this->encrypted());

		$this->assertStringContainsString("/Filter /Standard\n/V 5\n/R 6\n/Length 256\n/CF <</StdCF <</AuthEvent /DocOpen /CFM /AESV3 /Length 32>>>>\n/StmF /StdCF\n/StrF /StdCF", $pdf);
		$this->assertStringContainsString('/Version /1.7', $pdf);
		$this->assertStringContainsString('/Extensions <</ADBE <</BaseVersion /1.7 /ExtensionLevel 8>>>>', $pdf);
	}

	/**
	 * Each password opens the document, and the other password does not stand in for either
	 */
	public function testEachPasswordOpensTheDocument()
	{
		$entries = $this->entries($this->render($this->encrypted()));

		$user = $this->fileKey($entries, "\xC3\xBCser", false);

		$this->assertSame(32, strlen($user));
		$this->assertSame($user, $this->fileKey($entries, 'owner', true));
		$this->assertFalse($this->fileKey($entries, 'owner', false));
		$this->assertFalse($this->fileKey($entries, "\xC3\xBCser", true));
	}

	/**
	 * @return array[] The passwords given to SetProtection(), and the same passwords as they might be typed into a reader
	 */
	public function unicodePasswordProvider()
	{
		return [
			'accented Latin' => ["p\xC3\xA4ssw\xC3\xB6rd", "p\xC3\xA4ssw\xC3\xB6rd"],
			'Cyrillic' => ["\xD0\xBF\xD0\xB0\xD1\x80\xD0\xBE\xD0\xBB\xD1\x8C", "\xD0\xBF\xD0\xB0\xD1\x80\xD0\xBE\xD0\xBB\xD1\x8C"],
			'Chinese and Japanese' => ["\xE5\xAF\x86\xE7\xA0\x81\xE3\x83\x86\xE3\x82\xB9\xE3\x83\x88", "\xE5\xAF\x86\xE7\xA0\x81\xE3\x83\x86\xE3\x82\xB9\xE3\x83\x88"],
			'Arabic' => ["\xD9\x83\xD9\x84\xD9\x85\xD8\xA9", "\xD9\x83\xD9\x84\xD9\x85\xD8\xA9"],
			'emoji outside the BMP' => ["\xF0\x9F\x94\x92\xF0\x9F\x94\x91", "\xF0\x9F\x94\x92\xF0\x9F\x94\x91"],
			'decomposed, typed composed' => ["cafe\xCC\x81", "caf\xC3\xA9"],
			'no-break space, typed as a space' => ["test\xC2\xA0me", 'test me'],
			'over 127 bytes, cut inside a character' => [str_repeat("\xE5\xAF\x86", 43), str_repeat("\xE5\xAF\x86", 43)],
		];
	}

	/**
	 * A Unicode user and owner password each open the document when a reader prepares what is typed with SASLprep
	 *
	 * @param string $password
	 * @param string $typed
	 *
	 * @dataProvider unicodePasswordProvider
	 */
	public function testAUnicodePasswordOpensTheDocument($password, $typed)
	{
		$mpdf = new Mpdf(['mode' => 'c']);
		$mpdf->SetProtection(['print'], $password, "\xC3\x98 " . $password);
		$mpdf->WriteHTML('<p>Unicode password</p>');
		$entries = $this->entries($this->render($mpdf));

		$prepare = new PasswordHash();
		$user = $this->fileKey($entries, $prepare->prepare($typed), false);

		$this->assertSame(32, strlen($user));
		$this->assertSame($user, $this->fileKey($entries, $prepare->prepare("\xC3\x98 " . $typed), true));
		$this->assertFalse($this->fileKey($entries, $prepare->prepare('x' . $typed), false));
	}

	/**
	 * Every stream is as long as its /Length says and decrypts, and the title decrypts from the Info
	 */
	public function testEveryStreamAndStringDecrypts()
	{
		$pdf = $this->render($this->encrypted());
		$key = $this->fileKey($this->entries($pdf), "\xC3\xBCser", false);

		$this->assertStringContainsString('BT', $this->decryptStreams($pdf, $key));

		$writer = new \Mpdf\Writer\BaseWriter(new Mpdf(), new \Mpdf\Pdf\Protection());
		$this->assertSame($writer->utf8ToUtf16BigEndian("S\xC3\xABcret"), $this->decrypt($this->entry($pdf, 'Title'), $key));
	}

	/**
	 * A reader decrypts every string outside the encryption dictionary and the trailer, so every one has to be
	 * encrypted: those of forms, links, annotations, bookmarks, JavaScript, fonts and the catalog included
	 */
	public function testEveryStringOutsideTheEncryptionDictionaryDecrypts()
	{
		$mpdf = new Mpdf(['mode' => 'en-GB']);
		$mpdf->compress = false;
		$mpdf->useActiveForms = true;
		$mpdf->SetTitle('Title (x)');
		$mpdf->SetProtection(['print'], "\xC3\xBCser", 'owner');
		$mpdf->SetJS('app.alert("x");');
		$mpdf->WriteHTML('<h1>Heading</h1><bookmark content="Mark" />
			<form action="https://example.com/submit?a=(b)" method="post">
				<input type="text" name="a" value="val(ue)" />
				<input type="checkbox" name="c" value="on" checked="checked" />
				<input type="radio" name="r" value="1" checked="checked" /><input type="radio" name="r" value="2" />
				<select name="s"><option value="x">X</option><option value="y" selected="selected">Y</option></select>
				<textarea name="t">text</textarea>
				<input type="submit" name="go" value="Go" />
			</form>
			<p><a href="https://example.com/(x)">link</a><annotation content="note" /></p>');

		$pdf = $this->render($mpdf);
		$key = $this->fileKey($this->entries($pdf), "\xC3\xBCser", false);

		$strings = $this->strings($pdf);
		$this->assertGreaterThan(40, count($strings));

		foreach ($strings as $string) {
			$this->assertNotFalse($this->decrypt($string[0], $key), 'The string after ' . json_encode($string[1]));
		}

		$this->assertStringContainsString('/Lang (', $pdf);
		$this->assertStringContainsString('/DA (', $pdf);
		$this->assertStringContainsString('/SubmitForm /F (', $pdf);
	}

	/**
	 * Another instance's key does not open the pages, so the document is refused before any is changed
	 */
	public function testADocumentAnotherInstanceEncryptedIsNotOverwritten()
	{
		$file = tempnam(sys_get_temp_dir(), 'Encryption');
		$this->files[] = $file;
		file_put_contents($file, $this->render($this->encrypted('c')));

		$mpdf = new Mpdf(['mode' => 'c']);
		$mpdf->SetProtection(['print'], "\xC3\xBCser", 'owner');

		$this->expectException(MpdfException::class);
		$this->expectExceptionMessage('it was not encrypted by this instance');

		$mpdf->OverWrite($file, ['MAIN HEADING'], ['replacement'], Destination::STRING_RETURN);
	}

	/**
	 * The instance that encrypted a document can overwrite its text, decrypting and encrypting its pages again
	 */
	public function testAnEncryptedDocumentCanBeOverwritten()
	{
		$mpdf = $this->encrypted('c');
		$file = tempnam(sys_get_temp_dir(), 'Encryption');
		$this->files[] = $file;
		file_put_contents($file, $mpdf->Output('', Destination::STRING_RETURN));

		$pdf = $mpdf->OverWrite($file, ['MAIN HEADING'], ['replacement'], Destination::STRING_RETURN);
		$text = $this->decryptStreams($pdf, $this->fileKey($this->entries($pdf), "\xC3\xBCser", false));

		$this->assertStringContainsString('replacement', $text);
		$this->assertStringNotContainsString('MAIN HEADING', $text);
	}

	/**
	 * A page FPDI imports into an encrypted document is written encrypted with the rest: every stream it brings is as
	 * long as its /Length says, and the page content decrypts to what the source holds
	 */
	public function testAnImportedPageIsEncrypted()
	{
		$source = __DIR__ . '/../data/pdfs/Letterhead.pdf';
		$reader = new PdfReader(new PdfParser(StreamReader::createByFile($source)));

		$mpdf = new Mpdf();
		$mpdf->compress = false;
		$mpdf->SetProtection(['print'], "\xC3\xBCser", 'owner');
		$mpdf->setSourceFile($source);
		$mpdf->AddPage();
		$mpdf->useTemplate($mpdf->importPage(1));

		$pdf = $this->render($mpdf);
		$text = $this->decryptStreams($pdf, $this->fileKey($this->entries($pdf), "\xC3\xBCser", false));

		$this->assertStringContainsString($reader->getPage(1)->getContentStream(), $text);
		$this->assertStringNotContainsString('/T1_0 1 Tf', $pdf);
	}

	/**
	 * PDF/A does not allow encryption
	 */
	public function testPdfaIsRefused()
	{
		$mpdf = $this->encrypted();
		$mpdf->PDFA = true;

		$this->expectException(MpdfException::class);
		$this->expectExceptionMessage('does not permit encryption');

		$mpdf->Output('', Destination::STRING_RETURN);
	}

	/**
	 * A document with a title, a link, an image and a page of text, encrypted with a user and an owner password
	 *
	 * @param string $mode 'c' to use core fonts, which OverWrite() finds its text in as typed
	 *
	 * @return \Mpdf\Mpdf
	 */
	private function encrypted($mode = '')
	{
		$mpdf = new Mpdf(['mode' => $mode]);
		$mpdf->compress = false;
		$mpdf->SetTitle("S\xC3\xABcret");
		$mpdf->SetProtection(['print'], "\xC3\xBCser", 'owner');
		$mpdf->WriteHTML('<p>MAIN HEADING</p><p><a href="https://example.com">link</a></p><img src="' . __DIR__ . '/../data/img/greyscale-trns.png">');

		return $mpdf;
	}

	/**
	 * @param \Mpdf\Mpdf $mpdf
	 *
	 * @return string
	 */
	private function render(Mpdf $mpdf)
	{
		$pdf = $mpdf->Output('', Destination::STRING_RETURN);
		$mpdf->cleanup();

		return $pdf;
	}

	/**
	 * @param string $pdf
	 *
	 * @return string[] The /U, /O, /UE and /OE of its encryption dictionary
	 */
	private function entries($pdf)
	{
		$entries = [];
		foreach (['U', 'O', 'UE', 'OE'] as $name) {
			$entries[$name] = $this->entry($pdf, $name);
		}

		return $entries;
	}

	/**
	 * @param string $pdf
	 * @param string $name
	 *
	 * @return string The literal string of the first entry of that name
	 */
	private function entry($pdf, $name)
	{
		$this->assertSame(1, preg_match('/\/' . $name . ' \(((?:\\\\.|[^\\\\)])*)\)/s', $pdf, $match), $name);

		return PdfString::unescape($match[1]);
	}

	/**
	 * Every literal and hexadecimal string outside the streams, the encryption dictionary and the trailer
	 *
	 * @param string $pdf
	 *
	 * @return array[] The bytes of each string, and the 40 bytes before it
	 */
	private function strings($pdf)
	{
		$this->assertSame(1, preg_match('/\/Encrypt (\d+) 0 R/', $pdf, $encrypt));

		$body = preg_replace('/stream\n.*?\nendstream/s', '', $pdf);
		$body = preg_replace('/\n' . $encrypt[1] . ' 0 obj\n.*?endobj/s', '', $body);
		// Skips the header comments by position: an encrypted string may hold a line feed and a percent sign of its own
		$body = substr($body, 0, strrpos($body, 'trailer'));
		$body = substr($body, strpos($body, ' 0 obj'));

		preg_match_all('/\((?:\\\\.|[^\\\\()]|\((?:\\\\.|[^\\\\()])*\))*\)|<(?!<)[0-9A-Fa-f\s]*>/s', $body, $matches, PREG_OFFSET_CAPTURE);

		$strings = [];
		foreach ($matches[0] as $match) {
			$context = substr($body, max(0, $match[1] - 40), min(40, $match[1]));
			if ($match[0][0] === '(') {
				$strings[] = [PdfString::unescape(substr($match[0], 1, -1)), $context];
			} else {
				$hex = preg_replace('/\s+/', '', substr($match[0], 1, -1));
				$strings[] = [hex2bin(strlen($hex) % 2 ? $hex . '0' : $hex), $context];
			}
		}

		return $strings;
	}

	/**
	 * Asserts every stream is as long as its /Length says and decrypts
	 *
	 * @param string $pdf
	 * @param string $key
	 *
	 * @return string Every stream decrypted, and inflated where it says it is compressed, one after the other
	 */
	private function decryptStreams($pdf, $key)
	{
		preg_match_all('/ 0 obj\n<<((?:(?!endobj).)*?)stream\n(.*?)\nendstream/s', $pdf, $streams, PREG_SET_ORDER);
		$this->assertNotEmpty($streams);

		$text = '';
		foreach ($streams as $stream) {
			$this->assertSame(1, preg_match('/\/Length (\d+)/', $stream[1], $length));
			$this->assertSame((int) $length[1], strlen($stream[2]));

			$plain = $this->decrypt($stream[2], $key);
			$this->assertNotFalse($plain);

			$text .= strpos($stream[1], '/FlateDecode') !== false ? gzuncompress($plain) : $plain;
		}

		return $text;
	}

}
