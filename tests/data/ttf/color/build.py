"""
Builds the colour emoji fixture fonts in this directory from one set of designs:

    python3 tests/data/ttf/color/build.py

Needs fontTools and Pillow. Each font has the same glyph order, cmap, metrics and GSUB, so a test can
render the same text through every format and expect the same glyphs; only how a glyph is coloured
differs.

    TestEmoji-COLRv0.ttf  glyf outlines, COLR version 0 layers, CPAL with two palettes
    TestEmoji-FE0F.ttf    the COLRv0 font with Twemoji's GSUB: U+FE0F inside its sequences
    TestEmoji-COLRv1.ttf  glyf outlines, COLR version 1 paints of every kind (and the version 0 records)
    TestEmoji-CBDT.ttf    no outlines at all, PNG bitmaps in CBDT/CBLC at two strikes
    TestEmoji-sbix.ttf    empty outlines, PNG and JPEG bitmaps in sbix at two strikes

The GSUB is the shape Noto's is: one 'ccmp' feature under DFLT, ligatures for a ZWJ family, a flag, a
keycap, a skin tone and a subdivision flag, none of them with U+FE0F in the sequence. TestEmoji-FE0F
forms its keycap from the fully qualified sequence instead, digit, U+FE0F, U+20E3, as Twemoji does.

The CBDT strike at 64ppem holds one glyph per index subtable format (1 to 5), and so exercises every
image format mPDF reads (17, 18 and 19). The sbix strike at 64ppem holds a glyph of each graphic type
beside the PNGs: the woman is a 'dupe' of the man, the girl a 'jpg ', the regional indicator U a
'flip' of the thumb, so that the mirroring shows, and the skin tone a 'tiff', which mPDF does not
draw.

The fonts are committed; this is kept so they can be rebuilt and so what is in them can be read.
"""

import io
import os
import struct

from PIL import Image, ImageDraw
from fontTools.colorLib.builder import buildCOLR, buildCPAL, populateCOLRv0
from fontTools.feaLib.builder import addOpenTypeFeaturesFromString
from fontTools.fontBuilder import FontBuilder
from fontTools.pens.ttGlyphPen import TTGlyphPen
from fontTools.ttLib import newTable
from fontTools.ttLib.tables.DefaultTable import DefaultTable
from fontTools.ttLib.tables._g_l_y_f import Glyph, GlyphComponent
from fontTools.ttLib.tables.otTables import CompositeMode, ExtendMode
from fontTools.ttLib.tables.sbixGlyph import Glyph as SbixGlyph
from fontTools.ttLib.tables.sbixStrike import Strike
from fontTools.misc.timeTools import timestampFromString

HERE = os.path.dirname(os.path.abspath(__file__))
UPEM = 1000
ASCENT = 950
DESCENT = -250

# Palette entries, as RGBA. The second palette swaps the warm colours for cool ones.
PALETTES = [
    [(0xFF, 0xCC, 0x33, 0xFF), (0x00, 0x00, 0x00, 0xFF), (0xE0, 0x24, 0x5E, 0xFF), (0x33, 0x66, 0xCC, 0xFF),
     (0xC6, 0x86, 0x42, 0xFF), (0xFF, 0xFF, 0xFF, 0x80), (0x22, 0xAA, 0x44, 0xFF), (0xFF, 0xFF, 0xFF, 0xFF),
     (0x99, 0x99, 0x99, 0xFF)],
    [(0x33, 0xCC, 0xFF, 0xFF), (0x00, 0x00, 0x00, 0xFF), (0x5E, 0x24, 0xE0, 0xFF), (0xCC, 0x66, 0x33, 0xFF),
     (0x42, 0x86, 0xC6, 0xFF), (0xFF, 0xFF, 0xFF, 0x80), (0x44, 0xAA, 0x22, 0xFF), (0xFF, 0xFF, 0xFF, 0xFF),
     (0x99, 0x99, 0x99, 0xFF)],
]
YELLOW, BLACK, RED, BLUE, SKIN, HALF_WHITE, GREEN, WHITE, GREY = range(9)
FOREGROUND = 0xFFFF


