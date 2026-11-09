#!/usr/bin/env python3
"""
Strips TrueType hinting from the packaged fonts below, leaving their outlines and metrics as they were:

    python3 -I utils/dehint_fonts.py
    composer ranges:update

Needs fontTools. The ranges files record each font's size, so they follow the fonts.

A font not listed has next to no hinting, a licence that rules this out or one that doesn't clearly allow
it: a Reserved Font Name (Abyssinica, Dai Banna, Dhyana, Eeyek, Jomolhari, Lateef, Padauk, Tai Heritage,
TharLon), no modification (Estrangelo Edessa, Sun-ExtA, Aboriginal Serif, whose name table forbids copying
despite the package's GPL) or none stated (Zawgyi-One, Kaputa, Lanna Alif). Sun-ExtB has no glyph
instructions.
"""

import os

from fontTools.ttLib import TTFont

FONTS = {
	"Aboriginal-Family": [
		"AboriginalSansBOLD.ttf",
		"AboriginalSansBOLDITALIC.ttf",
		"AboriginalSansITALIC.ttf",
		"AboriginalSansREGULAR.ttf",
	],
	"Dejavu-Family": [
		"DejaVuSans-Bold.ttf",
		"DejaVuSans-BoldOblique.ttf",
		"DejaVuSans-Oblique.ttf",
		"DejaVuSans.ttf",
		"DejaVuSansCondensed-Bold.ttf",
		"DejaVuSansCondensed-BoldOblique.ttf",
		"DejaVuSansCondensed-Oblique.ttf",
		"DejaVuSansCondensed.ttf",
		"DejaVuSansMono-Bold.ttf",
		"DejaVuSansMono-BoldOblique.ttf",
		"DejaVuSansMono-Oblique.ttf",
		"DejaVuSansMono.ttf",
		"DejaVuSerif-Bold.ttf",
		"DejaVuSerif-BoldItalic.ttf",
		"DejaVuSerif-Italic.ttf",
		"DejaVuSerif.ttf",
		"DejaVuSerifCondensed-Bold.ttf",
		"DejaVuSerifCondensed-BoldItalic.ttf",
		"DejaVuSerifCondensed-Italic.ttf",
		"DejaVuSerifCondensed.ttf",
	],
	"Free-Family": [
		"FreeMono.ttf",
		"FreeMonoBold.ttf",
		"FreeMonoBoldOblique.ttf",
		"FreeMonoOblique.ttf",
		"FreeSans.ttf",
		"FreeSansBold.ttf",
		"FreeSansBoldOblique.ttf",
		"FreeSansOblique.ttf",
		"FreeSerif.ttf",
		"FreeSerifBold.ttf",
		"FreeSerifBoldItalic.ttf",
		"FreeSerifItalic.ttf",
	],
	"Garuda": [
		"Garuda-Bold.ttf",
		"Garuda-BoldOblique.ttf",
		"Garuda-Oblique.ttf",
		"Garuda.ttf",
	],
	"Khmer-OS": ["KhmerOS.ttf"],
	"Lohit-Kannada": ["Lohit-Kannada.ttf"],
	"Middle-East-Scripts-Bundle": [
		"XB Riyaz.ttf",
		"XB RiyazBd.ttf",
		"XB RiyazBdIt.ttf",
		"XB RiyazIt.ttf",
	],
	"Mph2bDamase": ["damase_v.2.ttf"],
	"Myanmar-Bundle": ["ayar.ttf"],
	"Pothana2000": ["Pothana2000.ttf"],
	"UnBatang": [
		"UnBatang.ttf",
		"UnBatangBold.ttf",
	],
}

# DSIG goes too: a signature over the original font doesn't hold for this one
TABLES = ("cvt ", "fpgm", "prep", "gasp", "hdmx", "LTSH", "VDMX", "DSIG")

packages = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", "packages")

for package, names in FONTS.items():
	for name in names:
		path = os.path.join(packages, package, "fonts", name)
		font = TTFont(path, recalcBBoxes=False, recalcTimestamp=False)
		for table in TABLES:
			if table in font:
				del font[table]
		font["glyf"].removeHinting()
		font["maxp"].maxSizeOfInstructions = 0
		font.save(path)
