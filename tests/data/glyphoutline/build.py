"""
Writes the paths GlyphOutlineTest expects, as fontTools draws them:

    python3 tests/data/glyphoutline/build.py

fontTools' BasePen splits a TrueType contour into quadratic segments at its implied on-curve points
and draws each as the cubic with control points two thirds of the way to the quadratic's, which is
what a PDF path needs; composites are drawn through their transforms. Each glyph's path is written
as the PDF operators GlyphOutline writes, to three places.
"""

import json
import os

from fontTools.pens.basePen import BasePen
from fontTools.ttLib import TTFont

HERE = os.path.dirname(os.path.abspath(__file__))
DATA = os.path.join(HERE, '..')

GLYPHS = {
    'ttf/NotoSans-Regular.ttf': ['O', 'a', 'i', 'percent', 'Aacute', 'uni01C4', 'space'],
    'ttf/color/TestEmoji-COLRv0.ttf': ['layer.face', 'layer.frame', 'layer.small.left', 'one'],
}


def number(value):
    text = ('%.3f' % value).rstrip('0').rstrip('.')
    return '0' if text == '-0' else text


class PdfPen(BasePen):
    def __init__(self, glyphSet):
        BasePen.__init__(self, glyphSet)
        self.ops = []

    def _moveTo(self, pt):
        self.ops.append('%s %s m' % (number(pt[0]), number(pt[1])))

    def _lineTo(self, pt):
        self.ops.append('%s %s l' % (number(pt[0]), number(pt[1])))

    def _curveToOne(self, p1, p2, p3):
        self.ops.append(' '.join(number(v) for v in (p1[0], p1[1], p2[0], p2[1], p3[0], p3[1])) + ' c')

    def _closePath(self):
        # BasePen draws the closing segment back to the start as a line where it is straight; a PDF
        # path closes with h either way
        if self.ops and self.ops[-1].endswith(' l'):
            start = self.ops[[i for i, op in enumerate(self.ops) if op.endswith(' m')][-1]]
            if self.ops[-1][:-2] == start[:-2]:
                self.ops.pop()
        self.ops.append('h')


expected = {}
for path, names in GLYPHS.items():
    font = TTFont(os.path.join(DATA, path))
    glyphSet = font.getGlyphSet()
    order = font.getGlyphOrder()
    for name in names:
        pen = PdfPen(glyphSet)
        glyphSet[name].draw(pen)
        expected[path + ' ' + name] = {'glyph': order.index(name), 'path': '\n'.join(pen.ops) + ('\n' if pen.ops else '')}

with open(os.path.join(HERE, 'paths.json'), 'w') as f:
    json.dump(expected, f, indent=1, sort_keys=True)
    f.write('\n')