def circle(pen, cx, cy, r):
    """A circle as TrueType draws one: eight off-curve points and every on-curve point implied."""
    k = r * 0.41421356  # tan(22.5deg)
    points = [
        (cx + r, cy + k), (cx + k, cy + r), (cx - k, cy + r), (cx - r, cy + k),
        (cx - r, cy - k), (cx - k, cy - r), (cx + k, cy - r), (cx + r, cy - k),
    ]
    pen.qCurveTo(*[(round(x), round(y)) for x, y in points], None)
    pen.closePath()


def polygon(pen, points):
    pen.moveTo(points[0])
    for point in points[1:]:
        pen.lineTo(point)
    pen.closePath()


def rect(x0, y0, x1, y1):
    return [(x0, y0), (x0, y1), (x1, y1), (x1, y0)]


# Each shape: how to draw it with a pen (glyf) and with PIL (bitmaps), in font units
SHAPES = {
    'layer.face': ('circle', (500, 350, 450)),
    'layer.eyes': ('polygons', [rect(330, 400, 420, 560), rect(580, 400, 670, 560)]),
    'layer.mouth': ('polygons', [[(300, 250), (700, 250), (500, 120)]]),
    'layer.heart': ('polygons', [[(500, -50), (80, 400), (250, 750), (500, 600), (750, 750), (920, 400)]]),
    'layer.shine': ('polygons', [rect(250, 450, 380, 580)]),
    'layer.square': ('polygons', [rect(50, -100, 950, 800)]),
    'layer.cross': ('polygons', [rect(420, -100, 580, 800), rect(50, 270, 950, 430)]),
    'layer.stripe': ('polygons', [rect(50, 250, 950, 450)]),
    'layer.pole': ('polygons', [rect(100, -100, 180, 850), rect(180, 400, 900, 850)]),
    'layer.thumb': ('polygons', [[(150, -50), (150, 450), (400, 800), (500, 800), (450, 450), (850, 450), (850, -50)]]),
    'layer.frame': ('polygons', [rect(50, -100, 950, 800), rect(150, 0, 850, 700)]),
}

# Composites, for the outline decoder: the face shrunk and moved, as a family draws three of them
COMPOSITES = {
    'layer.small.left': ('layer.face', 0.4, (0, 300)),
    'layer.small.middle': ('layer.face', 0.4, (300, 0)),
    'layer.small.right': ('layer.face', 0.4, (600, 300)),
}

# The emoji: glyph name, the codepoint cmap maps to it (None for a ligature), and its layers
EMOJI = [
    ('u1F600', 0x1F600, [('layer.face', YELLOW), ('layer.eyes', BLACK), ('layer.mouth', FOREGROUND)]),
    ('uni2764', 0x2764, [('layer.heart', RED), ('layer.shine', HALF_WHITE)]),
    ('u1F468', 0x1F468, [('layer.face', BLUE), ('layer.eyes', BLACK)]),
    ('u1F469', 0x1F469, [('layer.face', RED), ('layer.eyes', BLACK)]),
    ('u1F467', 0x1F467, [('layer.face', GREEN), ('layer.eyes', BLACK)]),
    ('u1F1E6', 0x1F1E6, [('layer.square', BLUE)]),
    ('u1F1FA', 0x1F1FA, [('layer.square', RED)]),
    ('u1F3F4', 0x1F3F4, [('layer.pole', BLACK)]),
    ('u1F3FD', 0x1F3FD, [('layer.square', SKIN)]),
    ('u1F44D', 0x1F44D, [('layer.thumb', YELLOW)]),
    ('u1F468_200D_u1F469_200D_u1F467', None,
     [('layer.small.left', BLUE), ('layer.small.middle', GREEN), ('layer.small.right', RED)]),
    ('u1F1E6_u1F1FA', None, [('layer.square', BLUE), ('layer.stripe', WHITE)]),
    ('one_uni20E3', None, [('layer.frame', GREY), ('one', BLACK)]),
    ('u1F44D_u1F3FD', None, [('layer.thumb', SKIN)]),
    ('u1F3F4_E0067_E0062_E0065_E006E_E0067_E007F', None, [('layer.square', WHITE), ('layer.cross', RED)]),
]

