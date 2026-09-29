GravityPDF mPDF changelog
===========================

This file lists what the `gravitypdf` branch (`8.x-dev`) changes compared with upstream mPDF. It covers everything since the branch left upstream `development` at `389e19e7`. [CHANGELOG.md](CHANGELOG.md) is kept identical to upstream's and only lists upstream's releases.

How to read the references:

* `#N` is a GravityPDF/mpdf pull request, or the issue it fixed.
* `mpdf/mpdf#N` is the upstream issue or pull request a change mirrors or fixes.

Unreleased
===========================

Breaking Changes from Upstream
------------------------------

Read this section before upgrading from upstream mPDF. Each entry says what changed and what to do about it.

* **Fonts are no longer shipped with mPDF.** The `ttfonts/` directory is gone. Each font family is now a Composer package that registers itself through `Mpdf\Fonts\FontRegistry` and `FontRegistrationInterface`. [mpdf/mpdf#2161] [#2] [#4] [#7] [#36] [#45]
  * The packages, such as `mpdf/font-bundle-lite`, `mpdf/font-bundle-all` and `mpdf/font-dejavu-family`, are not published yet. They live in this repository's `packages/` directory.
  * To install them, add `packages/` as a Composer `path` repository in your project.
* **Encryption uses AES-256 instead of RC4.** `SetProtection()` writes the V5/R6 security handler from ISO 32000-2. [#329]
  * You need `ext-openssl`.
  * Passwords are read as UTF-8 and prepared with SASLprep. A password that is not UTF-8, or that holds a prohibited character, throws `MpdfException`.
  * Every string in the document is now encrypted. Before, some were left in the clear, including form `/DA` strings, `/Lang` and attachment names.
* **`PDFAversion` defaults to `2-B` instead of `1-B`.** Set `'PDFAversion' => '1-B'` to keep PDF/A-1b. [#330]
* **`PDFX` is an on/off switch.** The new `PDFXversion` option picks the version: `4` (the default) or `1a`. Set `'PDFXversion' => '1a'` to keep PDF/X-1a. [#324]
* **`percentSubset` defaults to `100` instead of `30`.** Upstream ignores both `percentSubset` and `maxTTFFilesize`. A font is now subset when the document draws at most that share of its glyphs, or when the font is larger than `maxTTFFilesize`. With the defaults, output does not change. [#148] [#159]
* **`<annotation file="...">` is off by default.** Set `allowHtmlAnnotationFiles` to `true` to read the `file` attribute. [#375]
* **A `<select>` without `size` is written as a combo box**, as browsers draw it. [#419]
* **A `<select multiple>` without `size` is four rows tall instead of one**, as in browsers. Content after it on the page moves down. Add `size="1"` to keep the old height. [#437]
* **Dependencies changed.** `ext-json` is now required and `symfony/polyfill-intl-normalizer` is a new dependency. `myclabs/deep-copy` is no longer used. [#13] [#329]

New features
------------

### PDF standards

* **PDF/X-4 (ISO 15930-7).** Unlike PDF/X-1a, it keeps transparency, layers, `rgba()` colours and PNG alpha. [#324]
  * With no `ICCProfile`, the bundled `SWOP2006_Coated3v2.icc` is embedded as the output intent.
  * An imported page's device colours get a default colour space where PDF/X requires one.
  * Black converts to the black ink alone, not to all four inks.
* **PDF/A-2b, PDF/A-2u and PDF/A-3u.** PDF/A-2 and PDF/A-3 documents keep opacity, alpha, soft masks, watermarks, transparency groups, layers and hidden content. CI checks every PDF/A level mPDF writes with veraPDF. [#330] [#357] [#359] [#386]

### E-invoices

* **Factur-X and ZUGFeRD.** `SetEmbeddedInvoice(new Mpdf\Invoice\FacturX($xml))` embeds the invoice XML in a PDF/A-3 document with the right file name and relationship, and writes the `fx` XMP schema. The conformance level is read from the XML's guideline ID, or you can pass it. [#368] [#512]
  * mPDF does not write or validate the XML. `composer.json` suggests `horstoeko/zugferd` and `easybill/e-invoicing` for writing it.
* **Printing invoice XML as HTML.** `Mpdf\Invoice\HtmlInvoiceWriter` turns Cross Industry Invoice or UBL 2.1 XML into HTML for `WriteHTML()`. [#514] [#515]
  * `CiiInvoiceReader`, `UblInvoiceReader` and `InvoiceReader` read the XML into a plain array if you want your own layout.
  * `Formatter` writes amounts, dates and addresses the way a country does. It has 21 country presets.

### PDF structure

* **Object streams.** Set `useObjectStreams` to pack objects into compressed object streams with a cross-reference stream, for smaller files. It is off by default. It never applies to encrypted, PDF/A-1 or PDF/X-1a documents. [#337]
* **Importing PDF 1.5 files.** Imported PDFs can use cross-reference streams, object streams and hybrid cross-references. The new `Mpdf\Import\PdfParser` reads them. [#337]
* **Imported links keep their look.** Links on an imported page keep their border, colour, flags and `QuadPoints`. [mpdf/mpdf#2154] [#12]
* **Opening on the attachments pane.** `SetDisplayPreferences('UseAttachments')` opens the reader with its attachments pane showing. [mpdf/mpdf#2142] [#17]
* **Safer annotation files.** Annotation files are loaded through the asset fetcher, like images and stylesheets. [#375]
  * Only file types listed in `annotationFileAllowList` are embedded, and only up to `annotationFileMaxSize`.
  * A file that cannot be embedded becomes a text note and a logged warning. With `showAnnotationErrors`, it throws `MpdfAnnotationException` instead.
* **A fixed creation date.** The `creationDate` config option sets the document's creation date, for reproducible output. [#72]

### Fonts, emoji and text

* **Colour fonts are drawn in colour.** Supported formats:
  * CBDT [#291]
  * sbix [#292]
  * COLR version 0 [#293]
  * COLR version 1, including composites and sweep gradients [#299] [#306] [#308]
  * OpenType SVG [#300]
* **Emoji.** Emoji are found by the rules of UTS #51 (ZWJ sequences, flags, keycaps, skin tones and tag sequences) and drawn in a font that has them. [#290]
* **Fonts without a GDEF table** can be used with `useOTL`. Most emoji fonts are like this. [#290]
* **More OpenType lookups.** Four contextual lookup formats that mPDF used to refuse are now applied, including coverage-based GSUB context substitution. [#79] [#82]
* **Unicode 18.0.0.** The character, script, joining and emoji tables are generated from Unicode 18.0.0. Upstream's tables date from Unicode 6.1 and 6.2. The OpenType language system table is generated from HarfBuzz's. [#103] [#227] [#261] [#265] [#273]

### Images

* **Rounded images.** Images take CSS `border-radius`, including per-corner, elliptical and percentage radii. The picture, its background and its border all follow the curve. [#70]
* **Exif orientation.** Set `useImageExifOrientation` to turn JPEGs the way their Exif `Orientation` tag says, as browsers do. It is off by default. `imageJpegQuality` sets the quality of the re-encoded image. [#35] [#51]

### Forms

* **Appearance streams.** Every active form widget has its own appearance stream, so viewers draw the field as mPDF laid it out. [#387]
* **CSS styling.** Form fields take their CSS colour, background and border. Static fields draw them, and active fields use the border's width and style. [#442] [#469]
* **Static list boxes.** A static list box is drawn as rows of options, with the selected rows highlighted. [#446]

### Paged media

* **Side margins on `:first`, `:left` and `:right` pages.** The `:first`, `:left` and `:right` pages of an `@page` rule, named or not, can set `margin-left` and `margin-right`. Text flowing from page to page is set in the page area of each page, and columns are laid out across it. A side margin on a pseudo page belongs to that side of the physical page, so it is not mirrored. [#476] [#510]
  * Text beside a float, and the blocks around it, keep to the page area of each page the float runs over. [#549]
  * A block with a set width keeps its place in each page area: a centred block stays centred, and a block pushed right by `margin-left: auto` or set right to left stays against the right margin. [#550] [#551] [#553]

Performance
-----------

* **Long documents of floats.** Floats the layout has already passed are dropped, so each new row no longer rescans every earlier float. A 250-row grid of floats renders about four times faster. [#9]
* **Chaining context fonts.** A font with thousands of chaining context subtables shapes in a fraction of a second. Before, it never finished. [#98]
* **OpenType subtables** are decoded once per document instead of on every glyph they are offered, including GPOS context positioning and mark-to-base. [#314] [#323]
* **Ligatures and marks.** Ligature and mark records are renumbered by walking the records instead of the whole run. [#313]
* **Line measuring.** Each character of a flowing block is measured once instead of being decoded again for its width. Each chunk of a line is measured once, and `count()` calls are taken out of the layout loops. [#312] [#345]
* **Font substitution.** A text token is split into all of its font substitution runs in one pass instead of one run per pass. [#336]
* **CJK output.** The subset fonts of a CJK document are written without rereading the font for each one. [#311]
* **Image metadata.** JPEG and PNG metadata is read from the file's structure instead of by scanning the whole file, and PNG chunks are read from a chunk index. [#47] [#48]
* **GD memory.** GD asks for less memory when it re-encodes an image. [#49]

Bugfixes
--------

### Layout and CSS

* **`page-break-inside: avoid`** now moves the whole block to the next page when it does not fit. Its text, list numbers, floats, links, bookmarks, index and contents entries, form fields and annotations each appear once, on the page the block ends up on. A block that fits is laid out only once. [#38] [#43] [#60] [#63]
* A table with no background left the next table painted twice. [#43]
* `@page { size: A4 }`, or a `size` of two lengths with no `margin`, gave 63 pages with one character on each. A page-size name such as `A4`, `letter` or `A5 landscape` now sets the sheet, as a browser sets the paper, and the space around a page box given as two lengths is no longer counted twice. [mpdf/mpdf#1220] [#552] [#562]
* `@page { size: landscape }`, or a page box wider than it is tall, left the first page portrait, and `size: portrait` left a landscape document landscape. The first page now turns. A page box and percentage margins are measured on the turned sheet, so `size: 250mm 150mm` on A4 is no longer cut to 210 mm wide. [#552] [#562]
* `background-size: cover` scaled by the wrong ratio when the image came out shorter than the area. [mpdf/mpdf#833] [#22]
* Only double-quoted attributes were read. Single-quoted, unquoted and bare attributes are now read as well. [mpdf/mpdf#2030] [#24]
* A shadow colour written without spaces, such as `rgba(255,0,0,0.5)`, fell back to grey. Whitespace in shadows is now parsed as CSS writes it too. [#25]
* A border that draws nothing was described with fewer keys than one that draws, and the border code read the missing keys anyway. [mpdf/mpdf#1892] [#29]
* A `<tr>` border was read for sides the row did not set. [mpdf/mpdf#1893] [#30]
* A `<li>` with no list around it raised undefined-key warnings. It now gets the styles a list would have set. [mpdf/mpdf#1890] [#31]
* A font size of zero divided by zero in the letter- and word-spacing calculations. [mpdf/mpdf#1888] [#32]
* An empty `<textarea>` lost the placeholder space it needs to be drawn. [mpdf/mpdf#1735] [#33]
* A fixed-position block that shrinks to fit was drawn in the font the previous fixed-position block left behind. [mpdf/mpdf#2174] [#11]
* Descendant rules such as `.box p` did not reach the content of an absolutely or fixed positioned `.box`. [#474] [#493]
* Descendant rules naming a table, row or cell, such as `td img` or `td span`, matched nothing inside the cell. [#223] [#507]
* A descendant rule with a part mPDF cannot parse was cut short at that part and applied to the element before it. Such rules are now dropped. [#519]
* An inline `style` with `!important` drew no border for a `border` shorthand, dropped the bottom padding of a two-value `padding`, drew text at 0pt for a `font-size`, and ignored an image's `height` with a warning. The flag is now removed, as it is from a stylesheet. [mpdf/mpdf#1010] [mpdf/mpdf#1707] [#523] [#561]
* Text styled with the `font` shorthand or `<font face>` came out as empty boxes when the family was not installed or had a space in its name, such as `font: 12pt Roboto` or `font: 16px "DejaVu Sans Mono"`. It was drawn in the first registered font, which is the emoji font in a full install. The family is now read as `font-family` reads it: a name is kept whole, the list is tried in order, and when mPDF knows none of it the text keeps the family it inherits. `font-family` itself also reads an unquoted name with spaces whole. [#552] [#560]
* A descendant rule ending in `:lang()` or `[lang]`, such as `div :lang(fr)`, `div p:lang(fr)` or `table [lang=fr]`, never applied. It now applies in block content and in tables, and a regional language such as `fr-CA` falls back to the rule for `fr`, as the simple lang rules do. [#525] [#559]
* A rule such as `td:nth-child(2):not(.x)` or `td:nth-child(2 of .x)` was applied to the second cell of every row, as if it were `td:nth-child(2)`. A rule with anything after an nth-child argument, or with an argument that is not a formula, is now dropped. [mpdf/mpdf#83] [#522] [#564]
* `rgb()` and `hsl()` written with spaces, such as `color: rgb(255 0 0)`, threw a `TypeError` out of `WriteHTML()`, or drew the wrong colour with warnings. They are now read, with a slash before the alpha (`rgb(255 0 0 / 50%)`) and with the hue in `deg`, `grad`, `rad` or `turn`. [#552] [#563]
* A percentage alpha, as in `rgba(255, 0, 0, 50%)`, gave an opacity above 1, which viewers draw opaque.
* A fourth argument to `rgb()` or `hsl()` is read as the alpha instead of being dropped.
* `border-color: rgb(255, 0, 0)` or `cmyk(0, 100, 0, 0)`, with spaces after the commas, drew black.
* A declaration mPDF cannot read replaced the value before it. A `calc()`, `min()`, `max()`, `clamp()` or `var()` length became 0, so `margin: calc(…)` removed the default margins, and a colour mPDF does not know, or one written with `var()`, drew the text black. Such a declaration is now dropped, as a browser drops it, so the earlier declaration, the default or the inherited value applies. [#552] [#565]
* `vw`, `vh`, `vmin`, `vmax`, `Q` and `ch` were read as pixels, `+5mm` and `1e+1mm` as 0, and `1,5mm` as 1mm. The units are now resolved against the page and the font size, the numbers are read, and `1,5mm` is dropped. [#552] [#565]
* A percentage `width`, `min-width` or `max-width` on an image in a table cell was resolved against the block around the table, not the cell. In a 60mm cell, `max-width: 20%` came out as 36mm. [#223] [#505]
* An `@page :left` or `@page :right` rule was ignored unless the style sheet also had a plain `@page` rule. With one, the margins of a `:right` rule were applied to left pages too. [#555] [#556]
* When a later `WriteHTML()` call turned the document right to left, the text on the page already started was set between the swapped side margins. That page now keeps its margins, and the swap starts with the next page. [#554] [#558]
* Balancing columns raised a warning and drew a block's closing background past the last column. [#475] [#488]
* Inside `<columns>`, a block with an `rgba()` or `cmyka()` background was painted fully opaque. [#482] [#498]
* In a UTF-8 document, CSS naming `chelvetica`, `ctimes` or `ccourier` did not draw in the core font. [#467] [#468]
* The U+200B the line breaker inserts was drawn. [#28]
* A zero-width space was drawn, and gave no line break, in core fonts and in text written outside `WriteHTML()`. [#463] [#503]
* When a hyphenation hyphen moved to the next line, its record stayed on the line before. In a bidi paragraph that drew a hyphen in the wrong place and dropped the one that moved. [mpdf/mpdf#1831] [#145]
* A hyphen inserted at a line break had no bidi direction. It now takes the direction of the word it breaks. [#116] [#137]
* `$extgstates` had no default, so `count()` on it threw a `TypeError` on PHP 8. [mpdf/mpdf#2135] [#20]
* `linear-gradient(to bottom, …)` and `to top` were drawn upside down, and so was the vertical half of `to bottom right` and the other corners. Angles ran counter-clockwise from pointing right, so `90deg` drew bottom to top. A gradient now runs as CSS Images gives: `0deg` points up and angles turn clockwise, `turn` is read, and a corner keyword leaves the other two corners halfway along. `-moz-`, `-webkit-` and `-o-` gradients keep their legacy angles, and their side keyword, such as `left`, names the side the gradient starts from. [#577]

### Images and SVG

* A greyscale PNG read more `tRNS` samples than it has. [mpdf/mpdf#1927] [#27]
* `transform: scaleX()` and `scaleY()` flattened the image on the other axis. [mpdf/mpdf#1089] [#37]
* An SVG image resolved its variable name instead of the path it points at. [mpdf/mpdf#1384] [#40]
* An embedded SVG lost its `class`, so class rules no longer styled it. [mpdf/mpdf#1405] [#41]
* An inline SVG the XML parser could not read, for example one with an unquoted attribute, divided by zero. It is now reported. [#44]
* A GD decode could run out of memory on an image whose header claims a huge size. The claimed size is now checked against `memory_limit` first. [#50]
* A second transformed image with the same border drew that border in the PDF default width and colour. [#75]
* An image with both a `rotate` attribute and a CSS `transform` turned about the wrong centre and sat off its own border. [#70]
* An SVG `rgba()` or `cmyka()` fill, stroke or `stop-color` lost its alpha. [#395] [#420]
* SVG text lost the alpha of its `rgba()` or `cmyka()` fill or stroke. [#427] [#486]
* SVG text after a nested `<tspan>` was not drawn. [#288]
* Arabic in an SVG was not given an Arabic font. [#281]
* An RGB image converted to CMYK truncated its inks, so full ink fell short of 255. [#349] [#354]
* An image's `/SMask` pointed at whichever object came before it, which is only its mask if nothing was added in between. An image and its soft mask are now registered together. [#373]

### Forms

* A `<select>` with a bare `disabled` attribute was not disabled. [#429]
* A button with no `name` raised warnings under `useActiveForms`. Unnamed buttons are now named `Submit_<n>`, `Reset_<n>` or `Button_<n>`. [#438]
* Text wider than its field was cut off. It is now drawn in a smaller font, both on the page and in an active field's appearance. [#433] [#441]
* A shaped value too long for its field is cut to the longest start that fits, and only between whole clusters. [#460] [#492]
* In an active widget's appearance, Arabic did not join, right-to-left values were in logical order and Thai and Indic marks were not positioned. The text is now shaped and ordered as page text is. [#408] [#448]
* A list box's last row ran past its border, and a one-row box could show the top of the next option. [#426] [#444]
* A static select's drop-down arrow is drawn as a triangle, not a ZapfDingbats glyph. [#454] [#490]
* An active button with `value=""` showed its field name as its caption. It is now blank, as it is in a browser. [#459] [#489]
* An active field in a fixed-position block that shrinks to fit kept its unscaled appearance, so it clipped its value and changed size when clicked. [#457] [#487]
* Push buttons that share a name are written as one field, with each widget as a kid. [#447]
* Each radio widget wrote `/AP` twice. Radio groups and same-named push buttons are now one field that their widgets inherit from. [#450] [#500]

### Links, annotations and attachments

* A link was treated as external only if its address had a dot, so a link to `http://localhost/` was not. Links are now classed by their scheme. [#8]
* Internal links had no usable `/Border` entry, so poppler-based viewers such as Evince and Okular drew a box around each one. [#68] [#69]
* Annotations in an HTML header or footer were lost. They are now written on each page the header or footer is drawn on. [#410] [#418]
* With `forcePortraitHeaders`, a link in a rotated header or footer sat about 110mm away from its text. [#428]
* An annotation's file given as a Windows path was named after the whole path instead of the file name.
* A file attachment that is not allowed reserved more object numbers than it wrote. [#343] [#356]
* `SetAssociatedFiles()` raised warnings when the optional `description`, `mime` or `AFRelationship` keys were left out. [#328]
* In an encrypted document, an imported page used on several pages had its link strings encrypted again on each use, so they read as garbage from the second page on. [#335]

### Index and table of contents

* The table of contents look-ahead deep-cloned the whole document. It now saves and restores a state snapshot instead. [mpdf/mpdf#2207] [#13]
* With a table of contents at the front, index page numbers were counted from before the table moved into place. [#56] [#64]
* An index entry with a run of three or more pages followed by a later page printed only the later page: pages 1, 2, 3 and 5 came out as `5`. [#66] [#67]
* Index entries whose keys collate the same, such as `Term` and `term`, came out in a different order on PHP 7 and PHP 8. They now keep the order they were made in. [#74]

### PDF/A and PDF/X

* A PDF/A-1b document could carry fonts, SVG opacity, shadow blur and annotation colours that PDF/A-1 forbids. [#346] [#361]
* Under PDF/A-1b, SVG gradient stops are painted opaque, and only the visibility groups the catalog lists are written. [#346] [#351] [#386]
* An annotation lost its subject under PDF/A-2 and PDF/A-3. [#350] [#357]
* A PDF/A-3 annotation's file was not written as an associated file, so veraPDF rejected it. [#347] [#358]
* `PDFAauto` and `PDFXauto` now leave out screen-only content, and hidden content that the standard cannot hold. [#397] [#416]
* A PDF/A document with `restrictColorSpace => 3` and no `ICCProfile` embedded the sRGB profile with `/N 4` and wrote CMYK colours, which fails PDF/A. The output intent profile is now checked against what PDF/A permits, `/N` is read from the profile and a suitable bundled profile is embedded under `PDFAauto`. [#449] [#499]

### PDF output

* The file header kept the PDF version set at the start, even when later features raised it. [#339] [#378]
* `/PageMode` could appear more than once in the catalog. [mpdf/mpdf#2142] [#17]
* Every CID font carried a ToUnicode CMap whose single range mapped the whole two-byte space. [#344] [#362]
* A subset's ToUnicode map put more than 100 entries in one block. Readers that enforce the limit lost text extraction, copy and search past that point. [#363] [#365]
* Writing an SVG background pattern read keys that only used fonts have from fonts the document loaded but never used. [#19]
* `OverWrite()` returned a document it could not read unchanged. It now refuses it. [mpdf/mpdf#747] [#42]
* `OverWrite()` assumed every content stream was compressed the way the current instance compresses. It now reads each stream's compression from the document. [#52] [#53]
* In a core-font document, `OverWrite()` did not find search text with characters outside ASCII, or with `(`, `)` or `\`. [#464] [#485]
* An EAN or UPC code longer than the symbol holds is refused. [mpdf/mpdf#1334] [#39]

### Font cache

* Two processes creating the cache directory at the same time could fail. [mpdf/mpdf#1775] [#224]
* A cache file another process removed raised a warning. [mpdf/mpdf#1368] [#236]
* A cache entry that expired between the check and the read was drawn with as `false`. It is now regenerated. [#231] [#246]
* The right-to-left form table was sorted differently on different PHP versions, so the same font gave different cache files. [#248] [#249]
* `AddFont()` read back stale metrics when it regenerated a font in the same process. [#290]

### Fonts and subsetting

* `MarkGlyphSets` was not read from GDEF 1.3 and was read from the wrong offset, and `UseMarkFilteringSet` did not follow the specification. [#1] [#14] [#26]
* A subset's cmap could write a `glyphIdArray` that `idRangeOffset` cannot reach, and counted its length in entries instead of bytes. [#151] [#158] [#165]
* A format 6 cmap subtable too long for its uint16 length field is left out. [#164] [#169]
* A subset's `maxp` profile was not recalculated when it kept a simple or empty glyph. [#173] [#197]
* A subset's `head` bounds and `hhea` extremes came from the whole font instead of the glyphs it keeps. [#179] [#184]
* The font file stayed open when building a subset or a repackaged font threw, or when `getCTG` or `getTTCFonts` returned early. [#168] [#180] [#182] [#183]
* The character count header could overflow the two bytes that hold it. [#127] [#134]
* Font names outside ASCII were garbled because they were read one byte per character instead of as UTF-16. [#238] [#241]
* The synthetic Myanmar font kept the Reserved name of the font it was made from. [#237] [#239]
* Since mPDF 7.1.8, the `Ascent`, `Descent` and `Leading` overrides in `fontdata` had no effect, and `TTCfontID` always picked the first font of a collection. [#321] [#322]
* Font substitution moved characters the document font has into the backup font. A space between two emoji was drawn in the emoji font and left an emoji-sized gap. [#303]
* A backup font that the substitution scan tried and rejected was still added to the document, so the same document gave different bytes with a cold and a warm font cache. [#315] [#318] [#342] [#381]
* A run of Plane 2 characters next to a newline was not moved into its SIP font, and text after the newline could be dropped. [#411]
* `haskernGPOS` was read for core fonts, which have no GPOS. [#141] [#149]
* Core-font text is held in Windows-1252 but was decoded as UTF-8 when given bidi data, which dropped some bytes such as a lone `0xA0`. [#383]
* debugfont mode read the wrong byte offsets in `hhea`, `post` and `maxp`. [mpdf/mpdf#2053] [#23]

### Text shaping: general

* A GPOS feature with no lookups, such as Myriad Pro's old `size` feature, raised a notice while the font cache was built. [mpdf/mpdf#1967] [#10]
* `MultiCell()` with a blank string raised warnings when slicing its OTL data. [mpdf/mpdf#2197] [#15]
* The first `font-variant-position: super` or `sub` in a document raised an undefined-key warning. [mpdf/mpdf#2136] [#16]
* `font-feature-settings` could not turn off the features a script's shaper applies itself, such as `blwf`, `pref`, `half` or `rphf`. It now can, at the stage the shaper applies them, as in HarfBuzz. [#384]
* The `kern` feature was appended to a run's empty feature set instead of being set on it. [#157]
* A character added to Unicode since 6.1 was read as unassigned. [#99] [#101] [#103]
* The `lang` attribute now selects the language system HarfBuzz would for variant, script, region and extended language subtags. That includes ZHH, ZHS, ZHT and ZHTM for Chinese. [#191] [#201] [#206] [#218] [#220] [#221]

### Text shaping: Arabic, Syriac and Sogdian

* Arabic letters that a font's `ccmp` splits into a base and dots, as Noto Sans Arabic does for beh, lost their joining. Joining is now worked out from the characters as written, before `locl` and `ccmp`. [#209] [#226]
* A letter did not join to the base before it when more than one mark sat between them. [#161]
* A mark took its form from the mark after it instead of from the letters around it. [#163]
* A joining form the font writes as several glyphs was drawn as one. [#115] [#125]
* A final form made of several glyphs was matched as a whole in the kashida rules instead of by its first glyph. [#126] [#135]
* An Arabic form's plain context rule was given a backtrack or lookahead. [#189] [#215]
* An Arabic form's context walk ran past the edge of the run. [#204] [#219]
* Letters either side of an Arabic presentation form, U+FC5E to U+FC62, were drawn joined. [#267] [#276]
* The Arabic End of Ayah was not read as Arabic everywhere a script is read. [#277]
* An Arabic presentation feature the document names was applied twice when the shaper had already asked for it. [#230] [#240]
* Sogdian Fe now joins to the letter before it, and Low Alef joins to nothing. [#94]
* The Syriac Alaph took the wrong form. It now takes the form the font gives after DALATH or RISH, is chosen from the bases either side of it, and resolves `fin2`, `fin3` and `med2` from the joining state table. [#132] [#143] [#155] [#228] [#244] [#247] [#254]
* Cursive joining and Transparent-Joining now come from Unicode. A letter next to a recently added character joins, and a mark that only the font knows does not break a join. [#251] [#259] [#261] [#273]

### Text shaping: Indic, Khmer, Myanmar and Sinhala

* The Khmer Coeng was read outside the cluster it belongs to. [#93] [#95]
* A glyph of an Indic cluster the shaper did not reorder had no mask, which raised a warning per glyph per feature. [#96]
* The Myanmar shaper raised undefined-key warnings on every call to insert dotted circles. [mpdf/mpdf#2046] [#21]
* A v2 Indic script tag fell back to other scripts' original tags instead of only its own. [#192] [#216]
* An Indic run's shaper is chosen from the script chosen for the run, as in HarfBuzz. [#199] [#222]
* Above-base forms are applied at the stage HarfBuzz applies them, in Indic and Khmer fonts. [#263] [#269] [#270] [#279]
* Below-base forms are applied before the base as well as after it, as in HarfBuzz. [#422]
* A Sinhala repaya was not formed. It is now formed and drawn after the consonant it sits on. [#287] [#289]
* A Sinhala font whose `pref`, `blwf` or `pstf` ligature is written Consonant + Halant did not have its al-lakuna recognised, as the other viramas are. [#435] [#501]

### Text shaping: OpenType lookups

* Every feature of a language system is offered, not only the last of those that start at the same lookup. [#245] [#252]
* A shared lookup's rules are classed under every feature of the language system that names it. [#271] [#284]
* A GSUB lookup that two selected features name is applied once where HarfBuzz merges it, under the alternate the first feature asked for. A Khmer font reaches its merged mask. [#243] [#257] [#258] [#264] [#272]
* Where GSUB features are applied one at a time, each pass applied every feature. It now applies one, and each lookup of that feature runs over the whole run. [#212] [#229] [#233] [#234] [#242]
* A nested lookup applied what its subtable holds for the first glyph it covers to any glyph. It now applies only to a glyph the subtable covers. [#102] [#106]
* A chained context positioning by coverage ran its nested lookups twice on the same glyph. [#97]
* A contextual subtable that matched but changed nothing let the next subtable of the lookup run too. [#109]
* A GSUB subtable over 32 KB did not resolve, because its record offsets were read as signed. [#100] [#107]
* A class-based chained rule kept the extra backtrack and lookahead positions of the rule read before it. [#170] [#195]
* A class 0 backtrack or lookahead position in a chained context rule never matched. [#385]
* An input position naming a class the ClassDef does not define matched any glyph. It now matches nothing, as in HarfBuzz. [#412]
* Class 0 matched glyphs of classes numbered above a gap in the ClassDef, and glyphs a ClassDef lists as class 0 were kept as a class of their own. [#414] [#417]
* GDEF class membership was tested by searching for the glyph's hex in the class. [#193] [#217]
* A lookup that sets `IgnoreMarks` and names a mark attachment class skipped only the marks outside the class. It now skips every mark. [#175] [#185]
* NULL offsets were read as real offsets in GSUB and GPOS headers, class-based context ClassDefs, Class Definition tables and the rule sets of context and chained context Format 1 subtables. [#253] [#262] [#326] [#331] [#379] [#382] [#413]
* A NULL anchor on a base or mark was read as an anchor. It now attaches nothing. [#260] [#268]
* A Single Substitution warned for a glyph that text cannot reach. [#307] [#309]
* The GSUB row was built differently depending on whether the font cache had one. [#285] [#286]
* Four lookup keys were read by what the reader assumed instead of what the font states. [#104] [#105] [#113] [#139]

### OtlDump

* `MarkGlyphSets` was not read from GDEF 1.3. [#34]
* A context rule's example string and records were reported twice. [#124] [#130] [#144]
* The rules of a nested lookup reached at a class 0 position were not reported. [#123] [#138]
* Substitutions the parser does not keep were reported, and glyphs that GDEF does not class as marks were drawn on a dotted circle. [#187] [#194]

Internal
--------

These changes do not change output.

* **OTL refactor.** `Otl`, `OtlDump` and `TTFontFile` were refactored. Each OTL table is read once, the parser is split from the subsetter, the table readers moved to `Mpdf\Fonts\Table`, and `OtlData`, `OtlTags` and the `debugOTL` trace moved out of `Otl`. [#84] [#117] [#160] [#167] [#171] [#172] [#174] [#176] [#177] [#178] [#186] [#188] [#190] [#198] [#235] [#250] [#275] [#282]
* **Unicode namespace.** The Unicode character data and the bidi algorithm moved under `Mpdf\Unicode`. [#294]
* **Dead code.** State left by the pre-OTL Arabic shaper was deleted [#88] [#128], and so was the unread `Form::$form_checkboxes` [#471].
* **Annotation objects.** Annotation object numbers are given once instead of being predicted in `PageWriter`. [#360]
* **ToUnicode.** The byte-subset ToUnicode map is built in one place. [#372]
* **UTF-8 decoding.** A valid UTF-8 string is decoded with mbstring. [#364]
* **Snapshot tests.** Snapshot tests compare bytes first, then PDF objects, then pixels. Snapshot documents carry nothing of the machine that made them. [#72]
* **Data scripts.** Composer scripts regenerate the Unicode, emoji, joining, language system, font cache, subset, shaping and grey profile data, and update snapshots.
* **CI.** [#5] [#310] [#317] [#376] [#377] [#615]
  * CI runs on the `gravitypdf` branch, caches Composer downloads and uses current action versions.
  * It checks PDF/A and PDF/X-4 output with veraPDF, and tests e-invoices against the XML packages `composer.json` suggests.
  * The test suite runs under a 512M memory limit.
  * Pull requests run 6 of the 24 PHP and OS test jobs unless labelled `full-ci`. Pushes to `gravitypdf`, a nightly run and a manual run get all 24. A newer push to a pull request cancels the run it replaces.
  * Every workflow, code coverage included, also runs nightly on `gravitypdf` and can be run by hand.
  * The coding standard and PHPStan run as one Lint workflow. The e-invoice and PDF/X-4 checks run on a pull request only when it changes the code they cover.
* **PHPStan.** Fixes for newer PHP versions and PHPStan 2.2.15. [#6] [#295] [#319] [#451]
* **Branch alias.** The Composer branch alias maps `dev-gravitypdf` to `8.x-dev`. [#3]

[mpdf/mpdf#83]: https://github.com/mpdf/mpdf/issues/83
[mpdf/mpdf#747]: https://github.com/mpdf/mpdf/issues/747
[mpdf/mpdf#833]: https://github.com/mpdf/mpdf/issues/833
[mpdf/mpdf#1010]: https://github.com/mpdf/mpdf/issues/1010
[mpdf/mpdf#1089]: https://github.com/mpdf/mpdf/issues/1089
[mpdf/mpdf#1220]: https://github.com/mpdf/mpdf/issues/1220
[mpdf/mpdf#1334]: https://github.com/mpdf/mpdf/issues/1334
[mpdf/mpdf#1368]: https://github.com/mpdf/mpdf/issues/1368
[mpdf/mpdf#1384]: https://github.com/mpdf/mpdf/issues/1384
[mpdf/mpdf#1405]: https://github.com/mpdf/mpdf/issues/1405
[mpdf/mpdf#1707]: https://github.com/mpdf/mpdf/issues/1707
[mpdf/mpdf#1735]: https://github.com/mpdf/mpdf/issues/1735
[mpdf/mpdf#1775]: https://github.com/mpdf/mpdf/issues/1775
[mpdf/mpdf#1831]: https://github.com/mpdf/mpdf/issues/1831
[mpdf/mpdf#1888]: https://github.com/mpdf/mpdf/issues/1888
[mpdf/mpdf#1890]: https://github.com/mpdf/mpdf/issues/1890
[mpdf/mpdf#1892]: https://github.com/mpdf/mpdf/issues/1892
[mpdf/mpdf#1893]: https://github.com/mpdf/mpdf/issues/1893
[mpdf/mpdf#1927]: https://github.com/mpdf/mpdf/issues/1927
[mpdf/mpdf#1967]: https://github.com/mpdf/mpdf/issues/1967
[mpdf/mpdf#2030]: https://github.com/mpdf/mpdf/issues/2030
[mpdf/mpdf#2046]: https://github.com/mpdf/mpdf/issues/2046
[mpdf/mpdf#2053]: https://github.com/mpdf/mpdf/issues/2053
[mpdf/mpdf#2135]: https://github.com/mpdf/mpdf/issues/2135
[mpdf/mpdf#2136]: https://github.com/mpdf/mpdf/issues/2136
[mpdf/mpdf#2142]: https://github.com/mpdf/mpdf/issues/2142
[mpdf/mpdf#2154]: https://github.com/mpdf/mpdf/issues/2154
[mpdf/mpdf#2161]: https://github.com/mpdf/mpdf/issues/2161
[mpdf/mpdf#2174]: https://github.com/mpdf/mpdf/issues/2174
[mpdf/mpdf#2197]: https://github.com/mpdf/mpdf/issues/2197
[mpdf/mpdf#2207]: https://github.com/mpdf/mpdf/issues/2207
[#1]: https://github.com/GravityPDF/mpdf/pull/1
[#2]: https://github.com/GravityPDF/mpdf/pull/2
[#3]: https://github.com/GravityPDF/mpdf/pull/3
[#4]: https://github.com/GravityPDF/mpdf/pull/4
[#5]: https://github.com/GravityPDF/mpdf/pull/5
[#6]: https://github.com/GravityPDF/mpdf/pull/6
[#7]: https://github.com/GravityPDF/mpdf/pull/7
[#8]: https://github.com/GravityPDF/mpdf/pull/8
[#9]: https://github.com/GravityPDF/mpdf/pull/9
[#10]: https://github.com/GravityPDF/mpdf/pull/10
[#11]: https://github.com/GravityPDF/mpdf/pull/11
[#12]: https://github.com/GravityPDF/mpdf/pull/12
[#13]: https://github.com/GravityPDF/mpdf/pull/13
[#14]: https://github.com/GravityPDF/mpdf/pull/14
[#15]: https://github.com/GravityPDF/mpdf/pull/15
[#16]: https://github.com/GravityPDF/mpdf/pull/16
[#17]: https://github.com/GravityPDF/mpdf/pull/17
[#19]: https://github.com/GravityPDF/mpdf/pull/19
[#20]: https://github.com/GravityPDF/mpdf/pull/20
[#21]: https://github.com/GravityPDF/mpdf/pull/21
[#22]: https://github.com/GravityPDF/mpdf/pull/22
[#23]: https://github.com/GravityPDF/mpdf/pull/23
[#24]: https://github.com/GravityPDF/mpdf/pull/24
[#25]: https://github.com/GravityPDF/mpdf/pull/25
[#26]: https://github.com/GravityPDF/mpdf/pull/26
[#27]: https://github.com/GravityPDF/mpdf/pull/27
[#28]: https://github.com/GravityPDF/mpdf/pull/28
[#29]: https://github.com/GravityPDF/mpdf/pull/29
[#30]: https://github.com/GravityPDF/mpdf/pull/30
[#31]: https://github.com/GravityPDF/mpdf/pull/31
[#32]: https://github.com/GravityPDF/mpdf/pull/32
[#33]: https://github.com/GravityPDF/mpdf/pull/33
[#34]: https://github.com/GravityPDF/mpdf/pull/34
[#35]: https://github.com/GravityPDF/mpdf/pull/35
[#36]: https://github.com/GravityPDF/mpdf/pull/36
[#37]: https://github.com/GravityPDF/mpdf/pull/37
[#38]: https://github.com/GravityPDF/mpdf/pull/38
[#39]: https://github.com/GravityPDF/mpdf/pull/39
[#40]: https://github.com/GravityPDF/mpdf/pull/40
[#41]: https://github.com/GravityPDF/mpdf/pull/41
[#42]: https://github.com/GravityPDF/mpdf/pull/42
[#43]: https://github.com/GravityPDF/mpdf/pull/43
[#44]: https://github.com/GravityPDF/mpdf/pull/44
[#45]: https://github.com/GravityPDF/mpdf/pull/45
[#47]: https://github.com/GravityPDF/mpdf/pull/47
[#48]: https://github.com/GravityPDF/mpdf/pull/48
[#49]: https://github.com/GravityPDF/mpdf/pull/49
[#50]: https://github.com/GravityPDF/mpdf/pull/50
[#51]: https://github.com/GravityPDF/mpdf/pull/51
[#52]: https://github.com/GravityPDF/mpdf/issues/52
[#53]: https://github.com/GravityPDF/mpdf/pull/53
[#56]: https://github.com/GravityPDF/mpdf/issues/56
[#60]: https://github.com/GravityPDF/mpdf/pull/60
[#63]: https://github.com/GravityPDF/mpdf/pull/63
[#64]: https://github.com/GravityPDF/mpdf/pull/64
[#66]: https://github.com/GravityPDF/mpdf/issues/66
[#67]: https://github.com/GravityPDF/mpdf/pull/67
[#68]: https://github.com/GravityPDF/mpdf/issues/68
[#69]: https://github.com/GravityPDF/mpdf/pull/69
[#70]: https://github.com/GravityPDF/mpdf/pull/70
[#72]: https://github.com/GravityPDF/mpdf/pull/72
[#74]: https://github.com/GravityPDF/mpdf/pull/74
[#75]: https://github.com/GravityPDF/mpdf/pull/75
[#79]: https://github.com/GravityPDF/mpdf/pull/79
[#82]: https://github.com/GravityPDF/mpdf/pull/82
[#84]: https://github.com/GravityPDF/mpdf/pull/84
[#88]: https://github.com/GravityPDF/mpdf/issues/88
[#93]: https://github.com/GravityPDF/mpdf/issues/93
[#94]: https://github.com/GravityPDF/mpdf/pull/94
[#95]: https://github.com/GravityPDF/mpdf/pull/95
[#96]: https://github.com/GravityPDF/mpdf/pull/96
[#97]: https://github.com/GravityPDF/mpdf/pull/97
[#98]: https://github.com/GravityPDF/mpdf/pull/98
[#99]: https://github.com/GravityPDF/mpdf/issues/99
[#100]: https://github.com/GravityPDF/mpdf/issues/100
[#101]: https://github.com/GravityPDF/mpdf/issues/101
[#102]: https://github.com/GravityPDF/mpdf/issues/102
[#103]: https://github.com/GravityPDF/mpdf/pull/103
[#104]: https://github.com/GravityPDF/mpdf/issues/104
[#105]: https://github.com/GravityPDF/mpdf/pull/105
[#106]: https://github.com/GravityPDF/mpdf/pull/106
[#107]: https://github.com/GravityPDF/mpdf/pull/107
[#109]: https://github.com/GravityPDF/mpdf/pull/109
[#113]: https://github.com/GravityPDF/mpdf/issues/113
[#115]: https://github.com/GravityPDF/mpdf/issues/115
[#116]: https://github.com/GravityPDF/mpdf/issues/116
[#117]: https://github.com/GravityPDF/mpdf/pull/117
[#123]: https://github.com/GravityPDF/mpdf/issues/123
[#124]: https://github.com/GravityPDF/mpdf/issues/124
[#125]: https://github.com/GravityPDF/mpdf/pull/125
[#126]: https://github.com/GravityPDF/mpdf/issues/126
[#127]: https://github.com/GravityPDF/mpdf/issues/127
[#128]: https://github.com/GravityPDF/mpdf/pull/128
[#130]: https://github.com/GravityPDF/mpdf/pull/130
[#132]: https://github.com/GravityPDF/mpdf/issues/132
[#134]: https://github.com/GravityPDF/mpdf/pull/134
[#135]: https://github.com/GravityPDF/mpdf/pull/135
[#137]: https://github.com/GravityPDF/mpdf/pull/137
[#138]: https://github.com/GravityPDF/mpdf/pull/138
[#139]: https://github.com/GravityPDF/mpdf/pull/139
[#141]: https://github.com/GravityPDF/mpdf/issues/141
[#143]: https://github.com/GravityPDF/mpdf/pull/143
[#144]: https://github.com/GravityPDF/mpdf/pull/144
[#145]: https://github.com/GravityPDF/mpdf/pull/145
[#148]: https://github.com/GravityPDF/mpdf/pull/148
[#149]: https://github.com/GravityPDF/mpdf/pull/149
[#151]: https://github.com/GravityPDF/mpdf/pull/151
[#155]: https://github.com/GravityPDF/mpdf/pull/155
[#157]: https://github.com/GravityPDF/mpdf/pull/157
[#158]: https://github.com/GravityPDF/mpdf/pull/158
[#159]: https://github.com/GravityPDF/mpdf/pull/159
[#160]: https://github.com/GravityPDF/mpdf/issues/160
[#161]: https://github.com/GravityPDF/mpdf/pull/161
[#163]: https://github.com/GravityPDF/mpdf/pull/163
[#164]: https://github.com/GravityPDF/mpdf/issues/164
[#165]: https://github.com/GravityPDF/mpdf/pull/165
[#167]: https://github.com/GravityPDF/mpdf/pull/167
[#168]: https://github.com/GravityPDF/mpdf/issues/168
[#169]: https://github.com/GravityPDF/mpdf/pull/169
[#170]: https://github.com/GravityPDF/mpdf/issues/170
[#171]: https://github.com/GravityPDF/mpdf/pull/171
[#172]: https://github.com/GravityPDF/mpdf/pull/172
[#173]: https://github.com/GravityPDF/mpdf/issues/173
[#174]: https://github.com/GravityPDF/mpdf/pull/174
[#175]: https://github.com/GravityPDF/mpdf/issues/175
[#176]: https://github.com/GravityPDF/mpdf/pull/176
[#177]: https://github.com/GravityPDF/mpdf/pull/177
[#178]: https://github.com/GravityPDF/mpdf/pull/178
[#179]: https://github.com/GravityPDF/mpdf/issues/179
[#180]: https://github.com/GravityPDF/mpdf/pull/180
[#182]: https://github.com/GravityPDF/mpdf/issues/182
[#183]: https://github.com/GravityPDF/mpdf/pull/183
[#184]: https://github.com/GravityPDF/mpdf/pull/184
[#185]: https://github.com/GravityPDF/mpdf/pull/185
[#186]: https://github.com/GravityPDF/mpdf/pull/186
[#187]: https://github.com/GravityPDF/mpdf/issues/187
[#188]: https://github.com/GravityPDF/mpdf/pull/188
[#189]: https://github.com/GravityPDF/mpdf/issues/189
[#190]: https://github.com/GravityPDF/mpdf/pull/190
[#191]: https://github.com/GravityPDF/mpdf/issues/191
[#192]: https://github.com/GravityPDF/mpdf/issues/192
[#193]: https://github.com/GravityPDF/mpdf/issues/193
[#194]: https://github.com/GravityPDF/mpdf/pull/194
[#195]: https://github.com/GravityPDF/mpdf/pull/195
[#197]: https://github.com/GravityPDF/mpdf/pull/197
[#198]: https://github.com/GravityPDF/mpdf/pull/198
[#199]: https://github.com/GravityPDF/mpdf/issues/199
[#201]: https://github.com/GravityPDF/mpdf/issues/201
[#204]: https://github.com/GravityPDF/mpdf/issues/204
[#206]: https://github.com/GravityPDF/mpdf/issues/206
[#209]: https://github.com/GravityPDF/mpdf/issues/209
[#212]: https://github.com/GravityPDF/mpdf/issues/212
[#215]: https://github.com/GravityPDF/mpdf/pull/215
[#216]: https://github.com/GravityPDF/mpdf/pull/216
[#217]: https://github.com/GravityPDF/mpdf/pull/217
[#218]: https://github.com/GravityPDF/mpdf/pull/218
[#219]: https://github.com/GravityPDF/mpdf/pull/219
[#220]: https://github.com/GravityPDF/mpdf/pull/220
[#221]: https://github.com/GravityPDF/mpdf/pull/221
[#222]: https://github.com/GravityPDF/mpdf/pull/222
[#223]: https://github.com/GravityPDF/mpdf/issues/223
[#224]: https://github.com/GravityPDF/mpdf/pull/224
[#226]: https://github.com/GravityPDF/mpdf/pull/226
[#227]: https://github.com/GravityPDF/mpdf/pull/227
[#228]: https://github.com/GravityPDF/mpdf/issues/228
[#229]: https://github.com/GravityPDF/mpdf/pull/229
[#230]: https://github.com/GravityPDF/mpdf/issues/230
[#231]: https://github.com/GravityPDF/mpdf/issues/231
[#233]: https://github.com/GravityPDF/mpdf/issues/233
[#234]: https://github.com/GravityPDF/mpdf/issues/234
[#235]: https://github.com/GravityPDF/mpdf/pull/235
[#236]: https://github.com/GravityPDF/mpdf/pull/236
[#237]: https://github.com/GravityPDF/mpdf/issues/237
[#238]: https://github.com/GravityPDF/mpdf/issues/238
[#239]: https://github.com/GravityPDF/mpdf/pull/239
[#240]: https://github.com/GravityPDF/mpdf/pull/240
[#241]: https://github.com/GravityPDF/mpdf/pull/241
[#242]: https://github.com/GravityPDF/mpdf/pull/242
[#243]: https://github.com/GravityPDF/mpdf/issues/243
[#244]: https://github.com/GravityPDF/mpdf/issues/244
[#245]: https://github.com/GravityPDF/mpdf/issues/245
[#246]: https://github.com/GravityPDF/mpdf/pull/246
[#247]: https://github.com/GravityPDF/mpdf/pull/247
[#248]: https://github.com/GravityPDF/mpdf/issues/248
[#249]: https://github.com/GravityPDF/mpdf/pull/249
[#250]: https://github.com/GravityPDF/mpdf/pull/250
[#251]: https://github.com/GravityPDF/mpdf/issues/251
[#252]: https://github.com/GravityPDF/mpdf/pull/252
[#253]: https://github.com/GravityPDF/mpdf/issues/253
[#254]: https://github.com/GravityPDF/mpdf/pull/254
[#257]: https://github.com/GravityPDF/mpdf/issues/257
[#258]: https://github.com/GravityPDF/mpdf/pull/258
[#259]: https://github.com/GravityPDF/mpdf/issues/259
[#260]: https://github.com/GravityPDF/mpdf/issues/260
[#261]: https://github.com/GravityPDF/mpdf/pull/261
[#262]: https://github.com/GravityPDF/mpdf/pull/262
[#263]: https://github.com/GravityPDF/mpdf/issues/263
[#264]: https://github.com/GravityPDF/mpdf/pull/264
[#265]: https://github.com/GravityPDF/mpdf/pull/265
[#267]: https://github.com/GravityPDF/mpdf/issues/267
[#268]: https://github.com/GravityPDF/mpdf/pull/268
[#269]: https://github.com/GravityPDF/mpdf/issues/269
[#270]: https://github.com/GravityPDF/mpdf/pull/270
[#271]: https://github.com/GravityPDF/mpdf/issues/271
[#272]: https://github.com/GravityPDF/mpdf/pull/272
[#273]: https://github.com/GravityPDF/mpdf/pull/273
[#275]: https://github.com/GravityPDF/mpdf/pull/275
[#276]: https://github.com/GravityPDF/mpdf/pull/276
[#277]: https://github.com/GravityPDF/mpdf/pull/277
[#279]: https://github.com/GravityPDF/mpdf/pull/279
[#281]: https://github.com/GravityPDF/mpdf/pull/281
[#282]: https://github.com/GravityPDF/mpdf/pull/282
[#284]: https://github.com/GravityPDF/mpdf/pull/284
[#285]: https://github.com/GravityPDF/mpdf/issues/285
[#286]: https://github.com/GravityPDF/mpdf/pull/286
[#287]: https://github.com/GravityPDF/mpdf/issues/287
[#288]: https://github.com/GravityPDF/mpdf/pull/288
[#289]: https://github.com/GravityPDF/mpdf/pull/289
[#290]: https://github.com/GravityPDF/mpdf/pull/290
[#291]: https://github.com/GravityPDF/mpdf/pull/291
[#292]: https://github.com/GravityPDF/mpdf/pull/292
[#293]: https://github.com/GravityPDF/mpdf/pull/293
[#294]: https://github.com/GravityPDF/mpdf/pull/294
[#295]: https://github.com/GravityPDF/mpdf/pull/295
[#299]: https://github.com/GravityPDF/mpdf/pull/299
[#300]: https://github.com/GravityPDF/mpdf/pull/300
[#303]: https://github.com/GravityPDF/mpdf/pull/303
[#306]: https://github.com/GravityPDF/mpdf/pull/306
[#307]: https://github.com/GravityPDF/mpdf/issues/307
[#308]: https://github.com/GravityPDF/mpdf/pull/308
[#309]: https://github.com/GravityPDF/mpdf/pull/309
[#310]: https://github.com/GravityPDF/mpdf/pull/310
[#311]: https://github.com/GravityPDF/mpdf/pull/311
[#312]: https://github.com/GravityPDF/mpdf/pull/312
[#313]: https://github.com/GravityPDF/mpdf/pull/313
[#314]: https://github.com/GravityPDF/mpdf/pull/314
[#315]: https://github.com/GravityPDF/mpdf/issues/315
[#317]: https://github.com/GravityPDF/mpdf/pull/317
[#318]: https://github.com/GravityPDF/mpdf/pull/318
[#319]: https://github.com/GravityPDF/mpdf/pull/319
[#321]: https://github.com/GravityPDF/mpdf/issues/321
[#322]: https://github.com/GravityPDF/mpdf/pull/322
[#323]: https://github.com/GravityPDF/mpdf/pull/323
[#324]: https://github.com/GravityPDF/mpdf/pull/324
[#326]: https://github.com/GravityPDF/mpdf/issues/326
[#328]: https://github.com/GravityPDF/mpdf/pull/328
[#329]: https://github.com/GravityPDF/mpdf/pull/329
[#330]: https://github.com/GravityPDF/mpdf/pull/330
[#331]: https://github.com/GravityPDF/mpdf/pull/331
[#335]: https://github.com/GravityPDF/mpdf/pull/335
[#336]: https://github.com/GravityPDF/mpdf/pull/336
[#337]: https://github.com/GravityPDF/mpdf/pull/337
[#339]: https://github.com/GravityPDF/mpdf/issues/339
[#342]: https://github.com/GravityPDF/mpdf/issues/342
[#343]: https://github.com/GravityPDF/mpdf/issues/343
[#344]: https://github.com/GravityPDF/mpdf/issues/344
[#345]: https://github.com/GravityPDF/mpdf/pull/345
[#346]: https://github.com/GravityPDF/mpdf/issues/346
[#347]: https://github.com/GravityPDF/mpdf/issues/347
[#349]: https://github.com/GravityPDF/mpdf/issues/349
[#350]: https://github.com/GravityPDF/mpdf/issues/350
[#351]: https://github.com/GravityPDF/mpdf/issues/351
[#354]: https://github.com/GravityPDF/mpdf/pull/354
[#356]: https://github.com/GravityPDF/mpdf/pull/356
[#357]: https://github.com/GravityPDF/mpdf/pull/357
[#358]: https://github.com/GravityPDF/mpdf/pull/358
[#359]: https://github.com/GravityPDF/mpdf/pull/359
[#360]: https://github.com/GravityPDF/mpdf/pull/360
[#361]: https://github.com/GravityPDF/mpdf/pull/361
[#362]: https://github.com/GravityPDF/mpdf/pull/362
[#363]: https://github.com/GravityPDF/mpdf/issues/363
[#364]: https://github.com/GravityPDF/mpdf/pull/364
[#365]: https://github.com/GravityPDF/mpdf/pull/365
[#368]: https://github.com/GravityPDF/mpdf/pull/368
[#372]: https://github.com/GravityPDF/mpdf/pull/372
[#373]: https://github.com/GravityPDF/mpdf/pull/373
[#375]: https://github.com/GravityPDF/mpdf/pull/375
[#376]: https://github.com/GravityPDF/mpdf/pull/376
[#377]: https://github.com/GravityPDF/mpdf/pull/377
[#378]: https://github.com/GravityPDF/mpdf/pull/378
[#379]: https://github.com/GravityPDF/mpdf/pull/379
[#381]: https://github.com/GravityPDF/mpdf/pull/381
[#382]: https://github.com/GravityPDF/mpdf/pull/382
[#383]: https://github.com/GravityPDF/mpdf/pull/383
[#384]: https://github.com/GravityPDF/mpdf/pull/384
[#385]: https://github.com/GravityPDF/mpdf/pull/385
[#386]: https://github.com/GravityPDF/mpdf/pull/386
[#387]: https://github.com/GravityPDF/mpdf/pull/387
[#395]: https://github.com/GravityPDF/mpdf/issues/395
[#397]: https://github.com/GravityPDF/mpdf/issues/397
[#408]: https://github.com/GravityPDF/mpdf/issues/408
[#410]: https://github.com/GravityPDF/mpdf/issues/410
[#411]: https://github.com/GravityPDF/mpdf/pull/411
[#412]: https://github.com/GravityPDF/mpdf/pull/412
[#413]: https://github.com/GravityPDF/mpdf/pull/413
[#414]: https://github.com/GravityPDF/mpdf/pull/414
[#416]: https://github.com/GravityPDF/mpdf/pull/416
[#417]: https://github.com/GravityPDF/mpdf/pull/417
[#418]: https://github.com/GravityPDF/mpdf/pull/418
[#419]: https://github.com/GravityPDF/mpdf/pull/419
[#420]: https://github.com/GravityPDF/mpdf/pull/420
[#422]: https://github.com/GravityPDF/mpdf/pull/422
[#426]: https://github.com/GravityPDF/mpdf/issues/426
[#427]: https://github.com/GravityPDF/mpdf/issues/427
[#428]: https://github.com/GravityPDF/mpdf/pull/428
[#429]: https://github.com/GravityPDF/mpdf/pull/429
[#433]: https://github.com/GravityPDF/mpdf/issues/433
[#435]: https://github.com/GravityPDF/mpdf/issues/435
[#437]: https://github.com/GravityPDF/mpdf/pull/437
[#438]: https://github.com/GravityPDF/mpdf/pull/438
[#441]: https://github.com/GravityPDF/mpdf/pull/441
[#442]: https://github.com/GravityPDF/mpdf/pull/442
[#444]: https://github.com/GravityPDF/mpdf/pull/444
[#446]: https://github.com/GravityPDF/mpdf/pull/446
[#447]: https://github.com/GravityPDF/mpdf/pull/447
[#448]: https://github.com/GravityPDF/mpdf/pull/448
[#449]: https://github.com/GravityPDF/mpdf/issues/449
[#450]: https://github.com/GravityPDF/mpdf/issues/450
[#451]: https://github.com/GravityPDF/mpdf/pull/451
[#454]: https://github.com/GravityPDF/mpdf/issues/454
[#457]: https://github.com/GravityPDF/mpdf/issues/457
[#459]: https://github.com/GravityPDF/mpdf/issues/459
[#460]: https://github.com/GravityPDF/mpdf/issues/460
[#463]: https://github.com/GravityPDF/mpdf/issues/463
[#464]: https://github.com/GravityPDF/mpdf/issues/464
[#467]: https://github.com/GravityPDF/mpdf/issues/467
[#468]: https://github.com/GravityPDF/mpdf/pull/468
[#469]: https://github.com/GravityPDF/mpdf/pull/469
[#471]: https://github.com/GravityPDF/mpdf/pull/471
[#474]: https://github.com/GravityPDF/mpdf/issues/474
[#475]: https://github.com/GravityPDF/mpdf/issues/475
[#476]: https://github.com/GravityPDF/mpdf/issues/476
[#482]: https://github.com/GravityPDF/mpdf/issues/482
[#485]: https://github.com/GravityPDF/mpdf/pull/485
[#486]: https://github.com/GravityPDF/mpdf/pull/486
[#487]: https://github.com/GravityPDF/mpdf/pull/487
[#488]: https://github.com/GravityPDF/mpdf/pull/488
[#489]: https://github.com/GravityPDF/mpdf/pull/489
[#490]: https://github.com/GravityPDF/mpdf/pull/490
[#492]: https://github.com/GravityPDF/mpdf/pull/492
[#493]: https://github.com/GravityPDF/mpdf/pull/493
[#498]: https://github.com/GravityPDF/mpdf/pull/498
[#499]: https://github.com/GravityPDF/mpdf/pull/499
[#500]: https://github.com/GravityPDF/mpdf/pull/500
[#501]: https://github.com/GravityPDF/mpdf/pull/501
[#503]: https://github.com/GravityPDF/mpdf/pull/503
[#505]: https://github.com/GravityPDF/mpdf/pull/505
[#507]: https://github.com/GravityPDF/mpdf/pull/507
[#510]: https://github.com/GravityPDF/mpdf/pull/510
[#512]: https://github.com/GravityPDF/mpdf/pull/512
[#514]: https://github.com/GravityPDF/mpdf/pull/514
[#515]: https://github.com/GravityPDF/mpdf/pull/515
[#519]: https://github.com/GravityPDF/mpdf/pull/519
[#522]: https://github.com/GravityPDF/mpdf/issues/522
[#523]: https://github.com/GravityPDF/mpdf/issues/523
[#549]: https://github.com/GravityPDF/mpdf/pull/549
[#561]: https://github.com/GravityPDF/mpdf/pull/561
[#525]: https://github.com/GravityPDF/mpdf/issues/525
[#549]: https://github.com/GravityPDF/mpdf/pull/549
[#552]: https://github.com/GravityPDF/mpdf/issues/552
[#560]: https://github.com/GravityPDF/mpdf/pull/560
[#559]: https://github.com/GravityPDF/mpdf/pull/559
[#550]: https://github.com/GravityPDF/mpdf/issues/550
[#551]: https://github.com/GravityPDF/mpdf/issues/551
[#553]: https://github.com/GravityPDF/mpdf/pull/553
[#554]: https://github.com/GravityPDF/mpdf/issues/554
[#555]: https://github.com/GravityPDF/mpdf/issues/555
[#556]: https://github.com/GravityPDF/mpdf/pull/556
[#558]: https://github.com/GravityPDF/mpdf/pull/558
[#562]: https://github.com/GravityPDF/mpdf/pull/562
[#564]: https://github.com/GravityPDF/mpdf/pull/564
[#563]: https://github.com/GravityPDF/mpdf/pull/563
[#565]: https://github.com/GravityPDF/mpdf/pull/565
[#577]: https://github.com/GravityPDF/mpdf/pull/577
[#615]: https://github.com/GravityPDF/mpdf/pull/615
