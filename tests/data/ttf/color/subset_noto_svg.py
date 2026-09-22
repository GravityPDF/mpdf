"""Builds NotoColorEmoji-SVG-Subset.ttf, the real SVG emoji font ColorEmojiSvgNotoSnapshotTest draws.

It starts from Adobe's OpenType-SVG build of Noto Color Emoji, version 2.100, licensed under the SIL
Open Font License 1.1 (NotoColorEmoji-SVG-OFL.txt beside it):

    gh release download 2.100 -R adobe-fonts/noto-emoji-svg -p NotoColorEmoji-SVG.otf
    python3 subset_noto_svg.py NotoColorEmoji-SVG.otf

It modifies that font in two ways. It keeps only the glyphs the emoji below need, and it converts the
fallback outlines from CFF to TrueType, since mPDF reads only TrueType outlines. The SVG documents,
gradients and all, are kept as they are.
"""

import sys

from fontTools import subset
from fontTools.pens.cu2quPen import Cu2QuPen
from fontTools.pens.ttGlyphPen import TTGlyphPen
from fontTools.ttLib import TTFont, newTable

# The emoji the other colour emoji snapshots draw, then a few whose artwork is mostly gradients
EMOJI = ('\U0001F600 ❤️ \U0001F468 \U0001F469 \U0001F467 \U0001F1E6 \U0001F1FA \U0001F3F4 \U0001F3FD \U0001F44D '
         '\U0001F468‍\U0001F469‍\U0001F467 \U0001F1E6\U0001F1FA 1️⃣ \U0001F44D\U0001F3FD '
         '\U0001F3F4\U000E0067\U000E0062\U000E0065\U000E006E\U000E0067\U000E007F '
         '\U0001F308 \U0001F525 \U0001F389 \U0001F355 \U0001F680 ✨ \U0001F30D')


def truetype(font):
    """The font's CFF outlines as TrueType ones, quadratic curves within a font unit of the cubics."""
    order = font.getGlyphOrder()
    glyphs = font.getGlyphSet()
    glyf = newTable('glyf')
    glyf.glyphOrder = order
    glyf.glyphs = {}
    for name in order:
        pen = TTGlyphPen(None)
        glyphs[name].draw(Cu2QuPen(pen, 1.0, reverse_direction=True))
        glyf.glyphs[name] = pen.glyph()

    font['glyf'] = glyf
    font['loca'] = newTable('loca')
    del font['CFF ']

    maxp = font['maxp']
    maxp.tableVersion = 0x00010000
    for field in ('maxTwilightPoints', 'maxStorage', 'maxFunctionDefs', 'maxInstructionDefs', 'maxStackElements',
                  'maxSizeOfInstructions', 'maxComponentElements'):
        setattr(maxp, field, 0)
    maxp.maxZones = 1
    font['head'].glyphDataFormat = 0
    font['post'].formatType = 3.0
    font.sfntVersion = '\x00\x01\x00\x00'


def main(source):
    options = subset.Options()
    options.layout_features = ['*']
    options.name_IDs = ['*']
    options.notdef_outline = True
    options.drop_tables += ['DSIG']

    font = TTFont(source, recalcTimestamp=False)
    subsetter = subset.Subsetter(options)
    subsetter.populate(text=EMOJI)
    subsetter.subset(font)
    truetype(font)
    font.save('NotoColorEmoji-SVG-Subset.ttf')


if __name__ == '__main__':
    main(sys.argv[1])