# Plain glyphs: name, codepoint, advance, and an outline or None. These have no colour record, so a
# colour font draws them in the colour of the text.
PLAIN = [
    ('.notdef', None, 500, rect(50, 0, 450, 700)),
    ('space', 0x20, 300, None),
    ('numbersign', 0x23, 600, rect(100, 0, 500, 700)),
    ('one', 0x31, 600, [(250, 0), (250, 600), (150, 500), (150, 600), (300, 700), (350, 700), (350, 0)]),
    ('uni200D', 0x200D, 0, None),
    ('uni20E3', 0x20E3, 1000, None),
    ('uniFE0F', 0xFE0F, 0, None),
    ('uE0062', 0xE0062, 0, None),
    ('uE0065', 0xE0065, 0, None),
    ('uE0067', 0xE0067, 0, None),
    ('uE006E', 0xE006E, 0, None),
    ('uE007F', 0xE007F, 0, None),
]

FEATURES = """
languagesystem DFLT dflt;
feature ccmp {
    sub u1F468 uni200D u1F469 uni200D u1F467 by u1F468_200D_u1F469_200D_u1F467;
    sub u1F1E6 u1F1FA by u1F1E6_u1F1FA;
    sub one uni20E3 by one_uni20E3;
    sub u1F44D u1F3FD by u1F44D_u1F3FD;
    sub u1F3F4 uE0067 uE0062 uE0065 uE006E uE0067 uE007F by u1F3F4_E0067_E0062_E0065_E006E_E0067_E007F;
} ccmp;
"""


def glyph_order():
    order = [name for name, _, _, _ in PLAIN]
    order += [name for name, _, _ in EMOJI]
    order += sorted(SHAPES) + sorted(COMPOSITES)
    return order


def cmap():
    mapping = {}
    for name, codepoint, _, _ in PLAIN:
        if codepoint is not None:
            mapping[codepoint] = name
    for name, codepoint, _ in EMOJI:
        if codepoint is not None:
            mapping[codepoint] = name
    return mapping


def draw_shape(pen, shape):
    kind, data = SHAPES[shape]
    if kind == 'circle':
        circle(pen, *data)
    else:
        for points in data:
            polygon(pen, points)


def outlines(with_outlines):
    """Every glyph's outline. An emoji's own outline is its layers drawn in one colour, which is the
    fallback a renderer without colour support shows."""
    glyphs = {}
    for name, _, _, points in PLAIN:
        pen = TTGlyphPen(None)
        if points and with_outlines:
            polygon(pen, points)
        glyphs[name] = pen.glyph()
    for shape in SHAPES:
        pen = TTGlyphPen(None)
        if with_outlines:
            draw_shape(pen, shape)
        glyphs[shape] = pen.glyph()
    for name, (base, scale, offset) in COMPOSITES.items():
        if with_outlines:
            component = GlyphComponent()
            component.glyphName = base
            component.x, component.y = offset
            component.flags = 0x0004  # ROUND_XY_TO_GRID
            component.transform = [[scale, 0], [0, scale]]
            glyph = Glyph()
            glyph.numberOfContours = -1
            glyph.components = [component]
            glyphs[name] = glyph
        else:
            glyphs[name] = TTGlyphPen(None).glyph()
    for name, _, layers in EMOJI:
        pen = TTGlyphPen(None)
        if with_outlines:
            for layer, _ in layers:
                if layer in SHAPES:
                    draw_shape(pen, layer)
        glyphs[name] = pen.glyph()
    return glyphs


