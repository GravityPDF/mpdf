# mPDF Font Package

## Introduction

A collection of fonts for the Myanmar language. This package is used with the [mPDF library](https://github.com/mpdf/mpdf).

**Supported Scripts**: 
* Latin
* Myanmar
* Kayah Li
* Tai Le

## Installation

```bash
composer require mpdf/font-myanmar-bundle
```

## Usage

This package automatically registers the following fonts with mPDF:

- `ayar`
- `padaukbook`
- `tharlon`
- `zawgyi-one`

Example usage in mPDF:
```php
<?php

$mpdf = new \Mpdf\Mpdf();
$mpdf->WriteHTML('
<style>
    body {
        font-family: padaukbook;
    }

    .ayar {
        font-family: ayar;
    }
</style>

<h1>Hello World in padaukbook</h1>

<p class="ayar">Some text in ayar</p>
');

$mpdf->OutputHttpDownload('example.pdf');
```

## Advanced Installation

To manually register the font package with mPDF, you can initialize the package's `Registration` class and pass it to the `FontRegistry` constructor:

```php
<?php

use Mpdf\Mpdf;
use Mpdf\Fonts\FontRegistry;
use Mpdf\Fonts\MyanmarBundle\Registration as MyanmarBundleRegistration;

$registry = new FontRegistry([
    new MyanmarBundleRegistration(),
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

- `ayar`: [LICENSE-Ayar.txt](./fonts/LICENSE-Ayar.txt)
- `padaukbook`: [LICENSE-Padauk.txt](./fonts/LICENSE-Padauk.txt)
- `tharlon`: [LICENSE-Tharlon.txt](./fonts/LICENSE-Tharlon.txt)
- `zawgyi-one`: [LICENSE-ZawgyiOne.txt](./fonts/LICENSE-ZawgyiOne.txt)  