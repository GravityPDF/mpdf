# mPDF Font Package

## Introduction

OCR-A and OCR-B barcode fonts. OCR-A is the font used for the ISBNs on books; and OCR-B is used for the human-readable digits on UPC/EAN bar codes. This package is used with the [mPDF library](https://github.com/mpdf/mpdf).

**Supported Scripts**: 
* Latin

## Installation

```bash
composer require mpdf/font-ocr-family
```

## Usage

This package automatically registers the following fonts with mPDF:

- `ocra`
- `ocrb`

Example usage in mPDF:
```php
<?php

$mpdf = new \Mpdf\Mpdf();
$mpdf->WriteHTML('
<style>
    .ocra {
        font-family: ocra;
    }

    .ocrb {
        font-family: ocrb;
    }
</style>

<p class="ocra">Some text in ocra</p>
<p class="ocrb">Some text in ocrb</p>
');

$mpdf->OutputHttpDownload('example.pdf');
```

## Advanced Installation

To manually register the font package with mPDF, you can initialize the package's `Registration` class and pass it to the `FontRegistry` constructor:

```php
<?php

use Mpdf\Mpdf;
use Mpdf\Fonts\FontRegistry;
use Mpdf\Fonts\OcrFamily\Registration as OcrFamilyRegistration;

$registry = new FontRegistry([
    new OcrFamilyRegistration(),
]);

$mpdf = new Mpdf([
    'fontRegistry' => $registry
]);
```

## Character ranges

Beside each regular font, `fonts/` holds a `<font file>.ranges.php` file listing the characters the font has. When mPDF looks for a backup font to draw a character the current font lacks, it reads these files instead of opening each font, so only the font it draws in is loaded.

When you change a font, or build a package of your own, write the file for each regular font:

```bash
vendor/bin/mpdf-font-ranges fonts/<font file>
```

mPDF reads a `.ranges.php` file only from the directory of the font package that registers the font. Without one, it reads the font, as it would without ranges files.

## License

This package is licensed under the MIT License. Fonts are licensed separately by their authors, and can be found in the [`/fonts/LICENSE.txt`](./fonts/LICENSE.txt) file.