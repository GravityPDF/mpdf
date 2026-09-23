<?xml version="1.0" encoding="UTF-8"?>

<!--
	What a PDF/X-4 document written by mPDF must look like, as a policy over the features veraPDF
	extracts from it (the veraPDF CLI's policyfile option).

	This is NOT PDF/X-4 validation. veraPDF validates PDF/A, PDF/UA and WTPDF only; it has no PDF/X
	profile and the project does not intend to write one. Every assertion below is a regression test
	over a feature report, not a conformance verdict. Real conformance still needs a preflight tool.

	Each assertion opens with its name in brackets, which utils/verapdf/pdfx4_policy.php matches to
	decide whether the assertion mPDF was meant to break is the one that broke.

	ISO Schematron, XSLT 1.0: veraPDF refuses queryBinding="xslt2", so no let, no matches(), no min().
-->

<schema xmlns="http://purl.oclc.org/dsdl/schematron">

	<title>mPDF PDF/X-4 output policy</title>

	<ns prefix="dc" uri="http://purl.org/dc/elements/1.1/"/>
	<ns prefix="pdfxid" uri="http://www.npes.org/pdfx/ns/id/"/>
	<ns prefix="rdf" uri="http://www.w3.org/1999/02/22-rdf-syntax-ns#"/>

	<pattern id="output-intent">
		<rule context="/report/jobs/job/featuresReport">

			<assert test="count(outputIntents/outputIntent[subtype = 'GTS_PDFX']) = 1">[output-intent] A PDF/X document carries exactly one GTS_PDFX output intent, and this one carries <value-of select="count(outputIntents/outputIntent[subtype = 'GTS_PDFX'])"/>.</assert>

			<assert test="count(outputIntents/outputIntent[subtype = 'GTS_PDFX']/destOutputIntent) = 1">[dest-output-profile] The output intent of a PDF/X-4 document names an embedded ICC profile in /DestOutputProfile, and this one names none.</assert>

			<assert test="count(iccProfiles/iccProfile[@id = current()/outputIntents/outputIntent/destOutputIntent/@id][normalize-space(dataColorSpace) = 'GRAY' or normalize-space(dataColorSpace) = 'RGB' or normalize-space(dataColorSpace) = 'CMYK']) = 1">[intent-colour-space] The profile the output intent names is embedded and prints to grey, RGB or CMYK.</assert>

		</rule>
	</pattern>

	<!--
		A colour space is used where something names it: a shading, an image or form XObject, a page's
		resources, or the base of an Indexed space. The alternate of an ICC-based space is not a use of
		it - every ICC-based space in a document from mPDF carries DeviceRGB as its alternate.
	-->
	<pattern id="colour-spaces">
		<rule context="/report/jobs/job/featuresReport">

			<assert test="not(iccProfiles/iccProfile[@id = current()/outputIntents/outputIntent/destOutputIntent/@id][normalize-space(dataColorSpace) = 'CMYK' or normalize-space(dataColorSpace) = 'GRAY']) or count(documentResources/colorSpaces/colorSpace[@family = 'DeviceRGB'][@id = current()/documentResources/shadings/shading/colorSpace/@id or @id = current()/documentResources/xobjects/xobject/colorSpace/@id or @id = current()/pages/page/resources/colorSpaces/colorSpace/@id or @id = current()/documentResources/colorSpaces/colorSpace/base/@id]) = 0">[no-device-rgb] Printing to a CMYK or grey output intent, a PDF/X-4 document may not use DeviceRGB, and this one does.</assert>

			<assert test="not(iccProfiles/iccProfile[@id = current()/outputIntents/outputIntent/destOutputIntent/@id][normalize-space(dataColorSpace) = 'RGB' or normalize-space(dataColorSpace) = 'GRAY']) or count(documentResources/colorSpaces/colorSpace[@family = 'DeviceCMYK'][@id = current()/documentResources/shadings/shading/colorSpace/@id or @id = current()/documentResources/xobjects/xobject/colorSpace/@id or @id = current()/pages/page/resources/colorSpaces/colorSpace/@id or @id = current()/documentResources/colorSpaces/colorSpace/base/@id]) = 0">[no-device-cmyk] Printing to an RGB or grey output intent, a PDF/X-4 document may not use DeviceCMYK, and this one does.</assert>

		</rule>
	</pattern>

	<!--
		/N on an ICC-based colour space against the profile it wraps. veraPDF reports no /N for the
		output intent's own profile, so the same check cannot be made of that one.
	-->
	<pattern id="icc-components">
		<rule context="/report/jobs/job/featuresReport/documentResources/colorSpaces/colorSpace[@family = 'ICCBased']">

			<assert test="(components = '1' and normalize-space(/report/jobs/job/featuresReport/iccProfiles/iccProfile[@id = current()/iccProfile/@id]/dataColorSpace) = 'GRAY') or (components = '3' and (normalize-space(/report/jobs/job/featuresReport/iccProfiles/iccProfile[@id = current()/iccProfile/@id]/dataColorSpace) = 'RGB' or normalize-space(/report/jobs/job/featuresReport/iccProfiles/iccProfile[@id = current()/iccProfile/@id]/dataColorSpace) = 'Lab')) or (components = '4' and normalize-space(/report/jobs/job/featuresReport/iccProfiles/iccProfile[@id = current()/iccProfile/@id]/dataColorSpace) = 'CMYK')">[icc-components] An ICC-based colour space says it has <value-of select="components"/> components, and the profile it wraps prints to <value-of select="normalize-space(/report/jobs/job/featuresReport/iccProfiles/iccProfile[@id = current()/iccProfile/@id]/dataColorSpace)"/>.</assert>

		</rule>
	</pattern>

	<pattern id="identification">
		<rule context="/report/jobs/job/featuresReport">

			<assert test="lowLevelInfo/pdfVersion = '1.6'">[pdf-version] PDF/X-4 is written as PDF 1.6, and this document says <value-of select="lowLevelInfo/pdfVersion"/>.</assert>

			<assert test="metadata/xmpPackage//rdf:Description/@pdfxid:GTS_PDFXVersion = 'PDF/X-4'">[xmp-pdfx-version] The XMP metadata identify the document as PDF/X-4 through the pdfxid schema.</assert>

			<assert test="informationDict/entry[@key = 'GTS_PDFXVersion'] = 'PDF/X-4'">[info-pdfx-version] The Info dictionary identifies the document as PDF/X-4.</assert>

			<assert test="normalize-space(metadata/xmpPackage//dc:title) != ''">[xmp-title] PDF/X expects a document title, and the XMP metadata carry none in dc:title.</assert>

			<assert test="normalize-space(informationDict/entry[@key = 'Title']) != ''">[info-title] PDF/X expects a document title, and the Info dictionary carries none.</assert>

		</rule>
	</pattern>

	<pattern id="interactive">
		<rule context="/report/jobs/job/featuresReport">

			<assert test="count(actions/action[@type = 'JavaScript']) = 0">[no-javascript] PDF/X permits no JavaScript, and this document runs some.</assert>

			<assert test="count(annotations/annotation[subType = 'Widget']) = 0">[no-widget] PDF/X permits no interactive form field, and this document has <value-of select="count(annotations/annotation[subType = 'Widget'])"/>.</assert>

			<assert test="count(annotations/annotation[subType = 'FileAttachment']) = 0">[no-file-attachment] PDF/X permits no file attachment, and this document has <value-of select="count(annotations/annotation[subType = 'FileAttachment'])"/>.</assert>

			<assert test="count(annotations/annotation[subType = 'Link']) = 0">[no-link] PDF/X permits no annotation within the printed area. These documents are printed to the trimmed page, so a link anywhere on them is within it, and this one has <value-of select="count(annotations/annotation[subType = 'Link'])"/>.</assert>

		</rule>
	</pattern>

	<pattern id="images">
		<rule context="/report/jobs/job/featuresReport/documentResources/xobjects/xobject[@type = 'image']">

			<assert test="interpolate = 'false'">[no-interpolation] PDF/X permits no interpolation of an image, and this one asks for it.</assert>

		</rule>
	</pattern>

</schema>