def metrics(glyf=None):
    """Advance and left side bearing. The bearing is the outline's own xMin, as TrueType requires:
    a renderer places the outline by it, so any other value moves the drawing."""
    advances = {name: advance for name, _, advance, _ in PLAIN}
    result = {}
    for name in glyph_order():
        lsb = 0
        if glyf is not None:
            glyph = glyf[name]
            glyph.recalcBounds(glyf)
            lsb = getattr(glyph, 'xMin', 0) if glyph.numberOfContours else 0
        result[name] = (advances.get(name, 1000), lsb)
    return result


def base_font(with_outlines=True):
    fb = FontBuilder(UPEM, isTTF=True)
    fb.setupGlyphOrder(glyph_order())
    fb.setupCharacterMap(cmap())
    fb.setupGlyf(outlines(with_outlines))
    fb.setupHorizontalMetrics(metrics(fb.font['glyf']))
    fb.setupHorizontalHeader(ascent=ASCENT, descent=DESCENT)
    fb.setupOS2(sTypoAscender=ASCENT, sTypoDescender=DESCENT, usWinAscent=ASCENT, usWinDescent=-DESCENT)
    fb.setupPost()
    # A fixed date, so rebuilding a font whose design has not changed writes the same bytes
    fb.updateHead(created=timestampFromString('Sat Jan  1 00:00:00 2000'), modified=timestampFromString('Sat Jan  1 00:00:00 2000'))
    fb.font.recalcTimestamp = False
    return fb


def name_font(fb, style, features=FEATURES):
    fb.setupNameTable({'familyName': 'Test Emoji ' + style, 'styleName': 'Regular',
                       'psName': 'TestEmoji-' + style, 'uniqueFontIdentifier': 'TestEmoji-' + style})
    addOpenTypeFeaturesFromString(fb.font, features)


def colr_layers():
    return {name: [(layer, colour) for layer, colour in layers] for name, _, layers in EMOJI}


def build_colrv0(style='COLRv0', features=FEATURES):
    fb = base_font()
    name_font(fb, style, features)
    fb.font['COLR'] = buildCOLR(colr_layers(), version=0)
    fb.font['CPAL'] = buildCPAL([[tuple(c / 255 for c in (r, g, b, a)) for r, g, b, a in palette] for palette in PALETTES])
    fb.save(os.path.join(HERE, 'TestEmoji-' + style + '.ttf'))


