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
* **The `border` and `background` shorthands reset the longhands they do not name**, as in a browser, in standard mode. [#552] [#614]
  * `border`, or one side such as `border-top`, sets the width, style and colour of each side it covers. `p { border-top-color: red } p { border: 1px solid blue }` used to draw a red top and now draws a blue one.
  * `background` sets the colour, image, repeat, position, size, origin and clip. A `background-position`, `-repeat` or `-size` given before a `background` no longer applies to its image.
  * To keep the old look, restate the longhand after the shorthand, in the same rule or a later one.
  * `border` keeps `border-radius`. `background` keeps mPDF's own `background-image-resize`, `-opacity` and `-resolution`, which it cannot set, in the same way that `background` leaves `background-blend-mode` alone in CSS. `background-size: auto` no longer stops `background-image-resize` from sizing the image.
  * A property declared twice in one block is read at its last place, so `border-top-color: green; border: 1px solid blue; border-top-color: red` draws a red top.
* **Dependencies changed.** `ext-json` is now required and `symfony/polyfill-intl-normalizer` is a new dependency. `myclabs/deep-copy` is no longer used. [#13] [#329]
* **`outline-width` and `outline-color` no longer stroke the text.** mPDF read them as `text-outline-width` and `text-outline-color`, but in CSS they belong to the line around the box, which mPDF does not draw. `outline-width` on its own also raised an undefined-key warning. Write `text-outline-width` and `text-outline-color`, or `text-outline`, to keep stroking the text. [#578]
* **CSS rules apply by specificity, then in the order they are written**, as in a browser. mPDF applied them in a fixed order of selector groups, so `div p` beat `#id`, `p.c` beat `#i`, `body p` beat `.lead`, the rule from the nearest ancestor beat a heavier descendant rule, and classes applied in alphabetical order. Now the more specific selector wins each of those, and of `.a { } .b { } .a { }` the second `.a` wins. Set `'cssMode' => \Mpdf\CssMode::LEGACY` to parse and apply CSS as mPDF v7 did. [#535] [#631]
  * Each element takes, in order: the values it inherits; the built-in defaults and the rules of the default stylesheet (`defaultCssFile`), which any author rule beats; HTML attributes such as `<hr color>`, `width` and `vspace`, as author rules of no specificity; the stylesheets; then `style=""`.
  * Class and id names still match whatever their case.
  * An author's `a { }` rule now reaches the links of the table of contents and the index too. Style `a.mpdf_toc_a` and `a.mpdf_index_link` to keep them plain.
  * In legacy mode, a rule whose selector mPDF v7 could not read is dropped: child and sibling combinators, structural pseudo-classes outside tables, attribute selectors other than `[lang]`, `:lang()` through an ancestor, descendant rules through inline ancestors, and `:not()`, `:is()` and `:where()`. The `font`, `border` and `background` shorthands leave the longhands they do not name, as mPDF v7 did. Fixes to how values and selectors are read apply in both modes. [#633]
* **`!important` is honoured**, as in a browser, in standard mode. mPDF read a declaration marked `!important` as any other, so `p { color: green !important } #i { color: red }` drew `<p id="i">` red, and so did `style="color: red"` on a `<p>`. Now an important declaration beats every declaration that is not, the inline style included. Set `'cssMode' => \Mpdf\CssMode::LEGACY` to read the flag as nothing, as mPDF v7 did. [mpdf/mpdf#1010] [mpdf/mpdf#1707] [#532] [#638]
  * Important declarations apply after the inline style: those of the stylesheets by specificity and then order, then those of `style=""`, then those of the default stylesheet (`defaultCssFile`), as a browser's own important declarations beat a page's.
  * A shorthand marked `!important` makes every longhand it sets important, so `border: 1mm solid green !important` beats a `border-top-color` of a more specific rule or of a later declaration in the same block.
  * `@page` rules, `body` rules and the class rules SVG text looks up take an important declaration over a later one of the same selector that is not. An important declaration of a `body` rule beats `<body style>`.
* **HTML attributes that style an element are read only where HTML defines them**, as a browser reads them, in standard mode. Set `'cssMode' => \Mpdf\CssMode::LEGACY` to keep the old reading. [#531] [#641]
  * `color` applies to `font` and `hr` only, `width` to images, tables, cells, `hr`, `meter` and `progress`, `height` to images, tables, rows, row groups, cells, `meter` and `progress`, `valign` to rows, row groups and cells, `vspace` and `hspace` to images, and `align` to `div`, `p`, headings, table parts, `caption`, `hr` and `img`. So `<a color>`, `<span color>`, `<p color>`, `<td color>`, `<div width>`, `<img valign>` and `<blockquote align>` no longer do anything. mPDF's own tags, such as `<barcode color>`, keep every attribute.
  * A stylesheet rule beats the attribute everywhere. Before, `align` on a paragraph, div or table cell, `nowrap` on a cell, `border` on a table, `<caption align>`, `<hr width>` in a table cell and `align` on a text field beat any rule for the same property.
  * `<div align>` passes its alignment on to what it holds, and `<li type>` beats the marker the item inherits from its list.
  * Attributes mPDF ignored now take effect: `<hr align>`, `<hr size>`, `<img align>` (float left or right, or vertical alignment), `<img border>`, `<thead align>`, and `<table border>` wider than 1.
  * A table's `border` gives each cell a 1px border of its own, which a rule for the table's border leaves alone. Before, the cells copied the table's border.
* **`line-height` on an inline element sets the height of its line**, as in a browser. mPDF ignored it, so `<span style="line-height: 30mm">` left its line as it was. Now each inline element's box is as tall as its line-height, and a line grows to hold the tallest box on it, text or image, in blocks and table cells alike. The block's own line height is the least a line can be, so a smaller inline line-height, or `0`, does not shrink it. A percentage in `vertical-align` is taken of the element's own line-height. `line-height` on a `<p>` or `<div>` inside a table cell, which mPDF lays out as inline content, can now make its lines taller than the cell's. Set `'cssMode' => \Mpdf\CssMode::LEGACY` to ignore it as before. [#548] [#646]
* **`rem` is read against the font size of `html`, in standard mode**, as in a browser. It was read against the font size of `body`, and inside a table against the table's. Now `1rem` is the default font size, from the `default_font_size` configuration or `SetDefaultFontSize()`, unless an `html` or `:root` rule sizes `html`: with `html { font-size: 62.5% }`, `1.6rem` is the default size again. A `body { font-size }` rule no longer changes it. To keep the old sizes, give them in `em` or points, or set `'cssMode' => \Mpdf\CssMode::LEGACY`. [#529] [#642]

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

* **`break-before`, `break-after` and `break-inside`.** They are read as their `page-break-*` equivalents: `page` as `always`, `recto` and `verso` as `right` and `left`, and `avoid-page` as `avoid`. Column and region breaks are not page breaks, so they are read as `auto`. [#552] [#569]
* **Side margins on `:first`, `:left` and `:right` pages.** The `:first`, `:left` and `:right` pages of an `@page` rule, named or not, can set `margin-left` and `margin-right`. Text flowing from page to page is set in the page area of each page, and columns are laid out across it. A side margin on a pseudo page belongs to that side of the physical page, so it is not mirrored. [#476] [#510]
  * Text beside a float, and the blocks around it, keep to the page area of each page the float runs over. [#549]
  * A block with a set width keeps its place in each page area: a centred block stays centred, and a block pushed right by `margin-left: auto` or set right to left stays against the right margin. [#550] [#551] [#553]

### CSS

* **An id written with classes.** Selectors such as `p#note.warning`, `p.warning#note`, `#note.warning` and `p.a.b#note` match, on their own and as parts of descendant rules, in any order of id and classes. They are applied after `p#note`. A selector with a class or id followed by something mPDF cannot match, such as `.a:hover` or `#note::before`, is dropped like any other selector mPDF cannot match. [#527] [#568]
* **Child and sibling combinators, and structural pseudo-classes.** `div > p`, `h1 + p`, `h1 ~ p`, and `:first-child`, `:nth-child()`, `:first-of-type` and `:nth-of-type()` on any element, are matched against the elements open around the one being styled. Rules using them were dropped before, so a document that has them changes. They are applied with the descendant rules, after them, in order of specificity and then of source order. [mpdf/mpdf#7] [mpdf/mpdf#318] [#538] [#620]
* **Descendant rules through inline elements.** A descendant rule whose ancestor is an inline element, a block inside a table cell, or the `tbody` a table leaves out, such as `span em`, or `.note b` for a `<span class="note">`, applies. [mpdf/mpdf#830] [#538] [#621]
* **Attribute selectors and `:lang()`.** `[a]`, `[a=v]`, `[a~=v]`, `[a|=v]`, `[a^=v]`, `[a$=v]` and `[a*=v]`, with the `i` and `s` flags, match as in HTML: case-insensitively for the attributes HTML lists as such, and case-sensitively for the rest. `:lang()` matches the language an element inherits, from an ancestor or from the `<html>` or `<body>` tag. [mpdf/mpdf#134] [mpdf/mpdf#1838] [#538] [#627]
* **`:not()`, `:is()` and `:where()`.** Each takes a selector list whose selectors may have combinators, such as `p:not(.note, div > p)` or `:is(h2, h3) + p`. `:is()` and `:where()` leave out a selector they cannot read, as browsers do. `:not()` and `:is()` weigh as their most specific argument, and `:where()` as nothing. Rules using them were dropped before. [#538] [#628]
* **Pseudo-classes that look at what follows an element.** `:last-child`, `:nth-last-child()`, `:only-child`, `:last-of-type`, `:nth-last-of-type()`, `:only-of-type` and `:empty` match in the flow, in tables, headers and footers, and positioned blocks. Rules using them were dropped before, so a document that has them changes. Set `'cssMode' => \Mpdf\CssMode::LEGACY` to keep dropping them. [#537] [#634]
  * `WriteHTML()` reads ahead through the HTML it is given to count each element's children. An element still open at the end of a call that leaves it open is not known in full, so these do not match its children in that call, and nor does `:not()` of them.
  * `:empty` matches as in browsers: an element holding white space is not empty, and one holding only a comment is. A table cell holding only white space is empty, as mPDF strips it.
* **`:root`, `html`, `:link` and `:any-link`, in standard mode.** `a:link` and `:any-link` match a link with an `href`. mPDF has no `html` element, so `html` and `:root` match it as the parent of `body`: their rules reach the text as a parent's would, and rules for `body` win over them. `:visited`, `:hover`, `:focus`, `:active`, `:focus-within`, `:focus-visible` and `:target` never match, as a PDF is never visited or hovered, so `:not(:hover)` always does. Rules using them were dropped before, so a document that has them changes. Set `'cssMode' => \Mpdf\CssMode::LEGACY` to keep dropping them. [#529] [#642]
* **The universal selector.** `*` matches every element: alone, with a class, an id, an attribute or a pseudo-class, on either side of a combinator, and inside `:is()`, `:where()` and `:not()`. It weighs nothing, so any rule naming a tag, class, id, attribute or pseudo-class beats it. It reaches `html` and `body` too, so `* { color: … }` colours text written straight into the body, and `* { font-size: … }` sets the size `rem` is read against. Rules using it were dropped before, so a document that has them changes. Set `'cssMode' => \Mpdf\CssMode::LEGACY` to keep dropping them. [mpdf/mpdf#312] [mpdf/mpdf#1168] [mpdf/mpdf#1838] [#530] [#644]
  * A reset such as `* { margin: 0; padding: 0 }` now takes away the default margins of paragraphs, headings and lists, a list's indent and a cell's padding, as in a browser. It reaches the lines of the table of contents and the index as well, which are the document's elements, so their levels are no longer indented. Style `div.mpdf_toc_level_1` and the like to indent them again.
  * mPDF's own `<barcode>`, `<dottab>` and `<textcircle>` are not HTML elements. A rule reaches them only by naming their tag, or an id or class they carry, so `*`, and `:empty`, `[attr]` or `:not()` alone, leave them as they are: `:empty { display: none }` does not hide a barcode.
* **Font weights as numbers, `bolder` and `lighter`, and `font-size: larger` and `smaller`.** In the standard CSS mode, `font-weight` takes a number from 1 to 1000, and `bolder` and `lighter` step from the parent's weight by the table in CSS Fonts. The weight is inherited as a number, so `bolder` inside `bolder` gives 900 and `lighter` inside 700 gives 400, and `h1 { font-weight: lighter }` is regular, one step down from its parent's 400. A weight above 500 is drawn with the family's bold face and any other with its regular face. `larger` and `smaller` multiply and divide the parent's size by 1.2. A form field reads them against the document's size, as it reads its other font sizes. The `font` shorthand's weight is read the same way. All of these were ignored before, so a document that has them changes. Set `'cssMode' => \Mpdf\CssMode::LEGACY` to read only `normal` and `bold`, as before. [#545] [#647]

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
* **nth-child rules in tables.** Each row and cell looks up the nth-child rules the stylesheet uses by their keys, instead of running a regex over every rule. A 2,000-cell table under 1,000 rules is written in about 540 ms instead of 745 ms. Class combinations are built only up to the most classes one compound selector names, not one whole selector. [#526] [#580]
* **Selector matching.** A chain of descendant combinators stops trying ancestors once none left can match, instead of trying every combination of them, and a general sibling combinator skips a tag the parent has no child of and looks from the first sibling. `.x div div div div div p` is matched on a path 50 deep in well under a millisecond instead of 4 seconds, and `h2 ~ p` against 5,000 paragraphs after an `<h2>` in 20 ms instead of 5 seconds. [#535] [#631]

Bugfixes
--------

### Layout and CSS

* **`page-break-inside: avoid`** now moves the whole block to the next page when it does not fit. Its text, list numbers, floats, links, bookmarks, index and contents entries, form fields and annotations each appear once, on the page the block ends up on. A block that fits is laid out only once. [#38] [#43] [#60] [#63]
* A table with no background left the next table painted twice. [#43]
* `@page { size: A4 }`, or a `size` of two lengths with no `margin`, gave 63 pages with one character on each. A page-size name such as `A4`, `letter` or `A5 landscape` now sets the sheet, as a browser sets the paper, and the space around a page box given as two lengths is no longer counted twice. [mpdf/mpdf#1220] [#552] [#562]
* `@page { size: landscape }`, or a page box wider than it is tall, left the first page portrait, and `size: portrait` left a landscape document landscape. The first page now turns. A page box and percentage margins are measured on the turned sheet, so `size: 250mm 150mm` on A4 is no longer cut to 210 mm wide. [#552] [#562]
* `page-break-before: auto` or `avoid` on a block inside another block closed the outer block and opened it again, so its border was drawn around each part. `page-break-after: auto` or `avoid` on a table started a new page. [#552] [#569]
* `page-break-before` on a table was ignored. On a top-level table, `always`, `left` and `right` now start it on a new page, inside the blocks around it. [#552] [#622]
* A table with a `font-size` and a row with `text-rotate` threw a `TypeError` on PHP 8, and raised a warning before it. A cell whose font size was already in force saved its text with an empty size. [#632] [#631]
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
* A declaration whose flag had a space after the bang, such as `margin: 5mm ! important`, kept the flag in its value: that margin set the right and left margins to `!` and the bottom one to `important`, a `font-size` was lost, and a `border` drew nothing. The flag is now found whatever its spacing and case, in stylesheets and inline styles, in either `cssMode`. [#532] [#638]
* Text styled with the `font` shorthand or `<font face>` came out as empty boxes when the family was not installed or had a space in its name, such as `font: 12pt Roboto` or `font: 16px "DejaVu Sans Mono"`. It was drawn in the first registered font, which is the emoji font in a full install. The family is now read as `font-family` reads it: a name is kept whole, the list is tried in order, and when mPDF knows none of it the text keeps the family it inherits. `font-family` itself also reads an unquoted name with spaces whole. [#552] [#560]
* A descendant rule ending in `:lang()` or `[lang]`, such as `div :lang(fr)`, `div p:lang(fr)` or `table [lang=fr]`, never applied. It now applies in block content and in tables, and a regional language such as `fr-CA` falls back to the rule for `fr`, as the simple lang rules do. [#525] [#559]
* A rule such as `td:nth-child(2):not(.x)` or `td:nth-child(2 of .x)` was applied to the second cell of every row, as if it were `td:nth-child(2)`. A rule with anything after an nth-child argument, or with an argument that is not a formula, is now dropped. [mpdf/mpdf#83] [#522] [#564]
* `rgb()` and `hsl()` written with spaces, such as `color: rgb(255 0 0)`, threw a `TypeError` out of `WriteHTML()`, or drew the wrong colour with warnings. They are now read, with a slash before the alpha (`rgb(255 0 0 / 50%)`) and with the hue in `deg`, `grad`, `rad` or `turn`. [#552] [#563]
  * A percentage alpha, as in `rgba(255, 0, 0, 50%)`, gave an opacity above 1, which viewers draw opaque.
  * A fourth argument to `rgb()` or `hsl()` is read as the alpha instead of being dropped.
  * `border-color: rgb(255, 0, 0)` or `cmyk(0, 100, 0, 0)`, with spaces after the commas, drew black.
* A declaration mPDF cannot read replaced the value before it. A `calc()`, `min()`, `max()`, `clamp()` or `var()` length became 0, so `margin: calc(…)` removed the default margins, and a colour mPDF does not know, or one written with `var()`, drew the text black. Such a declaration is now dropped, as a browser drops it, so the earlier declaration, the default or the inherited value applies. [#552] [#565]
* `vw`, `vh`, `vmin`, `vmax`, `Q` and `ch` were read as pixels, `+5mm` and `1e+1mm` as 0, and `1,5mm` as 1mm. The units are now resolved against the page and the font size, the numbers are read, and `1,5mm` is dropped. [#552] [#565]
* `line-height: 0` gave lines a normal height. Lines now have no height, as in a browser. A line height well below the font size, such as `0.5` or `1mm`, was stretched down to the baseline; it is now kept. A negative `line-height`, which shrank lines to odd heights, is now ignored. Set `'cssMode' => \Mpdf\CssMode::LEGACY` to keep the old reading of all three. [#552] [#567] [#643]
* The rule after an `@supports`, `@layer`, `@keyframes`, `@container` or other block at-rule was lost, and so were the rules inside `@supports` and `@layer`. The rule after `@charset`, `@namespace` or `@import` was lost too. `@supports` and `@layer` blocks are now unwrapped as `@media` blocks are, except `@supports not`, and other at-rules are removed whole. [#524] [#566]
* `@media` was matched by looking for the word `print`, so `@media not print` applied, `orientation` was ignored and `min-width` never matched. Media queries are now evaluated for the print medium against the page current when the stylesheet is read: media types with `not` and `only`; `and`, `or` and `not` conditions; and `width`, `height` and `orientation`, with `min-`/`max-` prefixes and range syntax such as `(width >= 600px)`. An A4 page is about 794px wide, so `@media (min-width: 768px)` rules now apply to it. Set `'cssMode' => \Mpdf\CssMode::LEGACY` to keep matching by the word `print` or `all`. [#552] [#575] [#643]
* `@import` ignored its media list, and did not load a URL that does not end in `.css`, such as a Google Fonts stylesheet. Legacy mode loads them as before, except that in either mode an `@import` is read only from `<style>` blocks and stylesheets, not from the body text. [#552] [#575] [#643]
* A brace, semicolon or comment marker inside a quoted string, an unquoted `url()` or an escape broke the stylesheet around it. `q { quotes: "}" }` lost the rule after it, `font-family: 'a;color:red'` set the colour, a `/*` in one string and a `*/` in a later one removed every rule between them, and `@import url(…?family=Inter:wght@300;400)` lost the rule after it. A rule or at-rule nested in a block, as CSS nesting writes them, lost the rest of the block and the rule after it. Stylesheets are now split by a tokenizer that reads strings, `url()`, escapes and comments as CSS does, in either `cssMode`. A rule or at-rule nested in a block is left out, and the declarations after it apply. [#536] [#635]
  * A string left open ends at the line break, as in CSS. A block or comment left open ends with its `<style>` or stylesheet file instead of running into the next one.
  * A byte order mark at the start of a stylesheet, as `WriteHTML(file_get_contents('style.css'), HTMLParserMode::HEADER_CSS)` passes one on, no longer spoils the first rule.
  * An `@import` is loaded only from the stylesheet's own rules, not from a comment, a string or a block.
  * A `style` attribute is split the same way, so `style="font-family: 'a;b', serif; color: red"` applies both, and a value may run over several lines.
* An HTML comment between two words drew a space: `Book<!-- x -->keeper` read "Book keeper", and `<p><!-- x --></p>` held a space. A comment now leaves nothing in its place, as in a browser and as `HTMLParserMode::HTML_BODY` already had it, in either `cssMode`. A document that relied on the space can put one next to the comment. [#636] [#637]
  * In the default parser mode, a `<!--` inside a `<script>`, as in `var s = "<!--"`, drew part of the script and lost the text up to the end of the next comment.
  * In `HTMLParserMode::HTML_BODY`, a `<script>` tag inside a comment lost the text up to the next script's end.
* `td:nth-child()` and `th:nth-child()` counted grid columns, so a `colspan` or `rowspan` before a cell made the rule miss it and reach the cell after. They now count the cells of the row. `:first-child` on a `tr`, `td` or `th` now works, as `:nth-child(1)`. [#528] [#572]
* Some colour values were read wrongly. [#552] [#570]
  * `#rgba` was read from the wrong digits: `#f008` drew `rgb(240, 8, 0)`. `#rrggbbaa` dropped its alpha. Both are now read with their alpha.
  * A channel outside its range wrapped instead of being clamped: `rgb(255.5, 0, 0)` drew black and `rgb(300, 0, 0)` dark red. Channels, alphas, and `hsl()` saturation and lightness are now clamped, as CSS does.
  * `rebeccapurple` was missing. mPDF's `violetred`, which is not a CSS colour, is kept.
* A `list-style-image` whose URL had capitals, such as `url(img/List-Bullet.png)`, drew no marker on a case-sensitive disk or server, because the URL was lowercased before the image was fetched. The URL now keeps its case. [#552] [#623]
* The `border` shorthand drew nothing when its parts came in some orders, such as `border: solid #c00 2mm`, `2mm #c00 solid` or `red 2mm solid`. The width, style and colour of `border` and of each side are now read in any order. [#552] [#583]
  * A lone colour, as in `border-top: #f00`, was read as the width.
* The `background` shorthand lost a colour written after `url()` and misplaced the image, and lost a size after the position (`center / cover`). Its colour, image, position and size, repeat, attachment, and origin and clip boxes are now read in any order. [#552] [#583]
  * With several layers, mPDF still draws only the first, now over the colour given in the last.
* A `border` or `background` shorthand with a part that is none of its parts, such as `border: 1px solid bogus`, is dropped, as a browser drops it, so the value it would have replaced still applies. `border: 1px solid bogus` used to draw a black border. [#552] [#583]
* `tr:nth-child()` counted the rows of the whole table less its header and footer rows, so a second `<tbody>` carried on the count of the first, and `tr:first-child` missed the first footer row when `<tfoot>` came after the body. Rows are now counted within their `<thead>`, `<tbody>` or `<tfoot>`, or within the run of rows written straight into the table. [#528] [#582]
* `font: small-caps 14pt serif` drew the text in full-size capitals, as `text-transform: uppercase` does. It is now drawn in small capitals, as `font-variant: small-caps` draws it. The `font` shorthand also kept a `line-height` and a `font-variant` it inherited; like the style and weight, in standard mode they are now reset to `normal` when the shorthand does not name them, as in a browser. To keep a line height, give it in the shorthand, as in `font: 14pt/1.5 serif`, or declare `line-height` after it. A `font` shorthand with a negative line height, such as `font: 12pt/-1 serif`, is dropped whole. `font-variant: normal` now resets `font-variant-position` as well. [#552] [#581]
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
* `rotate: 90deg` on a positioned block turned it on PHP 5.6 to 7.4 but not on PHP 8, where `"90deg" == 90` is false. The angle is now read as tables read it: whole degrees, with or without `deg`, so `270deg` turns the block as `-90` does. A positioned block also takes `180deg` as it takes `180`. [#579]
* `background-size` lengths such as `60mm` or `100px` drew the image 2.83 times too small, because they were used as points. [#574]
* A stylesheet `url()` did not load its image when the path had spaces or parentheses, when there was whitespace inside the parentheses, or when it was an SVG data URI that is not base64, such as Bootstrap's `form-select` arrow. [#573]
* `linear-gradient(to bottom, …)` and `to top` were drawn upside down, and so was the vertical half of `to bottom right` and the other corners. Angles ran counter-clockwise from pointing right, so `90deg` drew bottom to top. A gradient now runs as CSS Images gives: `0deg` points up and angles turn clockwise, `turn` is read, and a corner keyword leaves the other two corners halfway along. `-moz-`, `-webkit-` and `-o-` gradients keep their legacy angles, and their side keyword, such as `left`, names the side the gradient starts from. Set `'cssMode' => \Mpdf\CssMode::LEGACY` to keep the directions mPDF v7 drew, prefixed or not. [#577] [#639]
* In a table with collapsed borders, a row with a `border-color` and no border style, or a border of no width, took the borders of its cells away. A reset such as `* { border-color: #dee2e6 }` or `* { border: 0 solid }` gives every row a border like that. A row border that draws nothing now leaves the cells' borders; `hidden` still takes them away. A row's `border-top` or `border-bottom` was drawn only when the row also set `border-left`; each side is now drawn on its own. [#644]
* Characters mPDF moved into a substitute font (`useSubstitutions`, and the fonts for supplementary planes) were wrapped in a `<span>` that stylesheet rules reached, so `span { border: 1px solid }` drew a box around them, and the span counted among its siblings for `:nth-child()`. In the standard CSS mode no rule reaches that span and it is not counted, as a browser has no such element. The legacy mode styles it as before. [#634]

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
* `font-size: larger` or `smaller` on an `<input>`, `<select>`, `<textarea>` or `<textcircle>` drew its text at 0pt, so it could not be seen. In the legacy CSS mode the value is now ignored and the element keeps the size around it; in the standard mode it is 1.2 times the document's size, or that divided by 1.2. [#545] [#647]
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
* **Glyph outline numbers.** A glyph outline's coordinates are written through `NumericString::decimal()`, as the colour glyphs and SVG paths write theirs. [#625]
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
* **Open elements.** `WriteHTML()` keeps a stack of the elements open in the HTML it reads, so that CSS rules can be matched against an element's parents, ancestors and earlier siblings rather than only the blocks mPDF lays out. Each element carries its position among its siblings and a record of the siblings before it. `Mpdf::getOpenElements()` reads it. [#533]
* **Compiled selectors.** A rule whose selector the legacy parser cannot read is compiled into its compound selectors, the combinators between them and its specificity, and filed under its rightmost compound for the selector matcher. A selector list is split at the commas outside parentheses, brackets and strings, so `:is(h1, h2)` and `[title="a,b"]` stay whole. [#538] [#619]
* **nth-child in tables from the open elements.** `tr`, `td` and `th:nth-child()` and `:first-child` take a row's or cell's position among its siblings from the stack of open elements, as the selector matcher does, instead of from mPDF's row and column counters. An element a browser moves out of a table, such as a `<bookmark>` or `<tocentry>` written between two cells, is not counted, and with `allow_html_optional_endtags` off a cell, row or row group with no end tag is closed by the next one, as mPDF lays it out. [#538] [#629]

[mpdf/mpdf#7]: https://github.com/mpdf/mpdf/issues/7
[mpdf/mpdf#83]: https://github.com/mpdf/mpdf/issues/83
[mpdf/mpdf#134]: https://github.com/mpdf/mpdf/issues/134
[mpdf/mpdf#312]: https://github.com/mpdf/mpdf/issues/312
[mpdf/mpdf#318]: https://github.com/mpdf/mpdf/issues/318
[mpdf/mpdf#747]: https://github.com/mpdf/mpdf/issues/747
[mpdf/mpdf#830]: https://github.com/mpdf/mpdf/issues/830
[mpdf/mpdf#833]: https://github.com/mpdf/mpdf/issues/833
[mpdf/mpdf#1010]: https://github.com/mpdf/mpdf/issues/1010
[mpdf/mpdf#1089]: https://github.com/mpdf/mpdf/issues/1089
[mpdf/mpdf#1168]: https://github.com/mpdf/mpdf/issues/1168
[mpdf/mpdf#1220]: https://github.com/mpdf/mpdf/issues/1220
[mpdf/mpdf#1334]: https://github.com/mpdf/mpdf/issues/1334
[mpdf/mpdf#1368]: https://github.com/mpdf/mpdf/issues/1368
[mpdf/mpdf#1384]: https://github.com/mpdf/mpdf/issues/1384
[mpdf/mpdf#1405]: https://github.com/mpdf/mpdf/issues/1405
[mpdf/mpdf#1707]: https://github.com/mpdf/mpdf/issues/1707
[mpdf/mpdf#1735]: https://github.com/mpdf/mpdf/issues/1735
[mpdf/mpdf#1775]: https://github.com/mpdf/mpdf/issues/1775
[mpdf/mpdf#1831]: https://github.com/mpdf/mpdf/issues/1831
[mpdf/mpdf#1838]: https://github.com/mpdf/mpdf/issues/1838
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
[#524]: https://github.com/GravityPDF/mpdf/issues/524
[#549]: https://github.com/GravityPDF/mpdf/pull/549
[#561]: https://github.com/GravityPDF/mpdf/pull/561
[#525]: https://github.com/GravityPDF/mpdf/issues/525
[#526]: https://github.com/GravityPDF/mpdf/issues/526
[#527]: https://github.com/GravityPDF/mpdf/issues/527
[#528]: https://github.com/GravityPDF/mpdf/issues/528
[#529]: https://github.com/GravityPDF/mpdf/issues/529
[#530]: https://github.com/GravityPDF/mpdf/issues/530
[#531]: https://github.com/GravityPDF/mpdf/issues/531
[#532]: https://github.com/GravityPDF/mpdf/issues/532
[#533]: https://github.com/GravityPDF/mpdf/issues/533
[#535]: https://github.com/GravityPDF/mpdf/issues/535
[#536]: https://github.com/GravityPDF/mpdf/issues/536
[#537]: https://github.com/GravityPDF/mpdf/issues/537
[#538]: https://github.com/GravityPDF/mpdf/issues/538
[#545]: https://github.com/GravityPDF/mpdf/issues/545
[#548]: https://github.com/GravityPDF/mpdf/issues/548
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
[#579]: https://github.com/GravityPDF/mpdf/pull/579
[#574]: https://github.com/GravityPDF/mpdf/pull/574
[#573]: https://github.com/GravityPDF/mpdf/pull/573
[#569]: https://github.com/GravityPDF/mpdf/pull/569
[#577]: https://github.com/GravityPDF/mpdf/pull/577
[#568]: https://github.com/GravityPDF/mpdf/pull/568
[#567]: https://github.com/GravityPDF/mpdf/pull/567
[#566]: https://github.com/GravityPDF/mpdf/pull/566
[#575]: https://github.com/GravityPDF/mpdf/pull/575
[#572]: https://github.com/GravityPDF/mpdf/pull/572
[#570]: https://github.com/GravityPDF/mpdf/pull/570
[#578]: https://github.com/GravityPDF/mpdf/pull/578
[#580]: https://github.com/GravityPDF/mpdf/pull/580
[#583]: https://github.com/GravityPDF/mpdf/pull/583
[#582]: https://github.com/GravityPDF/mpdf/pull/582
[#581]: https://github.com/GravityPDF/mpdf/pull/581
[#614]: https://github.com/GravityPDF/mpdf/pull/614
[#615]: https://github.com/GravityPDF/mpdf/pull/615
[#619]: https://github.com/GravityPDF/mpdf/pull/619
[#620]: https://github.com/GravityPDF/mpdf/pull/620
[#621]: https://github.com/GravityPDF/mpdf/pull/621
[#622]: https://github.com/GravityPDF/mpdf/pull/622
[#623]: https://github.com/GravityPDF/mpdf/pull/623
[#625]: https://github.com/GravityPDF/mpdf/pull/625
[#627]: https://github.com/GravityPDF/mpdf/pull/627
[#628]: https://github.com/GravityPDF/mpdf/pull/628
[#629]: https://github.com/GravityPDF/mpdf/pull/629
[#631]: https://github.com/GravityPDF/mpdf/pull/631
[#632]: https://github.com/GravityPDF/mpdf/issues/632
[#633]: https://github.com/GravityPDF/mpdf/pull/633
[#634]: https://github.com/GravityPDF/mpdf/pull/634
[#635]: https://github.com/GravityPDF/mpdf/pull/635
[#636]: https://github.com/GravityPDF/mpdf/issues/636
[#637]: https://github.com/GravityPDF/mpdf/pull/637
[#638]: https://github.com/GravityPDF/mpdf/pull/638
[#639]: https://github.com/GravityPDF/mpdf/pull/639
[#641]: https://github.com/GravityPDF/mpdf/pull/641
[#646]: https://github.com/GravityPDF/mpdf/pull/646
[#642]: https://github.com/GravityPDF/mpdf/pull/642
[#643]: https://github.com/GravityPDF/mpdf/pull/643
[#644]: https://github.com/GravityPDF/mpdf/pull/644
[#647]: https://github.com/GravityPDF/mpdf/pull/647
