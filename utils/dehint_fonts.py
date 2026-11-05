#!/usr/bin/env python3
"""
Strips TrueType hinting from the fonts below, leaving their outlines and metrics as they were:

    python3 -I utils/dehint_fonts.py
    composer ranges:update

Needs fontTools. The ranges files record each font's size, so they follow the fonts.

A font not listed either has next to no hinting, or a licence that rules this out: a Reserved Font
Name (Abyssinica, Dai Banna, Dhyana, Jomolhari, Lateef, Padauk, Taamey David, Tai Heritage,
TharLon), no modification (Uthman, Estrangelo Edessa, Sun-ExtA) or none stated.
"""

import os

from fontTools.ttLib import TTFont

FONTS = [
	"AboriginalSansREGULAR.ttf",
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
	"Garuda-Bold.ttf",
	"Garuda-BoldOblique.ttf",
	"Garuda-Oblique.ttf",
	"Garuda.ttf",
	"KhmerOS.ttf",
	"Lohit-Kannada.ttf",
	"Pothana2000.ttf",
	"UnBatang_0613.ttf",
	"XB Riyaz.ttf",
	"XB RiyazBd.ttf",
	"XB RiyazBdIt.ttf",
	"XB RiyazIt.ttf",
	"ayar.ttf",
	"damase_v.2.ttf",
]

# DSIG goes too: a signature over the original font doesn't hold for this one
TABLES = ("cvt ", "fpgm", "prep", "gasp", "hdmx", "LTSH", "VDMX", "DSIG")

directory = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", "ttfonts")

for name in FONTS:
	path = os.path.join(directory, name)
	font = TTFont(path, recalcBBoxes=False, recalcTimestamp=False)
	for table in TABLES:
		if table in font:
			del font[table]
	font["glyf"].removeHinting()
	font["maxp"].maxSizeOfInstructions = 0
	font.save(path)
