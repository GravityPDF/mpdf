# mPDF Font Package

## Introduction

An mPDF scripts bundle designed for middle-eastern languages. This package is used with the [mPDF library](https://github.com/mpdf/mpdf).

**Supported Scripts**: 
* Latin
* Arabic
* Hebrew
* Syriac

## Installation

```bash
composer require mpdf/font-middle-east-scripts-bundle
```

## Usage

This package automatically registers the following fonts with mPDF:

- `lateef`
- `xbriyaz`
- `estrangeloedessa`
- `taameydavidclm`

Example usage in mPDF:
```php
<?php

$mpdf = new \Mpdf\Mpdf();
$mpdf->WriteHTML('
<style>
    body {
        font-family: xbriyaz;
    }

    .taameydavidclm {
        font-family: taameydavidclm;
    }
</style>

<h1>Hello World in xbriyaz</h1>

<p class="taameydavidclm">Some text in taameydavidclm</p>
');

$mpdf->OutputHttpDownload('example.pdf');
```

## Advanced Installation

To manually register the font package with mPDF, you can initialize the package's `Registration` class and pass it to the `FontRegistry` constructor:

```php
<?php

use Mpdf\Mpdf;
use Mpdf\Fonts\FontRegistry;
use Mpdf\Fonts\ArabicScriptsBundle\Registration as ArabicScriptsBundleRegistration;

$registry = new FontRegistry([
    new ArabicScriptsBundleRegistration(),
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

This package is licensed under the MIT License. Fonts are licensed separately by their authors, and can be found in following license files:

- `lateef`: [LICENSE-Lateef.txt](./fonts/LICENSE-Lateef.txt)
- `xbriyaz`: [LICENSE-XB-Riyaz.txt](./fonts/LICENSE-XB-Riyaz.txt)
- `estrangeloedessa`: [LICENSE-Estrangelo-Edessa.txt](./fonts/LICENSE-Estrangelo-Edessa.txt)
- `taameydavidclm`: [LICENSE-TaameyDavidCLM.txt](./fonts/LICENSE-TaameyDavidCLM.txt)

The XB Riyaz fonts here are modified: their TrueType hinting has been removed by `utils/dehint_fonts.py` in the mPDF repository. Their outlines and metrics are unchanged.
