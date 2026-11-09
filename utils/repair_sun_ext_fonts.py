#!/usr/bin/env python3
"""
Repairs the packaged Sun-Ext fonts so a browser can load them, and an OpenType sanitizer pass them:

    python3 -I utils/repair_sun_ext_fonts.py
    composer ranges:update

Needs fontTools. The ranges files record each font's size, so they follow the fonts.

The OpenType Sanitizer in Chromium and Firefox rejects both fonts: Sun-ExtA's vhea claims more metrics than
it has glyphs, and Sun-ExtB's vmtx is too short for its glyphs. mPDF reads none of the vertical tables, so
they go. Sun-ExtA's GSUB also names glyphs it doesn't have, which a stricter sanitizer rejects, so those
entries go and the valid ones stay.

The font is opened lazily and the other tables are never decoded, so they're written back byte for byte.
"""

import os

from fontTools.ttLib import TTFont
from fontTools.ttLib.tables.DefaultTable import DefaultTable

FONTS = ("Sun-ExtA.ttf", "Sun-ExtB.ttf")

# DSIG goes too: a signature over the original font doesn't hold for this one
TABLES = ("vhea", "vmtx", "VORG", "DSIG")

SINGLE_SUBSTITUTION = 1
LIGATURE_SUBSTITUTION = 4


def repaired_gsub(path):
	"""The compiled GSUB without entries that name a glyph outside the font, or None if it has none."""
	font = TTFont(path, lazy=True)

	if "GSUB" not in font:
		return None

	glyphs = font["maxp"].numGlyphs
	gsub = font["GSUB"]
	removed = False

	def exists(*names):
		return all(font.getGlyphID(name) < glyphs for name in names)

	for lookup in gsub.table.LookupList.Lookup:
		for subtable in lookup.SubTable:
			if lookup.LookupType == SINGLE_SUBSTITUTION:
				valid = {source: target for source, target in subtable.mapping.items() if exists(source, target)}
				removed = removed or len(valid) != len(subtable.mapping)
				subtable.mapping = valid
			elif lookup.LookupType == LIGATURE_SUBSTITUTION:
				for first, ligatures in list(subtable.ligatures.items()):
					valid = [l for l in ligatures if exists(first, l.LigGlyph, *l.Component)]
					removed = removed or len(valid) != len(ligatures)
					if valid:
						subtable.ligatures[first] = valid
					else:
						del subtable.ligatures[first]

	return gsub.compile(font) if removed else None


directory = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", "packages", "SunExt", "fonts")

for name in FONTS:
	path = os.path.join(directory, name)
	gsub = repaired_gsub(path)
	font = TTFont(path, lazy=True, recalcBBoxes=False, recalcTimestamp=False)
	for table in TABLES:
		if table in font:
			del font[table]
	if gsub is not None:
		font["GSUB"] = DefaultTable("GSUB")
		font["GSUB"].data = gsub
	font.save(path + ".tmp")
	font.close()
	os.replace(path + ".tmp", path)