def build_colrv1():
    """Every glyph as version 1 paints, each emoji showing another part of what version 1 can say, and
    beside them the version 0 records a renderer that knows only those falls back to."""
    fb = base_font()
    name_font(fb, 'COLRv1')

    def solid(index, alpha=1.0):
        return {'Format': 2, 'PaletteIndex': index, 'Alpha': alpha}

    def glyph(layer, paint):
        return {'Format': 10, 'Glyph': layer, 'Paint': paint}

    def layers(*paints):
        return {'Format': 1, 'Layers': list(paints)}

    def line(stops, extend=ExtendMode.PAD):
        return {'Extend': extend, 'ColorStop': [
            {'StopOffset': offset, 'PaletteIndex': index, 'Alpha': alpha} for offset, index, alpha in stops]}

    def composite(mode, source, backdrop):
        return {'Format': 32, 'CompositeMode': mode, 'SourcePaint': source, 'BackdropPaint': backdrop}

    eyes = glyph('layer.eyes', solid(BLACK))
    paints = {
        # A radial gradient, and a layer in the colour of the text
        'u1F600': layers(
            glyph('layer.face', {'Format': 6, 'ColorLine': line([(0.0, WHITE, 1.0), (1.0, YELLOW, 1.0)]),
                                 'x0': 400, 'y0': 450, 'r0': 0, 'x1': 500, 'y1': 350, 'r1': 450}),
            eyes,
            glyph('layer.mouth', solid(FOREGROUND))),
        # A linear gradient kept to the heart by SRC_IN, and a half-opaque highlight
        'uni2764': layers(
            composite(CompositeMode.SRC_IN,
                      glyph('layer.square', {'Format': 4, 'ColorLine': line([(0.0, RED, 1.0), (1.0, YELLOW, 1.0)]),
                                             'x0': 500, 'y0': 750, 'x1': 500, 'y1': -50, 'x2': 900, 'y2': 750}),
                      glyph('layer.heart', solid(RED))),
            glyph('layer.shine', solid(HALF_WHITE))),
        # Scaled uniformly about the face's centre
        'u1F468': {'Format': 22, 'scale': 0.8, 'centerX': 500, 'centerY': 350,
                   'Paint': layers(glyph('layer.face', solid(BLUE)), eyes)},
        # A reflected linear gradient whose alpha varies, and whose stops the font lists out of order
        'u1F469': layers(
            glyph('layer.face', {'Format': 4, 'ColorLine': line([(1.0, RED, 0.25), (0.0, RED, 1.0)], ExtendMode.REFLECT),
                                 'x0': 400, 'y0': 0, 'x1': 600, 'y1': 0, 'x2': 400, 'y2': 200}),
            eyes),
        # A repeated radial gradient: rings
        'u1F467': layers(
            glyph('layer.face', {'Format': 6, 'ColorLine': line([(0.0, GREEN, 1.0), (1.0, WHITE, 1.0)], ExtendMode.REPEAT),
                                 'x0': 500, 'y0': 350, 'r0': 0, 'x1': 500, 'y1': 350, 'r1': 120}),
            eyes),
        # An affine transform: turned a little and moved
        'u1F1E6': {'Format': 12, 'Transform': {'xx': 0.9, 'yx': 0.1, 'xy': -0.1, 'yy': 0.9, 'dx': 80, 'dy': 0},
                   'Paint': glyph('layer.square', solid(BLUE))},
        # Another colour glyph drawn inside this one, and a stripe over it
        'u1F1FA': layers({'Format': 11, 'Glyph': 'u1F1E6'}, glyph('layer.stripe', solid(RED))),
        # Skewed, then rotated
        'u1F3F4': {'Format': 24, 'angle': -10, 'Paint': {'Format': 28, 'xSkewAngle': 15, 'ySkewAngle': 0,
                                                          'Paint': glyph('layer.pole', solid(BLACK))}},
        # A sweep gradient, which mPDF draws in the colour of its middle stop
        'u1F3FD': glyph('layer.square', {'Format': 8, 'ColorLine': line([(0.0, SKIN, 1.0), (0.5, RED, 1.0), (1.0, SKIN, 1.0)]),
                                         'centerX': 500, 'centerY': 350, 'startAngle': 0, 'endAngle': 360}),
        # Turned about its centre, and cut off by its clip box
        'u1F44D': {'Format': 26, 'angle': 20, 'centerX': 500, 'centerY': 375, 'Paint': glyph('layer.thumb', solid(YELLOW))},
        # The faces multiplied onto a yellow square
        'u1F468_200D_u1F469_200D_u1F467': composite(
            CompositeMode.MULTIPLY,
            layers(glyph('layer.small.left', solid(BLUE)), glyph('layer.small.middle', solid(GREEN)),
                   glyph('layer.small.right', solid(RED))),
            glyph('layer.square', solid(YELLOW))),
        'u1F1E6_u1F1FA': layers(
            glyph('layer.square', {'Format': 4, 'ColorLine': line([(0.0, BLUE, 1.0), (1.0, GREEN, 1.0)]),
                                   'x0': 50, 'y0': 0, 'x1': 950, 'y1': 0, 'x2': 50, 'y2': 900}),
            glyph('layer.stripe', solid(WHITE))),
        # The digit cut out of the key by DEST_OUT
        'one_uni20E3': composite(CompositeMode.DEST_OUT, glyph('one', solid(BLACK)), glyph('layer.square', solid(GREY))),
        # Moved, then scaled across
        'u1F44D_u1F3FD': {'Format': 14, 'dx': 100, 'dy': 0, 'Paint': {
            'Format': 16, 'scaleX': 0.8, 'scaleY': 1.0, 'Paint': glyph('layer.thumb', solid(SKIN))}},
        # A Var paint, read as the paint it varies
        'u1F3F4_E0067_E0062_E0065_E006E_E0067_E007F': layers(
            glyph('layer.square', {'Format': 3, 'PaletteIndex': WHITE, 'Alpha': 1.0, 'VarIndexBase': 0xFFFFFFFF}),
            glyph('layer.cross', solid(RED))),
    }
    # buildCOLR would move every paint version 0 can express into the version 0 records and out of the
    # paints, so the two are built apart and put together
    colr = buildCOLR(paints, version=1, glyphMap=fb.font.getReverseGlyphMap(), clipBoxes={'u1F44D': (50, -100, 950, 600)})
    populateCOLRv0(colr.table, colr_layers(), fb.font.getReverseGlyphMap())
    fb.font['COLR'] = colr
    fb.font['CPAL'] = buildCPAL([[tuple(c / 255 for c in (r, g, b, a)) for r, g, b, a in palette] for palette in PALETTES])
    fb.save(os.path.join(HERE, 'TestEmoji-COLRv1.ttf'))


