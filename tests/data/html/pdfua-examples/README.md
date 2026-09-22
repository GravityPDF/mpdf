# PDF/UA fixtures

HTML documents rendered with `PDFUA` set by `Mpdf\Ua\VeraPdfConformanceTest`, which checks the output with veraPDF, and by `Mpdf\Ua\ValidationTest`, which renders every `example*.html` here in auto mode.

The `exampleNN_*.html` files are the `$html` of the matching `exampleNN_*.php` in [mpdf/mpdf-examples](https://github.com/mpdf/mpdf-examples), changed only as far as a PDF/UA document needs:

- core fonts (`mode => 'c'`) are dropped, since every font must be embedded;
- the PHP around the HTML is dropped, and settings the example made in PHP (headers, columns, protection, PDF/A) are made by the test instead;
- images not under `tests/data/img/` are replaced with a small data URI.

Examples that need fonts not bundled here, core fonts, source PDFs for FPDI, or no HTML at all are left out.

The other files are written for these tests:

- `imagemap.html`: an image map, with the `<map>` after the `<img>` that uses it
- `legacy_forms_useactiveformsfalse.html`: form fields drawn as page content, not as widgets, when `useActiveForms` is off
- `svg-accessible.html`: SVG images whose `<title>` and `<desc>` become the figure's alternate text