def render(name, ppem, fmt='PNG'):
    """An emoji as a bitmap, square, ppem pixels to the em, its bottom edge at the descender."""
    layers = dict((n, l) for n, _, l in EMOJI)[name]
    size = ppem
    scale = ppem / UPEM
    image = Image.new('RGBA', (size, size), (0, 0, 0, 0))
    draw = ImageDraw.Draw(image)

    def point(x, y):
        return (x * scale, (ASCENT - 50 - y) * scale)

    def paint(shape, colour, transform=None):
        if shape in COMPOSITES:
            base, s, (dx, dy) = COMPOSITES[shape]
            return paint(base, colour, (s, dx, dy))
        s, dx, dy = transform or (1, 0, 0)

        def t(x, y):
            return point(x * s + dx, y * s + dy)

        rgba = PALETTES[0][colour] if colour != FOREGROUND else (0, 0, 0, 0xFF)
        if shape == 'one':
            draw.polygon([t(x, y) for x, y in PLAIN[3][3]], fill=rgba)
            return
        kind, data = SHAPES[shape]
        if kind == 'circle':
            cx, cy, r = data
            x0, y0 = t(cx - r, cy + r)
            x1, y1 = t(cx + r, cy - r)
            draw.ellipse([x0, y0, x1, y1], fill=rgba)
        else:
            for points in data:
                draw.polygon([t(x, y) for x, y in points], fill=rgba)

    for shape, colour in layers:
        overlay = Image.new('RGBA', image.size, (0, 0, 0, 0))
        draw = ImageDraw.Draw(overlay)
        paint(shape, colour)
        image = Image.alpha_composite(image, overlay)

    out = io.BytesIO()
    if fmt == 'JPEG':
        flat = Image.new('RGB', image.size, (255, 255, 255))
        flat.paste(image, mask=image.split()[3])
        flat.save(out, 'JPEG', quality=90)
    else:
        image.save(out, 'PNG', optimize=True)
    return out.getvalue()


def small_metrics(ppem):
    # height, width, bearingX, bearingY, advance, in pixels
    top = round((ASCENT - 50) * ppem / UPEM)
    return (ppem, ppem, 0, top, ppem)


def big_metrics(ppem):
    h, w, bx, by, adv = small_metrics(ppem)
    # horiBearingX, horiBearingY, horiAdvance, vertBearingX, vertBearingY, vertAdvance
    return (h, w, bx, by, adv, -w // 2, 0, h)


def cbdt_glyph(image_format, gid, ppem, fmt='PNG'):
    png = render(glyph_order()[gid], ppem, fmt)
    if image_format == 17:
        return struct.pack('>BBbbB', *small_metrics(ppem)) + struct.pack('>I', len(png)) + png
    if image_format == 18:
        return struct.pack('>BBbbBbbB', *big_metrics(ppem)) + struct.pack('>I', len(png)) + png
    return struct.pack('>I', len(png)) + png  # 19


def build_cbdt():
    fb = base_font(with_outlines=False)
    name_font(fb, 'CBDT')
    font = fb.font
    del font['glyf']
    del font['loca']
    font['maxp'].tableVersion = 0x00005000
    for attr in ('maxPoints', 'maxContours', 'maxCompositePoints', 'maxCompositeContours', 'maxZones',
                 'maxTwilightPoints', 'maxStorage', 'maxFunctionDefs', 'maxInstructionDefs',
                 'maxStackElements', 'maxSizeOfInstructions', 'maxComponentElements', 'maxComponentDepth'):
        if hasattr(font['maxp'], attr):
            delattr(font['maxp'], attr)

    gid = {name: i for i, name in enumerate(glyph_order())}
    emoji = [name for name, _, _ in EMOJI]

    # At 64ppem, one index subtable of each format. The subtables must be in glyph order and must not
    # overlap, so the emoji are split into runs by glyph id.
    runs = [
        (1, 17, emoji[0:3]),     # variable size, small metrics in the glyph
        (3, 18, emoji[3:5]),     # variable size with 16-bit offsets, big metrics in the glyph
        (2, 19, emoji[5:7]),     # constant size, metrics in the index
        (4, 17, emoji[7:10]),    # sparse, variable size
        (5, 19, emoji[10:15]),   # sparse, constant size, metrics in the index
    ]
    strikes = [(64, runs), (32, [(1, 17, emoji)])]

    cbdt = bytearray(struct.pack('>HH', 3, 0))
    strike_blobs = []
    for ppem, strike_runs in strikes:
        subtables = []
        for index_format, image_format, names in strike_runs:
            ids = [gid[n] for n in names]
            records = [cbdt_glyph(image_format, glyph_id, ppem) for glyph_id in ids]
            if index_format in (2, 5):
                # Constant size: every record is padded to the longest, after the PNG's own length
                longest = max(len(r) for r in records)
                records = [r + b'\x00' * (longest - len(r)) for r in records]
            offsets = []
            data_start = len(cbdt)
            for record in records:
                offsets.append(len(cbdt) - data_start)
                cbdt += record
            offsets.append(len(cbdt) - data_start)
            subtables.append((index_format, image_format, ids, offsets, data_start, ppem))
        strike_blobs.append((ppem, subtables))

    # CBLC: header, BitmapSize records, then per strike an IndexSubTableArray and the subtables
    cblc_head = struct.pack('>HHI', 3, 0, len(strike_blobs))
    size_record_len = 48
    body = bytearray()
    size_records = []
    base = len(cblc_head) + size_record_len * len(strike_blobs)
    for ppem, subtables in strike_blobs:
        array_offset = base + len(body)
        array = bytearray()
        tables = bytearray()
        array_len = 8 * len(subtables)
        for index_format, image_format, ids, offsets, data_start, ppem_ in subtables:
            first, last = min(ids), max(ids)
            sub_offset = array_len + len(tables)
            array += struct.pack('>HHI', first, last, sub_offset)
            header = struct.pack('>HHI', index_format, image_format, data_start)
            if index_format == 1:
                # one Offset32 per glyph from first to last, glyphs outside the run left empty
                table = header
                by_id = dict(zip(ids, offsets))
                values = []
                for i, g in enumerate(range(first, last + 1)):
                    values.append(by_id[g] if g in by_id else values[-1])
                values.append(offsets[-1])
                table += b''.join(struct.pack('>I', v) for v in values)
            elif index_format == 3:
                by_id = dict(zip(ids, offsets))
                values = []
                for g in range(first, last + 1):
                    values.append(by_id[g] if g in by_id else values[-1])
                values.append(offsets[-1])
                table = header + b''.join(struct.pack('>H', v) for v in values)
                if len(table) % 4:
                    table += b'\x00' * (4 - len(table) % 4)
            elif index_format == 2:
                size = offsets[1] - offsets[0]
                table = header + struct.pack('>I', size) + struct.pack('>BBbbBbbB', *big_metrics(ppem_))
            elif index_format == 4:
                table = header + struct.pack('>I', len(ids))
                for glyph_id, offset in zip(ids, offsets):
                    table += struct.pack('>HH', glyph_id, offset)
                table += struct.pack('>HH', 0, offsets[-1])
            elif index_format == 5:
                size = offsets[1] - offsets[0]
                table = header + struct.pack('>I', size) + struct.pack('>BBbbBbbB', *big_metrics(ppem_))
                table += struct.pack('>I', len(ids)) + b''.join(struct.pack('>H', g) for g in ids)
                if len(table) % 4:
                    table += b'\x00' * (4 - len(table) % 4)
            tables += table
        blob = array + tables
        body += blob
        first_glyph = min(min(s[2]) for s in subtables)
        last_glyph = max(max(s[2]) for s in subtables)
        top = round((ASCENT - 50) * ppem / UPEM)
        line = struct.pack('>bbBbbbbbbbbb', top, top - ppem, ppem, 0, 0, 0, 0, 0, 0, 0, 0, 0)
        size_records.append(struct.pack('>IIII', array_offset, len(blob), len(subtables), 0) + line + line +
                            struct.pack('>HHBBBb', first_glyph, last_glyph, ppem, ppem, 32, 0x01))
    cblc = cblc_head + b''.join(size_records) + body

    # Written as the bytes laid out above, which keeps the index formats fontTools would not choose
    for tag, data in (('CBDT', cbdt), ('CBLC', cblc)):
        table = DefaultTable(tag)
        table.data = bytes(data)
        font[tag] = table
    fb.save(os.path.join(HERE, 'TestEmoji-CBDT.ttf'))


def build_sbix():
    fb = base_font(with_outlines=False)
    name_font(fb, 'sbix')
    font = fb.font
    sbix = newTable('sbix')
    sbix.version = 1
    sbix.flags = 1
    sbix.strikes = {}
    order = glyph_order()
    emoji = [name for name, _, _ in EMOJI]
    for ppem in (64, 32):
        strike = Strike(ppem=ppem, resolution=72)
        for name in emoji:
            if ppem == 64 and name == 'u1F469':
                # the same image as the man's, by reference
                glyph = SbixGlyph(glyphName=name, graphicType='dupe')
                glyph.referenceGlyphName = 'u1F468'
                strike.glyphs[name] = glyph
                continue
            if ppem == 64 and name == 'u1F1FA':
                glyph = SbixGlyph(glyphName=name, graphicType='flip')
                glyph.referenceGlyphName = 'u1F44D'
                strike.glyphs[name] = glyph
                continue
            if ppem == 64 and name == 'u1F3FD':
                strike.glyphs[name] = SbixGlyph(glyphName=name, graphicType='tiff', imageData=b'II*\x00 not drawn',
                                                originOffsetX=0, originOffsetY=0)
                continue
            fmt = 'JPEG' if (ppem == 64 and name == 'u1F467') else 'PNG'
            graphic = 'jpg ' if fmt == 'JPEG' else 'png '
            # The bitmap's bottom edge is 100 units below the baseline, as render() draws it
            strike.glyphs[name] = SbixGlyph(glyphName=name, graphicType=graphic, imageData=render(name, ppem, fmt),
                                            originOffsetX=0, originOffsetY=round(-100 * ppem / UPEM))
        sbix.strikes[ppem] = strike
    font['sbix'] = sbix
    fb.save(os.path.join(HERE, 'TestEmoji-sbix.ttf'))


if __name__ == '__main__':
    build_colrv0()
    build_colrv0('FE0F', FEATURES.replace('sub one uni20E3 by', 'sub one uniFE0F uni20E3 by'))
    build_colrv1()
    build_cbdt()
    build_sbix()
